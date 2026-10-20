<?php

declare(strict_types=1);

namespace App\Application\Exams;

/** Schema canônico do pedido e da validação; adaptadores podem enviar um subconjunto sem alterá-lo. */
final class ExamOutputSchema
{
    public const NAME = 'exam_generation_v1';

    public static function json(ExamGenerationRequest $request): string
    {
        $number = ['type' => 'integer', 'minimum' => 1, 'maximum' => $request->questionCount];
        $statementNumber = ['type' => 'integer', 'minimum' => 1, 'maximum' => $request->trueFalseStatements];
        $keys = range('A', chr(64 + $request->alternativesCount));
        $base = ['number' => $number, 'statement' => self::text(4000),
            'bncc_skills' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 30, 'uniqueItems' => true,
                'items' => ['type' => 'string', 'pattern' => '^EF[0-9]{2}[A-Z]{2}[0-9]{2}$']],
            'image_description' => ['anyOf' => [self::text(1000), ['type' => 'null']]]];
        $questions = ['anyOf' => [
            self::object([...$base, 'type' => ['type' => 'string', 'enum' => ['multiple_choice']],
                'alternatives' => self::list(self::object([
                    'key' => ['type' => 'string', 'enum' => $keys], 'text' => self::text(2000),
                ]), $request->alternativesCount)]),
            self::object([...$base, 'type' => ['type' => 'string', 'enum' => ['open_ended']]]),
            self::object([...$base, 'type' => ['type' => 'string', 'enum' => ['true_false']],
                'statements' => self::list(self::object([
                    'number' => $statementNumber, 'text' => self::text(2000),
                ]), $request->trueFalseStatements)]),
        ]];
        $answers = ['anyOf' => [
            self::object(['question_number' => $number, 'type' => ['type' => 'string', 'enum' => ['multiple_choice']],
                'correct_alternative' => ['type' => 'string', 'enum' => $keys]]),
            self::object(['question_number' => $number, 'type' => ['type' => 'string', 'enum' => ['open_ended']],
                'expected_answer' => self::text(4000),
                'grading_criteria' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 10, 'uniqueItems' => true,
                    'items' => self::text(1000)]]),
            self::object(['question_number' => $number, 'type' => ['type' => 'string', 'enum' => ['true_false']],
                'answers' => self::list(self::object([
                    'statement_number' => $statementNumber, 'correct' => ['type' => 'boolean'],
                ]), $request->trueFalseStatements)]),
        ]];
        return json_encode(self::object([
            'exam' => self::object(['title' => self::text(200), 'instructions' => self::text(2000),
                'questions' => self::list($questions, $request->questionCount)]),
            'answer_key' => self::list($answers, $request->questionCount),
        ]), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private static function object(array $properties): array
    {
        return ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties), 'additionalProperties' => false];
    }

    private static function list(array $items, int $count): array
    {
        return ['type' => 'array', 'items' => $items, 'minItems' => $count, 'maxItems' => $count];
    }

    private static function text(int $maxLength): array
    {
        return ['type' => 'string', 'minLength' => 1, 'maxLength' => $maxLength, 'pattern' => '\\S'];
    }
}
