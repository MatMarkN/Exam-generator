<?php

declare(strict_types=1);

use App\Application\Exams\ExamGenerationException;
use App\Application\Exams\ExamGenerationRequest;
use App\Application\Exams\ExamOutputSchema;

require __DIR__ . '/../vendor/autoload.php';

if (count($argv) > 3) {
    fwrite(STDERR, 'Uso: php examples/exam-request.php [alternativas:2-6] [afirmacoes-vf:1-10]' . PHP_EOL);
    exit(2);
}

try {
    $request = ExamGenerationRequest::fromArray([
        'subject' => 'matematica',
        'grade' => 6,
        'topics' => ['frações'],
        'question_count' => 3,
        'question_mode' => 'manual',
        'question_distribution' => ['multiple_choice' => 1, 'open_ended' => 1, 'true_false' => 1],
        'alternatives_count' => filter_var($argv[1] ?? '5', FILTER_VALIDATE_INT),
        'true_false_statements' => filter_var($argv[2] ?? '3', FILTER_VALIDATE_INT),
        'teacher_notes' => 'Trabalhamos comparação de frações e frações equivalentes.',
    ]);
    echo json_encode([
        'mode' => 'preview_request_and_schema',
        'request' => $request->toArray(),
        'schema_name' => ExamOutputSchema::NAME,
        'output_schema' => json_decode(ExamOutputSchema::json($request), false, 64, JSON_THROW_ON_ERROR),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (ExamGenerationException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(2);
}
