<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Medoo\Medoo;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseConnectionTest extends TestCase
{
    private Medoo $database;

    protected function setUp(): void
    {
        $settings = require __DIR__ . '/../../config/database.php';
        $this->database = (new DatabaseConnectionFactory($settings))->create();

        // A tabela só existe nesta conexão e nunca substitui uma tabela persistente.
        $this->database->query('CREATE TEMPORARY TABLE medoo_integration_probe (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            value VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB');
    }

    protected function tearDown(): void
    {
        if (!isset($this->database)) {
            return;
        }

        if ($this->database->pdo->inTransaction()) {
            $this->database->pdo->rollBack();
        }

        $this->database->query('DROP TEMPORARY TABLE IF EXISTS medoo_integration_probe');
    }

    public function testConnectsToTheConfiguredDatabaseWithStrictMode(): void
    {
        $settings = require __DIR__ . '/../../config/database.php';
        $row = $this->database->query('SELECT DATABASE() AS database_name,
            CURRENT_USER() AS database_user,
            @@SESSION.sql_mode AS sql_mode,
            @@SESSION.time_zone AS time_zone')->fetch(PDO::FETCH_ASSOC);

        self::assertSame($settings['database'], $row['database_name']);
        self::assertStringStartsWith($settings['username'] . '@', $row['database_user']);
        self::assertStringContainsString('STRICT_TRANS_TABLES', $row['sql_mode']);
        self::assertStringContainsString('ANSI_QUOTES', $row['sql_mode']);
        self::assertSame('+00:00', $row['time_zone']);
    }

    public function testPreservesUnicodeAndTreatsSqlLikeTextAsData(): void
    {
        $value = "Frações 🧮'); DROP TABLE medoo_integration_probe; --";

        $this->database->insert('medoo_integration_probe', ['value' => $value]);
        $id = $this->database->id();

        self::assertSame($value, $this->database->get('medoo_integration_probe', 'value', ['id' => $id]));
        self::assertSame(1, $this->database->count('medoo_integration_probe'));
    }

    public function testCommitsASuccessfulTransaction(): void
    {
        $this->database->action(static function (Medoo $database): void {
            $database->insert('medoo_integration_probe', ['value' => 'primeiro']);
            $database->insert('medoo_integration_probe', ['value' => 'segundo']);
        });

        self::assertSame(2, $this->database->count('medoo_integration_probe'));
        self::assertFalse($this->database->pdo->inTransaction());
    }

    public function testRollsBackWhenTheCallbackReturnsFalse(): void
    {
        $this->database->action(static function (Medoo $database): bool {
            $database->insert('medoo_integration_probe', ['value' => 'não persistir']);

            return false;
        });

        self::assertSame(0, $this->database->count('medoo_integration_probe'));
        self::assertFalse($this->database->pdo->inTransaction());
    }

    public function testRollsBackAndPropagatesAnException(): void
    {
        $failure = new RuntimeException('Falha simulada após uma escrita.');

        try {
            $this->database->action(static function (Medoo $database) use ($failure): void {
                $database->insert('medoo_integration_probe', ['value' => 'não persistir']);

                throw $failure;
            });

            self::fail('A exceção deveria ter sido propagada.');
        } catch (RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertSame(0, $this->database->count('medoo_integration_probe'));
        self::assertFalse($this->database->pdo->inTransaction());
    }

    public function testRejectsDataThatWouldOtherwiseBeSilentlyTruncated(): void
    {
        $this->expectException(PDOException::class);

        $this->database->insert('medoo_integration_probe', ['value' => str_repeat('x', 256)]);
    }
}
