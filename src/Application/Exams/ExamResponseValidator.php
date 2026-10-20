<?php

declare(strict_types=1);

namespace App\Application\Exams;

use App\Application\AI\AIRequest;
use App\Application\AI\AIResponse;
use Opis\JsonSchema\CompliantValidator;

final class ExamResponseValidator
{
    public function validate(AIResponse $response, AIRequest $sentRequest, ExamGenerationRequest $request, array $context): array
    {
        $data = $response->data();
        // Schema canônico da aplicação, antes de adaptações do provedor. Nenhum schema vem da resposta.
        if (!(new CompliantValidator())->validate($data, $sentRequest->outputSchema())->isValid()) {
            $this->reject('schema');
        }

        $allowedCodes = array_column($context['skills'], 'code');
        $distribution = array_fill_keys(ExamGenerationRequest::QUESTION_TYPES, 0);
        $images = 0;
        foreach ($data->exam->questions as $index => $question) {
            $number = $index + 1;
            $answer = $data->answer_key[$index];
            if ($question->number !== $number) {
                $this->reject('question_numbering', $number);
            }
            if ($answer->question_number !== $number || $answer->type !== $question->type) {
                $this->reject('answer_key_mapping', $number);
            }
            if (array_diff($question->bncc_skills, $allowedCodes) !== []) {
                $this->reject('bncc_skills', $number);
            }
            ++$distribution[$question->type];
            if ($question->image_description !== null) {
                ++$images;
            }

            if ($question->type === 'multiple_choice') {
                $keys = array_column($question->alternatives, 'key');
                $texts = array_map(static fn ($option): string => trim($option->text), $question->alternatives);
                if ($keys !== range('A', chr(64 + $request->alternativesCount))
                    || count(array_unique($texts, SORT_STRING)) !== count($texts)
                    || !in_array($answer->correct_alternative, $keys, true)) {
                    $this->reject('alternatives', $number);
                }
            } elseif ($question->type === 'true_false') {
                $numbers = range(1, $request->trueFalseStatements);
                if (array_column($question->statements, 'number') !== $numbers
                    || array_column($answer->answers, 'statement_number') !== $numbers) {
                    $this->reject('true_false_statements', $number);
                }
            }
        }

        if ($request->questionDistribution !== null && $distribution !== $request->questionDistribution) {
            $this->reject('question_distribution');
        }
        if (($request->imageMode === 'none' && $images !== 0) || ($request->imageMode === 'required' && $images === 0)) {
            $this->reject('images');
        }
        return json_decode($response->json, true, 64, JSON_THROW_ON_ERROR);
    }

    private function reject(string $check, ?int $question = null): never
    {
        $details = ['check' => $check];
        if ($question !== null) {
            $details['question_number'] = $question;
        }
        throw new ExamGenerationException('invalid_exam_response', 'A resposta não atende ao formato ou às regras da prova.', $details);
    }
}
