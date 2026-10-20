<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Medoo\Medoo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\OpenApiAssertions;
use Tests\Support\AuthFixture;

final class TeacherHttpTest extends TestCase
{
    use OpenApiAssertions;

    private Medoo $database;
    private App $app;
    private array $ids = [];
    private array $session;

    protected function setUp(): void
    {
        $connections = new DatabaseConnectionFactory(require __DIR__ . '/../../config/database.php');
        $this->database = $connections->create();
        $this->session = (new AuthFixture($this->database))->create();
        $this->app = AppFactory::create();
        (require __DIR__ . '/../../config/routes.php')($this->app, $connections);
        $this->app->addRoutingMiddleware();
    }

    protected function tearDown(): void
    {
        if ($this->ids !== []) {
            $this->database->delete('teachers', ['id' => $this->ids]);
        }
        (new AuthFixture($this->database))->remove($this->session);
    }

    public function testCreateReadEditPreferencesAndClearNullableFields(): void
    {
        $response = $this->request('POST', '/teachers', ['name' => ' Professora Érica 🧮 ', 'email' => ' erica@example.test ']);
        self::assertSame(201, $response->getStatusCode());
        $created = $this->data($response);
        $this->assertOpenApiSchema('Teacher', $created);
        $id = $created['id'];
        self::assertSame('/teachers/' . $id, $response->getHeaderLine('Location'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('Professora Érica 🧮', $created['name']);
        self::assertSame(['school_name' => null, 'default_font_family' => 'Arial', 'default_font_size' => 12], $created['settings']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $created['created_at']);
        self::assertSame($created, $this->data($this->request('GET', '/teachers/' . $id)));

        $settings = $this->data($this->request('PATCH', '/teachers/' . $id . '/settings',
            ['school_name' => 'Escola Sintética', 'default_font_family' => 'Verdana', 'default_font_size' => 16]));
        self::assertSame(['school_name' => 'Escola Sintética', 'default_font_family' => 'Verdana', 'default_font_size' => 16], $settings);
        $this->assertOpenApiSchema('TeacherSettings', $settings);
        self::assertSame($settings, $this->data($this->request('GET', '/teachers/' . $id . '/settings')));
        $profile = $this->data($this->request('PATCH', '/teachers/' . $id, ['name' => 'Érica Atualizada', 'email' => null]));
        self::assertSame('Érica Atualizada', $profile['name']);
        self::assertNull($profile['email']);
        self::assertSame($settings, $profile['settings']);
        $settings = $this->data($this->request('PATCH', '/teachers/' . $id . '/settings', ['school_name' => null]));
        self::assertNull($settings['school_name']);
        self::assertSame(16, $settings['default_font_size']);
        self::assertSame('Verdana', $settings['default_font_family']);
        $again = $this->request('PATCH', '/teachers/' . $id, ['name' => 'Érica Atualizada']);
        self::assertSame(200, $again->getStatusCode(), 'PATCH idempotente continua tendo sucesso.');
    }

    public function testDuplicateEmailRollsBackCreationAndProfileUpdate(): void
    {
        $first = $this->data($this->request('POST', '/teachers', ['name' => 'Primeira', 'email' => 'shared@example.test']));
        $second = $this->data($this->request('POST', '/teachers', ['name' => 'Segunda']));
        $teacherCount = $this->database->count('teachers');
        $settingsCount = $this->database->count('teacher_settings');
        $response = $this->request('POST', '/teachers', ['name' => 'Duplicada', 'email' => 'SHARED@example.test']);
        self::assertSame(409, $response->getStatusCode());
        self::assertSame('duplicate_email', $this->data($response)['error']['code']);
        self::assertSame($teacherCount, $this->database->count('teachers'));
        self::assertSame($settingsCount, $this->database->count('teacher_settings'));
        $response = $this->request('PATCH', '/teachers/' . $second['id'], ['name' => 'Não salvar', 'email' => $first['email']]);
        self::assertSame(409, $response->getStatusCode());
        self::assertSame('Segunda', $this->data($this->request('GET', '/teachers/' . $second['id']))['name']);
        self::assertStringNotContainsString('SQL', (string) $response->getBody());
    }

    public function testLegacyTeacherGetsDefaultsWithoutWritingAndPatchCreatesSettings(): void
    {
        $this->database->insert('teachers', ['name' => 'Legado', 'user_id' => $this->session['user']->id]);
        $id = (int) $this->database->id();
        $this->ids[] = $id;
        self::assertSame(['school_name' => null, 'default_font_family' => 'Arial', 'default_font_size' => 12],
            $this->data($this->request('GET', '/teachers/' . $id . '/settings')));
        self::assertFalse($this->database->has('teacher_settings', ['teacher_id' => $id]));
        $settings = $this->data($this->request('PATCH', '/teachers/' . $id . '/settings', ['default_font_size' => 14]));
        self::assertSame('Arial', $settings['default_font_family']);
        self::assertSame(14, $settings['default_font_size']);
        self::assertTrue($this->database->has('teacher_settings', ['teacher_id' => $id]));
    }

    public function testTeacherListUsesStableCursorPagination(): void
    {
        $first = $this->data($this->request('POST', '/teachers', ['name' => 'Primeira']));
        $second = $this->data($this->request('POST', '/teachers', ['name' => 'Segunda']));
        $third = $this->data($this->request('POST', '/teachers', ['name' => 'Terceira']));
        $page = $this->data($this->request('GET', '/teachers', query: ['limit' => '2']));
        $this->assertOpenApiSchema('TeacherList', $page);
        self::assertSame([$third['id'], $second['id']], array_column($page['items'], 'id'));
        self::assertSame($second['id'], $page['next_before_id']);
        $page = $this->data($this->request('GET', '/teachers', query: ['limit' => '2', 'before_id' => (string) $page['next_before_id']]));
        self::assertSame([$first['id']], array_column($page['items'], 'id'));
        self::assertNull($page['next_before_id']);
        self::assertSame(422, $this->request('GET', '/teachers', query: ['limit' => '101'])->getStatusCode());
        self::assertSame(422, $this->request('GET', '/teachers', query: ['email' => 'not-a-filter'])->getStatusCode());
    }

    public function testCatalogsExposeCodesAndYearsUsedByGeneration(): void
    {
        $subjectList = $this->data($this->request('GET', '/subjects'));
        $this->assertOpenApiSchema('SubjectList', $subjectList);
        $subjects = $subjectList['items'];
        self::assertCount(9, $subjects);
        self::assertSame('Matemática', array_column($subjects, 'name', 'code')['matematica']);
        $gradeList = $this->data($this->request('GET', '/school-grades'));
        $this->assertOpenApiSchema('SchoolGradeList', $gradeList);
        $grades = $gradeList['items'];
        self::assertSame(range(1, 9), array_column($grades, 'year_number'));
        self::assertSame('ensino_fundamental', $grades[0]['education_stage']);
        self::assertSame(422, $this->request('GET', '/subjects', query: ['unknown' => 'x'])->getStatusCode());
    }

    #[DataProvider('missingOperations')]
    public function testUnknownTeacherReturns404(string $method, string $suffix, ?array $body): void
    {
        $response = $this->request($method, '/teachers/4294967295' . $suffix, $body);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('not_found', $this->data($response)['error']['code']);
    }

    public static function missingOperations(): array
    {
        return [['GET', '', null], ['PATCH', '', ['name' => 'A']], ['GET', '/settings', null],
            ['PATCH', '/settings', ['default_font_size' => 14]]];
    }

    #[DataProvider('badBodies')]
    public function testTransportAndInputErrorsAreControlled(string $raw, string $contentType, int $status): void
    {
        $before = $this->database->count('teachers');
        $response = $this->request('POST', '/teachers', contentType: $contentType, raw: $raw);
        self::assertSame($status, $response->getStatusCode());
        self::assertSame($before, $this->database->count('teachers'));
        self::assertSame('', $response->getHeaderLine('Location'));
    }

    public static function badBodies(): array
    {
        return [['{}', 'application/json', 422], ['[]', 'application/json', 422], ['null', 'application/json', 422],
            ['{bad', 'application/json', 422], ['{"name":"A"}', 'text/plain', 415],
            [str_repeat('x', 65537), 'application/json', 413], ['{"name":"A","unknown":1}', 'application/json', 422]];
    }

    private function request(string $method, string $path, ?array $body = null, array $query = [], string $contentType = 'application/json', ?string $raw = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path)->withQueryParams($query);
        if ($body !== null || $raw !== null) {
            $request = $request->withHeader('Content-Type', $contentType);
            $request->getBody()->write($raw ?? json_encode($body, JSON_THROW_ON_ERROR));
            $request->getBody()->rewind();
        }
        $response = $this->app->handle((new AuthFixture($this->database))->authorize($request, $this->session));
        if ($method === 'POST' && $path === '/teachers' && $response->getStatusCode() === 201) {
            $this->ids[] = $this->data($response)['id'];
        }
        return $response;
    }

    private function data(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
