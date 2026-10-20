<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\AI\AIProviderInterface;
use App\Application\AI\AIRequest;
use App\Application\AI\AIResponse;
use App\Application\Bncc\BnccContextQuery;
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
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\BnccFixtures;
use Tests\Support\AuthFixture;
use Tests\Support\CurriculumFixture;
use Tests\Support\ExamFixture;

final class ExamBnccValidationTest extends TestCase
{
    private Medoo $database;
    private Medoo $concurrent;
    private DatabaseConnectionFactory $connections;
    private BnccContextService $bncc;
    private BnccFixtures $fixtures;
    private array $documents = [];
    private array $curriculum;
    private int $skillId;
    private int $sourceId;
    private int $competencyId;
    private array $session;

    protected function setUp(): void
    {
        $this->connections = new DatabaseConnectionFactory(require __DIR__ . '/../../config/database.php');
        $this->database = $this->connections->create();
        $this->session = (new AuthFixture($this->database))->create();
        $this->concurrent = $this->connections->create();
        $this->fixtures = new BnccFixtures($this->database);
        $this->bncc = new BnccContextService(new BnccContextRepository($this->database), new BnccTopicMatcher());
        $this->curriculum = $this->curriculum();
        $this->skillId = $this->skill($this->curriculum, 'EF06MA07', [6], 'Frações SINTÉTICAS.');
        $this->sourceId = $this->database->get('bncc_skill_contexts', 'id', ['skill_id' => $this->skillId]);
        $this->competencyId = $this->fixtures->competency($this->curriculum, overrides: [
            'review_status' => 'approved', 'reviewed_at' => '2026-01-01 00:00:00']);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->documents) as $id) {
            (new \Tests\Support\GenerationHistoryFixture($this->database))->removeForDocument($id);
            (new CurriculumFixture($this->database))->remove($id);
        }
        (new AuthFixture($this->database))->remove($this->session);
    }

    public function testReportUsesDatabaseIdsAndProvenanceAndDoesNotWriteOrCallTheProviderAgain(): void
    {
        $extraId = $this->skill($this->curriculum, 'EF06MA08', [6], 'Frações equivalentes SINTÉTICAS.');
        $fake = $this->fake();
        $before = $this->snapshot();
        $result = (new ExamGenerationService($this->bncc, $fake))->generate($this->input());
        $report = $result->generation()['bncc_validation'];
        self::assertSame('passed', $report['status']);
        self::assertTrue($report['database_rechecked']);
        self::assertSame($this->curriculum['document_id'], $report['document_id']);
        self::assertSame([['id' => $this->skillId, 'code' => 'EF06MA07', 'question_numbers' => [1, 2, 3],
            'source_ids' => [$this->sourceId]]], $report['skills']);
        self::assertSame(['EF06MA08'], $report['unused_context_skill_codes']);
        self::assertNotSame($extraId, $report['skills'][0]['id']);
        self::assertSame('not_performed', $report['semantic_review']);
        self::assertSame(1, $fake->callCount());
        self::assertSame($before, $this->snapshot());
        self::assertFalse($this->database->pdo->inTransaction());
        $context = json_decode(json_encode($fake->lastRequest()->input()->bncc_context, JSON_THROW_ON_ERROR), true);
        self::assertSame(hash('sha256', json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), $report['context_sha256']);
    }

    #[DataProvider('concurrentChanges')]
    public function testChangedDatabaseContextAfterAiCannotBeAccepted(string $change): void
    {
        $fake = $this->fake();
        $provider = $this->duringGeneration($fake, function () use ($change): void {
            match ($change) {
                'skill_description' => $this->concurrent->update('bncc_skills', ['description' => 'Descrição privada alterada: frações.'], ['id' => $this->skillId]),
                'skill_unapproved' => $this->concurrent->update('bncc_skills', ['review_status' => 'pending', 'reviewed_at' => null], ['id' => $this->skillId]),
                'grade_unlinked' => $this->concurrent->delete('bncc_skill_grades', ['skill_id' => $this->skillId]),
                'source_excerpt' => $this->concurrent->update('bncc_skill_contexts', ['source_excerpt' => 'Origem privada alterada.'], ['id' => $this->sourceId]),
                'source_pages' => $this->concurrent->update('bncc_skill_contexts', ['page_end' => 7], ['id' => $this->sourceId]),
                'source_removed' => $this->concurrent->delete('bncc_skill_contexts', ['id' => $this->sourceId]),
                'competency_description' => $this->concurrent->update('bncc_competencies', ['description' => 'Competência privada alterada.'], ['id' => $this->competencyId]),
                'competency_unapproved' => $this->concurrent->update('bncc_competencies', ['review_status' => 'pending', 'reviewed_at' => null], ['id' => $this->competencyId]),
                'document_unreleased' => $this->concurrent->update('bncc_documents', ['status' => 'draft', 'ready_at' => null], ['id' => $this->curriculum['document_id']]),
                'document_revision' => $this->concurrent->update('bncc_documents', ['revision' => 2], ['id' => $this->curriculum['document_id']]),
                'document_hash' => $this->concurrent->update('bncc_documents', ['sha256' => hash('sha256', 'changed')], ['id' => $this->curriculum['document_id']]),
            };
        });
        try {
            (new ExamGenerationService($this->bncc, $provider))->generate($this->input());
            self::fail('A alteração concorrente deveria impedir a devolução da prova.');
        } catch (ImportException $exception) {
            self::assertSame('curriculum_changed', $exception->reason);
            self::assertStringNotContainsString('privada', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertSame(1, $fake->callCount());
            self::assertFalse($this->database->pdo->inTransaction());
        }
    }

    public static function concurrentChanges(): array
    {
        return array_map(static fn ($case) => [$case], ['skill_description', 'skill_unapproved', 'grade_unlinked',
            'source_excerpt', 'source_pages', 'source_removed', 'competency_description', 'competency_unapproved',
            'document_unreleased', 'document_revision', 'document_hash']);
    }

    public function testNewReleasedRevisionDoesNotReplaceTheRevisionActuallySentToAi(): void
    {
        $next = $this->curriculum(overrides: ['status' => 'draft', 'ready_at' => null, 'revision' => 2,
            'sha256' => $this->database->get('bncc_documents', 'sha256', ['id' => $this->curriculum['document_id']])]);
        $this->skill($next, 'EF06MA07', [6], 'Frações na NOVA revisão SINTÉTICA.');
        $this->fixtures->competency($next, overrides: ['review_status' => 'approved', 'reviewed_at' => '2026-01-01 00:00:00']);
        $fake = $this->fake();
        $provider = $this->duringGeneration($fake, function () use ($next): void {
            $this->concurrent->update('bncc_documents', ['status' => 'ready', 'ready_at' => '2026-09-29 12:00:00'], ['id' => $next['document_id']]);
        });
        $result = (new ExamGenerationService($this->bncc, $provider))->generate(ExamFixture::input());
        self::assertSame($this->curriculum['document_id'], $result->generation()['bncc_validation']['document_id']);
        self::assertSame(1, $result->generation()['bncc_validation']['document_revision']);
        self::assertSame($this->skillId, $result->generation()['bncc_validation']['skills'][0]['id']);
        $latest = $this->bncc->find(BnccContextQuery::fromArray(['subject' => 'matematica', 'grade' => 6, 'topics' => ['frações']]));
        self::assertSame($next['document_id'], $latest['document']['id']);
        self::assertSame(1, $fake->callCount());
    }

    #[DataProvider('invalidSources')]
    public function testInvalidProvenanceStopsBeforeProvider(string $table, array $changes): void
    {
        $id = $table === 'bncc_skill_contexts' ? $this->sourceId : $this->competencyId;
        $this->database->update($table, $changes, ['id' => $id]);
        $fake = $this->fake();
        try {
            (new ExamGenerationService($this->bncc, $fake))->generate($this->input());
            self::fail('Origem incompleta deveria interromper a geração.');
        } catch (ImportException $exception) {
            self::assertSame('invalid_curriculum', $exception->reason);
            self::assertSame('source_provenance', $exception->details['check']);
            self::assertSame(0, $fake->callCount());
        }
    }

    public static function invalidSources(): array
    {
        return [['bncc_skill_contexts', ['page_end' => 41]], ['bncc_competencies', ['page_end' => 41]],
            ['bncc_competencies', ['source_excerpt' => "\u{00a0}"]]];
    }

    public function testRequestedTopicsCannotBeSilentlyOmittedFromTheExamReferences(): void
    {
        $this->skill($this->curriculum, 'EF06MA08', [6], 'Decimais SINTÉTICOS.');
        $fake = $this->fake();
        try {
            (new ExamGenerationService($this->bncc, $fake))->generate($this->input(['topics' => ['frações', 'decimais']]));
            self::fail('Faltam referências para decimais.');
        } catch (ExamGenerationException $exception) {
            self::assertSame('bncc_topic_references', $exception->details['check']);
            self::assertSame(['decimais'], $exception->details['unrepresented_topics']);
            self::assertSame(1, $fake->callCount());
            self::assertCount(2, $fake->lastRequest()->input()->bncc_context->skills);
        }
    }

    public function testExistingCodeForSameGradeButAnotherSubjectIsNotAccepted(): void
    {
        $other = $this->curriculum('arte');
        $this->skill($other, 'EF69AR01', [6, 7, 8, 9], 'Frações em contexto SINTÉTICO de arte.');
        $data = ExamFixture::response();
        $data['exam']['questions'][0]['bncc_skills'] = ['EF69AR01'];
        $fake = $this->fake($data);
        try {
            (new ExamGenerationService($this->bncc, $fake))->generate($this->input());
            self::fail('Habilidade de outra matéria.');
        } catch (ExamGenerationException $exception) {
            self::assertSame('bncc_skills', $exception->details['check']);
            self::assertSame(1, $fake->callCount());
        }
    }

    public function testSkillAssignedToSeveralYearsIsAcceptedThroughItsDatabaseRelations(): void
    {
        $art = $this->curriculum('arte');
        $id = $this->skill($art, 'EF69AR01', [6, 7, 8, 9], 'Composição SINTÉTICA.');
        $this->fixtures->competency($art, overrides: ['review_status' => 'approved', 'reviewed_at' => '2026-01-01 00:00:00']);
        $data = ExamFixture::response();
        foreach ($data['exam']['questions'] as &$question) {
            $question['bncc_skills'] = ['EF69AR01'];
        }
        unset($question);
        $result = (new ExamGenerationService($this->bncc, $this->fake($data)))->generate(
            ExamFixture::input(['subject' => 'arte', 'grade' => 7, 'topics' => ['composição'], 'bncc_document_id' => $art['document_id']]));
        self::assertSame($id, $result->generation()['bncc_validation']['skills'][0]['id']);
        self::assertSame([6, 7, 8, 9], $result->bnccContext()['skills'][0]['grades']);
        self::assertSame(7, $result->exam()['grade']['year_number']);
        self::assertSame('arte', $result->exam()['subject']['code']);
    }

    public function testHttpReportsChangedCurriculumAsConflictAndNeverIncludesAPartialExam(): void
    {
        $fake = $this->fake();
        $provider = $this->duringGeneration($fake, function (): void {
            $this->concurrent->update('bncc_skills', ['description' => 'Frações com texto privado alterado.'], ['id' => $this->skillId]);
        });
        $app = AppFactory::create();
        (require __DIR__ . '/../../config/routes.php')($app, $this->connections, $provider);
        $app->addRoutingMiddleware();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/exams/generate')->withHeader('Content-Type', 'application/json');
        $request->getBody()->write(json_encode($this->input()));
        $request->getBody()->rewind();
        $response = $app->handle((new AuthFixture($this->database))->authorize($request, $this->session));
        self::assertSame(409, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        self::assertSame('curriculum_changed', $body['error']['code']);
        self::assertArrayNotHasKey('exam', $body);
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame(1, $fake->callCount());
    }

    private function curriculum(string $subject = 'matematica', array $overrides = []): array
    {
        $id = $this->fixtures->document(array_replace(['status' => 'ready', 'ready_at' => '2026-01-01 00:00:00',
            'structured_at' => '2026-01-01 00:00:00', 'structure_profile' => 'synthetic-validation-test'], $overrides));
        $this->documents[] = $id;
        $section = $this->fixtures->section($id);
        $area = $this->fixtures->area($id, $section, ['code' => $subject]);
        return ['document_id' => $id, 'section_id' => $section, 'area_id' => $area,
            'component_id' => $this->fixtures->component($id, $area, $section, $subject)];
    }

    private function skill(array $curriculum, string $code, array $grades, string $description): int
    {
        $id = $this->fixtures->skill($curriculum, ['code' => $code, 'description' => $description,
            'review_status' => 'approved', 'reviewed_at' => '2026-01-01 00:00:00']);
        foreach ($grades as $grade) {
            $gradeId = $this->database->get('school_grades', 'id', ['education_stage' => 'ensino_fundamental', 'year_number' => $grade]);
            $this->database->insert('bncc_skill_grades', ['skill_id' => $id, 'school_grade_id' => $gradeId]);
        }
        $this->fixtures->context($curriculum, $id);
        return $id;
    }

    private function duringGeneration(FakeAIProvider $fake, \Closure $change): AIProviderInterface
    {
        return new class($fake, $change, $this->database) implements AIProviderInterface {
            public function __construct(private FakeAIProvider $fake, private \Closure $change, private Medoo $database) {}
            public function generate(AIRequest $request): AIResponse
            {
                TestCase::assertFalse($this->database->pdo->inTransaction());
                ($this->change)();
                return $this->fake->generate($request);
            }
        };
    }

    private function input(array $overrides = []): array
    {
        return ExamFixture::input(array_replace(['bncc_document_id' => $this->curriculum['document_id']], $overrides));
    }

    private function fake(?array $data = null): FakeAIProvider
    {
        return new FakeAIProvider(json_encode($data ?? ExamFixture::response(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function snapshot(): string
    {
        $rows = [];
        foreach (['bncc_documents', 'bncc_skills', 'bncc_skill_grades', 'bncc_skill_contexts', 'bncc_competencies'] as $table) {
            $rows[$table] = $this->database->select($table, '*');
        }
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }
}
