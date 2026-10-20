<?php

declare(strict_types=1);

$password = getenv('DB_PASSWORD');

if ($password === false || $password === '') {
    throw new RuntimeException('Configure DB_PASSWORD no ambiente da aplicação.');
}

$port = filter_var(getenv('DB_PORT') ?: '3306', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 65535],
]);

if ($port === false) {
    throw new RuntimeException('DB_PORT deve ser uma porta válida.');
}

return [
    'host' => getenv('DB_HOST') ?: 'mysql',
    'port' => $port,
    'database' => getenv('DB_DATABASE') ?: 'gerador_de_provas',
    'username' => getenv('DB_USERNAME') ?: 'gerador',
    'password' => $password,
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_0900_ai_ci',
];
