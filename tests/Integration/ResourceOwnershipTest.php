<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Bncc\BnccContextService;
use App\Application\Bncc\BnccTopicMatcher;
use App\Application\Exams\ExamGenerationService;
use App\Infrastructure\AI\FakeAIProvider;
use App\Infrastructure\Persistence\BnccContextRepository;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use App\Infrastructure\Persistence\GenerationHistoryRepository;
use App\Infrastructure\Persistence\TeacherRepository;
use Medoo\Medoo;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AuthFixture;
use Tests\Support\BnccFixtures;
use Tests\Support\CurriculumFixture;
use Tests\Support\ExamFixture;
use Tests\Support\GenerationHistoryFixture;

final class ResourceOwnershipTest extends TestCase
{
    private Medoo $database;
    private App $app;
    private AuthFixture $auth;
    private array $sessions;
    private array $teacherIds = [];
    private int $documentId;
    private FakeAIProvider $provider;

    protected function setUp(): void
    {
        $connections = new DatabaseConnectionFactory(require __DIR__ . '/../../config/database.php');
        $this->database = $connections->create();
        $this->auth = new AuthFixture($this->database);
        $this->sessions = [$this->auth->create('teacher'), $this->auth->create('teacher'), $this->auth->create('admin')];
        $fixtures = new BnccFixtures($this->database);
        $this->documentId = $fixtures->document(['status' => 'ready', 'ready_at' => '2026-01-01 00:00:00',
            'structured_at' => '2026-01-01 00:00:00', 'structure_profile' => 'synthetic-ownership-test']);
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
        $this->provider = new FakeAIProvider(json_encode(ExamFixture::response(), JSON_THROW_ON_ERROR));
        $this->app = AppFactory::create();
        (require __DIR__ . '/../../config/routes.php')($this->app, $connections, $this->provider);
        $this->app->addRoutingMiddleware();
    }

    protected function tearDown(): void
    {
        (new GenerationHistoryFixture($this->database))->removeForDocument($this->documentId);
        (new CurriculumFixture($this->database))->remove($this->documentId);
        if ($this->teacherIds !== []) {
            $this->database->delete('teachers', ['id' => $this->teacherIds]);
        }
        foreach ($this->sessions as $session) {
            $this->auth->remove($session);
        }
    }

    public function testProfilesAreIsolatedAndOwnershipCannotBeAssignedThroughTheJson(): void
    {
        $first = $this->teacher(0);
        $second = $this->teacher(1);
        [, $list] = $this->call(0, 'GET', '/teachers');
        self::assertSame([$first['id']], array_column($list['items'], 'id'));
        self::assertSame(200, $this->call(0, 'GET', '/teachers/' . $first['id'])[0]);
        foreach (['GET', 'PATCH'] as $method) {
            self::assertSame(404, $this->call(1, $method, '/teachers/' . $first['id'], $method === 'PATCH' ? ['name' => 'Intrusão'] : null)[0]);
        }
        self::assertSame(404, $this->call(1, 'PATCH', '/teachers/' . $first['id'] . '/settings', ['school_name' => 'Outra escola'])[0]);
        self::assertSame(422, $this->call(0, 'POST', '/teachers', ['name' => 'Teste', 'user_id' => $this->sessions[1]['user']->id])[0]);
        self::assertSame($first['name'], (new TeacherRepository($this->database))->find($first['id'])['name']);
        self::assertNotSame($first['id'], $second['id']);
    }

    public function testExamAnswerKeyAndDetailedHistoryArePrivateEvenAgainstAnAdministrator(): void
    {
        $teacher = $this->teacher(0);
        [$status, $result] = $this->call(0, 'POST', '/exams/generate', $this->input(['teacher_id' => $teacher['id']]));
        self::assertSame(200, $status, json_encode($result));
        $paths = ['/exams/' . $result['exam']['id'], '/exams/' . $result['exam']['id'] . '/answer-key',
            '/generations/' . $result['generation']['id']];
        foreach ($paths as $path) {
            self::assertSame(200, $this->call(0, 'GET', $path)[0]);
            self::assertSame(404, $this->call(1, 'GET', $path)[0]);
            self::assertSame(404, $this->call(2, 'GET', $path)[0]);
        }
        [, $own] = $this->call(0, 'GET', '/generations');
        [, $other] = $this->call(1, 'GET', '/generations');
        self::assertSame([$result['generation']['id']], array_column($own['items'], 'id'));
        self::assertTrue($own['items'][0]['requester_verified']);
        self::assertSame([], $other['items']);
        self::assertSame($this->sessions[0]['user']->id,
            $this->database->get('generation_history', 'user_id', ['id' => $result['generation']['id']]));
    }

