<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Http\OpenApi\OpenApiDefinition;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Examples\Demo\DemoAIProvider;
use Medoo\Medoo;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\BnccFixtures;
use Tests\Support\AuthFixture;
use Tests\Support\CurriculumFixture;
use Tests\Support\GenerationHistoryFixture;
use Tests\Support\OpenApiAssertions;

require_once __DIR__ . '/../../examples/demo/FractionsExam.php';
require_once __DIR__ . '/../../examples/demo/DemoAIProvider.php';

final class SwaggerFlowTest extends TestCase
{
    use OpenApiAssertions;

    private Medoo $database;
    private App $app;
    private int $documentId;
    private int $competencyId;
    private array $session;

    protected function setUp(): void
    {
        $connections = new DatabaseConnectionFactory(require __DIR__ . '/../../config/database.php');
        $this->database = $connections->create();
        $this->session = (new AuthFixture($this->database))->create();
        $fixtures = new BnccFixtures($this->database);
        $this->documentId = $fixtures->document(['status' => 'ready', 'ready_at' => '2026-01-01 00:00:00',
            'structured_at' => '2026-01-01 00:00:00', 'structure_profile' => 'synthetic-swagger-test']);
        $section = $fixtures->section($this->documentId);
        $area = $fixtures->area($this->documentId, $section, ['code' => 'matematica']);
        $curriculum = ['document_id' => $this->documentId, 'section_id' => $section, 'area_id' => $area,
            'component_id' => $fixtures->component($this->documentId, $area, $section, 'matematica')];
        $skill = $fixtures->skill($curriculum, ['code' => 'EF06MA07', 'description' => 'Comparar frações — teste sintético.',
            'review_status' => 'approved', 'reviewed_at' => '2026-01-01 00:00:00']);
        $this->database->insert('bncc_skill_grades', ['skill_id' => $skill,
            'school_grade_id' => $this->database->get('school_grades', 'id', ['year_number' => 6])]);
        $fixtures->context($curriculum, $skill);
        $this->competencyId = $fixtures->competency($curriculum, overrides: ['review_status' => 'approved', 'reviewed_at' => '2026-01-01 00:00:00']);
        $this->app = AppFactory::create();
        (require __DIR__ . '/../../config/routes.php')($this->app, $connections, new DemoAIProvider());
        $this->app->addRoutingMiddleware();
    }

    protected function tearDown(): void
    {
        (new GenerationHistoryFixture($this->database))->removeForDocument($this->documentId);
        (new CurriculumFixture($this->database))->remove($this->documentId);
        (new AuthFixture($this->database))->remove($this->session);
    }

    public function testSwaggerExampleGeneratesAndRetrievesExamAnswersAndHistoryMatchingDocumentedSchemas(): void
    {
        $data = $this->request('POST', '/exams/generate', 'GenerationResult', [
            ...OpenApiDefinition::examExample(), 'bncc_document_id' => $this->documentId,
        ]);
        self::assertSame('fake', $data['generation']['provider']);
        self::assertSame('simulated', $data['generation']['estimated_cost']['basis']);
        $exam = $this->request('GET', '/exams/' . $data['exam']['id'], 'ExamLookup');
        // MySQL JSON reorganiza chaves de objetos; a ordem das questões continua relevante.
        self::assertEquals($data['exam'], $exam['exam']);
        self::assertArrayNotHasKey('answer_key', $exam);
        $key = $this->request('GET', '/exams/' . $data['exam']['id'] . '/answer-key', 'AnswerKeyLookup');
        self::assertEquals($data['answer_key'], $key['answer_key']);
        $history = $this->request('GET', '/generations/' . $data['generation']['id'], 'GenerationDetail');
        self::assertSame('validated', $history['response_format']);
        $listing = $this->request('GET', '/generations?limit=1&status=succeeded', 'GenerationList');
        self::assertSame($data['generation']['id'], $listing['items'][0]['id']);
    }

    public function testCurriculumReadRoutesMatchTheirDocumentedSchemas(): void
    {
        $base = '/bncc/documents/' . $this->documentId;
        foreach (['' => 'Document', '/pages' => 'Pages', '/structure' => 'Structure', '/skills' => 'Skills',
            '/skills/EF06MA07' => 'Skill', '/competencies' => 'Competencies'] as $suffix => $schema) {
            $this->request('GET', $base . $suffix, $schema);
        }
        $this->request('POST', '/bncc/context', 'BnccContext', ['subject' => 'matematica', 'grade' => 6,
            'topics' => ['frações'], 'document_id' => $this->documentId]);
    }

    public function testDemoErrorsAndFailedHistoryAreDocumentedWithoutSavingAnExam(): void
    {
        $error = $this->request('POST', '/exams/generate', 'Error', [
            ...OpenApiDefinition::examExample(), 'bncc_document_id' => $this->documentId, 'image_mode' => 'required',
        ], 422);
        self::assertSame('invalid_input', $error['error']['code']);
        $history = $this->request('GET', '/generations/' . $error['error']['generation_id'], 'GenerationDetail');
        self::assertSame('failed', $history['status']);
        self::assertNull($history['exam_id']);
        $this->request('GET', '/exams/4294967295', 'Error', status: 404);
        $this->request('POST', '/exams/generate', 'Error', ['question_count' => 0], 422);
        $this->request('PATCH', '/bncc/documents/' . $this->documentId . '/competencies/' . $this->competencyId . '/review',
            'Error', ['status' => 'pending', 'note' => 'Não se pode revisar ready.'], 409);
    }

    private function request(string $method, string $url, string $schema, ?array $input = null, int $status = 200): array
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $url);
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
        $request = $request->withQueryParams($query);
        if ($input !== null) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request->getBody()->write(json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $request->getBody()->rewind();
        }
        $response = $this->app->handle((new AuthFixture($this->database))->authorize($request, $this->session));
        $body = (string) $response->getBody();
        self::assertSame($status, $response->getStatusCode(), $body);
        $value = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        $this->assertOpenApiSchema($schema, $value);
        return $value;
    }
}
