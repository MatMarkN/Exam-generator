<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use Medoo\Medoo;
use PDO;

final class DatabaseConnectionFactory
{
    /** @param array<string, mixed> $settings */
    public function __construct(private readonly array $settings)
    {
    }

    public function create(): Medoo
    {
        return new Medoo([
            ...$this->settings,
            'type' => 'mysql',
            'error' => PDO::ERRMODE_EXCEPTION,
            'logging' => false,
            'option' => [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_TIMEOUT => 5,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
            ],
            'command' => [
                // O Medoo define ANSI_QUOTES; restauramos também os modos do servidor.
                "SET SESSION sql_mode = CONCAT_WS(',', @@GLOBAL.sql_mode, 'ANSI_QUOTES')",
                "SET SESSION time_zone = '+00:00'",
            ],
        ]);
    }
}
