<?php

declare(strict_types=1);

namespace App\Application\Exams;

use App\Application\AI\AIResponse;

/** Estimativa por tarifas explicitamente configuradas para o modelo retornado, sem tabela de preços embutida. */
final readonly class GenerationCostEstimator
{
    public function __construct(private array $rates = [])
    {
        foreach ($rates as $models) {
            foreach ($models as $rate) {
                foreach (['input_usd_per_million', 'output_usd_per_million'] as $key) {
                    if (!isset($rate[$key]) || !is_numeric($rate[$key]) || !is_finite((float) $rate[$key])
                        || (float) $rate[$key] < 0 || (float) $rate[$key] > 10000) {
                        throw new \InvalidArgumentException('As duas tarifas devem ser valores de 0 a 10000 USD por milhão de tokens.');
                    }
                }
            }
        }
    }

    public function estimate(?AIResponse $response): array
    {
        $unknown = ['amount' => null, 'currency' => 'USD', 'basis' => 'usage_unavailable'];
        if ($response === null) {
            return $unknown;
        }
        if ($response->provider === 'fake') {
            return ['amount' => '0.00000000', 'currency' => 'USD', 'basis' => 'simulated'];
        }
        $rates = $this->rates[$response->provider][$response->model] ?? null;
        if ($rates === null) {
            return [...$unknown, 'basis' => 'pricing_not_configured'];
        }
        if ($response->inputTokens === null || $response->outputTokens === null) {
            return $unknown;
        }
        $amount = ($response->inputTokens / 1_000_000 * (float) $rates['input_usd_per_million'])
            + ($response->outputTokens / 1_000_000 * (float) $rates['output_usd_per_million']);
        return ['amount' => number_format($amount, 8, '.', ''), 'currency' => 'USD', 'basis' => 'configured_token_rates',
            'rates' => $rates, 'provider' => $response->provider, 'model' => $response->model];
    }
}
