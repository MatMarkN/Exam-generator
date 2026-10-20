<?php

declare(strict_types=1);

namespace App\Application\AI;

use InvalidArgumentException;
use RuntimeException;

final class AIProviderException extends RuntimeException
{
    public function __construct(public readonly AIErrorReason $reason, public readonly ?int $retryAfterSeconds = null)
    {
        if ($retryAfterSeconds !== null && ($retryAfterSeconds < 0 || $retryAfterSeconds > 86400
            || !in_array($reason, [AIErrorReason::RateLimited, AIErrorReason::Unavailable], true))) {
            throw new InvalidArgumentException('retryAfterSeconds deve ser de 0 a 86400 e só se aplica a limite de uso ou indisponibilidade.');
        }
        parent::__construct(match ($reason) {
            AIErrorReason::ConfigurationMissing => 'Configure GEMINI_API_KEY no .env e recrie o container app.',
            AIErrorReason::BillingRequired => 'O provedor recusou a chamada por uma condição de cobrança. Confira o projeto no Google AI Studio.',
            AIErrorReason::AuthenticationFailed => 'O provedor de IA recusou as credenciais configuradas.',
            AIErrorReason::RateLimited => 'O provedor de IA atingiu um limite de uso.',
            AIErrorReason::Timeout => 'A geração ultrapassou o tempo permitido.',
            AIErrorReason::Unavailable => 'O provedor de IA está indisponível.',
            AIErrorReason::InvalidRequest => 'O provedor de IA não aceitou os parâmetros da geração.',
            AIErrorReason::UnsupportedFeature => 'O provedor ou modelo não suporta um recurso exigido pela geração.',
            AIErrorReason::Refused => 'O provedor de IA recusou produzir a resposta solicitada.',
            AIErrorReason::IncompleteResponse => 'O provedor de IA devolveu uma resposta incompleta.',
            AIErrorReason::InvalidResponse => 'O provedor de IA devolveu uma resposta inválida.',
        });
    }

    /** Classificação informativa: não autoriza repetição automática nem garante ausência de cobrança. */
    public function isTransient(): bool
    {
        return in_array($this->reason, [AIErrorReason::RateLimited, AIErrorReason::Timeout, AIErrorReason::Unavailable], true);
    }
}
