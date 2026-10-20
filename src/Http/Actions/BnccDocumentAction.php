<?php

declare(strict_types=1);

namespace App\Http\Actions;

use App\Application\Bncc\ImportBnccDocument;
use App\Application\Bncc\ImportException;
use App\Http\JsonResponder;
use App\Infrastructure\Documents\LocalPdfStorage;
use App\Infrastructure\Documents\PopplerPdfExtractor;
use App\Infrastructure\Documents\ProcessRunner;
use App\Infrastructure\Persistence\BnccDocumentRepository;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final class BnccDocumentAction
{
    public function __construct(
        private readonly DatabaseConnectionFactory $connections,
        private readonly string $storagePath,
    ) {
    }

    public function import(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->respond($response, function () use ($request): array {
            if ((int) $request->getHeaderLine('Content-Length') > 52 * 1024 * 1024) {
                throw new ImportException('file_too_large', 'O envio excedeu o tamanho permitido. O PDF deve ter no máximo 50 MiB.');
            }
            if (!str_starts_with(strtolower($request->getHeaderLine('Content-Type')), 'multipart/form-data;')) {
                throw new ImportException('unsupported_media_type', 'Use multipart/form-data com pdf, title e version_label.');
            }
            $fields = $request->getParsedBody();
            $upload = $request->getUploadedFiles()['pdf'] ?? null;
            if (!$upload instanceof UploadedFileInterface || !is_array($fields)
                || !is_string($fields['title'] ?? null) || !is_string($fields['version_label'] ?? null)) {
                throw new ImportException('invalid_input', 'Envie um PDF no campo pdf e os textos title e version_label.');
            }
            return $this->publicDocument($this->service()->import($upload, $fields['title'], $fields['version_label']));
        }, 201);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->respond($response, fn (): array => $this->publicDocument($this->service()->get($this->positiveInteger($args['id']))));
    }

    public function pages(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->respond($response, function () use ($request, $args): array {
            $query = $request->getQueryParams();
            return $this->service()->pages(
                $this->positiveInteger($args['id']),
                $this->positiveInteger($query['from_page'] ?? '1'),
                $this->positiveInteger($query['limit'] ?? '5'),
            );
        });
    }

    public function reprocess(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->respond($response, fn (): array => $this->publicDocument($this->service()->reprocess($this->positiveInteger($args['id']))), 201);
    }

    private function service(): ImportBnccDocument
    {
        return new ImportBnccDocument(
            new LocalPdfStorage($this->storagePath), new PopplerPdfExtractor(new ProcessRunner()),
            new BnccDocumentRepository($this->connections->create()),
        );
    }

    private function publicDocument(array $document): array
    {
        unset($document['storage_path']);
        $document['curriculum_status'] = $document['status'] === 'ready' ? 'ready'
            : (($document['structured_at'] ?? null) !== null ? 'awaiting_review' : 'awaiting_structuring_and_review');
        return $document;
    }

    private function positiveInteger(mixed $value): int
    {
        if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,9}$/D', $value) || (int) $value > 4294967295) {
            throw new ImportException('invalid_input', 'IDs e páginas devem ser números inteiros positivos.');
        }
        return (int) $value;
    }

    private function respond(ResponseInterface $response, callable $operation, int $status = 200): ResponseInterface
    {
        return JsonResponder::run($response, $operation, $status);
    }
}
