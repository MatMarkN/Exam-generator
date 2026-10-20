<?php
declare(strict_types=1);
namespace App\Application\Auth;

final class AuthException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message, public readonly int $status = 401,
        public readonly ?int $retryAfterSeconds = null)
    {
        parent::__construct($message);
    }
}
