<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\AI\AIErrorReason;
use App\Application\AI\AIProviderException;
use App\Application\AI\AIProviderInterface;
use App\Application\AI\AIRequest;
use App\Application\AI\AIResponse;
use App\Application\Bncc\BnccContextService;
use App\Application\Bncc\BnccTopicMatcher;
use App\Application\Bncc\ImportException;
use App\Application\Exams\ExamGenerationException;
use App\Application\Exams\ExamGenerationService;
use App\Infrastructure\AI\FakeAIProvider;
use App\Infrastructure\Persistence\BnccContextRepository;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Medoo\Medoo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\BnccFixtures;
use Tests\Support\CurriculumFixture;
use Tests\Support\ExamFixture;

final class ExamGenerationServiceTest extends TestCase
{
    private Medoo $database;
    private BnccContextService $bncc;
    private int $documentId;

    protected function setUp(): void
    {
        $this->database = (new DatabaseConnectionFactory(require __DIR__ . '/../../config/database.php'))->create();
        $this->bncc = new BnccContextService(new BnccContextRepository($this->database), new BnccTopicMatcher());
        $fixtures = new BnccFixtures($this->database);
        $this->documentId = $fixtures->document(['status' => 'ready', 'ready_at' => '2026-01-01 00:00:00',
            'structured_at' => '2026-01-01 00:00:00', 'structure_profile' => 'synthetic-exam-fixture']);
        $section = $fixtures->section($this->documentId);
        $area = $fixtures->area($this->documentId, $section, ['code' => 'matematica']);
        $curriculum = ['document_id' => $this->documentId, 'section_id' => $section, 'area_id' => $area,
            'component_id' => $fixtures->component($this->documentId, $area, $section, 'matematica')];
        foreach (['EF06MA07' => 6, 'EF07MA07' => 7] as $code => $year) {
            $id = $fixtures->skill($curriculum, ['code' => $code, 'description' => 'Frações em habilidade SINTÉTICA.',
                'review_status' => 'approved', 'reviewed_at' => '2026-01-01 00:00:00']);
            $gradeId = $this->database->get('school_grades', 'id', ['year_number' => $year]);
            $this->database->insert('bncc_skill_grades', ['skill_id' => $id, 'school_grade_id' => $gradeId]);
            $fixtures->context($curriculum, $id);
        }
        $fixtures->competency($curriculum, overrides: ['review_status' => 'approved', 'reviewed_at' => '2026-01-01 00:00:00']);
    }

    protected function tearDown(): void
    {
        (new CurriculumFixture($this->database))->remove($this->documentId);
    }

    public function testServiceUsesApprovedContextCallsProviderOnceAndSeparatesStudentExamFromAnswerKey(): void
    {
        $fake = $this->fake();
        $database = $this->database;
        $provider = new class($fake, $database) implements AIProviderInterface {
            public function __construct(private FakeAIProvider $fake, private Medoo $database) {}
            public function generate(AIRequest $request): AIResponse
            {
                TestCase::assertFalse($this->database->pdo->inTransaction(), 'Não manter transação aberta durante a geração.');
                return $this->fake->generate($request);
            }
        };
        $before = $this->snapshot();
        $result = (new ExamGenerationService($this->bncc, $provider))->generate($this->input());
        self::assertSame(1, $fake->callCount());
        $sent = $fake->lastRequest()->input();
        self::assertSame($this->documentId, $sent->bncc_context->document->id);
        self::assertSame(['EF06MA07'], array_column($sent->bncc_context->skills, 'code'));
        self::assertStringContainsString('SINTÉTICA', $sent->bncc_context->skills[0]->description);
        self::assertNotEmpty($sent->bncc_context->skills[0]->sources);
        self::assertNotEmpty($sent->bncc_context->competencies);
        self::assertSame(5, $sent->teacher_request->alternatives_count);
        self::assertSame(3, $sent->teacher_request->true_false_statements);
        self::assertCount(3, $result->exam()['questions']);
        self::assertCount(3, $result->answerKey());
        self::assertArrayNotHasKey('answer_key', $result->exam());
        self::assertSame('matematica', $result->exam()['subject']['code']);
        self::assertSame(6, $result->exam()['grade']['year_number']);
        self::assertSame($this->documentId, $result->bnccContext()['document']['id']);
        self::assertSame('fake', $result->generation()['provider']);
        self::assertSame('exam-prompt-v2', $result->generation()['prompt_version']);
        self::assertSame(0, $result->generation()['input_tokens']);
        $copy = $result->exam();
        $copy['questions'][0]['statement'] = 'alterado';
        self::assertSame('Enunciado SINTÉTICO.', $result->exam()['questions'][0]['statement']);
        self::assertSame($before, $this->snapshot());
    }

