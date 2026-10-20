<?php
declare(strict_types=1);

$list = static fn (string $variable, string $fallback): array => array_values(array_filter(array_map('trim', explode(',', getenv($variable) ?: $fallback))));
return [
    'cookie_name' => getenv('APP_ENV') === 'demo' ? 'prova_clara_demo_session' : 'prova_clara_session',
    'cookie_secure' => filter_var(getenv('AUTH_COOKIE_SECURE') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'session_seconds' => 43200,
    'allowed_hosts' => $list('AUTH_ALLOWED_HOSTS', 'localhost,127.0.0.1,::1'),
    'allowed_origins' => $list('AUTH_ALLOWED_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173,http://localhost:8080,http://127.0.0.1:8080,http://localhost:8081,http://127.0.0.1:8081,http://localhost:4173,http://127.0.0.1:4173,http://[::1]:5173,http://[::1]:8080'),
];
