<?php

declare(strict_types=1);

use App\Application\AI\AIProviderException;
use App\Application\AI\AIRequest;
use Opis\JsonSchema\Validator;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc !== 2 || $argv[1] !== '--send') {
    echo "Teste de conexão preparado. Nenhuma chamada foi feita.\n"
        . "Depois de configurar a chave de um projeto Free Tier:\n"
        . "docker compose exec -T app php examples/gemini-connection.php --send\n";
    exit(0);
}
try {
    $request = new AIRequest(
        instructions: 'Teste de conexão. Responda somente com o objeto JSON {"received": true}.',
        inputJson: '{"test":"connection"}',
        schemaName: 'connection_test',
        outputSchemaJson: '{"type":"object","properties":{"received":{"type":"boolean"}},"required":["received"],"additionalProperties":false}',
        maxOutputTokens: 1024,
        timeoutSeconds: 30,
    );
    $provider = require dirname(__DIR__) . '/config/ai.php';
    $response = $provider->generate($request);
    if (!(new Validator())->validate($response->data(), $request->outputSchema())->isValid()
        || $response->data()->received !== true) {
        throw new RuntimeException('O teste não recebeu a confirmação esperada.');
    }
    echo json_encode(['status' => 'ok', 'data' => $response->data(), 'provider' => $response->provider,
        'model' => $response->model, 'duration_ms' => $response->durationMs, 'input_tokens' => $response->inputTokens,
        'output_tokens' => $response->outputTokens, 'provider_request_id' => $response->providerRequestId],
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (AIProviderException $exception) {
    fwrite(STDERR, $exception->reason->value . ': ' . $exception->getMessage() . "\n");
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, "Falha no teste de conexão. Confira o guia da etapa 11.\n");
    exit(1);
}
