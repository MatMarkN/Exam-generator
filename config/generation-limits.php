<?php

declare(strict_types=1);

// Limites de chamadas, não um orçamento monetário. Zero nunca significa ilimitado.
$positive = static function (string $name, int $default, int $maximum): int {
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }
    if (!preg_match('/^[1-9][0-9]*$/D', $value) || strlen($value) > 7 || (int) $value > $maximum) {
        throw new RuntimeException("{$name} deve ser um inteiro positivo até {$maximum}.");
    }
    return (int) $value;
};

return [
    'user_daily_limit' => $positive('GENERATION_USER_DAILY_LIMIT', 20, 1000000),
    'global_daily_limit' => $positive('GENERATION_GLOBAL_DAILY_LIMIT', 100, 1000000),
    'min_interval_seconds' => $positive('GENERATION_MIN_INTERVAL_SECONDS', 10, 3600),
    'max_concurrent' => $positive('GENERATION_MAX_CONCURRENT', 2, 32),
];
