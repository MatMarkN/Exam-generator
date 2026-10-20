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
use App\Application\Exams\ExamGenerationService;
use App\Infrastructure\AI\FakeAIProvider;
use App\Infrastructure\Persistence\BnccContextRepository;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use App\Infrastructure\Persistence\GenerationHistoryRepository;
use App\Infrastructure\Persistence\TeacherRepository;
use Medoo\Medoo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\BnccFixtures;
use Tests\Support\CurriculumFixture;
use Tests\Support\ExamFixture;
use Tests\Support\GenerationHistoryFixture;

final class GenerationHistoryTest extends TestCase
{
    private Medoo $database;
    private DatabaseConnectionFactory $connections;
    private GenerationHistoryRepository $history;
    private int $documentId;
    private int $skillId;
    private array $teachers = [];

    protected function setUp(): void
    {
        $this->connections = new DatabaseConnectionFactory(require __DIR__ . '/../../config/database.php');
        $this->database = $this->connections->create();
        $this->history = new GenerationHistoryRepository($this->database);
        $fixtures = new BnccFixtures($this->database);
        $this->documentId = $fixtures->document(['status' => 'ready', 'ready_at' => '2026-01-01 00:00:00',
            'structured_at' => '2026-01-01 00:00:00', 'structure_profile' => 'synthetic-history-test']);
        $section = $fixtures->section($this->documentId);
        $area = $fixtures->area($this->documentId, $section, ['code' => 'matematica']);
        $curriculum = ['document_id' => $this->documentId, 'section_id' => $section, 'area_id' => $area,
            'component_id' => $fixtures->component($this->documentId, $area, $section, 'matematica')];
        $this->skillId = $fixtures->skill($curriculum, ['code' => 'EF06MA07', 'description' => 'Frações SINTÉTICAS do histórico.',
            'review_status' => 'approved', 'reviewed_at' => '2026-01-01 00:00:00']);
        $this->database->insert('bncc_skill_grades', ['skill_id' => $this->skillId,
            'school_grade_id' => $this->database->get('school_grades', 'id', ['year_number' => 6])]);
        $fixtures->context($curriculum, $this->skillId);
        $fixtures->competency($curriculum, overrides: ['review_status' => 'approved', 'reviewed_at' => '2026-01-01 00:00:00']);
    }

