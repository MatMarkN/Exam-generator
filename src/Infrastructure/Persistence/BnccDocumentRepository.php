<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Bncc\ImportException;
use Medoo\Medoo;
use PDOException;

final class BnccDocumentRepository
{
    public function __construct(private readonly Medoo $database)
    {
    }

    public function find(int $id): ?array
    {
        $row = $this->database->get('bncc_documents', '*', ['id' => $id]);
        return $row === null ? null : $this->decodeDocument($row);
    }

    public function latest(string $hash): ?array
    {
        $row = $this->database->get('bncc_documents', '*', ['sha256' => $hash, 'ORDER' => ['revision' => 'DESC']]);
        return $row === null ? null : $this->decodeDocument($row);
    }

    /** Documento e todas as páginas entram juntos; uma falha não deixa uma importação parcial. */
    public function create(array $document, array $extraction): array
    {
        $this->database->pdo->beginTransaction();
        try {
            $this->database->insert('bncc_documents', [
                ...$document,
                'status' => 'draft',
                'page_count' => count($extraction['pages']),
                'extractor_version' => $extraction['extractor_version'],
                'extracted_at' => gmdate('Y-m-d H:i:s'),
                'extraction_warnings' => json_encode($extraction['warnings'], JSON_THROW_ON_ERROR),
            ]);
            $id = (int) $this->database->id();
            foreach (array_chunk($extraction['pages'], 25) as $batch) {
                $rows = [];
                foreach ($batch as $page) {
                    $rows[] = [
                        'document_id' => $id,
                        'page_number' => $page['page_number'],
                        'source_text' => $page['source_text'],
                        'detected_skill_codes' => json_encode($page['detected_skill_codes'], JSON_THROW_ON_ERROR),
                    ];
                }
                $this->database->insert('bncc_document_pages', $rows);
            }
            $saved = $this->find($id);
            $this->database->pdo->commit();
            return $saved;
        } catch (\Throwable $exception) {
            if ($this->database->pdo->inTransaction()) {
                $this->database->pdo->rollBack();
            }
            if ($exception instanceof PDOException && ($exception->errorInfo[1] ?? null) === 1062) {
                throw new ImportException('revision_conflict', 'Uma importação concorrente já criou essa revisão. Consulte o documento e tente novamente.');
            }
            throw $exception;
        }
    }

    public function pages(int $documentId, int $fromPage, int $limit): array
    {
        $pages = $this->database->select('bncc_document_pages', ['page_number', 'source_text', 'detected_skill_codes'], [
            'document_id' => $documentId, 'page_number[>=]' => $fromPage,
            'ORDER' => ['page_number' => 'ASC'], 'LIMIT' => $limit,
        ]);
        foreach ($pages as &$page) {
            $page['detected_skill_codes'] = json_decode($page['detected_skill_codes'], true, flags: JSON_THROW_ON_ERROR);
        }
        return $pages;
    }

    private function decodeDocument(array $row): array
    {
        foreach (['extraction_warnings', 'structure_report'] as $key) {
            if (isset($row[$key])) {
                $row[$key] = json_decode($row[$key], true, flags: JSON_THROW_ON_ERROR);
            }
        }
        return $row;
    }
}
