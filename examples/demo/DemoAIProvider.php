<?php

declare(strict_types=1);

namespace Examples\Demo;

use App\Application\AI\AIProviderIdentity;
use App\Application\AI\AIProviderInterface;
use App\Application\AI\AIRequest;
use App\Application\AI\AIResponse;
use App\Application\Exams\ExamGenerationException;
use App\Application\Exams\ExamGenerationRequest;
use App\Infrastructure\AI\FakeAIProvider;

/** Adaptador do exemplo fixo para a API demo. Não interpreta texto nem acessa a rede. */
final class DemoAIProvider implements AIProviderInterface, AIProviderIdentity
{
    public function identity(): array
    {
        return ['provider' => 'fake', 'model' => 'static-fixture-v1'];
    }

    public function generate(AIRequest $request): AIResponse
    {
        $input = json_decode($request->inputJson, true, flags: JSON_THROW_ON_ERROR)['teacher_request'];
        if ($input['question_mode'] === 'automatic') {
            unset($input['question_distribution']);
        }
        $exam = ExamGenerationRequest::fromArray($input);
        if ($exam->bnccQuery->subject !== 'matematica' || $exam->bnccQuery->grade !== 6
            || $exam->bnccQuery->topics !== ['frações'] || $exam->questionCount !== 3
            || $exam->imageMode !== 'none'
            || ($exam->questionDistribution !== null && $exam->questionDistribution !== [
                'multiple_choice' => 1, 'open_ended' => 1, 'true_false' => 1,
            ])) {
            throw new ExamGenerationException('invalid_input',
                'A simulação usa o exemplo fixo: matematica, 6º ano, tópico frações, 3 questões (uma de cada tipo) e image_mode none. '
                . 'Você pode variar alternatives_count de 2 a 6 e true_false_statements de 1 a 10.');
        }
        return (new FakeAIProvider(json_encode(FractionsExam::response($exam), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)))
            ->generate($request);
    }
}
