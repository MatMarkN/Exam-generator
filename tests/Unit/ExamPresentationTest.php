<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Exams\ExamGenerationException;
use App\Application\Exams\ExamGenerationRequest;
use App\Application\Exams\ExamPromptBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\ExamFixture;

final class ExamPresentationTest extends TestCase
{
    public function testExistingRequestsKeepTheirShapeAndPresentationDoesNotChangeTheAiRequest(): void
    {
        $builder = new ExamPromptBuilder();
        $original = ExamGenerationRequest::fromArray(ExamFixture::input());
        self::assertArrayNotHasKey('exam_header', $original->toArray());
        self::assertArrayNotHasKey('formatting', $original->toArray());

        $presented = ExamGenerationRequest::fromArray(ExamFixture::input([
            'teacher_id' => 123,
            'exam_header' => ['school_name' => 'ESCOLA_PRIVADA', 'teacher_name' => 'PROFESSOR_PRIVADO',
                'student_name' => 'ALUNO_PRIVADO', 'class_name' => 'TURMA_PRIVADA', 'date' => '2028-02-29'],
            'formatting' => ['font_family' => 'FONTE_PRIVADA', 'font_size' => 18],
        ]));
        self::assertSame('ALUNO_PRIVADO', $presented->toArray()['exam_header']['student_name']);
        self::assertSame(18, $presented->toArray()['formatting']['font_size']);

        $withoutPresentation = $builder->build($original, ExamFixture::context());
        $withPresentation = $builder->build($presented, ExamFixture::context());
        self::assertSame($withoutPresentation->inputJson, $withPresentation->inputJson);
        self::assertSame($withoutPresentation->instructions, $withPresentation->instructions);
        self::assertSame($withoutPresentation->outputSchemaJson, $withPresentation->outputSchemaJson);
    }

    public function testPresentationPreservesExplicitNullAndCountsUnicodeCharacters(): void
    {
        $request = ExamGenerationRequest::fromArray(ExamFixture::input([
            'exam_header' => ['school_name' => null, 'teacher_name' => str_repeat('á', 160),
                'student_name' => null, 'class_name' => str_repeat('ç', 80), 'date' => null],
            'formatting' => ['font_family' => str_repeat('é', 80), 'font_size' => 8],
        ]));
        $normalized = $request->toArray();
        self::assertArrayHasKey('school_name', $normalized['exam_header']);
        self::assertNull($normalized['exam_header']['school_name']);
        self::assertNull($normalized['exam_header']['date']);
        self::assertSame(str_repeat('á', 160), $normalized['exam_header']['teacher_name']);
        self::assertSame(str_repeat('ç', 80), $normalized['exam_header']['class_name']);
        self::assertSame(str_repeat('é', 80), $normalized['formatting']['font_family']);
        self::assertSame(8, $normalized['formatting']['font_size']);
    }

    public function testExplicitEmptyObjectsKeepTheirJsonObjectType(): void
    {
        $request = ExamGenerationRequest::fromArray(ExamFixture::input([
            'exam_header' => (object) [], 'formatting' => (object) [],
        ]));
        $normalized = $request->toArray();
        self::assertInstanceOf(\stdClass::class, $normalized['exam_header']);
        self::assertInstanceOf(\stdClass::class, $normalized['formatting']);
        self::assertSame('{}', json_encode($normalized['exam_header']));
        self::assertSame('{}', json_encode($normalized['formatting']));
    }

    #[DataProvider('invalidPresentation')]
    public function testInvalidPresentationStopsBeforeAnyDatabaseOrProvider(array $overrides): void
    {
        try {
            ExamGenerationRequest::fromArray(ExamFixture::input($overrides));
            self::fail('Cabeçalho ou formatação inválidos devem ser rejeitados.');
        } catch (ExamGenerationException $exception) {
            self::assertSame('invalid_input', $exception->reason);
        }
    }

    public static function invalidPresentation(): array
    {
        return [
            'null header' => [['exam_header' => null]],
            'header text' => [['exam_header' => 'cabeçalho']],
            'header list' => [['exam_header' => ['escola']]],
            'empty header list' => [['exam_header' => []]],
            'unknown header field' => [['exam_header' => ['subject' => 'historia']]],
            'blank school' => [['exam_header' => ['school_name' => '   ']]],
            'numeric student' => [['exam_header' => ['student_name' => 12]]],
            'long school' => [['exam_header' => ['school_name' => str_repeat('é', 181)]]],
            'long teacher' => [['exam_header' => ['teacher_name' => str_repeat('é', 161)]]],
            'long student' => [['exam_header' => ['student_name' => str_repeat('é', 161)]]],
            'long class' => [['exam_header' => ['class_name' => str_repeat('é', 81)]]],
            'newline' => [['exam_header' => ['teacher_name' => "Ana\nMaria"]]],
            'tab' => [['exam_header' => ['class_name' => "6\tA"]]],
            'invalid UTF8' => [['exam_header' => ['school_name' => "\xff"]]],
            'null byte' => [['exam_header' => ['student_name' => "Nome\0Oculto"]]],
            'invalid day' => [['exam_header' => ['date' => '2026-04-31']]],
            'non leap date' => [['exam_header' => ['date' => '2026-02-29']]],
            'localized date' => [['exam_header' => ['date' => '20/10/2026']]],
            'date with time' => [['exam_header' => ['date' => '2026-10-20T00:00:00Z']]],
            'blank date' => [['exam_header' => ['date' => '']]],
            'numeric date' => [['exam_header' => ['date' => 20261020]]],
            'null formatting' => [['formatting' => null]],
            'formatting text' => [['formatting' => 'Arial']],
            'formatting list' => [['formatting' => ['Arial']]],
            'empty formatting list' => [['formatting' => []]],
            'unknown formatting field' => [['formatting' => ['color' => 'red']]],
            'font null' => [['formatting' => ['font_family' => null]]],
            'font blank' => [['formatting' => ['font_family' => ' ']]],
            'font too long' => [['formatting' => ['font_family' => str_repeat('á', 81)]]],
            'font newline' => [['formatting' => ['font_family' => "Arial\rCalibri"]]],
            'size null' => [['formatting' => ['font_size' => null]]],
            'size string' => [['formatting' => ['font_size' => '12']]],
            'size float' => [['formatting' => ['font_size' => 12.0]]],
            'size below min' => [['formatting' => ['font_size' => 7]]],
            'size above max' => [['formatting' => ['font_size' => 37]]],
        ];
    }
}
