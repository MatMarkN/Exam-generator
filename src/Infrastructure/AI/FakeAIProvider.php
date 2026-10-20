<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Application\AI\AIProviderException;
use App\Application\AI\AIProviderInterface;
use App\Application\AI\AIProviderIdentity;
use App\Application\AI\AIRequest;
use App\Application\AI\AIResponse;

/** Simulador local: repete uma resposta ou falha configurada, sem interpretar o pedido. */
final class FakeAIProvider implements AIProviderInterface, AIProviderIdentity
{
    private int $callCount = 0;
    private ?AIRequest $lastRequest = null;

    /**
     * A string é o conteúdo gerado simulado, sem envelope de uma API.
     * Sua validação acontece em generate(), permitindo testar também saídas inválidas.
     */
    public function __construct(private readonly string|AIProviderException $outcome)
    {
    }

    public function generate(AIRequest $request): AIResponse
    {
        ++$this->callCount;
        $this->lastRequest = $request;

        if ($this->outcome instanceof AIProviderException) {
            // Uma exceção nova preserva a origem desta chamada; não reutiliza a trace da configuração.
            throw new AIProviderException($this->outcome->reason, $this->outcome->retryAfterSeconds);
        }

        return new AIResponse(
            json: $this->outcome,
            provider: 'fake',
            model: 'static-fixture-v1',
            durationMs: 0, // Valor simulado, não uma medição de desempenho.
            inputTokens: 0, // Nenhum modelo é executado; não estimamos tokens do pedido.
            outputTokens: 0,
        );
    }

    public function identity(): array
    {
        return ['provider' => 'fake', 'model' => 'static-fixture-v1'];
    }

    /** Conta as tentativas, incluindo as que lançaram uma exceção. */
    public function callCount(): int
    {
        return $this->callCount;
    }

    /** Guarda somente o último pedido, em memória; AIRequest é imutável. */
    public function lastRequest(): ?AIRequest
    {
        return $this->lastRequest;
    }
}
