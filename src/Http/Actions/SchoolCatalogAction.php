<?php

declare(strict_types=1);

namespace App\Http\Actions;

use App\Application\Teachers\TeacherException;
use App\Http\JsonResponder;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use App\Infrastructure\Persistence\SchoolCatalogRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SchoolCatalogAction
{
    public function __construct(private readonly DatabaseConnectionFactory $connections)
    {
    }

    public function subjects(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponder::run($response, function () use ($request): array {
            $this->noFilters($request);
            return (new SchoolCatalogRepository($this->connections->create()))->subjects();
        });
    }

    public function grades(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponder::run($response, function () use ($request): array {
            $this->noFilters($request);
            return (new SchoolCatalogRepository($this->connections->create()))->grades();
        });
    }

    private function noFilters(ServerRequestInterface $request): void
    {
        if ($request->getQueryParams() !== []) {
            throw new TeacherException('invalid_input', 'Esta consulta de catálogo não aceita filtros.');
        }
    }
}
