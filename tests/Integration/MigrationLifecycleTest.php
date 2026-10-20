<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Medoo\Medoo;
use Phinx\Config\Config;
use Phinx\Migration\Manager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class MigrationLifecycleTest extends TestCase
{
    private Manager $migrations;
    private Medoo $database;

    protected function setUp(): void
    {
        // Proteção adicional: esta classe executa DDL, que não participa de rollback de dados.
        if (getenv('APP_ENV') !== 'test' || getenv('DB_DATABASE') !== 'gerador_de_provas_test') {
            self::fail('O ciclo de migrations só pode rodar no banco descartável.');
        }

        $this->migrations = new Manager(
            new Config(require __DIR__ . '/../../phinx.php'),
            new ArrayInput([]),
            new BufferedOutput(),
        );
        $settings = require __DIR__ . '/../../config/database.php';
        $this->database = (new DatabaseConnectionFactory($settings))->create();
    }

    public function testMigrationsCanBeReversedAndAppliedAgainInAnEmptyDatabase(): void
    {
        try {
            $this->migrations->rollback('test', 0);

            self::assertSame(['phinxlog'], $this->database->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN));
            self::assertSame(0, $this->database->count('phinxlog'));

            $this->migrations->migrate('test');
            $this->migrations->seed('test', 'InitialSchoolCatalogs');

            self::assertSame(8, $this->database->count('phinxlog'));
            self::assertSame(9, $this->database->count('school_grades'));
            self::assertSame(9, $this->database->count('subjects'));
            self::assertSame(0, $this->database->count('teachers'));
            self::assertSame(0, $this->database->count('teacher_settings'));

            // Aplicar novamente deve manter os registros e o histórico.
            $before = $this->database->select('school_grades', '*', ['ORDER' => ['id' => 'ASC']]);
            $this->migrations->migrate('test');
            self::assertSame(8, $this->database->count('phinxlog'));
            self::assertSame($before, $this->database->select('school_grades', '*', ['ORDER' => ['id' => 'ASC']]));
        } finally {
            $this->migrations->migrate('test');
            $this->migrations->seed('test', 'InitialSchoolCatalogs');
        }
    }

    public function testSeedingAgainPreservesIdsAndEditedNames(): void
    {
        $this->database->pdo->beginTransaction();

        try {
            $this->database->update('subjects', ['name' => 'Matemática — nome local'], ['code' => 'matematica']);
            $this->database->update('school_grades', ['name' => 'Sexto ano — nome local'], ['code' => 'ef_06']);

            $subjects = $this->database->select('subjects', '*', ['ORDER' => ['id' => 'ASC']]);
            $grades = $this->database->select('school_grades', '*', ['ORDER' => ['id' => 'ASC']]);

            // O seed precisa compartilhar a conexão desta transação para ver as edições.
            require_once __DIR__ . '/../../database/seeds/InitialSchoolCatalogs.php';
            $adapter = new \Phinx\Db\Adapter\MysqlAdapter([
                'name' => 'gerador_de_provas_test',
                'connection' => $this->database->pdo,
            ]);
            $seed = new \InitialSchoolCatalogs();
            $seed->setAdapter($adapter);
            $seed->run();
            $seed->run();

            self::assertSame($subjects, $this->database->select('subjects', '*', ['ORDER' => ['id' => 'ASC']]));
            self::assertSame($grades, $this->database->select('school_grades', '*', ['ORDER' => ['id' => 'ASC']]));
        } finally {
            $this->database->pdo->rollBack();
        }
    }

    public function testHistoryMigrationCanBeReappliedWithoutChangingExistingCurriculum(): void
    {
        $fixture = new \Tests\Support\CurriculumFixture($this->database);
        $document = $fixture->document();
        $pages = $this->database->select('bncc_document_pages', '*', ['document_id' => $document['id'], 'ORDER' => ['page_number' => 'ASC']]);
        try {
            $this->migrations->rollback('test', 20260924170000);
            self::assertSame(6, $this->database->count('phinxlog'));
            self::assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()
                AND table_name IN ('generation_history', 'exams', 'exam_answers')")->fetchColumn());
            self::assertSame($document, $this->database->get('bncc_documents', '*', ['id' => $document['id']]));
            $this->migrations->migrate('test');
            self::assertSame(8, $this->database->count('phinxlog'));
            self::assertSame($pages, $this->database->select('bncc_document_pages', '*', ['document_id' => $document['id'], 'ORDER' => ['page_number' => 'ASC']]));
            foreach (['generation_history', 'exams', 'exam_answers'] as $table) {
                self::assertSame(0, $this->database->count($table));
            }
        } finally {
            $this->migrations->migrate('test');
            $fixture->remove($document['id']);
        }
    }

    public function testBnccMigrationsCanBeReversedWithoutRemovingTheExistingCatalogsOrTeachers(): void
    {
        $this->database->insert('teachers', ['name' => 'Professor SINTÉTICO para testar preservação']);
        $teacherId = (int) $this->database->id();
        $subjects = $this->database->select('subjects', '*', ['ORDER' => ['id' => 'ASC']]);
        $grades = $this->database->select('school_grades', '*', ['ORDER' => ['id' => 'ASC']]);

        try {
            $this->migrations->rollback('test', 20260924143100);

            self::assertSame(2, $this->database->count('phinxlog'));
            self::assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = DATABASE() AND LEFT(table_name, 5) = 'bncc_'")->fetchColumn());
            self::assertTrue($this->database->has('teachers', ['id' => $teacherId]));
            self::assertSame($subjects, $this->database->select('subjects', '*', ['ORDER' => ['id' => 'ASC']]));
            self::assertSame($grades, $this->database->select('school_grades', '*', ['ORDER' => ['id' => 'ASC']]));

            $this->migrations->migrate('test');
            self::assertSame(8, $this->database->count('phinxlog'));
            self::assertSame(0, $this->database->count('bncc_documents'));
        } finally {
            $this->migrations->migrate('test');
            $this->database->delete('teachers', ['id' => $teacherId]);
        }
    }
}
