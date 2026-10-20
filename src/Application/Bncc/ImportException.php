<?php

declare(strict_types=1);

namespace App\Application\Bncc;

use RuntimeException;

final class ImportException extends RuntimeException
{
    /** @param array<string, mixed> $details */
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}
