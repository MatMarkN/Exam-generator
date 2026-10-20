<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\AI\FakeAIProvider;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Medoo\Medoo;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\BnccFixtures;
use Tests\Support\CurriculumFixture;
use Tests\Support\ExamFixture;
use Tests\Support\GenerationHistoryFixture;
use Tests\Support\OpenApiAssertions;

final class ExamPresentationTest extends TestCase
{
    use OpenApiAssertions;

    private Medoo $database;
    private FakeAIProvider $provider;
    private App $app;
    private int $documentId;
    private int $teacherId;
    private \stdClass $lastResponse;

    protected function setUp(): void
    {
        $connections = new DatabaseConnectionFactory(require __DIR__ . '/../../config/database.php');
        $this->database = $connections->create();
        $fixtures = new BnccFixtures($this->database);
        $this->documentId = $fixtures->document(['status' => 'ready', 'ready_at' => '2026-01-01 00:00:00',
            'structured_at' => '2026-01-01 00:00:00', 'structure_profile' => 'synthetic-presentation-test']);
        $section = $fixtures->section($this->documentId);
        $area = $fixtures->area($this->documentId, $section, ['code' => 'matematica']);
        $curriculum = ['document_id' => $this->documentId, 'section_id' => $section, 'area_id' => $area,
            'component_id' => $fixtures->component($this->documentId, $area, $section, 'matematica')];
        $skill = $fixtures->skill($curriculum, ['code' => 'EF06MA07', 'description' => 'Frações SINTÉTICAS.',
            'review_status' => 'approved', 'reviewed_at' => '2026-01-01 00:00:00']);
        $this->database->insert('bncc_skill_grades', ['skill_id' => $skill,
            'school_grade_id' => $this->database->get('school_grades', 'id', ['year_number' => 6])]);
        $fixtures->context($curriculum, $skill);
        $fixtures->competency($curriculum, overrides: ['review_status' => 'approved', 'reviewed_at' => '2026-01-01 00:00:00']);
        $this->database->insert('teachers', ['name' => 'PROFESSORA_PRIVADA', 'email' => 'apresentacao@example.test']);
        $this->teacherId = (int) $this->database->id();
        $this->database->insert('teacher_settings', ['teacher_id' => $this->teacherId,
            'school_name' => 'ESCOLA_PRIVADA', 'default_font_family' => 'Calibri', 'default_font_size' => 14]);
        $this->provider = new FakeAIProvider(json_encode(ExamFixture::response(), JSON_THROW_ON_ERROR));
        $this->app = AppFactory::create();
        (require __DIR__ . '/../../config/routes.php')($this->app, $connections, $this->provider);
        $this->app->addRoutingMiddleware();
    }

    protected function tearDown(): void
    {
        (new GenerationHistoryFixture($this->database))->removeForDocument($this->documentId);
        (new CurriculumFixture($this->database))->remove($this->documentId);
        $this->database->delete('teachers', ['id' => $this->teacherId]);
    }

    public function testTeacherDefaultsAreCopiedIntoExamWithoutBeingSentToTheAiAndStayAfterProfileEdits(): void
    {
        $data = $this->generate(['teacher_id' => $this->teacherId]);
        $header = $data['exam']['header'];
        self::assertSame('PROFESSORA_PRIVADA', $header['teacher_name']);
        self::assertSame('ESCOLA_PRIVADA', $header['school_name']);
        self::assertNull($header['student_name']);
        self::assertNull($header['class_name']);
        self::assertNull($header['date']);
        self::assertSame($data['exam']['subject']['name'], $header['subject']);
        self::assertSame($data['exam']['grade']['name'], $header['grade']);
        self::assertSame(['font_family' => 'Calibri', 'font_size' => 14], $data['exam']['formatting']);
        self::assertSame(1, $this->provider->callCount());
        $sent = $this->provider->lastRequest()->inputJson;
        foreach (['PROFESSORA_PRIVADA', 'ESCOLA_PRIVADA', 'apresentacao@example.test', 'Calibri'] as $private) {
            self::assertStringNotContainsString($private, $sent);
        }
        self::assertFalse(property_exists($this->provider->lastRequest()->input()->teacher_request, 'teacher_id'));

        $this->request('PATCH', '/teachers/' . $this->teacherId, ['name' => 'Nome alterado']);
        $this->request('PATCH', '/teachers/' . $this->teacherId . '/settings',
            ['school_name' => 'Outra escola', 'default_font_family' => 'Arial', 'default_font_size' => 10]);
        $saved = $this->request('GET', '/exams/' . $data['exam']['id']);
        $this->assertOpenApiSchema('ExamLookup', $saved);
        self::assertEquals($data['exam'], $saved['exam']);
        self::assertArrayNotHasKey('answer_key', $saved);
        $history = $this->request('GET', '/generations/' . $data['generation']['id']);
        self::assertEquals($header, $history['response']['exam']['header']);
        self::assertSame('PROFESSORA_PRIVADA', $history['requester_name']);
    }

