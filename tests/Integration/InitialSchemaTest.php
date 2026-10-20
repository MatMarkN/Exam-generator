<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DatabaseTestCase;

final class InitialSchemaTest extends DatabaseTestCase
{
    public function testFundamentalCatalogsAreReadyWithoutCreatingAFakeTeacher(): void
    {
        self::assertSame(range(1, 9), $this->database->select('school_grades', 'year_number', [
            'education_stage' => 'ensino_fundamental',
            'ORDER' => ['year_number' => 'ASC'],
        ]));
        self::assertSame(9, $this->database->count('subjects'));
        self::assertSame('Matemática', $this->database->get('subjects', 'name', ['code' => 'matematica']));
        self::assertSame(0, $this->database->count('teachers'));
        self::assertSame(0, $this->database->count('teacher_settings'));
    }

    public function testTeacherSettingsUseDefaultsAndCanBeReadWithTheirTeacher(): void
    {
        $this->database->insert('teachers', ['name' => 'Professora de integração 🧮']);
        $teacherId = (int) $this->database->id();
        $this->database->insert('teacher_settings', ['teacher_id' => $teacherId]);

        $row = $this->database->get('teachers', ['[>]teacher_settings' => ['id' => 'teacher_id']], [
            'teachers.name',
            'teachers.email',
            'teachers.created_at',
            'teacher_settings.school_name',
            'teacher_settings.default_font_family',
            'teacher_settings.default_font_size',
        ], ['teachers.id' => $teacherId]);

        self::assertSame('Professora de integração 🧮', $row['name']);
        self::assertNull($row['email']);
        self::assertNull($row['school_name']);
        self::assertSame('Arial', $row['default_font_family']);
        self::assertSame(12, $row['default_font_size']);
        self::assertNotEmpty($row['created_at']);
    }

    public function testSettingsCannotReferenceAnUnknownTeacher(): void
    {
        $this->assertMysqlFailure(1452, fn () => $this->database->insert('teacher_settings', [
            'teacher_id' => 4294967295,
        ]));
    }

    public function testATeacherCannotHaveTwoSettingsRows(): void
    {
        $this->database->insert('teachers', ['name' => 'Professor de integração']);
        $settings = ['teacher_id' => (int) $this->database->id()];
        $this->database->insert('teacher_settings', $settings);

        $this->assertMysqlFailure(1062, fn () => $this->database->insert('teacher_settings', $settings));
    }

    public function testDeletingATeacherRemovesOnlyTheirOwnSettings(): void
    {
        $ids = [];
        foreach (['Primeiro professor', 'Segundo professor'] as $name) {
            $this->database->insert('teachers', ['name' => $name]);
            $id = (int) $this->database->id();
            $this->database->insert('teacher_settings', ['teacher_id' => $id]);
            $ids[] = $id;
        }

        $this->database->delete('teachers', ['id' => $ids[0]]);

        self::assertFalse($this->database->has('teacher_settings', ['teacher_id' => $ids[0]]));
        self::assertTrue($this->database->has('teacher_settings', ['teacher_id' => $ids[1]]));
        self::assertTrue($this->database->has('teachers', ['id' => $ids[1]]));
    }

    public function testTeachersCanShareANameAndHaveNoEmail(): void
    {
        $this->database->insert('teachers', [['name' => 'Alex Silva'], ['name' => 'Alex Silva']]);

        self::assertSame(2, $this->database->count('teachers', ['name' => 'Alex Silva']));
    }

    public function testAnEmailCannotBeAssignedToTwoTeachers(): void
    {
        $this->database->insert('teachers', ['name' => 'Primeiro', 'email' => 'docente@example.test']);

        $this->assertMysqlFailure(1062, fn () => $this->database->insert('teachers', [
            'name' => 'Segundo',
            'email' => 'DOCENTE@example.test',
        ]));
    }

    public function testSubjectCodesCannotBeDuplicated(): void
    {
        $this->assertMysqlFailure(1062, fn () => $this->database->insert('subjects', [
            'code' => 'matematica',
            'name' => 'Outro nome',
        ]));
    }

    public function testAStageCannotContainTheSameYearTwice(): void
    {
        $this->assertMysqlFailure(1062, fn () => $this->database->insert('school_grades', [
            'code' => 'outro_codigo',
            'name' => 'Sexto ano duplicado',
            'education_stage' => 'ensino_fundamental',
            'year_number' => 6,
        ]));
    }

    #[DataProvider('invalidYears')]
    public function testSchoolYearsOutsideTheInitialRangeAreRejected(int $year): void
    {
        $this->assertMysqlFailure(3819, fn () => $this->database->insert('school_grades', [
            'code' => 'ano_invalido',
            'name' => 'Ano inválido',
            'education_stage' => 'ensino_fundamental',
            'year_number' => $year,
        ]));
    }

    public static function invalidYears(): array
    {
        return ['zero' => [0], 'dez' => [10]];
    }

    public function testANameMadeOnlyOfSpacesIsRejected(): void
    {
        $this->assertMysqlFailure(3819, fn () => $this->database->insert('teachers', ['name' => '   ']));
    }

    public function testATeacherMustHaveAName(): void
    {
        $this->assertMysqlFailure(1048, fn () => $this->database->insert('teachers', ['name' => null]));
    }

    public function testFontDefaultsCannotBeReplacedWithNull(): void
    {
        $this->database->insert('teachers', ['name' => 'Professor de integração']);

        $this->assertMysqlFailure(1048, fn () => $this->database->insert('teacher_settings', [
            'teacher_id' => (int) $this->database->id(),
            'default_font_size' => null,
        ]));
    }

    public function testFontSizeMustBePositive(): void
    {
        $this->database->insert('teachers', ['name' => 'Professor de integração']);

        $this->assertMysqlFailure(3819, fn () => $this->database->insert('teacher_settings', [
            'teacher_id' => (int) $this->database->id(),
            'default_font_size' => 0,
        ]));
    }

    private function assertMysqlFailure(int $expectedCode, callable $operation): void
    {
        try {
            $operation();
        } catch (PDOException $exception) {
            self::assertSame($expectedCode, (int) $exception->errorInfo[1]);

            return;
        }

        self::fail('O banco deveria ter rejeitado a operação inválida.');
    }
}
