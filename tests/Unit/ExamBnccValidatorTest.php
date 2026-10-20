<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Bncc\ImportException;
use App\Application\Exams\ExamBnccValidator;
use App\Application\Exams\ExamGenerationException;
use App\Application\Exams\ExamGenerationRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\ExamFixture;

final class ExamBnccValidatorTest extends TestCase
{
    public function testReportLinksQuestionsToStoredSkillsAndSourcesWithoutClaimingSemanticApproval(): void
    {
        $request = ExamGenerationRequest::fromArray(ExamFixture::input());
        $context = ExamFixture::context();
        $validator = new ExamBnccValidator();
        $validator->validateContext($request, $context);
        $report = $validator->validateReferences(ExamFixture::response(), $request, $context);
        self::assertSame('exam-bncc-validation-v1', $report['version']);
        self::assertSame(1, $report['document_id']);
        self::assertSame(1, $report['document_revision']);
        self::assertSame($context['document']['sha256'], $report['document_sha256']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $report['context_sha256']);
        self::assertSame([['id' => 11, 'code' => 'EF06MA07', 'question_numbers' => [1, 2, 3], 'source_ids' => [31]]], $report['skills']);
        self::assertSame(['question_number' => 1, 'skill_ids' => [11]], $report['questions'][0]);
        self::assertSame([['topic' => 'frações', 'skill_codes' => ['EF06MA07'], 'question_numbers' => [1, 2, 3]]], $report['topic_references']);
        self::assertSame([], $report['unused_context_skill_codes']);
        self::assertSame('not_performed', $report['semantic_review']);
        self::assertTrue($report['teacher_review_required']);
        self::assertStringNotContainsString('Trecho SINTÉTICO', json_encode($report));
        self::assertSame($report, $validator->validateReferences(ExamFixture::response(), $request, $context));
    }

    #[DataProvider('inconsistentContexts')]
    public function testRejectsIncompatibleOrIncompleteContextBeforeGeneration(array $path, mixed $value, string $check): void
    {
        $context = ExamFixture::context();
        $target = &$context;
        foreach ($path as $part) {
            $target = &$target[$part];
        }
        $target = $value;
        unset($target);
        try {
            (new ExamBnccValidator())->validateContext(ExamGenerationRequest::fromArray(ExamFixture::input()), $context);
            self::fail('O contexto inválido deveria ser rejeitado.');
        } catch (ImportException $exception) {
            self::assertSame('invalid_curriculum', $exception->reason);
            self::assertSame($check, $exception->details['check']);
        }
    }

    public static function inconsistentContexts(): array
    {
        return [
            [['subject', 'code'], 'arte', 'context_identity'],
            [['grade', 'year_number'], 7, 'context_identity'],
            [['grade', 'education_stage'], 'outro', 'context_identity'],
            [['topics'], ['decimais'], 'context_identity'],
            [['document', 'status'], 'draft', 'context_identity'],
            [['document', 'ready_at'], null, 'context_identity'],
            [['document', 'structured_at'], null, 'context_identity'],
            [['document', 'page_count'], 0, 'context_identity'],
            [['skills'], [], 'context_identity'], [['competencies'], [], 'context_identity'],
            [['skills', 0, 'grades'], [7], 'skill_context'],
            [['skills', 0, 'description'], " \n\u{00a0}", 'skill_context'],
            [['skills', 0, 'sources'], [], 'skill_context'],
            [['skills', 0, 'matches'], [], 'skill_context'],
            [['skills', 0, 'sources', 0, 'page_start'], 0, 'source_provenance'],
            [['skills', 0, 'sources', 0, 'page_start'], '5', 'source_provenance'],
            [['skills', 0, 'sources', 0, 'page_end'], 4, 'source_provenance'],
            [['skills', 0, 'sources', 0, 'page_end'], 41, 'source_provenance'],
            [['skills', 0, 'sources', 0, 'source_excerpt'], " \u{00a0}", 'source_provenance'],
            [['competencies', 0, 'description'], '', 'competency_context'],
            [['competencies', 0, 'page_start'], null, 'source_provenance'],
            [['competencies', 0, 'page_end'], 41, 'source_provenance'],
            [['competencies', 0, 'source_excerpt'], null, 'source_provenance'],
        ];
    }

