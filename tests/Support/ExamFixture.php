<?php

declare(strict_types=1);

namespace Tests\Support;

/** Questões e habilidades SINTÉTICAS exclusivas de testes; não representam uma prova revisada. */
final class ExamFixture
{
    public static function input(array $overrides = []): array
    {
        return array_replace([
            'subject' => 'matematica', 'grade' => 6, 'topics' => ['frações'], 'question_count' => 3,
            'question_mode' => 'manual', 'question_distribution' => ['multiple_choice' => 1, 'open_ended' => 1, 'true_false' => 1],
            'alternatives_count' => 5, 'true_false_statements' => 3,
        ], $overrides);
    }

    public static function response(int $alternatives = 5, int $statements = 3): array
    {
        $base = ['statement' => 'Enunciado SINTÉTICO.', 'bncc_skills' => ['EF06MA07'], 'image_description' => null];
        $options = [];
        foreach (range('A', chr(64 + $alternatives)) as $key) {
            $options[] = ['key' => $key, 'text' => 'Alternativa SINTÉTICA ' . $key . '.'];
        }
        $items = $answers = [];
        for ($number = 1; $number <= $statements; ++$number) {
            $items[] = ['number' => $number, 'text' => 'Afirmação SINTÉTICA ' . $number . '.'];
            $answers[] = ['statement_number' => $number, 'correct' => $number % 2 === 1];
        }
        return [
            'exam' => ['title' => 'Prova SINTÉTICA de teste', 'instructions' => 'Instruções SINTÉTICAS.', 'questions' => [
                [...$base, 'number' => 1, 'type' => 'multiple_choice', 'alternatives' => $options],
                [...$base, 'number' => 2, 'type' => 'open_ended'],
                [...$base, 'number' => 3, 'type' => 'true_false', 'statements' => $items],
            ]],
            'answer_key' => [
                ['question_number' => 1, 'type' => 'multiple_choice', 'correct_alternative' => 'A'],
                ['question_number' => 2, 'type' => 'open_ended', 'expected_answer' => 'Resposta SINTÉTICA.', 'grading_criteria' => ['Critério SINTÉTICO.']],
                ['question_number' => 3, 'type' => 'true_false', 'answers' => $answers],
            ],
        ];
    }

    public static function context(): array
    {
        return ['schema_version' => 'bncc-context-v1',
            'document' => ['id' => 1, 'revision' => 1, 'title' => 'Documento SINTÉTICO', 'version_label' => 'fixture',
                'sha256' => hash('sha256', 'synthetic-context'), 'status' => 'ready', 'page_count' => 40,
                'ready_at' => '2026-01-01 00:00:00', 'structured_at' => '2026-01-01 00:00:00', 'structure_profile' => 'synthetic'],
            'subject' => ['id' => 1, 'code' => 'matematica', 'name' => 'Matemática'],
            'grade' => ['id' => 6, 'year_number' => 6, 'name' => '6º ano', 'education_stage' => 'ensino_fundamental'],
            'area' => ['id' => 2, 'code' => 'matematica', 'name' => 'Matemática'], 'topics' => ['frações'],
            'skills' => [['id' => 11, 'code' => 'EF06MA07', 'description' => 'Frações em habilidade SINTÉTICA.',
                'grades' => [6], 'matches' => [['topic' => 'frações', 'fields' => ['description']]],
                'sources' => [['id' => 31, 'section_id' => 3, 'source_excerpt' => 'Trecho SINTÉTICO de origem.', 'page_start' => 5, 'page_end' => 6]]]],
            'competencies' => [['id' => 21, 'scope' => 'general', 'number' => 1, 'description' => 'Competência SINTÉTICA.',
                'source_section_id' => 3, 'page_start' => 2, 'page_end' => 2, 'source_excerpt' => 'Trecho SINTÉTICO de competência.']]];
    }
}
