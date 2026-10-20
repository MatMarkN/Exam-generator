<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\AI\GeminiAIProvider;
use App\Infrastructure\AI\Http\HttpResponse;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Medoo\Medoo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\BnccFixtures;
use Tests\Support\CurriculumFixture;
use Tests\Support\ExamFixture;
use Tests\Support\StubHttpTransport;

final class ExamGenerationHttpTest extends TestCase
{
    private Medoo $database;
    private int $documentId;
    private StubHttpTransport $http;
    private App $app;

    protected function setUp(): void
    {
        $connections = new DatabaseConnectionFactory(require __DIR__ . '/../../config/database.php');
        $this->database = $connections->create();
        $fixtures = new BnccFixtures($this->database);
        $this->documentId = $fixtures->document(['status' => 'ready', 'ready_at' => '2026-01-01 00:00:00',
            'structured_at' => '2026-01-01 00:00:00', 'structure_profile' => 'synthetic-gemini-fixture']);
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
        $this->http = new StubHttpTransport(StubHttpTransport::success(json_encode(ExamFixture::response())));
        $this->app = AppFactory::create();
        (require __DIR__ . '/../../config/routes.php')($this->app, $connections, new GeminiAIProvider('test-secret', http: $this->http));
        $this->app->addRoutingMiddleware();
    }

    protected function tearDown(): void
    {
        (new \Tests\Support\GenerationHistoryFixture($this->database))->removeForDocument($this->documentId);
        (new CurriculumFixture($this->database))->remove($this->documentId);
    }

    public function testRegisteredRouteUsesRealAdapterAndValidatesStudentExamAndSeparateAnswerKey(): void
    {
        $before = $this->database->get('bncc_documents', '*', ['id' => $this->documentId]);
        $response = $this->post();
        self::assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        self::assertCount(3, $data['exam']['questions']);
        self::assertCount(3, $data['answer_key']);
        self::assertArrayNotHasKey('answer_key', $data['exam']);
        self::assertSame('gemini', $data['generation']['provider']);
        self::assertSame(40, $data['generation']['output_tokens']);
        self::assertSame('passed', $data['generation']['bncc_validation']['status']);
        self::assertTrue($data['generation']['bncc_validation']['database_rechecked']);
        self::assertSame('not_performed', $data['generation']['bncc_validation']['semantic_review']);
        self::assertSame($this->documentId, $data['generation']['bncc_validation']['document_id']);
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertCount(1, $this->http->calls);
        $sent = json_decode(json_decode($this->http->calls[0]['json'], true)['contents'][0]['parts'][0]['text'], true);
        self::assertSame(['EF06MA07'], array_column($sent['bncc_context']['skills'], 'code'));
        self::assertSame(5, $sent['teacher_request']['alternatives_count']);
        self::assertSame(3, $sent['teacher_request']['true_false_statements']);
        self::assertSame($before, $this->database->get('bncc_documents', '*', ['id' => $this->documentId]));
        self::assertStringNotContainsString('test-secret', (string) $response->getBody());
        $history = (new \App\Infrastructure\Persistence\GenerationHistoryRepository($this->database))->find($data['generation']['id']);
        self::assertSame('succeeded', $history['status']);
        self::assertSame($data['exam']['id'], $history['exam_id']);
        self::assertSame('gemini', $history['provider']);
        self::assertSame('pricing_not_configured', $history['estimated_cost']['basis']);
        self::assertStringNotContainsString('test-secret', json_encode($history));
    }

    public function testDraftCurriculumPreventsTheHttpCall(): void
    {
        $this->database->update('bncc_documents', ['status' => 'draft', 'ready_at' => null], ['id' => $this->documentId]);
        $response = $this->post();
        self::assertSame(409, $response->getStatusCode());
        self::assertSame('document_not_ready', json_decode((string) $response->getBody(), true)['error']['code']);
        self::assertCount(0, $this->http->calls);
    }

    #[DataProvider('badInputs')]
    public function testBadInputsStopBeforeCallingGoogle(string $body, string $contentType, int $status): void
    {
        $response = $this->post($body, $contentType);
        self::assertSame($status, $response->getStatusCode());
        self::assertCount(0, $this->http->calls);
    }

    public static function badInputs(): array
    {
        return [['{}', 'text/plain', 415], ['{broken', 'application/json', 422], ['[]', 'application/json', 422],
            ['{"question_count":0}', 'application/json', 422], [str_repeat('x', 65537), 'application/json', 413]];
    }

    #[DataProvider('upstreamFailures')]
    public function testUpstreamFailuresProduceUsefulSafeStatusAndNoExam(int $upstream, int $status, string $code): void
    {
        $this->http->result = new HttpResponse($upstream, '{"error":{"message":"test-secret private"}}', ['retry-after' => '9']);
        $response = $this->post();
        $body = (string) $response->getBody();
        self::assertSame($status, $response->getStatusCode());
        self::assertSame($code, json_decode($body, true)['error']['code']);
        self::assertStringNotContainsString('test-secret', $body);
        self::assertArrayNotHasKey('exam', json_decode($body, true));
        self::assertSame(in_array($upstream, [429, 503], true) ? '9' : '', $response->getHeaderLine('Retry-After'));
        self::assertCount(1, $this->http->calls);
    }

    public static function upstreamFailures(): array
    {
        return [[401, 502, 'ai_authentication_failed'], [402, 503, 'ai_billing_required'],
            [429, 429, 'ai_rate_limited'], [503, 503, 'ai_unavailable'], [504, 504, 'ai_timeout'],
            [404, 502, 'ai_unsupported_feature'], [400, 502, 'ai_invalid_request']];
    }

    public function testGeminiOutputWithSkillOutsideTheSelectedContextIsRejected(): void
    {
        $data = ExamFixture::response();
        $data['exam']['questions'][0]['bncc_skills'] = ['EF07MA07'];
        $this->http->result = StubHttpTransport::success(json_encode($data));
        $response = $this->post();
        self::assertSame(502, $response->getStatusCode());
        self::assertSame('invalid_exam_response', json_decode((string) $response->getBody(), true)['error']['code']);
        self::assertCount(1, $this->http->calls);
    }

    public function testApplicationBootsWithoutKeyButReadyGenerationReportsMissingConfiguration(): void
    {
        // O Compose de testes não recebe GEMINI_API_KEY, mesmo que o .env local esteja preenchido.
        self::assertSame('', getenv('GEMINI_API_KEY') ?: '');
        $this->app = require __DIR__ . '/../../config/bootstrap.php';
        $health = $this->app->handle((new ServerRequestFactory())->createServerRequest('GET', '/health'));
        self::assertSame(200, $health->getStatusCode());
        $response = $this->post();
        self::assertSame(503, $response->getStatusCode());
        self::assertSame('ai_configuration_missing', json_decode((string) $response->getBody(), true)['error']['code']);
        self::assertCount(0, $this->http->calls);
    }

    private function post(?string $body = null, string $contentType = 'application/json'): \Psr\Http\Message\ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/exams/generate')->withHeader('Content-Type', $contentType);
        $request->getBody()->write($body ?? json_encode(ExamFixture::input(['bncc_document_id' => $this->documentId])));
        $request->getBody()->rewind();
        return $this->app->handle($request);
    }
}