    public function testExplicitOverridesAndNullClearOnlyThisExamAndAreRecordedInHistory(): void
    {
        $configuration = ['teacher_id' => $this->teacherId,
            'exam_header' => ['school_name' => null, 'teacher_name' => 'PROFESSOR_EVENTUAL',
                'student_name' => 'ALUNO_PRIVADO', 'class_name' => 'TURMA_PRIVADA', 'date' => '2028-02-29'],
            'formatting' => ['font_size' => 18]];
        $data = $this->generate($configuration);
        self::assertNull($data['exam']['header']['school_name']);
        self::assertSame('PROFESSOR_EVENTUAL', $data['exam']['header']['teacher_name']);
        self::assertSame('ALUNO_PRIVADO', $data['exam']['header']['student_name']);
        self::assertSame('TURMA_PRIVADA', $data['exam']['header']['class_name']);
        self::assertSame('2028-02-29', $data['exam']['header']['date']);
        self::assertSame(['font_family' => 'Calibri', 'font_size' => 18], $data['exam']['formatting']);
        self::assertSame('ESCOLA_PRIVADA', $this->database->get('teacher_settings', 'school_name', ['teacher_id' => $this->teacherId]));
        self::assertSame(14, $this->database->get('teacher_settings', 'default_font_size', ['teacher_id' => $this->teacherId]));
        self::assertSame('PROFESSORA_PRIVADA', $this->database->get('teachers', 'name', ['id' => $this->teacherId]));
        foreach (['PROFESSOR_EVENTUAL', 'ALUNO_PRIVADO', 'TURMA_PRIVADA', '2028-02-29'] as $private) {
            self::assertStringNotContainsString($private, $this->provider->lastRequest()->inputJson);
        }
        $history = $this->request('GET', '/generations/' . $data['generation']['id']);
        $this->assertOpenApiSchema('GenerationDetail', $history);
        self::assertEquals($configuration['exam_header'], $history['request']['exam_header']);
        self::assertSame(['font_size' => 18], $history['request']['formatting']);
    }

    public function testRequestsWithoutTeacherAndLegacyTeacherWithoutSettingsUseDefaults(): void
    {
        $anonymous = $this->generate();
        self::assertNull($anonymous['exam']['header']['teacher_name']);
        self::assertNull($anonymous['exam']['header']['school_name']);
        self::assertSame(['font_family' => 'Arial', 'font_size' => 12], $anonymous['exam']['formatting']);

        $this->database->delete('teacher_settings', ['teacher_id' => $this->teacherId]);
        $legacy = $this->generate(['teacher_id' => $this->teacherId]);
        self::assertSame('PROFESSORA_PRIVADA', $legacy['exam']['header']['teacher_name']);
        self::assertNull($legacy['exam']['header']['school_name']);
        self::assertSame(['font_family' => 'Arial', 'font_size' => 12], $legacy['exam']['formatting']);
    }

