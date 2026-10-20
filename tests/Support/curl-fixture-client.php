<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || $argc !== 3) {
    exit(2);
}
require dirname(__DIR__, 2) . '/vendor/autoload.php';
try {
    $response = (new App\Infrastructure\AI\Http\CurlHttpTransport())->post(
        'https://localhost:' . (int) $argv[1] . '/fixture',
        ['Content-Type' => 'application/json', 'x-goog-api-key' => 'synthetic-key'],
        '{"test":"local"}',
        (int) $argv[2],
    );
    echo json_encode(['status' => $response->status, 'body' => $response->body, 'headers' => $response->headers], JSON_THROW_ON_ERROR);
} catch (App\Application\AI\AIProviderException $exception) {
    echo json_encode(['error' => $exception->reason->value], JSON_THROW_ON_ERROR);
}
