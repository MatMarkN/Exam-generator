<?php

declare(strict_types=1);

namespace App\Application\Bncc;

use App\Infrastructure\Documents\LocalPdfStorage;
use App\Infrastructure\Documents\PopplerPdfExtractor;
use App\Infrastructure\Persistence\BnccDocumentRepository;
use Psr\Http\Message\UploadedFileInterface;

final class ImportBnccDocument
{
    public function __construct(
        private readonly LocalPdfStorage $storage,
        private readonly PopplerPdfExtractor $extractor,
        private readonly BnccDocumentRepository $documents,
    ) {
    }

    public function import(UploadedFileInterface $upload, string $title, string $versionLabel): array
    {
        $title = $this->validateLabel($title, 180, 'title');
        $versionLabel = $this->validateLabel($versionLabel, 80, 'version_label');
        $file = $this->storage->receive($upload);
        try {
            $existing = $this->documents->latest($file['sha256']);
            if ($existing !== null) {
                throw new ImportException('duplicate_document', 'Este PDF já foi importado. Use a rota de reprocessamento para criar outra revisão.', [
                    'document_id' => $existing['id'],
                ]);
            }
            $extraction = $this->extractor->extract($file['absolute_path']);
            unset($file['absolute_path']);
            return $this->documents->create([
                ...$file, 'title' => $title, 'version_label' => $versionLabel, 'revision' => 1,
            ], $extraction);
        } catch (\Throwable $exception) {
            $this->storage->remove($file['storage_path']);
            throw $exception;
        }
    }

    public function reprocess(int $documentId): array
    {
        $source = $this->get($documentId);
        $path = $this->storage->verifiedPath($source['storage_path'], $source['sha256']);
        $extraction = $this->extractor->extract($path);
        $latest = $this->documents->latest($source['sha256']);

        // O arquivo original é compartilhado, mas a extração e seus estados são novos e independentes.
        return $this->documents->create([
            'title' => $source['title'], 'version_label' => $source['version_label'],
            'original_filename' => $source['original_filename'], 'storage_path' => $source['storage_path'],
            'sha256' => $source['sha256'], 'file_size' => filesize($path),
            'revision' => $latest['revision'] + 1,
        ], $extraction);
    }

    public function get(int $documentId): array
    {
        return $this->documents->find($documentId)
            ?? throw new ImportException('not_found', 'Documento não encontrado.');
    }

    public function pages(int $documentId, int $fromPage, int $limit): array
    {
        $document = $this->get($documentId);
        if ($fromPage < 1 || $fromPage > $document['page_count'] || $limit < 1 || $limit > 20) {
            throw new ImportException('invalid_input', 'from_page deve estar dentro do documento e limit deve ser de 1 a 20.');
        }
        $pages = $this->documents->pages($documentId, $fromPage, $limit);
        return [
            'document_id' => $documentId, 'total_pages' => $document['page_count'], 'pages' => $pages,
            'next_page' => $pages !== [] && end($pages)['page_number'] < $document['page_count']
                ? end($pages)['page_number'] + 1 : null,
        ];
    }

    private function validateLabel(string $value, int $maxCharacters, string $field): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('//u', $value) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $value)
            || preg_match_all('/./us', $value) > $maxCharacters) {
            throw new ImportException('invalid_input', "{$field} deve conter de 1 a {$maxCharacters} caracteres, sem caracteres de controle.");
        }
        return $value;
    }
}
