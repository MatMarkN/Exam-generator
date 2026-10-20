<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Teachers\TeacherException;
use App\Application\Teachers\TeacherInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TeacherInputTest extends TestCase
{
    public function testProfileNormalizesSpacesSupportsUnicodeAndPreservesExplicitNull(): void
    {
        self::assertSame(['name' => 'Professora Érica 🧮', 'email' => 'erica@example.test'],
            TeacherInput::profile(['name' => '  Professora Érica 🧮  ', 'email' => ' erica@example.test '], true));
        self::assertSame(['email' => null], TeacherInput::profile(['email' => null]));
        self::assertSame(['name' => str_repeat('á', 160)], TeacherInput::profile(['name' => str_repeat('á', 160)], true));
    }

    public function testPartialPreferencesPreserveOmittedFieldsAndCanClearSchool(): void
    {
        self::assertSame(['school_name' => null], TeacherInput::settings(['school_name' => null]));
        self::assertSame(['default_font_family' => 'Times New Roman', 'default_font_size' => 36],
            TeacherInput::settings(['default_font_family' => ' Times New Roman ', 'default_font_size' => 36]));
        self::assertSame(['default_font_size' => 8], TeacherInput::settings(['default_font_size' => 8]));
    }

    #[DataProvider('badProfile')]
    public function testInvalidProfileIsRejected(array $input, bool $creating): void
    {
        $this->expectException(TeacherException::class);
        TeacherInput::profile($input, $creating);
    }

    public static function badProfile(): array
    {
        return [[[], true], [[], false], [['email' => null], true], [['name' => null], true],
            [['name' => ''], true], [['name' => " \u{00a0} "], true], [['name' => "A\nB"], true],
            [['name' => "A\tB"], false], [['name' => "A\u{2028}B"], false], [['name' => "A\u{0081}B"], false],
            [['name' => str_repeat('á', 161)], false], [['name' => "\xff"], false],
            [['name' => 2], false], [['email' => 'invalido'], false], [['email' => ''], false],
            [['email' => 2], false], [['id' => 1], false], [['name' => 'A', 'settings' => []], true]];
    }

    #[DataProvider('badSettings')]
    public function testInvalidPreferencesAreRejected(array $input): void
    {
        $this->expectException(TeacherException::class);
        TeacherInput::settings($input);
    }

    public static function badSettings(): array
    {
        return [[[]], [['school_name' => '']], [['school_name' => false]],
            [['school_name' => str_repeat('á', 181)]], [['default_font_family' => null]],
            [['default_font_family' => str_repeat('a', 81)]], [['default_font_family' => "Arial\nVerdana"]],
            [['default_font_size' => 0]], [['default_font_size' => 7]], [['default_font_size' => 37]],
            [['default_font_size' => 12.0]], [['default_font_size' => '12']], [['default_font_size' => null]],
            [['teacher_id' => 1]]];
    }
}