    public function testInvalidInputStopsBeforeContextOrProvider(): void
    {
        $fake = $this->fake();
        try {
            (new ExamGenerationService($this->bncc, $fake))->generate($this->input(['question_count' => 0, 'subject' => 'inexistente']));
            self::fail('O pedido inválido deveria falhar.');
        } catch (ExamGenerationException $exception) {
            self::assertSame('invalid_input', $exception->reason);
            self::assertSame(0, $fake->callCount());
        }
    }

    #[DataProvider('unavailableContexts')]
    public function testContextFailureStopsBeforeProvider(string $case, string $reason): void
    {
        $input = $this->input();
        if ($case === 'draft') {
            $this->database->update('bncc_documents', ['status' => 'draft', 'ready_at' => null], ['id' => $this->documentId]);
        } elseif ($case === 'topic') {
            $input['topics'] = ['polígonos'];
        } else {
            $input['subject'] = 'inexistente';
        }
        $fake = $this->fake();
        try {
            (new ExamGenerationService($this->bncc, $fake))->generate($input);
            self::fail('O contexto indisponível deveria falhar.');
        } catch (ImportException $exception) {
            self::assertSame($reason, $exception->reason);
            self::assertSame(0, $fake->callCount());
            self::assertNull($fake->lastRequest());
            self::assertFalse($this->database->pdo->inTransaction());
        }
    }

    public static function unavailableContexts(): array
    {
        return [['draft', 'document_not_ready'], ['topic', 'topics_without_matches'], ['subject', 'unknown_subject']];
    }

    #[DataProvider('providerFailures')]
    public function testProviderErrorsPropagateWithoutRetryOrDatabaseWrites(string|AIErrorReason $failure, AIErrorReason $expected): void
    {
        $fake = new FakeAIProvider(is_string($failure) ? $failure : new AIProviderException($failure));
        $before = $this->snapshot();
        try {
            (new ExamGenerationService($this->bncc, $fake))->generate($this->input());
            self::fail('A falha do provedor deveria ser propagada.');
        } catch (AIProviderException $exception) {
            self::assertSame($expected, $exception->reason);
            self::assertSame(1, $fake->callCount());
            self::assertSame($before, $this->snapshot());
        }
    }

    public static function providerFailures(): array
    {
        return [[AIErrorReason::Timeout, AIErrorReason::Timeout], [AIErrorReason::RateLimited, AIErrorReason::RateLimited],
            [AIErrorReason::IncompleteResponse, AIErrorReason::IncompleteResponse], ['{"broken":', AIErrorReason::InvalidResponse]];
    }

    public function testSkillFromAnotherYearCannotEnterExamEvenWhenItExistsInDatabase(): void
    {
        $data = ExamFixture::response();
        $data['exam']['questions'][0]['bncc_skills'] = ['EF07MA07'];
        $fake = $this->fake($data);
        $before = $this->snapshot();
        try {
            (new ExamGenerationService($this->bncc, $fake))->generate($this->input());
            self::fail('Código existente fora do contexto não pode ser aceito.');
        } catch (ExamGenerationException $exception) {
            self::assertSame('invalid_exam_response', $exception->reason);
            self::assertSame('bncc_skills', $exception->details['check']);
            self::assertSame(1, $fake->callCount());
            self::assertSame($before, $this->snapshot());
        }
    }

    private function input(array $overrides = []): array
    {
        return ExamFixture::input(array_replace(['bncc_document_id' => $this->documentId], $overrides));
    }

    private function fake(?array $data = null): FakeAIProvider
    {
        return new FakeAIProvider(json_encode($data ?? ExamFixture::response(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function snapshot(): string
    {
        $data = [];
        foreach (['bncc_documents', 'bncc_skills', 'bncc_skill_contexts', 'bncc_competencies'] as $table) {
            $data[$table] = $this->database->select($table, '*');
        }
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }
}