    public function testLegacyFontSizeRemainsReadableAndInheritedWhileNewApiInputsRespectTheCurrentLimit(): void
    {
        // A migration original permitia qualquer SMALLINT unsigned positivo.
        $this->database->update('teacher_settings', ['default_font_size' => 40], ['teacher_id' => $this->teacherId]);
        $teacher = $this->request('GET', '/teachers/' . $this->teacherId);
        $this->assertOpenApiSchema('Teacher', $this->lastResponse);
        self::assertSame(40, $teacher['settings']['default_font_size']);
        $settings = $this->request('GET', '/teachers/' . $this->teacherId . '/settings');
        $this->assertOpenApiSchema('TeacherSettings', $this->lastResponse);
        self::assertSame(40, $settings['default_font_size']);

        $generated = $this->generate(['teacher_id' => $this->teacherId]);
        self::assertSame(40, $generated['exam']['formatting']['font_size']);
        $error = $this->request('PATCH', '/teachers/' . $this->teacherId . '/settings', ['default_font_size' => 40], 422);
        self::assertSame('invalid_input', $error['error']['code']);
        self::assertSame(40, $this->database->get('teacher_settings', 'default_font_size', ['teacher_id' => $this->teacherId]));
        self::assertSame(1, $this->provider->callCount());
    }

    public function testUnknownTeacherStopsBeforeCallingProviderOrRecordingAGeneration(): void
    {
        $before = $this->database->count('generation_history');
        $error = $this->request('POST', '/exams/generate', $this->input(['teacher_id' => 4294967295]), 422);
        self::assertSame('invalid_input', $error['error']['code']);
        self::assertSame(0, $this->provider->callCount());
        self::assertSame($before, $this->database->count('generation_history'));
    }

    public function testHttpDistinguishesEmptyObjectsFromListsBeforeCallingTheProvider(): void
    {
        $data = $this->generate(['exam_header' => (object) [], 'formatting' => (object) []]);
        self::assertInstanceOf(\stdClass::class, $this->lastResponse->generation->request->exam_header);
        self::assertInstanceOf(\stdClass::class, $this->lastResponse->generation->request->formatting);
        $this->request('GET', '/generations/' . $data['generation']['id']);
        $this->assertOpenApiSchema('GenerationDetail', $this->lastResponse);
        foreach (['exam_header', 'formatting'] as $field) {
            self::assertInstanceOf(\stdClass::class, $this->lastResponse->request->{$field});
            self::assertSame('{}', json_encode($this->lastResponse->request->{$field}));
        }
        foreach (['exam_header', 'formatting'] as $field) {
            $error = $this->request('POST', '/exams/generate', $this->input([$field => []]), 422);
            self::assertSame('invalid_input', $error['error']['code']);
        }
        self::assertSame(1, $this->provider->callCount());
    }

    public function testSavedExamsFromBeforePresentationRemainReadableWithoutInventingHeaders(): void
    {
        $data = $this->generate();
        $original = $data['exam'];
        unset($original['id'], $original['header'], $original['formatting']);
        $this->database->update('exams', ['exam_json' => json_encode($original, JSON_THROW_ON_ERROR)], ['id' => $data['exam']['id']]);
        $saved = $this->request('GET', '/exams/' . $data['exam']['id']);
        self::assertArrayNotHasKey('header', $saved['exam']);
        self::assertArrayNotHasKey('formatting', $saved['exam']);
        $this->assertOpenApiSchema('ExamLookup', $saved);
    }

    private function input(array $overrides = []): array
    {
        return ExamFixture::input(['bncc_document_id' => $this->documentId, ...$overrides]);
    }

    private function generate(array $overrides = []): array
    {
        $result = $this->request('POST', '/exams/generate', $this->input($overrides));
        $this->assertOpenApiSchema('GenerationResult', $this->lastResponse);
        return $result;
    }

    private function request(string $method, string $url, ?array $input = null, int $status = 200): array
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $url);
        if ($input !== null) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request->getBody()->write(json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $request->getBody()->rewind();
        }
        $response = $this->app->handle($request);
        self::assertSame($status, $response->getStatusCode(), (string) $response->getBody());
        $this->lastResponse = json_decode((string) $response->getBody(), flags: JSON_THROW_ON_ERROR);
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
