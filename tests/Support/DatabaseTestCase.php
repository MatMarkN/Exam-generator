<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Medoo\Medoo;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected Medoo $database;

    protected function setUp(): void
    {
        $settings = require __DIR__ . '/../../config/database.php';
        $this->database = (new DatabaseConnectionFactory($settings))->create();
        $this->database->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->database) && $this->database->pdo->inTransaction()) {
            $this->database->pdo->rollBack();
        }
    }
}
