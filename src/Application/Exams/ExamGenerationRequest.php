<?php

declare(strict_types=1);

namespace App\Application\Exams;

use App\Application\Bncc\BnccContextQuery;
use App\Application\Bncc\ImportException;

final readonly class ExamGenerationRequest
{
    public const QUESTION_TYPES = ['multiple_choice', 'open_ended', 'true_false'];

    private function __construct(
        public BnccContextQuery $bnccQuery,
        public int $questionCount,
        public string $questionMode,
        public ?array $questionDistribution,
        public int $alternativesCount,
        public int $trueFalseStatements,
        public string $imageMode,
        public string $teacherNotes,
        public string $referenceText,
        public ?int $teacherId,
        public ExamPresentation $presentation,
    ) {
    }

    public static function fromArray(array $input): self
    {
        $allowed = ['subject', 'grade', 'topics', 'bncc_document_id', 'context_limit', 'question_count',
            'question_mode', 'question_distribution', 'alternatives_count', 'true_false_statements',
            'image_mode', 'teacher_notes', 'reference_text', 'teacher_id', 'exam_header', 'formatting'];
        if (array_diff(array_keys($input), $allowed) !== []) {
            throw new ExamGenerationException('invalid_input', 'O pedido de geração contém campos não reconhecidos.');
        }

        $queryInput = array_intersect_key($input, array_flip(['subject', 'grade', 'topics']));
        if (array_key_exists('bncc_document_id', $input)) {
            $queryInput['document_id'] = $input['bncc_document_id'];
        }
        if (array_key_exists('context_limit', $input)) {
            $queryInput['limit'] = $input['context_limit'];
        }
        try {
            $query = BnccContextQuery::fromArray($queryInput);
        } catch (ImportException $exception) {
            throw new ExamGenerationException('invalid_input', $exception->getMessage());
        }

        $count = $input['question_count'] ?? null;
        if (!is_int($count) || $count < 1 || $count > 30) {
            throw new ExamGenerationException('invalid_input', 'question_count deve ser um inteiro entre 1 e 30.');
        }
        $mode = array_key_exists('question_mode', $input) ? $input['question_mode'] : 'automatic';
        if (!in_array($mode, ['manual', 'automatic'], true)) {
            throw new ExamGenerationException('invalid_input', 'question_mode deve ser manual ou automatic.');
        }
        $distribution = null;
        if ($mode === 'manual') {
            $distribution = $input['question_distribution'] ?? null;
            if (!is_array($distribution) || array_diff(array_keys($distribution), self::QUESTION_TYPES) !== []
                || array_diff(self::QUESTION_TYPES, array_keys($distribution)) !== []) {
                throw new ExamGenerationException('invalid_input', 'No modo manual, informe a quantidade de cada um dos três tipos em question_distribution.');
            }
            foreach ($distribution as $quantity) {
                if (!is_int($quantity) || $quantity < 0 || $quantity > 30) {
                    throw new ExamGenerationException('invalid_input', 'Cada quantidade da distribuição deve ser um inteiro entre 0 e 30.');
                }
            }
            if (array_sum($distribution) !== $count) {
                throw new ExamGenerationException('invalid_input', 'A soma da distribuição deve ser igual a question_count.');
            }
            $distribution = array_replace(array_fill_keys(self::QUESTION_TYPES, 0), $distribution);
        } elseif (array_key_exists('question_distribution', $input)) {
            throw new ExamGenerationException('invalid_input', 'Envie question_distribution somente no modo manual.');
        }

        $alternatives = array_key_exists('alternatives_count', $input) ? $input['alternatives_count'] : 4;
        $statements = array_key_exists('true_false_statements', $input) ? $input['true_false_statements'] : 1;
        if (!is_int($alternatives) || $alternatives < 2 || $alternatives > 6
            || !is_int($statements) || $statements < 1 || $statements > 10) {
            throw new ExamGenerationException('invalid_input', 'Use alternatives_count de 2 a 6 e true_false_statements de 1 a 10, ambos inteiros.');
        }
        $imageMode = array_key_exists('image_mode', $input) ? $input['image_mode'] : 'none';
        if (!in_array($imageMode, ['none', 'required', 'auto'], true)) {
            throw new ExamGenerationException('invalid_input', 'image_mode deve ser none, required ou auto.');
        }

        $teacherId = $input['teacher_id'] ?? null;
        if ($teacherId !== null && (!is_int($teacherId) || $teacherId < 1 || $teacherId > 4294967295)) {
            throw new ExamGenerationException('invalid_input', 'teacher_id deve identificar um professor cadastrado, com um inteiro positivo.');
        }
        return new self($query, $count, $mode, $distribution, $alternatives, $statements, $imageMode,
            self::text($input, 'teacher_notes', 8 * 1024), self::text($input, 'reference_text', 16 * 1024), $teacherId,
            ExamPresentation::fromInput($input));
    }

    /** Dados pedagógicos do pedido; as instruções da aplicação serão montadas separadamente. */
    public function toArray(): array
    {
        return [...$this->toPedagogicalArray(), ...$this->presentation->toArray()];
    }

    /** O provedor recebe apenas os dados necessários para produzir as questões. */
    public function toPedagogicalArray(): array
    {
        return [
            'subject' => $this->bnccQuery->subject,
            'grade' => $this->bnccQuery->grade,
            'topics' => $this->bnccQuery->topics,
            'question_count' => $this->questionCount,
            'question_mode' => $this->questionMode,
            'question_distribution' => $this->questionDistribution,
            'alternatives_count' => $this->alternativesCount,
            'true_false_statements' => $this->trueFalseStatements,
            'image_mode' => $this->imageMode,
            'teacher_notes' => $this->teacherNotes,
            'reference_text' => $this->referenceText,
        ];
    }

    private static function text(array $input, string $field, int $limit): string
    {
        $value = array_key_exists($field, $input) ? $input[$field] : '';
        if (!is_string($value) || strlen($value) > $limit || !preg_match('//u', $value)
            || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u', $value)) {
            throw new ExamGenerationException('invalid_input', "$field deve ser texto UTF-8 de até $limit bytes, sem caracteres de controle além de tabulação e quebras de linha.");
        }
        return trim($value);
    }
}
