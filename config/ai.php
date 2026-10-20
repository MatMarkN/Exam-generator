<?php

declare(strict_types=1);

use App\Infrastructure\AI\GeminiAIProvider;

// O fake só está disponível no Compose isolado da demonstração.
if (getenv('APP_ENV') === 'demo') {
    require_once __DIR__ . '/../examples/demo/DemoCurriculum.php';
    require_once __DIR__ . '/../examples/demo/FractionsExam.php';
    require_once __DIR__ . '/../examples/demo/DemoAIProvider.php';
    \Examples\Demo\DemoCurriculum::assertEnvironment();
    return new \Examples\Demo\DemoAIProvider();
}

// A leitura não faz chamadas; a chave é exigida somente no generate().
return new GeminiAIProvider(
    apiKey: getenv('GEMINI_API_KEY') ?: '',
    model: getenv('GEMINI_MODEL') ?: GeminiAIProvider::DEFAULT_MODEL,
);
