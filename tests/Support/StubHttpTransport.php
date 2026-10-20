<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\AI\AIProviderException;
use App\Infrastructure\AI\Http\HttpResponse;
use App\Infrastructure\AI\Http\HttpTransportInterface;

/** Não possui rede. A resposta HTTP é escrita pelo teste, inclusive quando o adaptador é real. */
final class StubHttpTransport implements HttpTransportInterface
{
    public array $calls = [];

    public function __construct(public HttpResponse|AIProviderException $result)
    {
    }

    public function post(string $url, #[\SensitiveParameter] array $headers, string $json, int $timeoutSeconds): HttpResponse
    {
        $this->calls[] = compact('url', 'headers', 'json', 'timeoutSeconds');
        if ($this->result instanceof AIProviderException) {
            throw $this->result;
        }
        return $this->result;
    }

    public static function success(string $json = '{"received":true}'): HttpResponse
    {
        return new HttpResponse(200, json_encode([
            'candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $json]]], 'finishReason' => 'STOP']],
            'modelVersion' => 'gemini-3.5-flash-lite-fixture', 'responseId' => 'synthetic-request',
            'usageMetadata' => ['promptTokenCount' => 120, 'candidatesTokenCount' => 30, 'thoughtsTokenCount' => 10],
        ], JSON_THROW_ON_ERROR));
    }
}
