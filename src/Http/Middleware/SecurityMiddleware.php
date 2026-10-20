<?php
declare(strict_types=1);
namespace App\Http\Middleware;

use App\Application\Auth\AuthException;
use App\Http\JsonResponder;
use App\Infrastructure\Persistence\AuthRepository;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SecurityMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly DatabaseConnectionFactory $connections, private readonly array $settings,
        private readonly ResponseFactoryInterface $responses)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $request = $this->secure($request);
        } catch (\Throwable $exception) {
            return JsonResponder::run($this->responses->createResponse(), static fn (): array => throw $exception);
        }
        // Exceções de roteamento/Actions pertencem ao middleware de erros da aplicação.
        return $handler->handle($request);
    }

    private function secure(ServerRequestInterface $request): ServerRequestInterface
    {
            $this->validateHost($request);
            // Match Slim RouteResolver: authorization must inspect the path the router dispatches.
            $path = rawurldecode($request->getUri()->getPath());
            if ($path === '' || $path[0] !== '/') {
                $path = '/' . $path;
            }
            $method = strtoupper($request->getMethod());
            $read = in_array($method, ['GET', 'HEAD'], true);
            if (!$read) {
                $this->validateOrigin($request);
            }
            $public = $read && in_array($path, ['/health', '/health/database', '/docs', '/docs/', '/openapi.json'], true);
            $public = $public || ($method === 'POST' && in_array($path, ['/auth/login', '/auth/register'], true));
            if ($public) {
                return $request;
            }
            $token = $request->getCookieParams()[$this->settings['cookie_name']] ?? '';
            if (!is_string($token)) {
                $token = '';
            }
            $session = (new AuthRepository($this->connections->create()))->session($token);
            if (!$read && !hash_equals($session['csrf_token'], $request->getHeaderLine('X-CSRF-Token'))) {
                throw new AuthException('csrf_invalid', 'Atualize a sessão antes de enviar essa alteração.', 403);
            }
            if (!$read && str_starts_with($path, '/bncc/documents') && $session['user']->role !== 'admin') {
                throw new AuthException('admin_required', 'Somente administradores podem alterar o currículo da BNCC.', 403);
            }
            return $request->withAttribute('auth_user', $session['user'])->withAttribute('auth_session', $session);
    }

    private function validateHost(ServerRequestInterface $request): void
    {
        $hostHeader = trim($request->getHeaderLine('Host'));
        $authority = parse_url('http://' . $hostHeader);
        $host = is_array($authority) ? ($authority['host'] ?? null) : null;
        $host = is_string($host) ? strtolower(trim($host, '[]')) : '';
        if ($host === '' || !in_array($host, array_map(static fn (string $value): string => strtolower(trim($value, '[]')), $this->settings['allowed_hosts']), true)
            || !is_array($authority) || array_diff(array_keys($authority), ['scheme', 'host', 'port']) !== []
            || preg_match('/[\s\/@\\\\]/', $hostHeader)) {
            throw new AuthException('host_not_allowed', 'O endereço usado para acessar a API não é permitido.', 403);
        }
    }

    private function validateOrigin(ServerRequestInterface $request): void
    {
        $origin = $request->getHeaderLine('Origin');
        if (($origin !== '' && !in_array($origin, $this->settings['allowed_origins'], true))
            || strtolower($request->getHeaderLine('Sec-Fetch-Site')) === 'cross-site') {
            throw new AuthException('origin_not_allowed', 'A origem dessa alteração não é permitida.', 403);
        }
    }
}
