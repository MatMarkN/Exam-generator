<?php

declare(strict_types=1);

namespace App\Application\Exams;

use RuntimeException;

final class ExamGenerationException extends RuntimeException
{
    /** @param array<string, mixed> $details Informações controladas, sem copiar prompts ou respostas. */
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}
