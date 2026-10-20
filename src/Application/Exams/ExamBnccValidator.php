<?php

declare(strict_types=1);

namespace App\Application\Exams;

use App\Application\Bncc\ImportException;

/** Confere referências curriculares; não julga o significado dos enunciados ou a correção das respostas. */
final class ExamBnccValidator
{
    public const VERSION = 'exam-bncc-validation-v1';

    /** Executado antes do provedor para não gerar com origens incompletas ou incompatíveis. */
    public function validateContext(ExamGenerationRequest $request, array $context): void
    {
        $document = $context['document'];
        if ($context['subject']['code'] !== $request->bnccQuery->subject
            || $context['grade']['year_number'] !== $request->bnccQuery->grade
            || $context['grade']['education_stage'] !== 'ensino_fundamental'
            || $context['topics'] !== $request->bnccQuery->topics
            || ($request->bnccQuery->documentId !== null && $document['id'] !== $request->bnccQuery->documentId)
            || $document['status'] !== 'ready' || $document['structured_at'] === null || $document['ready_at'] === null
            || !is_int($document['page_count']) || $document['page_count'] < 1
            || $context['skills'] === [] || $context['competencies'] === []) {
            $this->invalidContext('context_identity');
        }
        $codes = $ids = [];
        foreach ($context['skills'] as $skill) {
            if (isset($codes[$skill['code']]) || isset($ids[$skill['id']])
                || !in_array($request->bnccQuery->grade, $skill['grades'], true)
                || preg_match('/\S/u', $skill['description']) !== 1 || $skill['sources'] === [] || $skill['matches'] === []) {
                $this->invalidContext('skill_context');
            }
            $codes[$skill['code']] = $ids[$skill['id']] = true;
            foreach ($skill['sources'] as $source) {
                $this->validateSource($source, $document['page_count']);
            }
        }
        foreach ($context['competencies'] as $competency) {
            if (preg_match('/\S/u', $competency['description']) !== 1) {
                $this->invalidContext('competency_context');
            }
            $this->validateSource($competency, $document['page_count']);
        }
    }

    /** Recebe a prova que já passou pelo JSON Schema e pelas regras estruturais da etapa 9. */
    public function validateReferences(array $examData, ExamGenerationRequest $request, array $context): array
    {
        $skills = array_column($context['skills'], null, 'code');
        $used = [];
        $questions = [];
        foreach ($examData['exam']['questions'] as $question) {
            $skillIds = [];
            foreach ($question['bncc_skills'] as $code) {
                if (!isset($skills[$code])) {
                    throw new ExamGenerationException('invalid_exam_response',
                        'A prova cita uma habilidade fora do contexto selecionado.',
                        ['check' => 'bncc_skills', 'question_number' => $question['number']]);
                }
                $skillIds[] = $skills[$code]['id'];
                $used[$code][] = $question['number'];
            }
            $questions[] = ['question_number' => $question['number'], 'skill_ids' => $skillIds];
        }
        $topicReferences = [];
        $missing = [];
        foreach ($request->bnccQuery->topics as $topic) {
            $topicCodes = $numbers = [];
            foreach ($context['skills'] as $skill) {
                if (isset($used[$skill['code']]) && in_array($topic, array_column($skill['matches'], 'topic'), true)) {
                    $topicCodes[] = $skill['code'];
                    array_push($numbers, ...$used[$skill['code']]);
                }
            }
            $numbers = array_values(array_unique($numbers));
            sort($numbers, SORT_NUMERIC);
            $topicReferences[] = ['topic' => $topic, 'skill_codes' => $topicCodes, 'question_numbers' => $numbers];
            if ($numbers === []) {
                $missing[] = $topic;
            }
        }
        if ($missing !== []) {
            throw new ExamGenerationException('invalid_exam_response',
                'A prova não cita habilidades selecionadas para todos os tópicos solicitados.',
                ['check' => 'bncc_topic_references', 'unrepresented_topics' => $missing]);
        }

        $skillReferences = [];
        foreach ($context['skills'] as $skill) {
            if (isset($used[$skill['code']])) {
                $skillReferences[] = ['id' => $skill['id'], 'code' => $skill['code'],
                    'question_numbers' => $used[$skill['code']], 'source_ids' => array_column($skill['sources'], 'id')];
            }
        }
        return [
            'version' => self::VERSION,
            'document_id' => $context['document']['id'],
            'document_revision' => $context['document']['revision'],
            'document_sha256' => $context['document']['sha256'],
            'context_sha256' => hash('sha256', json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'questions' => $questions,
            'skills' => $skillReferences,
            'topic_references' => $topicReferences,
            'unused_context_skill_codes' => array_values(array_diff(array_keys($skills), array_keys($used))),
            'semantic_review' => 'not_performed',
            'teacher_review_required' => true,
        ];
    }

    private function validateSource(array $source, int $pageCount): void
    {
        if (!is_int($source['page_start']) || !is_int($source['page_end'])
            || $source['page_start'] < 1 || $source['page_end'] < $source['page_start'] || $source['page_end'] > $pageCount
            || !is_string($source['source_excerpt']) || preg_match('/\S/u', $source['source_excerpt']) !== 1) {
            $this->invalidContext('source_provenance');
        }
    }

    private function invalidContext(string $check): never
    {
        throw new ImportException('invalid_curriculum',
            'O contexto curricular contém dados incompatíveis ou origens incompletas. Confira a revisão da BNCC.', ['check' => $check]);
    }
}
