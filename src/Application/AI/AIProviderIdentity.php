<?php

declare(strict_types=1);

namespace App\Application\AI;

/** Metadados opcionais para registrar inclusive tentativas que falham sem AIResponse. */
interface AIProviderIdentity
{
    /** @return array{provider: string, model: ?string} Somente nomes públicos; nunca credenciais. */
    public function identity(): array;
}
