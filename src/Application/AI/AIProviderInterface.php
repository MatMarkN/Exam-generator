<?php

declare(strict_types=1);

namespace App\Application\AI;

interface AIProviderInterface
{
    /**
     * Executa uma geração síncrona com o contrato da aplicação.
     * O adaptador traduz instruções, dados, schema e limites para a API escolhida.
     * Recusa, resposta incompleta ou recurso incompatível devem produzir uma exceção,
     * mesmo que algum trecho recebido pareça JSON válido. Não repetir silenciosamente.
     *
     * @throws AIProviderException
     */
    public function generate(AIRequest $request): AIResponse;
}
