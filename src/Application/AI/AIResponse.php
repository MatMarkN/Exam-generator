<?php

declare(strict_types=1);

namespace App\Application\AI;

use InvalidArgumentException;
use stdClass;

/** Resposta normalizada de uma geração concluída; a validação do schema e da BNCC ocorre depois. */
final readonly class AIResponse
{
    public function __construct(
        public string $json,
        public string $provider,
        public string $model,
        public int $durationMs,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
        public ?string $providerRequestId = null,
    ) {
        try {
            JsonObject::decode($json, 128 * 1024);
        } catch (InvalidArgumentException) {
            throw new AIProviderException(AIErrorReason::InvalidResponse);
        }
        if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $provider)
            || trim($model) === '' || strlen($model) > 200 || !preg_match('//u', $model)
            || preg_match('/[\x00-\x1f\x7f]/u', $model)
            || ($providerRequestId !== null && !preg_match('/^[\x21-\x7e]{1,200}$/D', $providerRequestId))
            || $durationMs < 0 || ($inputTokens !== null && $inputTokens < 0) || ($outputTokens !== null && $outputTokens < 0)) {
            throw new AIProviderException(AIErrorReason::InvalidResponse);
        }
    }

    /** Cada chamada produz um objeto independente e preserva a distinção entre {} e []. */
    public function data(): stdClass
    {
        return JsonObject::decode($this->json, 128 * 1024);
    }
}
