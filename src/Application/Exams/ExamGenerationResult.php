<?php

declare(strict_types=1);

namespace App\Application\Exams;

/** Os acessos separam aluno e correção; a geração persistida acrescenta IDs e metadados. */
final readonly class ExamGenerationResult
{
    public function __construct(
        private array $exam,
        private array $answerKey,
        private array $bnccContext,
        private array $generation,
    ) {
    }

    public function exam(): array
    {
        return $this->exam;
    }

    public function answerKey(): array
    {
        return $this->answerKey;
    }

    public function bnccContext(): array
    {
        return $this->bnccContext;
    }

    public function generation(): array
    {
        return $this->generation;
    }
}
