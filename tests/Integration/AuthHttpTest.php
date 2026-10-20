<?php
declare(strict_types=1);
namespace Tests\Integration;

use App\Infrastructure\Persistence\AuthRepository;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Medoo\Medoo;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AuthFixture;
use Tests\Support\OpenApiAssertions;

final class AuthHttpTest extends TestCase
{
    use OpenApiAssertions;
    private App $app;
    private Medoo $database;
    private AuthFixture $fixtures;
    private array $sessions = [];
    private array $registeredIds = [];
    private string $ip;

    protected function setUp(): void
    {
        $this->app = require __DIR__ . '/../../config/bootstrap.php';
        $this->database = (new DatabaseConnectionFactory(require __DIR__ . '/../../config/database.php'))->create();
        $this->fixtures = new AuthFixture($this->database);
        $this->ip = '198.51.100.' . random_int(1, 254) . '-' . bin2hex(random_bytes(3));
    }

    protected function tearDown(): void
    {
        foreach ($this->sessions as $session) {
            $this->fixtures->remove($session);
        }
        foreach ($this->registeredIds as $id) {
            $this->database->delete('auth_sessions', ['user_id' => $id]);
            $this->database->delete('users', ['id' => $id]);
        }
    }

    public function testSignupCreatesOnlyTeacherWithHashedPasswordAndHttpOnlySessionCookie(): void
    {
        $input = ['name' => 'Conta SINTÉTICA', 'email' => 'signup-' . bin2hex(random_bytes(6)) . '@example.test',
            'password' => 'SenhaSintetica123!', 'password_confirmation' => 'SenhaSintetica123!'];
        [$response, $body] = $this->request('POST', '/auth/register', $input);
        self::assertSame(201, $response->getStatusCode());
        $this->assertOpenApiSchema('AuthSession', $body);
        $this->registeredIds[] = $body['user']['id'];
        self::assertSame('teacher', $body['user']['role']);
        $cookie = $response->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('HttpOnly', $cookie);
        self::assertStringContainsString('SameSite=Strict', $cookie);
        self::assertStringContainsString('Max-Age=43200', $cookie);
        self::assertStringContainsString('Path=/', $cookie);
        $token = $this->token($response);
        self::assertStringNotContainsString($token, json_encode($body));
        $stored = $this->database->get('users', '*', ['id' => $body['user']['id']]);
        self::assertNotSame($input['password'], $stored['password_hash']);
        self::assertTrue(password_verify($input['password'], $stored['password_hash']));
        self::assertTrue($this->database->has('auth_sessions', ['token_hash' => hash('sha256', $token)]));
        self::assertFalse($this->database->has('auth_sessions', ['token_hash' => $token]));
        [, $current] = $this->request('GET', '/auth/session', token: $token);
        self::assertSame($body, $current);
        [$duplicate] = $this->request('POST', '/auth/register', $input);
        self::assertSame(409, $duplicate->getStatusCode());
    }

    public function testAnonymousRequestsCannotReadPrivateDataUploadPdfOrCallTheAi(): void
    {
        foreach ([['GET', '/teachers'], ['GET', '/generations'], ['GET', '/exams/1/answer-key'],
            ['POST', '/exams/generate'], ['POST', '/bncc/documents'], ['POST', '/bncc/documents/1/reprocess']] as [$method, $path]) {
            [$response, $body] = $this->request($method, $path);
            self::assertSame(401, $response->getStatusCode(), $path);
            self::assertSame('auth_required', $body['error']['code']);
        }
        [$health] = $this->request('GET', '/health');
        self::assertSame(200, $health->getStatusCode());
    }

    public function testRoleCannotBeChosenAtRegistrationAndTeacherCannotMutateOfficialCurriculum(): void
    {
        [$response] = $this->request('POST', '/auth/register', ['name' => 'Tentativa SINTÉTICA', 'email' => 'role@example.test',
            'password' => 'SenhaSintetica123!', 'password_confirmation' => 'SenhaSintetica123!', 'role' => 'admin']);
        self::assertSame(422, $response->getStatusCode());
        $session = $this->session('teacher');
        foreach (['/bncc/documents', '/bncc/documents/1/reprocess', '/bncc/documents/1/structure', '/bncc/documents/1/releases',
            '/%62ncc/documents', '/bncc%2Fdocuments', '/%62ncc%2Fdocuments/1/reprocess', '/bncc/documents%2F1/structure'] as $path) {
            [$response, $body] = $this->request('POST', $path, [], $session['token'], $session['csrf_token']);
            self::assertSame(403, $response->getStatusCode(), $path);
            self::assertSame('admin_required', $body['error']['code']);
        }
        [$catalog] = $this->request('GET', '/subjects', token: $session['token']);
        self::assertSame(200, $catalog->getStatusCode());
    }