    public function testCannotSubstituteTheDocumentExplicitlyRequestedByTheTeacher(): void
    {
        $this->expectException(ImportException::class);
        (new ExamBnccValidator())->validateContext(ExamGenerationRequest::fromArray(ExamFixture::input(['bncc_document_id' => 2])), ExamFixture::context());
    }

    public function testPartialReleaseAuthorizesOnlyTheRequestedSubjectAndYearAndIsRecordedInReport(): void
    {
        $context = $this->partiallyReleasedContext();
        $request = ExamGenerationRequest::fromArray(ExamFixture::input());
        $validator = new ExamBnccValidator();
        $validator->validateContext($request, $context);
        $report = $validator->validateReferences(ExamFixture::response(), $request, $context);
        self::assertSame($context['document']['release'], $report['curriculum_release']);
        self::assertSame('draft', $context['document']['status']);
    }

    #[DataProvider('invalidPartialReleases')]
    public function testPartialReleaseCannotAuthorizeOtherSubjectsYearsOrIncompleteReleaseMetadata(mixed $release): void
    {
        $context = $this->partiallyReleasedContext();
        $context['document']['release'] = $release;
        $this->expectException(ImportException::class);
        (new ExamBnccValidator())->validateContext(ExamGenerationRequest::fromArray(ExamFixture::input()), $context);
    }

    public static function invalidPartialReleases(): array
    {
        $valid = ['id' => 1, 'subject' => 'matematica', 'grade' => 6, 'released_at' => '2026-10-20 15:00:00'];
        return [
            [null], [false], [[]],
            [[...$valid, 'id' => 0]], [[...$valid, 'id' => '1']],
            [[...$valid, 'subject' => 'arte']], [[...$valid, 'grade' => 7]], [[...$valid, 'grade' => '6']],
            [[...$valid, 'released_at' => null]], [[...$valid, 'released_at' => ' ']],
        ];
    }

    public function testArchivalInvalidatesAnExistingPartialRelease(): void
    {
        $context = $this->partiallyReleasedContext();
        $context['document']['status'] = 'archived';
        $this->expectException(ImportException::class);
        (new ExamBnccValidator())->validateContext(ExamGenerationRequest::fromArray(ExamFixture::input()), $context);
    }

    private function partiallyReleasedContext(): array
    {
        $context = ExamFixture::context();
        $context['document']['status'] = 'draft';
        $context['document']['ready_at'] = null;
        $context['document']['release'] = ['id' => 1, 'subject' => 'matematica', 'grade' => 6, 'released_at' => '2026-10-20 15:00:00'];
        return $context;
    }

    public function testRepeatedSkillsCannotCreateAmbiguousReferences(): void
    {
        $context = ExamFixture::context();
        $context['skills'][] = $context['skills'][0];
        $this->expectException(ImportException::class);
        (new ExamBnccValidator())->validateContext(ExamGenerationRequest::fromArray(ExamFixture::input()), $context);
    }

    public function testYearsComeFromStoredRelationsIncludingSkillsSharedAcrossYears(): void
    {
        $context = ExamFixture::context();
        $context['subject']['code'] = 'arte';
        $context['grade']['year_number'] = 4;
        $context['skills'][0]['code'] = 'EF15AR01';
        $context['skills'][0]['grades'] = [1, 2, 3, 4, 5];
        $request = ExamGenerationRequest::fromArray(ExamFixture::input(['subject' => 'arte', 'grade' => 4]));
        $data = ExamFixture::response();
        foreach ($data['exam']['questions'] as &$question) {
            $question['bncc_skills'] = ['EF15AR01'];
        }
        unset($question);
        $validator = new ExamBnccValidator();
        $validator->validateContext($request, $context);
        self::assertSame('EF15AR01', $validator->validateReferences($data, $request, $context)['skills'][0]['code']);
    }

