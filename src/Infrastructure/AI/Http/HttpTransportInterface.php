<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Http;

interface HttpTransportInterface
{
    /** @param array<string, string> $headers */
    public function post(string $url, #[\SensitiveParameter] array $headers, string $json, int $timeoutSeconds): HttpResponse;
}
