<?php

declare(strict_types=1);

namespace App\Http\Actions;

use App\Application\Teachers\TeacherException;
use App\Application\Teachers\TeacherInput;
use App\Http\JsonResponder;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use App\Infrastructure\Persistence\TeacherRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TeacherAction
{
    public function __construct(private readonly DatabaseConnectionFactory $connections)
    {
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponder::run($response, function () use ($request): array {
            $input = $this->body($request);
            TeacherInput::profile($input, true);
            return $this->repository()->create($input);
        }, 201, '/teachers/');
    }

    public function listing(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponder::run($response, fn (): array => $this->repository()->listing($request->getQueryParams()));
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return JsonResponder::run($response, fn (): array => $this->repository()->find(TeacherRepository::positiveId($args['id'])));
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return JsonResponder::run($response, function () use ($request, $args): array {
            $input = $this->body($request);
            TeacherInput::profile($input);
            return $this->repository()->update(TeacherRepository::positiveId($args['id']), $input);
        });
    }

    public function settings(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return JsonResponder::run($response, fn (): array => $this->repository()->settings(TeacherRepository::positiveId($args['id'])));
    }

    public function updateSettings(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return JsonResponder::run($response, function () use ($request, $args): array {
            $input = $this->body($request);
            TeacherInput::settings($input);
            return $this->repository()->updateSettings(TeacherRepository::positiveId($args['id']), $input);
        });
    }

    private function repository(): TeacherRepository
    {
        return new TeacherRepository($this->connections->create());
    }

    private function body(ServerRequestInterface $request): array
    {
        if (strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0])) !== 'application/json') {
            throw new TeacherException('unsupported_media_type', 'Envie o pedido como application/json.', 415);
        }
        $raw = $request->getBody()->read(65537);
        if (strlen($raw) > 65536) {
            throw new TeacherException('payload_too_large', 'O pedido deve ter no máximo 64 KiB.', 413);
        }
        try {
            $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new TeacherException('invalid_input', 'JSON de pedido inválido.');
        }
        if (!is_array($input) || array_is_list($input)) {
            throw new TeacherException('invalid_input', 'Envie um objeto JSON não vazio.');
        }
        return $input;
    }
}