    public function testEachRequestedTopicNeedsAReferenceButNotEveryAvailableSkillIsMandatory(): void
    {
        $context = $this->twoTopicContext();
        $context['skills'][] = [...$context['skills'][0], 'id' => 13, 'code' => 'EF06MA09'];
        $request = ExamGenerationRequest::fromArray(ExamFixture::input(['topics' => ['frações', 'decimais']]));
        $data = ExamFixture::response();
        $data['exam']['questions'][1]['bncc_skills'] = ['EF06MA08'];
        $report = (new ExamBnccValidator())->validateReferences($data, $request, $context);
        self::assertSame([1, 3], $report['topic_references'][0]['question_numbers']);
        self::assertSame([2], $report['topic_references'][1]['question_numbers']);
        self::assertSame(['EF06MA09'], $report['unused_context_skill_codes']);
    }

    public function testOneQuestionCanReferenceSeveralTopicsWithoutBeingCountedTwice(): void
    {
        $data = ExamFixture::response();
        $data['exam']['questions'][0]['bncc_skills'] = ['EF06MA07', 'EF06MA08'];
        $context = $this->twoTopicContext();
        $context['skills'][1]['matches'][] = ['topic' => 'frações', 'fields' => ['knowledge_objects']];
        $report = (new ExamBnccValidator())->validateReferences($data,
            ExamGenerationRequest::fromArray(ExamFixture::input(['topics' => ['frações', 'decimais']])), $context);
        self::assertSame([11, 12], $report['questions'][0]['skill_ids']);
        self::assertSame([1, 2, 3], $report['topic_references'][0]['question_numbers']);
        self::assertSame([1], $report['topic_references'][1]['question_numbers']);
    }

    public function testMentioningATopicInTextDoesNotReplaceTheRequiredCurricularReference(): void
    {
        $data = ExamFixture::response();
        $data['exam']['questions'][0]['statement'] = 'Questão que menciona decimais, mas só cita habilidade selecionada para frações.';
        try {
            (new ExamBnccValidator())->validateReferences($data,
                ExamGenerationRequest::fromArray(ExamFixture::input(['topics' => ['frações', 'decimais']])), $this->twoTopicContext());
            self::fail('Falta referência para decimais.');
        } catch (ExamGenerationException $exception) {
            self::assertSame('invalid_exam_response', $exception->reason);
            self::assertSame('bncc_topic_references', $exception->details['check']);
            self::assertSame(['decimais'], $exception->details['unrepresented_topics']);
        }
    }

    public function testReferencesAloneDoNotCertifyMeaningOrCorrectAnswers(): void
    {
        $data = ExamFixture::response();
        $data['exam']['questions'][0]['statement'] = 'Qual é a capital de um país?';
        $report = (new ExamBnccValidator())->validateReferences($data,
            ExamGenerationRequest::fromArray(ExamFixture::input()), ExamFixture::context());
        self::assertSame('not_performed', $report['semantic_review']);
        self::assertTrue($report['teacher_review_required']);
    }

    public function testUnknownCodeCannotAcquireAReport(): void
    {
        $data = ExamFixture::response();
        $data['exam']['questions'][0]['bncc_skills'] = ['EF06MA99'];
        $this->expectException(ExamGenerationException::class);
        (new ExamBnccValidator())->validateReferences($data,
            ExamGenerationRequest::fromArray(ExamFixture::input()), ExamFixture::context());
    }

    private function twoTopicContext(): array
    {
        $context = ExamFixture::context();
        $context['topics'] = ['frações', 'decimais'];
        $context['skills'][] = [...$context['skills'][0], 'id' => 12, 'code' => 'EF06MA08',
            'description' => 'Decimais SINTÉTICOS.', 'matches' => [['topic' => 'decimais', 'fields' => ['description']]],
            'sources' => [[...$context['skills'][0]['sources'][0], 'id' => 32]]];
        return $context;
    }
}
