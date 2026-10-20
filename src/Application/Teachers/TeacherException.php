<?php

declare(strict_types=1);

namespace App\Application\Teachers;

final class TeacherException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