    protected function tearDown(): void
    {
        foreach (['exam_answers' => ['history_test_answer_failure'],
            'generation_history' => ['history_test_start_failure', 'history_test_finish_failure', 'history_test_update_failure']] as $table => $checks) {
            foreach ($checks as $check) {
                if ($this->database->query('SELECT COUNT(*) FROM information_schema.check_constraints
                    WHERE constraint_schema = DATABASE() AND constraint_name = :name', [':name' => $check])->fetchColumn() > 0) {
                    $this->database->query("ALTER TABLE $table DROP CHECK $check");
                }
            }
        }
        (new GenerationHistoryFixture($this->database))->removeForDocument($this->documentId);
        (new CurriculumFixture($this->database))->remove($this->documentId);
        if ($this->teachers !== []) {
            $this->database->delete('teachers', ['id' => $this->teachers]);
        }
    }

    public function testCommittedHistoryIsVisibleBeforeProviderWithoutHoldingATransaction(): void
    {
        $fake = $this->fake();
        $reader = new GenerationHistoryRepository($this->connections->create());
        $teacher = $this->teacher();
        $provider = $this->during($fake, function (AIRequest $request) use ($reader, $teacher): void {
            self::assertFalse($this->database->pdo->inTransaction());
            $running = $reader->listing(['status' => 'running', 'teacher_id' => $teacher])['items'];
            self::assertCount(1, $running);
            self::assertSame('provider', $running[0]['stage']);
            $detail = $reader->find($running[0]['id']);
            self::assertSame($request->inputJson, $detail['ai_request']['input_json']);
            self::assertSame($request->outputSchemaJson, $detail['ai_request']['output_schema_json']);
            self::assertNull($detail['exam_id']);
            self::assertNull($detail['finished_at']);
        });
        $result = $this->service($provider)->generate($this->input(['teacher_id' => $teacher, 'teacher_notes' => 'DADO_PRIVADO_DO_PEDIDO']));
        $saved = $reader->find($result->generation()['id']);
        self::assertSame('succeeded', $saved['status']);
        self::assertSame('completed', $saved['stage']);
        self::assertSame($result->exam()['id'], $saved['exam_id']);
        self::assertSame($teacher, $saved['teacher_id']);
        self::assertSame('Professor SINTÉTICO privado', $saved['requester_name']);
        self::assertFalse($saved['requester_verified']);
        self::assertSame($this->documentId, $saved['bncc_document_id']);
        self::assertSame('0.00000000', $saved['estimated_cost']['amount']);
        self::assertSame($result->generation()['total_duration_ms'], $saved['total_duration_ms']);
        self::assertSame('validated', $saved['response_format']);
        self::assertSame(hash('sha256', json_encode(ExamFixture::response())), $saved['response_sha256']);
        self::assertEquals($result->answerKey(), $reader->answerKey($saved['exam_id'])['answer_key']);
        self::assertEquals($result->exam(), $reader->exam($saved['exam_id'])['exam']);
        self::assertNull($this->database->get('generation_history', 'rejected_response_json', ['id' => $saved['id']]));
        self::assertSame(1, $fake->callCount());
        self::assertFalse($this->database->pdo->inTransaction());
        $log = $this->logs();
        self::assertStringContainsString('generation_succeeded', $log);
        self::assertStringContainsString('"generation_id":' . $saved['id'], $log);
        foreach (['DADO_PRIVADO_DO_PEDIDO', 'Professor SINTÉTICO privado', 'Resposta SINT', 'source_excerpt'] as $private) {
            self::assertStringNotContainsString($private, $log);
        }
    }

    public function testSnapshotsSurviveTeacherDeletionAndCurriculumChanges(): void
    {
        $teacher = $this->teacher();
        $result = $this->service($this->fake())->generate($this->input(['teacher_id' => $teacher]));
        $before = $this->history->find($result->generation()['id']);
        $this->database->delete('teachers', ['id' => $teacher]);
        $this->database->update('bncc_skills', ['description' => 'Texto alterado depois da geração.'], ['id' => $this->skillId]);
        $this->database->update('bncc_documents', ['status' => 'draft', 'ready_at' => null], ['id' => $this->documentId]);
        $after = $this->history->find($before['id']);
        self::assertNull($after['teacher_id']);
        self::assertSame($teacher, $after['request']['teacher_id']);
        self::assertSame($before['requester_name'], $after['requester_name']);
        self::assertSame($before['ai_request'], $after['ai_request']);
        self::assertSame($before['response'], $after['response']);
        self::assertSame($before['result_metadata'], $after['result_metadata']);
    }

    #[DataProvider('providerFailures')]
    public function testProviderErrorsAreRecordedAndHttpClassificationIsPreserved(AIErrorReason $reason, int $status): void
    {
        $fake = new FakeAIProvider(new AIProviderException($reason, $reason === AIErrorReason::RateLimited ? 8 : null));
        $response = $this->call($this->app($fake), 'POST', '/exams/generate', $this->input());
        self::assertSame($status, $response->getStatusCode());
        $error = $this->body($response)['error'];
        $history = $this->history->find($error['generation_id']);
        self::assertSame('ai_' . $reason->value, $history['error_code']);
        self::assertSame($error['message'], $history['error_message']);
        self::assertSame('failed', $history['status']);
        self::assertSame('provider', $history['stage']);
        self::assertSame('fake', $history['provider']);
        self::assertSame('static-fixture-v1', $history['requested_model']);
        self::assertNull($history['model']);
        self::assertNull($history['response']);
        self::assertNull($history['exam_id']);
        self::assertNull($history['estimated_cost']['amount']);
        self::assertSame(1, $fake->callCount());
        self::assertSame($reason === AIErrorReason::RateLimited ? '8' : '', $response->getHeaderLine('Retry-After'));
    }

    public static function providerFailures(): array
    {
        return [[AIErrorReason::RateLimited, 429], [AIErrorReason::Timeout, 504], [AIErrorReason::Refused, 422],
            [AIErrorReason::ConfigurationMissing, 503], [AIErrorReason::InvalidResponse, 502]];
    }

    public function testDraftCurriculumIsRecordedWithoutCallingProvider(): void
    {
        $this->database->update('bncc_documents', ['status' => 'draft', 'ready_at' => null], ['id' => $this->documentId]);
        $fake = $this->fake();
        $response = $this->call($this->app($fake), 'POST', '/exams/generate', $this->input());
        self::assertSame(409, $response->getStatusCode());
        $saved = $this->history->find($this->body($response)['error']['generation_id']);
        self::assertSame('context', $saved['stage']);
        self::assertSame('document_not_ready', $saved['error_code']);
        self::assertNull($saved['ai_request']);
        self::assertNull($saved['exam_id']);
        self::assertSame(0, $fake->callCount());
    }

    public function testRejectedResponseIsKeptAsUnvalidatedContentWithoutCreatingExam(): void
    {
        $invalid = ExamFixture::response();
        $invalid['exam']['questions'][0]['bncc_skills'] = ['EF99MA99'];
        $json = json_encode($invalid);
        $fake = new FakeAIProvider($json);
        $response = $this->call($this->app($fake), 'POST', '/exams/generate', $this->input());
        self::assertSame(502, $response->getStatusCode());
        $saved = $this->history->find($this->body($response)['error']['generation_id']);
        self::assertSame('validation', $saved['stage']);
        self::assertSame('invalid_exam_response', $saved['error_code']);
        self::assertSame('unvalidated_json', $saved['response_format']);
        self::assertSame($json, $saved['response']);
        self::assertSame(hash('sha256', $json), $saved['response_sha256']);
        self::assertNull($saved['exam_id']);
        self::assertNull($saved['result_metadata']);
        self::assertSame(0, $this->database->count('exams', ['generation_id' => $saved['id']]));
    }

    public function testCurriculumChangeKeepsOriginalSnapshotButNeverSavesExam(): void
    {
        $fake = $this->fake();
        $provider = $this->during($fake, function (): void {
            $this->connections->create()->update('bncc_skills', ['description' => 'Frações ALTERADAS.'], ['id' => $this->skillId]);
        });
        $response = $this->call($this->app($provider), 'POST', '/exams/generate', $this->input());
        self::assertSame(409, $response->getStatusCode());
        $saved = $this->history->find($this->body($response)['error']['generation_id']);
        self::assertSame('curriculum_changed', $saved['error_code']);
        self::assertStringNotContainsString('ALTERADAS', $saved['ai_request']['input_json']);
        self::assertNull($saved['exam_id']);
        self::assertSame(1, $fake->callCount());
    }

    public function testFailureSavingAnswerKeyRollsBackExamAndRecordsFailureWithoutRetry(): void
    {
        $lastExamId = (int) ($this->database->max('exams', 'id') ?? 0);
        $this->database->query("ALTER TABLE exam_answers ADD CONSTRAINT history_test_answer_failure CHECK (exam_id <= $lastExamId)");
        $fake = $this->fake();
        $before = $this->database->count('exams');
        $response = $this->call($this->app($fake), 'POST', '/exams/generate', $this->input());
        self::assertSame(503, $response->getStatusCode());
        $saved = $this->history->find($this->body($response)['error']['generation_id']);
        self::assertSame('persistence', $saved['stage']);
        self::assertSame('failed', $saved['status']);
        self::assertNull($saved['exam_id']);
        self::assertSame($before, $this->database->count('exams'));
        self::assertSame(1, $fake->callCount());
        self::assertFalse($this->database->pdo->inTransaction());
        self::assertStringNotContainsString('history_test_answer_failure', json_encode($saved));
        self::assertStringNotContainsString('history_test_answer_failure', $this->logs());
        self::assertStringNotContainsString('history_test_answer_failure', (string) $response->getBody());
    }

    public function testFailureToStartHistoryPreventsAnyProviderCall(): void
    {
        $this->database->query("ALTER TABLE generation_history ADD CONSTRAINT history_test_start_failure
            CHECK (CAST(JSON_UNQUOTE(JSON_EXTRACT(request_json, '$.bncc_document_id')) AS UNSIGNED) <> {$this->documentId})");
        $fake = $this->fake();
        $response = $this->call($this->app($fake), 'POST', '/exams/generate', $this->input());
        self::assertSame(503, $response->getStatusCode());
        self::assertArrayNotHasKey('generation_id', $this->body($response)['error']);
        self::assertSame(0, $fake->callCount());
        self::assertStringNotContainsString('history_test_start_failure', $this->logs());
    }

    public function testFailureToMarkSuccessRollsBackBothExamAndAnswerKey(): void
    {
        $this->database->query("ALTER TABLE generation_history ADD CONSTRAINT history_test_finish_failure
            CHECK (status <> 'succeeded' OR bncc_document_id <> {$this->documentId})");
        $fake = $this->fake();
        $beforeExams = $this->database->count('exams');
        $beforeAnswers = $this->database->count('exam_answers');
        $response = $this->call($this->app($fake), 'POST', '/exams/generate', $this->input());
        self::assertSame(503, $response->getStatusCode());
        $history = $this->history->find($this->body($response)['error']['generation_id']);
        self::assertSame('failed', $history['status']);
        self::assertSame('persistence', $history['stage']);
        self::assertSame($beforeExams, $this->database->count('exams'));
        self::assertSame($beforeAnswers, $this->database->count('exam_answers'));
        self::assertSame(1, $fake->callCount());
    }

    public function testHistoryWriteOutagePreservesInitialRecordAndLogsCorrelation(): void
    {
        $this->database->query("ALTER TABLE generation_history ADD CONSTRAINT history_test_update_failure
            CHECK (CAST(JSON_UNQUOTE(JSON_EXTRACT(request_json, '$.bncc_document_id')) AS UNSIGNED) <> {$this->documentId}
                OR (status = 'running' AND stage = 'context'))");
        $fake = $this->fake();
        $response = $this->call($this->app($fake), 'POST', '/exams/generate', $this->input());
        self::assertSame(503, $response->getStatusCode());
        $id = $this->body($response)['error']['generation_id'];
        self::assertSame('running', $this->history->find($id)['status']);
        self::assertSame(0, $fake->callCount());
        $logs = $this->logs();
        self::assertStringContainsString('generation_history_write_failed', $logs);
        self::assertStringContainsString('"generation_id":' . $id, $logs);
        self::assertStringNotContainsString('history_test_update_failure', $logs);
    }

    public function testCompletedGenerationCannotBeCompletedAgainOrMarkedFailed(): void
    {
        $result = $this->service($this->fake())->generate($this->input());
        $id = $result->generation()['id'];
        $before = $this->history->find($id);
        try {
            $this->history->complete($id, $result, hrtime(true));
            self::fail('Não deve criar outra prova para a mesma geração.');
        } catch (\LogicException) {
            self::assertFalse($this->database->pdo->inTransaction());
        }
        try {
            $this->history->fail($id, new \RuntimeException('private'), 'provider', 0, null, []);
            self::fail('Uma geração finalizada não deve ser sobrescrita.');
        } catch (\LogicException) {
            self::assertSame($before, $this->history->find($id));
            self::assertSame(1, $this->database->count('exams', ['generation_id' => $id]));
        }
    }

    public function testUnexpectedExceptionIsSanitizedInHistoryHttpAndTechnicalLog(): void
    {
        $provider = $this->during($this->fake(), static function (): void { throw new \RuntimeException('PRIVATE_SECRET_FROM_EXCEPTION'); });
        $response = $this->call($this->app($provider), 'POST', '/exams/generate', $this->input());
        self::assertSame(500, $response->getStatusCode());
        $history = $this->history->find($this->body($response)['error']['generation_id']);
        self::assertSame('generation_unavailable', $history['error_code']);
        self::assertStringNotContainsString('PRIVATE_SECRET_FROM_EXCEPTION', json_encode($history));
        self::assertStringNotContainsString('PRIVATE_SECRET_FROM_EXCEPTION', (string) $response->getBody());
        self::assertStringNotContainsString('PRIVATE_SECRET_FROM_EXCEPTION', $this->logs());
    }

    public function testMalformedOrUnknownTeacherRequestDoesNotCreateGeneration(): void
    {
        $fake = $this->fake();
        $app = $this->app($fake);
        $before = $this->database->count('generation_history');
        foreach ([['question_count' => 0], ['teacher_id' => 4294967295]] as $change) {
            $response = $this->call($app, 'POST', '/exams/generate', $this->input($change));
            self::assertSame(422, $response->getStatusCode());
            self::assertArrayNotHasKey('generation_id', $this->body($response)['error']);
        }
        self::assertSame($before, $this->database->count('generation_history'));
        self::assertSame(0, $fake->callCount());
    }

    public function testHistoryPaginationDoesNotRepeatEntriesAndOmitsLargeOrPrivateFields(): void
    {
        $teacher = $this->teacher();
        $ids = [];
        $service = $this->service($this->fake());
        for ($i = 0; $i < 3; ++$i) {
            $ids[] = $service->generate($this->input(['teacher_id' => $teacher, 'reference_text' => 'PRIVATE_REFERENCE']))->generation()['id'];
        }
        $app = $this->app($this->fake());
        $firstResponse = $this->call($app, 'GET', '/generations', query: ['limit' => '2', 'teacher_id' => (string) $teacher, 'status' => 'succeeded']);
        $first = $this->body($firstResponse);
        self::assertSame(200, $firstResponse->getStatusCode());
        self::assertSame([$ids[2], $ids[1]], array_column($first['items'], 'id'));
        self::assertSame($ids[1], $first['next_before_id']);
        $second = $this->body($this->call($app, 'GET', '/generations', query: ['limit' => '2', 'teacher_id' => (string) $teacher,
            'before_id' => (string) $first['next_before_id']]));
        self::assertSame([$ids[0]], array_column($second['items'], 'id'));
        self::assertNull($second['next_before_id']);
        self::assertSame(['frações'], $first['items'][0]['topics']);
        foreach (['PRIVATE_REFERENCE', 'ai_request', 'answer_key', 'rejected_response', 'teacher_notes'] as $field) {
            self::assertStringNotContainsString($field, (string) $firstResponse->getBody());
        }
    }

    public function testSeparateReadRoutesReturnSavedContentWithoutAnotherGeneration(): void
    {
        $fake = $this->fake();
        $app = $this->app($fake);
        $generated = $this->body($this->call($app, 'POST', '/exams/generate', $this->input()));
        $examId = $generated['exam']['id'];
        $exam = $this->call($app, 'GET', '/exams/' . $examId);
        self::assertSame('no-store', $exam->getHeaderLine('Cache-Control'));
        self::assertEquals($generated['exam'], $this->body($exam)['exam']);
        foreach (['answer_key', 'correct_alternative', 'expected_answer', 'grading_criteria', 'teacher_notes'] as $field) {
            self::assertStringNotContainsString($field, (string) $exam->getBody());
        }
        $answers = $this->call($app, 'GET', '/exams/' . $examId . '/answer-key');
        self::assertEquals($generated['answer_key'], $this->body($answers)['answer_key']);
        $detail = $this->call($app, 'GET', '/generations/' . $generated['generation']['id']);
        self::assertSame('succeeded', $this->body($detail)['status']);
        self::assertSame('no-store', $detail->getHeaderLine('Cache-Control'));
        self::assertSame(1, $fake->callCount());
        foreach (['/generations/4294967295', '/exams/4294967295', '/exams/4294967295/answer-key'] as $path) {
            self::assertSame(404, $this->call($app, 'GET', $path)->getStatusCode());
        }
        self::assertSame(422, $this->call($app, 'GET', '/generations/999999999999999999')->getStatusCode());
        self::assertSame(422, $this->call($app, 'GET', '/exams/0')->getStatusCode());
    }

    #[DataProvider('invalidFilters')]
    public function testInvalidHistoryFiltersAreRejected(array $query): void
    {
        self::assertSame(422, $this->call($this->app($this->fake()), 'GET', '/generations', query: $query)->getStatusCode());
    }

    public static function invalidFilters(): array
    {
        return [[['limit' => '0']], [['limit' => '101']], [['limit' => []]], [['before_id' => '1 OR 1=1']],
            [['teacher_id' => '-1']], [['status' => 'whatever']], [['status' => []]], [['unexpected' => 'x']]];
    }

    private function teacher(): int
    {
        $this->database->insert('teachers', ['name' => 'Professor SINTÉTICO privado']);
        $id = (int) $this->database->id();
        $this->teachers[] = $id;
        return $id;
    }

    private function input(array $change = []): array
    {
        return ExamFixture::input(array_replace(['bncc_document_id' => $this->documentId], $change));
    }

    private function fake(): FakeAIProvider
    {
        return new FakeAIProvider(json_encode(ExamFixture::response()));
    }

    private function service(AIProviderInterface $provider): ExamGenerationService
    {
        return new ExamGenerationService(new BnccContextService(new BnccContextRepository($this->database), new BnccTopicMatcher()),
            $provider, history: $this->history, teachers: new TeacherRepository($this->database));
    }

    private function during(FakeAIProvider $fake, \Closure $callback): AIProviderInterface
    {
        return new class($fake, $callback) implements AIProviderInterface {
            public function __construct(private FakeAIProvider $fake, private \Closure $callback) {}
            public function generate(AIRequest $request): AIResponse
            {
                ($this->callback)($request);
                return $this->fake->generate($request);
            }
        };
    }

    private function app(AIProviderInterface $provider): App
    {
        $app = AppFactory::create();
        (require __DIR__ . '/../../config/routes.php')($app, $this->connections, $provider);
        $app->addRoutingMiddleware();
        return $app;
    }

    private function call(App $app, string $method, string $path, ?array $input = null, array $query = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path)->withQueryParams($query);
        if ($input !== null) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request->getBody()->write(json_encode($input));
            $request->getBody()->rewind();
        }
        return $app->handle($request);
    }

    private function body(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function logs(): string
    {
        // PHPUnit 12.5 inicia sua captura após setUp(). Conferimos o destino ativo durante o teste.
        $this->expectErrorLog();
        return file_get_contents((string) ini_get('error_log'));
    }
}
