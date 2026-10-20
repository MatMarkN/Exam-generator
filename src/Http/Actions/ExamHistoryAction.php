<?php

declare(strict_types=1);

namespace App\Http\Actions;

use App\Application\Auth\AuthenticatedUser;
use App\Http\JsonResponder;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use App\Infrastructure\Persistence\GenerationHistoryRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ExamHistoryAction
{
    public function __construct(private readonly DatabaseConnectionFactory $connections)
    {
    }

    public function listing(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponder::run($response, fn (): array => $this->repository($request)->listing($request->getQueryParams()));
    }

    public function generation(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return JsonResponder::run($response, fn (): array => $this->repository($request)->find(GenerationHistoryRepository::positiveId($args['id'])));
    }

    public function exam(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return JsonResponder::run($response, fn (): array => $this->repository($request)->exam(GenerationHistoryRepository::positiveId($args['id'])));
    }

    public function answers(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return JsonResponder::run($response, fn (): array => $this->repository($request)->answerKey(GenerationHistoryRepository::positiveId($args['id'])));
    }

    private function repository(ServerRequestInterface $request): GenerationHistoryRepository
    {
        return new GenerationHistoryRepository($this->connections->create(), AuthenticatedUser::fromRequest($request)->id);
    }
}
