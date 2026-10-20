<?php

declare(strict_types=1);

namespace App\Application\Exams;

/** Acrescenta correlação ao erro original, sem substituir sua classificação HTTP. */
final class RecordedGenerationException extends \RuntimeException
{
    public function __construct(public readonly int $generationId, public readonly \Throwable $failure)
    {
        parent::__construct('A geração não foi concluída. Consulte o histórico pelo identificador.');
    }
}
