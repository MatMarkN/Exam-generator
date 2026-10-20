<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\AI\AIResponse;
use App\Application\Exams\ExamGenerationException;
use App\Application\Exams\ExamGenerationRequest;
use App\Application\Exams\ExamPromptBuilder;
use App\Application\Exams\ExamResponseValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\ExamFixture;

final class ExamResponseValidatorTest extends TestCase
{
    #[DataProvider('formats')]
    public function testSchemaAndValidatorHonorBothConfigurableQuestionFormats(int $alternatives, int $statements): void
    {
        $result = $this->validate(ExamFixture::response($alternatives, $statements),
            ['alternatives_count' => $alternatives, 'true_false_statements' => $statements]);
        self::assertCount($alternatives, $result['exam']['questions'][0]['alternatives']);
        self::assertCount($statements, $result['exam']['questions'][2]['statements']);
        self::assertCount($statements, $result['answer_key'][2]['answers']);
        self::assertSame('A', $result['answer_key'][0]['correct_alternative']);
        self::assertArrayNotHasKey('correct_alternative', $result['exam']['questions'][0]);
    }

    public static function formats(): array
    {
        return [[2, 1], [5, 3], [6, 10]];
    }

    #[DataProvider('badOutputs')]
    public function testStructurallyOrLogicallyInvalidResponsesFailWithSafeErrors(array $path, mixed $value, string $check): void
    {
        $data = ExamFixture::response();
        $target = &$data;
        foreach ($path as $part) {
            $target = &$target[$part];
        }
        $target = $value;
        unset($target);
        $this->expectRejection($data, $check);
    }

    public static function badOutputs(): array
    {
        return [
            'unknown_root' => [['private_raw_response'], 'NAO_EXIBIR', 'schema'],
            'missing_key' => [['answer_key'], null, 'schema'],
            'wrong_total' => [['exam', 'questions'], [], 'schema'],
            'whitespace_statement' => [['exam', 'questions', 0, 'statement'], '   ', 'schema'],
            'answer_in_student_exam' => [['exam', 'questions', 0, 'correct_answer'], 'A', 'schema'],
            'wrong_type' => [['exam', 'questions', 0, 'type'], 'unknown', 'schema'],
            'wrong_choice_count' => [['exam', 'questions', 0, 'alternatives'], array_slice(ExamFixture::response()['exam']['questions'][0]['alternatives'], 0, 4), 'schema'],
            'repeated_choice_key' => [['exam', 'questions', 0, 'alternatives', 1, 'key'], 'A', 'alternatives'],
            'repeated_choice_text' => [['exam', 'questions', 0, 'alternatives', 1, 'text'], 'Alternativa SINTÉTICA A.', 'alternatives'],
            'choice_not_present' => [['answer_key', 0, 'correct_alternative'], 'F', 'schema'],
            'repeated_question_number' => [['exam', 'questions', 1, 'number'], 1, 'question_numbering'],
            'repeated_answer_number' => [['answer_key', 1, 'question_number'], 1, 'answer_key_mapping'],
            'wrong_answer_type' => [['answer_key', 0], ['question_number' => 1, 'type' => 'open_ended', 'expected_answer' => 'Resposta.', 'grading_criteria' => ['Critério.']], 'answer_key_mapping'],
            'missing_grading_criteria' => [['answer_key', 1, 'grading_criteria'], [], 'schema'],
            'unknown_bncc' => [['exam', 'questions', 0, 'bncc_skills'], ['EF06MA99'], 'bncc_skills'],
            'wrong_year_bncc' => [['exam', 'questions', 0, 'bncc_skills'], ['EF07MA07'], 'bncc_skills'],
            'repeated_bncc' => [['exam', 'questions', 0, 'bncc_skills'], ['EF06MA07', 'EF06MA07'], 'schema'],
            'empty_bncc' => [['exam', 'questions', 0, 'bncc_skills'], [], 'schema'],
            'wrong_statement_count' => [['exam', 'questions', 2, 'statements'], [], 'schema'],
            'repeated_statement_number' => [['exam', 'questions', 2, 'statements', 1, 'number'], 1, 'true_false_statements'],
            'repeated_statement_answer' => [['answer_key', 2, 'answers', 1, 'statement_number'], 1, 'true_false_statements'],
            'string_instead_of_boolean' => [['answer_key', 2, 'answers', 0, 'correct'], 'true', 'schema'],
            'missing_statement_answer' => [['answer_key', 2, 'answers'], [], 'schema'],
            'image_despite_none' => [['exam', 'questions', 0, 'image_description'], 'Figura SINTÉTICA.', 'images'],
        ];
    }

    public function testManualDistributionIsExactButAutomaticMayChooseAnyValidDistribution(): void
    {
        $data = ExamFixture::response();
        $this->expectRejection($data, 'question_distribution', ['question_distribution' => ['multiple_choice' => 3, 'open_ended' => 0, 'true_false' => 0]]);

        $input = ExamFixture::input(['question_mode' => 'automatic']);
        unset($input['question_distribution']);
        self::assertCount(3, $this->validate($data, input: $input)['exam']['questions']);
    }

    public function testRequiredAndAutomaticImagesOnlyPlanDescriptions(): void
    {
        $data = ExamFixture::response();
        $this->expectRejection($data, 'images', ['image_mode' => 'required']);
        self::assertNull($this->validate($data, ['image_mode' => 'auto'])['exam']['questions'][0]['image_description']);
        $data['exam']['questions'][0]['image_description'] = 'Figura SINTÉTICA a produzir depois.';
        self::assertSame($data, $this->validate($data, ['image_mode' => 'required']));
        self::assertSame($data, $this->validate($data, ['image_mode' => 'auto']));
    }

    private function validate(array $data, array $overrides = [], ?array $input = null): array
    {
        $request = ExamGenerationRequest::fromArray($input ?? ExamFixture::input($overrides));
        $context = ExamFixture::context();
        $sent = (new ExamPromptBuilder())->build($request, $context);
        $response = new AIResponse(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'fake', 'fixture', 0);
        return (new ExamResponseValidator())->validate($response, $sent, $request, $context);
    }

    private function expectRejection(array $data, string $check, array $overrides = []): void
    {
        try {
            $this->validate($data, $overrides);
            self::fail('A resposta deveria ser rejeitada.');
        } catch (ExamGenerationException $exception) {
            self::assertSame('invalid_exam_response', $exception->reason);
            self::assertSame($check, $exception->details['check']);
            self::assertStringNotContainsString('NAO_EXIBIR', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }
}
