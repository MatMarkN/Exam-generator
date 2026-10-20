<?php

declare(strict_types=1);

namespace App\Http\Actions;

use App\Application\Bncc\ImportException;
use App\Application\Bncc\StructureBnccDocument;
use App\Http\JsonResponder;
use App\Infrastructure\Documents\LocalPdfStorage;
use App\Infrastructure\Documents\PopplerLayoutExtractor;
use App\Infrastructure\Documents\ProcessRunner;
use App\Infrastructure\Persistence\BnccCurriculumRepository;
use App\Infrastructure\Persistence\BnccDocumentRepository;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class BnccCurriculumAction
{
    public function __construct(private readonly DatabaseConnectionFactory $connections, private readonly string $storagePath)
    {
    }

    public function structure(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return JsonResponder::run($response, function () use ($args): array {
            $database = $this->connections->create();
            $service = new StructureBnccDocument(new BnccDocumentRepository($database), new BnccCurriculumRepository($database),
                new LocalPdfStorage($this->storagePath), new PopplerLayoutExtractor(new ProcessRunner()));
            return $service->structure($this->integer($args['id']));
        });
    }

    public function overview(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return JsonResponder::run($response, fn (): array => $this->repository()->overview($this->integer($args['id'])));
    }

    public function skills(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return JsonResponder::run($response, function () use ($request, $args): array {
            $query = $request->getQueryParams();
            $offset = $this->integer($query['offset'] ?? '0', 0);
            $limit = $this->integer($query['limit'] ?? '20');
            if ($limit > 50) {
                throw new ImportException('invalid_input', 'limit deve ser de 1 a 50.');
            }
            return $this->repository()->skills($this->integer($args['id']), $offset, $limit);
        });
    }

    public function skill(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return JsonResponder::run($response, fn (): array => $this->repository()->skill($this->integer($args['id']), $args['code']));
    }

    public function competencies(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return JsonResponder::run($response, fn (): array => $this->repository()->competencies($this->integer($args['id'])));
    }

    public function reviewSkill(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->review($request, $response, $args, 'skill', $args['code']);
    }

    public function reviewCompetency(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->review($request, $response, $args, 'competency', $args['competencyId']);
    }

    public function publish(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return JsonResponder::run($response, fn (): array => $this->repository()->publish($this->integer($args['id'])));
    }

    private function review(ServerRequestInterface $request, ResponseInterface $response, array $args, string $type, string $key): ResponseInterface
    {
        return JsonResponder::run($response, function () use ($request, $args, $type, $key): array {
            if (strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0])) !== 'application/json') {
                throw new ImportException('unsupported_media_type', 'Envie a revisão como application/json.');
            }
            $raw = $request->getBody()->read(8193);
            if (strlen($raw) > 8192) {
                throw new ImportException('invalid_input', 'O JSON de revisão deve ter no máximo 8 KiB.');
            }
            try {
                $body = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new ImportException('invalid_input', 'JSON de revisão inválido.');
            }
            if (!is_array($body) || array_is_list($body) || array_diff(array_keys($body), ['status', 'note', 'description']) !== []
                || !in_array($body['status'] ?? null, ['pending', 'approved', 'rejected'], true)
                || !is_string($body['note'] ?? null) || trim($body['note']) === ''
                || preg_match_all('/./us', $body['note']) > 1000
                || (array_key_exists('description', $body) && (!is_string($body['description']) || trim($body['description']) === ''))) {
                throw new ImportException('invalid_input', 'Informe status (pending, approved ou rejected) e note com 1 a 1.000 caracteres. description é opcional.');
            }
            return $this->repository()->review($this->integer($args['id']), $type,
                $type === 'competency' ? $this->integer($key) : $key, $body['status'], trim($body['note']),
                isset($body['description']) ? trim($body['description']) : null);
        });
    }

    private function repository(): BnccCurriculumRepository
    {
        return new BnccCurriculumRepository($this->connections->create());
    }

    private function integer(mixed $value, int $minimum = 1): int
    {
        if (!is_string($value) || !preg_match('/^(0|[1-9][0-9]{0,9})$/D', $value)
            || (int) $value < $minimum || (int) $value > 4294967295) {
            throw new ImportException('invalid_input', 'IDs e limites devem ser inteiros válidos.');
        }
        return (int) $value;
    }
}
