<?php

declare(strict_types=1);

namespace App\Http\Actions;

use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DatabaseHealthAction
{
    public function __construct(private readonly DatabaseConnectionFactory $connections)
    {
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        try {
            $database = $this->connections->create();
            $available = (int) $database->query('SELECT 1')->fetchColumn() === 1;
        } catch (PDOException $exception) {
            // Não registrar DSN, credenciais, argumentos ou mensagem bruta da conexão.
            error_log('database_health_failed exception=' . $exception::class);
            $available = false;
        }

        $response->getBody()->write(json_encode([
            'status' => $available ? 'ok' : 'unavailable',
        ], JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withStatus($available ? 200 : 503);
    }
}
