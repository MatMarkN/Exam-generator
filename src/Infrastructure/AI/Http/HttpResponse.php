<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Http;

final readonly class HttpResponse
{
    /** @param array<string, string> $headers Nomes normalizados em minúsculas. */
    public function __construct(public int $status, public string $body, public array $headers = [])
    {
    }
}
