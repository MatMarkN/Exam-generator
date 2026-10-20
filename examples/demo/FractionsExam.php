<?php

declare(strict_types=1);

namespace Examples\Demo;

use App\Application\Exams\ExamGenerationRequest;

/** Questões escritas à mão para o FakeAIProvider. Não há raciocínio de IA aqui. */
final class FractionsExam
{
    public static function input(int $alternatives, int $statements): array
    {
        return [
            'subject' => 'matematica', 'grade' => 6, 'topics' => ['frações'],
            'question_count' => 3, 'question_mode' => 'manual',
            'question_distribution' => ['multiple_choice' => 1, 'open_ended' => 1, 'true_false' => 1],
            'alternatives_count' => $alternatives, 'true_false_statements' => $statements,
            'image_mode' => 'none',
            'teacher_notes' => 'Use linguagem clara, uma situação de partilha e uma comparação com justificativa. Esta é uma demonstração sintética.',
            'reference_text' => '',
        ];
    }

    public static function response(ExamGenerationRequest $request): array
    {
        // Apenas recortamos alternativas e afirmações previamente escritas conforme a configuração.
        $alternatives = [];
        foreach (array_slice(['1/2', '1/4', '3/4', '1/8', '3/8', '2/3'], 0, $request->alternativesCount) as $index => $text) {
            $alternatives[] = ['key' => chr(65 + $index), 'text' => $text];
        }
        $statements = [];
        $answers = [];
        foreach (array_slice([
            ['1/2 e 2/4 representam a mesma quantidade de um mesmo inteiro.', true],
            ['3/8 é maior que 1/2.', false],
            ['Entre frações de mesmo denominador positivo, a de maior numerador é maior.', true],
            ['1/3 e 1/4 são frações equivalentes.', false],
            ['2/3 é maior que 1/3.', true],
            ['4/6 pode ser simplificada para 1/3.', false],
            ['5/5 representa um inteiro.', true],
            ['2/5 é maior que 3/5.', false],
            ['3/4 é equivalente a 6/8.', true],
            ['1/2 é menor que 1/3.', false],
        ], 0, $request->trueFalseStatements) as $index => [$text, $correct]) {
            $statements[] = ['number' => $index + 1, 'text' => $text];
            $answers[] = ['statement_number' => $index + 1, 'correct' => $correct];
        }
        $common = ['bncc_skills' => ['EF06MA07'], 'image_description' => null];

        return [
            'exam' => [
                'title' => 'DEMONSTRAÇÃO SINTÉTICA — Frações — 6º ano',
                'instructions' => 'Material de teste com currículo sintético. Leia as questões. Marque uma alternativa na questão 1, justifique a questão 2 e classifique cada afirmação da questão 3 como verdadeira ou falsa.',
                'questions' => [
                    [...$common, 'number' => 1, 'type' => 'multiple_choice',
                        'statement' => 'Uma pizza foi dividida em 8 fatias iguais. Quatro fatias foram consumidas. Qual fração é equivalente à parte consumida?',
                        'alternatives' => $alternatives],
                    [...$common, 'number' => 2, 'type' => 'open_ended',
                        'statement' => 'Qual fração é maior: 3/4 ou 2/3? Justifique usando frações equivalentes com o mesmo denominador.'],
                    [...$common, 'number' => 3, 'type' => 'true_false',
                        'statement' => 'Classifique cada afirmação como verdadeira ou falsa.', 'statements' => $statements],
                ],
            ],
            'answer_key' => [
                ['question_number' => 1, 'type' => 'multiple_choice', 'correct_alternative' => 'A'],
                ['question_number' => 2, 'type' => 'open_ended',
                    'expected_answer' => '3/4 é maior. Como 3/4 = 9/12 e 2/3 = 8/12, comparamos 9/12 e 8/12 e concluímos que 3/4 > 2/3.',
                    'grading_criteria' => [
                        'Obtém frações equivalentes com o mesmo denominador, como 9/12 e 8/12.',
                        'Compara os numeradores e conclui que 3/4 é maior que 2/3, justificando a resposta.',
                    ]],
                ['question_number' => 3, 'type' => 'true_false', 'answers' => $answers],
            ],
        ];
    }
}
