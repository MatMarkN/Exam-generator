<?php

declare(strict_types=1);

if (getenv('APP_ENV') !== 'test' || getenv('DB_DATABASE') !== 'gerador_de_provas_test') {
    throw new RuntimeException(
        'Os testes exigem o banco descartável. Execute: '
        . 'docker compose -f compose.test.yaml up --build --abort-on-container-exit --exit-code-from tests',
    );
}

require __DIR__ . '/../vendor/autoload.php';
