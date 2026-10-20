<?php

declare(strict_types=1);

namespace App\Application\Bncc;

use Medoo\Medoo;

/** The database lock serializes extraction across workers and application instances. */
final class PdfProcessingGuard
{
    public const LOCK_NAME = 'prova-clara:pdf-processing';

    public function __construct(private readonly Medoo $database) {}

    public function run(callable $operation): mixed
    {
        $free = $this->database->query('SELECT IS_FREE_LOCK(:name)', [':name' => self::LOCK_NAME])->fetchColumn();
        $acquired = (int) $free === 1
            ? $this->database->query('SELECT GET_LOCK(:name, 0)', [':name' => self::LOCK_NAME])->fetchColumn() : 0;
        if ((int) $acquired !== 1) {
            throw new ImportException('pdf_processing_busy', 'Já existe um PDF sendo processado. Aguarde a conclusão e tente novamente.');
        }
        try {
            return $operation();
        } finally {
            $this->database->query('SELECT RELEASE_LOCK(:name)', [':name' => self::LOCK_NAME]);
        }
    }
}