    public function testForeignTeacherAndForgedOwnerStopBeforeCallingTheProvider(): void
    {
        $teacher = $this->teacher(1);
        self::assertSame(422, $this->call(0, 'POST', '/exams/generate', $this->input(['teacher_id' => $teacher['id']]))[0]);
        self::assertSame(422, $this->call(0, 'POST', '/exams/generate', $this->input(['user_id' => $this->sessions[1]['user']->id]))[0]);
        self::assertSame(0, $this->provider->callCount());
    }

    public function testUnownedLegacyDataIsNotGivenToTheFirstRegisteredAccountOrAnAdministrator(): void
    {
        $legacy = (new TeacherRepository($this->database))->create(['name' => 'Perfil legado SINTÉTICO']);
        $this->teacherIds[] = $legacy['id'];
        $history = new GenerationHistoryRepository($this->database);
        $result = (new ExamGenerationService(new BnccContextService(new BnccContextRepository($this->database), new BnccTopicMatcher()),
            $this->provider, history: $history))->generate($this->input());
        self::assertFalse($history->find($result->generation()['id'])['requester_verified']);
        foreach ([0, 1, 2] as $user) {
            self::assertSame(404, $this->call($user, 'GET', '/teachers/' . $legacy['id'])[0]);
            self::assertSame(404, $this->call($user, 'GET', '/exams/' . $result->exam()['id'])[0]);
        }
    }

    public function testPdfAndAiRequireLoginAndCurriculumWritesAlsoRequireAdminAndCsrf(): void
    {
        self::assertSame(401, $this->call(null, 'POST', '/exams/generate', $this->input())[0]);
        self::assertSame(401, $this->call(null, 'POST', '/bncc/documents')[0]);
        self::assertSame(403, $this->call(0, 'POST', '/bncc/documents/4294967295/reprocess')[0]);
        self::assertSame(403, $this->call(0, 'POST', '/bncc/documents/1/releases', ['subject' => 'matematica', 'grade' => 6])[0]);
        self::assertSame(403, $this->call(2, 'POST', '/bncc/documents/4294967295/reprocess', null, ['Origin' => 'https://example.invalid'])[0]);
        self::assertSame(403, $this->call(2, 'POST', '/bncc/documents/4294967295/reprocess', null, ['X-CSRF-Token' => 'wrong'])[0]);
        self::assertSame(403, $this->call(2, 'GET', '/teachers', null, ['Host' => 'audit.invalid'])[0]);
        self::assertSame(404, $this->call(2, 'POST', '/bncc/documents/4294967295/reprocess')[0]);
        self::assertSame(0, $this->provider->callCount());
    }

    private function teacher(int $user): array
    {
        [$status, $teacher] = $this->call($user, 'POST', '/teachers', ['name' => 'Perfil SINTÉTICO ' . $user]);
        self::assertSame(201, $status);
        $this->teacherIds[] = $teacher['id'];
        return $teacher;
    }

    private function input(array $changes = []): array
    {
        return ExamFixture::input(['bncc_document_id' => $this->documentId, ...$changes]);
    }

    private function call(?int $user, string $method, string $path, ?array $body = null, array $headers = []): array
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path)->withHeader('Host', 'localhost');
        if ($user !== null) {
            $request = $this->auth->authorize($request, $this->sessions[$user]);
        }
        if ($body !== null) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request->getBody()->write(json_encode($body, JSON_THROW_ON_ERROR));
            $request->getBody()->rewind();
        }
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        $response = $this->app->handle($request);
        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)];
    }
}
