<?php

declare(strict_types=1);

namespace App\Application\Exams;

use App\Application\AI\AIProviderInterface;
use App\Application\AI\AIProviderIdentity;
use App\Application\Bncc\BnccContextService;
use App\Infrastructure\Persistence\GenerationHistoryRepository;
use App\Infrastructure\Persistence\TeacherRepository;
use App\Application\Teachers\TeacherException;

final class ExamGenerationService
{
    public function __construct(
        private readonly BnccContextService $bncc,
        private readonly AIProviderInterface $provider,
        private readonly ExamPromptBuilder $promptBuilder = new ExamPromptBuilder(),
        private readonly ExamResponseValidator $validator = new ExamResponseValidator(),
        private readonly ExamBnccValidator $bnccValidator = new ExamBnccValidator(),
        private readonly ?GenerationHistoryRepository $history = null,
        private readonly GenerationCostEstimator $costs = new GenerationCostEstimator(),
        private readonly ?TeacherRepository $teachers = null,
    ) {
    }

    public function generate(array $input): ExamGenerationResult
    {
        $request = ExamGenerationRequest::fromArray($input);
        $teacher = null;
        if ($request->teacherId !== null) {
            if ($this->teachers === null) {
                throw new ExamGenerationException('invalid_input', 'A geração com teacher_id exige o repositório de professores.');
            }
            try {
                $teacher = $this->teachers->find($request->teacherId);
            } catch (TeacherException $exception) {
                if ($exception->reason !== 'not_found') {
                    throw $exception;
                }
                throw new ExamGenerationException('invalid_input', 'O professor informado não está cadastrado.');
            }
        }
        $start = hrtime(true);
        // Pedidos inválidos não são gerações. Se o registro inicial falhar, não chamamos a IA.
        $id = $this->history?->start($request, $this->provider instanceof AIProviderIdentity ? $this->provider->identity() : []);
        $stage = 'context';
        $response = null;
        $cost = $this->costs->estimate(null);
        if ($id !== null) {
            $this->log('generation_started', $id, $stage);
        }
        try {
            $context = $this->bncc->find($request->bnccQuery);
            $this->bnccValidator->validateContext($request, $context);
            $aiRequest = $this->promptBuilder->build($request, $context);
            if ($id !== null) {
                $this->history->prepared($id, $aiRequest, $context);
            }
            $stage = 'provider';
            // Consultas e gravações anteriores terminaram; não mantemos transação durante a IA.
            $response = $this->provider->generate($aiRequest);
            $cost = $this->costs->estimate($response);
            $stage = 'validation';
            if ($id !== null) {
                $this->history->received($id, $response, $cost);
            }
            $data = $this->validator->validate($response, $aiRequest, $request, $context);
            $bnccValidation = $this->bnccValidator->validateReferences($data, $request, $context);
            $this->bncc->assertUnchanged($request->bnccQuery, $context);

            $result = new ExamGenerationResult(
                exam: [...$data['exam'], 'subject' => $context['subject'], 'grade' => $context['grade'], 'topics' => $request->bnccQuery->topics,
                    ...$request->presentation->resolve($teacher, $context)],
                answerKey: $data['answer_key'],
                bnccContext: $context,
                generation: [
                    'schema_name' => $aiRequest->schemaName,
                    'prompt_version' => ExamPromptBuilder::VERSION,
                    'provider' => $response->provider,
                    'model' => $response->model,
                    'duration_ms' => $response->durationMs,
                    'input_tokens' => $response->inputTokens,
                    'output_tokens' => $response->outputTokens,
                    'provider_request_id' => $response->providerRequestId,
                    'request' => $request->toArray(),
                    'bncc_validation' => ['status' => 'passed', 'database_rechecked' => true, ...$bnccValidation],
                    'estimated_cost' => $cost,
                ],
            );
            if ($id === null) {
                return $result; // Uso didático em memória; a rota HTTP sempre injeta o histórico.
            }
            $stage = 'persistence';
            $saved = $this->history->complete($id, $result, $start);
            $this->log('generation_succeeded', $id, 'completed');
            return new ExamGenerationResult(['id' => $saved['exam_id'], ...$result->exam()], $result->answerKey(), $result->bnccContext(),
                [...$result->generation(), 'id' => $id, ...$saved, 'status' => 'succeeded']);
        } catch (\Throwable $exception) {
            if ($id === null) {
                throw $exception;
            }
            try {
                $this->history->fail($id, $exception, $stage, (int) ((hrtime(true) - $start) / 1_000_000), $response, $cost);
            } catch (\Throwable $recordingFailure) {
                // Preserva o erro original. Uma queda do banco pode deixar a tentativa como running.
                $this->log('generation_history_write_failed', $id, $stage, $recordingFailure);
            }
            $this->log('generation_failed', $id, $stage, $exception);
            throw new RecordedGenerationException($id, $exception);
        }
    }

    private function log(string $event, int $id, string $stage, ?\Throwable $exception = null): void
    {
        error_log(json_encode(['event' => $event, 'generation_id' => $id, 'stage' => $stage,
            'exception' => $exception === null ? null : $exception::class], JSON_THROW_ON_ERROR));
    }
}
