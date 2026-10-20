<?php
declare(strict_types=1);
namespace App\Http\Actions;

use App\Application\Auth\AuthenticatedUser;
use App\Application\Auth\AuthException;
use App\Application\Auth\AuthInput;
use App\Http\JsonResponder;
use App\Infrastructure\Persistence\AuthRepository;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthAction
{
    public function __construct(private readonly DatabaseConnectionFactory $connections, private readonly array $settings)
    {
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->authenticate($request, $response, true);
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->authenticate($request, $response, false);
    }

    public function session(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponder::run($response, static fn (): array => [
            'user' => AuthenticatedUser::fromRequest($request)->toArray(), 'csrf_token' => $request->getAttribute('auth_session')['csrf_token'],
        ]);
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response = JsonResponder::run($response, function () use ($request): array {
            AuthenticatedUser::fromRequest($request);
            $this->repository()->revoke((string) ($request->getCookieParams()[$this->settings['cookie_name']] ?? ''));
            return ['success' => true];
        });
        return $response->withAddedHeader('Set-Cookie', $this->cookie('', 0));
    }

    private function authenticate(ServerRequestInterface $request, ResponseInterface $response, bool $register): ResponseInterface
    {
        $session = null;
        $response = JsonResponder::run($response, function () use ($request, $register, &$session): array {
            $body = $this->body($request);
            $input = $register ? AuthInput::register($body) : AuthInput::login($body);
            $repository = $this->repository();
            // Não confiar em IPs enviados em X-Forwarded-For. Atrás de proxy, o limite de IP é compartilhado.
            $remote = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
            if ($register) {
                $repository->limit('register-ip:' . $remote, 5, 3600);
                $user = $repository->register($input);
            } else {
                $repository->limit('login-ip:' . $remote, 60, 900);
                $repository->limit('login-email:' . $input['email'], 10, 900);
                $user = $repository->login($input['email'], $input['password']);
                $repository->clearLimit('login-email:' . $input['email'], 900);
            }
            $old = $request->getCookieParams()[$this->settings['cookie_name']] ?? '';
            if (is_string($old) && $old !== '') {
                $repository->revoke($old);
            }
            $session = $repository->createSession($user, $this->settings['session_seconds']);
            return ['user' => $user->toArray(), 'csrf_token' => $session['csrf_token']];
        }, $register ? 201 : 200);
        return $session === null ? $response : $response->withAddedHeader('Set-Cookie', $this->cookie($session['token'], $this->settings['session_seconds']));
    }

    private function body(ServerRequestInterface $request): array
    {
        if (strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0])) !== 'application/json') {
            throw new AuthException('unsupported_media_type', 'Envie o cadastro ou login como application/json.', 415);
        }
        $raw = $request->getBody()->read(8193);
        if (strlen($raw) > 8192) {
            throw new AuthException('payload_too_large', 'O cadastro ou login deve ter no máximo 8 KiB.', 413);
        }
        try {
            $input = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new AuthException('invalid_input', 'JSON de cadastro ou login inválido.', 422);
        }
        if (!is_array($input) || array_is_list($input)) {
            throw new AuthException('invalid_input', 'Envie um objeto JSON de cadastro ou login.', 422);
        }
        return $input;
    }

    private function cookie(string $token, int $seconds): string
    {
        return $this->settings['cookie_name'] . '=' . $token . '; Path=/; Max-Age=' . $seconds
            . '; Expires=' . gmdate('D, d M Y H:i:s \G\M\T', time() + ($seconds > 0 ? $seconds : -3600))
            . '; HttpOnly; SameSite=Strict' . ($this->settings['cookie_secure'] ? '; Secure' : '');
    }

    private function repository(): AuthRepository
    {
        return new AuthRepository($this->connections->create());
    }
}
