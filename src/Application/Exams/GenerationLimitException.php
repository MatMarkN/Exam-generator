<?php

declare(strict_types=1);

namespace App\Application\Exams;

use RuntimeException;

final class GenerationLimitException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly int $retryAfterSeconds,
    ) {
        parent::__construct($message);
    }
}