    public function testWriteRequiresCsrfAndForeignOriginsAndHostsAreRejectedBeforeAction(): void
    {
        $session = $this->session();
        foreach ([null, str_repeat('f', 64)] as $csrf) {
            [$response, $body] = $this->request('POST', '/bncc/documents/4294967295/reprocess', token: $session['token'], csrf: $csrf);
            self::assertSame(403, $response->getStatusCode());
            self::assertSame('csrf_invalid', $body['error']['code']);
        }
        foreach ([['Origin' => 'https://example.invalid'], ['Sec-Fetch-Site' => 'cross-site'], ['Host' => 'evil.invalid'], ['Host' => 'localhost#evil.invalid']] as $headers) {
            [$response] = $this->request('POST', '/bncc/documents/4294967295/reprocess', token: $session['token'], csrf: $session['csrf_token'], headers: $headers);
            self::assertSame(403, $response->getStatusCode());
        }
        [$response, $body] = $this->request('POST', '/bncc/documents/4294967295/reprocess', token: $session['token'], csrf: $session['csrf_token']);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('not_found', $body['error']['code']);
    }

    public function testLoginRotatesSessionLogoutRevokesAndExpiredSessionIsRejected(): void
    {
        $initial = $this->session('teacher');
        [$login, $body] = $this->request('POST', '/auth/login', ['email' => $initial['user']->email, 'password' => 'FixturePassword123!'], $initial['token']);
        self::assertSame(200, $login->getStatusCode());
        $token = $this->token($login);
        self::assertNotSame($initial['token'], $token);
        [$old] = $this->request('GET', '/auth/session', token: $initial['token']);
        self::assertSame(401, $old->getStatusCode());
        [$logout, $loggedOut] = $this->request('POST', '/auth/logout', token: $token, csrf: $body['csrf_token']);
        self::assertSame(200, $logout->getStatusCode());
        self::assertSame(['success' => true], $loggedOut);
        self::assertStringContainsString('Max-Age=0', $logout->getHeaderLine('Set-Cookie'));
        [$revoked] = $this->request('GET', '/auth/session', token: $token);
        self::assertSame(401, $revoked->getStatusCode());
        $expired = (new AuthRepository($this->database))->createSession($initial['user'], -10);
        [$response] = $this->request('GET', '/auth/session', token: $expired['token']);
        self::assertSame(401, $response->getStatusCode());
    }

    public function testInvalidLoginIsGenericAndLoginAttemptsAreLimitedOnTheServer(): void
    {
        $session = $this->session('teacher');
        $unknown = 'unknown-' . bin2hex(random_bytes(6)) . '@example.test';
        [, $invalidExisting] = $this->request('POST', '/auth/login', ['email' => $session['user']->email, 'password' => 'SenhaIncorreta123!']);
        [, $invalidUnknown] = $this->request('POST', '/auth/login', ['email' => $unknown, 'password' => 'SenhaIncorreta123!']);
        self::assertSame($invalidExisting, $invalidUnknown);
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            [$response, $body] = $this->request('POST', '/auth/login', ['email' => $unknown, 'password' => 'SenhaIncorreta123!']);
        }
        self::assertSame(429, $response->getStatusCode());
        self::assertSame('auth_rate_limited', $body['error']['code']);
        self::assertGreaterThan(0, (int) $response->getHeaderLine('Retry-After'));
    }

    private function session(string $role = 'admin'): array
    {
        $this->sessions[] = $session = $this->fixtures->create($role);
        return $session;
    }

    private function token(ResponseInterface $response): string
    {
        preg_match('/prova_clara_session=([a-f0-9]{64})/', $response->getHeaderLine('Set-Cookie'), $matches);
        self::assertArrayHasKey(1, $matches);
        return $matches[1];
    }

    private function request(string $method, string $path, ?array $body = null, ?string $token = null, ?string $csrf = null, array $headers = []): array
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, 'http://localhost' . $path, ['REMOTE_ADDR' => $this->ip]);
        if ($token !== null) {
            $request = $request->withCookieParams(['prova_clara_session' => $token]);
        }
        if ($csrf !== null) {
            $request = $request->withHeader('X-CSRF-Token', $csrf);
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
        return [$response, json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)];
    }
}
