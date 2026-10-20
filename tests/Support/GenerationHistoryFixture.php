<?php

declare(strict_types=1);

namespace Tests\Support;

use Medoo\Medoo;

final class GenerationHistoryFixture
{
    public function __construct(private readonly Medoo $database) {}

    public function removeForDocument(int $documentId): void
    {
        $ids = $this->database->query('SELECT id FROM generation_history WHERE bncc_document_id = :selected
            OR CAST(JSON_UNQUOTE(JSON_EXTRACT(request_json, \'$.bncc_document_id\')) AS UNSIGNED) = :requested',
            [':selected' => $documentId, ':requested' => $documentId])->fetchAll(\PDO::FETCH_COLUMN);
        $this->remove($ids);
    }

    public function remove(array $ids): void
    {
        if ($ids !== []) {
            $this->database->delete('exams', ['generation_id' => $ids]); // Gabarito acompanha a prova pela FK.
            $this->database->delete('generation_history', ['id' => $ids]);
        }
    }
}
