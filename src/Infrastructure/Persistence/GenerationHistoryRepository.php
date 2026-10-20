<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\AI\AIProviderException;
use App\Application\AI\AIRequest;
use App\Application\AI\AIResponse;
use App\Application\Bncc\ImportException;
use App\Application\Exams\ExamGenerationException;
use App\Application\Exams\ExamGenerationRequest;
use App\Application\Exams\ExamGenerationResult;
use Medoo\Medoo;

final class GenerationHistoryRepository
{
    /** userId null is reserved for trusted CLI/maintenance callers, never an HTTP request. */
    public function __construct(private readonly Medoo $database, private readonly ?int $userId = null)
    {
    }

    public function start(ExamGenerationRequest $request, array $identity): int
    {
        $teacher = null;
        if ($request->teacherId !== null) {
            $teacher = $this->database->get('teachers', ['id', 'name'], ['id' => $request->teacherId,
                ...($this->userId === null ? [] : ['user_id' => $this->userId])])
                ?? throw new ExamGenerationException('invalid_input', 'O professor informado não está cadastrado.');
        }
        $this->database->insert('generation_history', [
            'user_id' => $this->userId,
            'teacher_id' => $teacher['id'] ?? null, 'requester_name' => $teacher['name'] ?? null,
            'subject' => $request->bnccQuery->subject, 'grade' => $request->bnccQuery->grade,
            'question_count' => $request->questionCount,
            'request_json' => self::json([...$request->toArray(), 'teacher_id' => $request->teacherId,
                'bncc_document_id' => $request->bnccQuery->documentId, 'context_limit' => $request->bnccQuery->limit]),
            'provider' => $identity['provider'] ?? null, 'requested_model' => $identity['model'] ?? null,
        ]);
        return (int) $this->database->id();
    }

    public function prepared(int $id, AIRequest $request, array $context): void
    {
        $this->updateRunning($id, [
            'stage' => 'provider', 'bncc_document_id' => $context['document']['id'], 'bncc_revision' => $context['document']['revision'],
            // Strings preservam o pedido canônico exato, inclusive a ordem das chaves no contexto.
            'ai_request_json' => self::json(['instructions' => $request->instructions, 'input_json' => $request->inputJson,
                'schema_name' => $request->schemaName, 'output_schema_json' => $request->outputSchemaJson,
                'max_output_tokens' => $request->maxOutputTokens, 'timeout_seconds' => $request->timeoutSeconds]),
        ]);
    }

    public function received(int $id, AIResponse $response, array $cost): void
    {
        $this->updateRunning($id, ['stage' => 'validation', ...$this->responseFields($response), 'cost_json' => self::json($cost)]);
    }

    /** Prova, gabarito e sucesso são confirmados juntos. Nenhum HTTP ocorre nesta transação. */
    public function complete(int $id, ExamGenerationResult $result, int $startedAt): array
    {
        $pdo = $this->database->pdo;
        $pdo->beginTransaction();
        try {
            $status = $this->database->query('SELECT status FROM generation_history WHERE id = :id FOR UPDATE', [':id' => $id])->fetchColumn();
            if ($status !== 'running') {
                throw new \LogicException('A geração não está disponível para conclusão.');
            }
            $this->database->insert('exams', ['generation_id' => $id, 'exam_json' => self::json($result->exam())]);
            $examId = (int) $this->database->id();
            $this->database->insert('exam_answers', ['exam_id' => $examId, 'answer_key_json' => self::json($result->answerKey())]);
            $metadata = $result->generation();
            unset($metadata['request']); // Já está no pedido; o contexto completo está no prompt.
            $totalMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);
            $this->updateRunning($id, ['status' => 'succeeded', 'stage' => 'completed',
                'finished_at' => gmdate('Y-m-d H:i:s'), 'total_duration_ms' => $totalMs,
                'result_metadata_json' => self::json($metadata), 'rejected_response_json' => null]);
            $pdo->commit();
            return ['exam_id' => $examId, 'total_duration_ms' => $totalMs];
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function fail(int $id, \Throwable $exception, string $stage, int $totalMs, ?AIResponse $response, array $cost): void
    {
        $code = 'generation_unavailable';
        $message = 'Não foi possível concluir a geração. Confira os logs técnicos pelo identificador.';
        if ($exception instanceof AIProviderException) {
            $code = 'ai_' . $exception->reason->value;
            $message = $exception->getMessage();
        } elseif ($exception instanceof ImportException || $exception instanceof ExamGenerationException
            || $exception instanceof \App\Application\Exams\GenerationLimitException) {
            $code = $exception->reason;
            $message = $exception->getMessage();
        }
        $this->updateRunning($id, ['status' => 'failed', 'stage' => $stage, 'finished_at' => gmdate('Y-m-d H:i:s'),
            'total_duration_ms' => $totalMs, 'error_code' => $code, 'error_message' => $message,
            ...($response === null ? [] : $this->responseFields($response)), 'cost_json' => self::json($cost)]);
    }

    public function listing(array $query): array
    {
        if (array_diff(array_keys($query), ['limit', 'before_id', 'status', 'teacher_id']) !== []) {
            throw new ExamGenerationException('invalid_input', 'Filtro de histórico não reconhecido.');
        }
        $limit = array_key_exists('limit', $query) ? self::positiveId($query['limit'], 100) : 20;
        $clauses = [];
        $bindings = [];
        if ($this->userId !== null) {
            $clauses[] = 'g.user_id = :owner';
            $bindings[':owner'] = $this->userId;
        }
        foreach (['before_id' => 'g.id < :before_id', 'teacher_id' => 'g.teacher_id = :teacher_id'] as $key => $clause) {
            if (array_key_exists($key, $query)) {
                $bindings[':' . $key] = self::positiveId($query[$key]);
                $clauses[] = $clause;
            }
        }
        if (array_key_exists('status', $query)) {
            if (!in_array($query['status'], ['running', 'succeeded', 'failed'], true)) {
                throw new ExamGenerationException('invalid_input', 'status deve ser running, succeeded ou failed.');
            }
            $clauses[] = 'g.status = :status';
            $bindings[':status'] = $query['status'];
        }
        $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);
        // O limite é validado e os valores dos filtros são parâmetros. A listagem não lê prompts nem respostas.
        $rows = $this->database->query('SELECT g.id, g.user_id, g.teacher_id, g.requester_name, g.status, g.stage,
            g.started_at, g.finished_at, g.subject, g.grade, g.question_count,
            JSON_EXTRACT(g.request_json, \'$.topics\') AS topics_json,
            g.provider, g.requested_model, g.model, g.bncc_document_id, g.bncc_revision,
            g.input_tokens, g.output_tokens, g.provider_duration_ms, g.total_duration_ms, g.cost_json,
            g.error_code, e.id AS exam_id FROM generation_history g LEFT JOIN exams e ON e.generation_id = g.id'
            . $where . ' ORDER BY g.id DESC LIMIT ' . ($limit + 1), $bindings)->fetchAll(\PDO::FETCH_ASSOC);
        $hasMore = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        $items = array_map(fn (array $row): array => $this->decode($row), $rows);
        return ['items' => $items, 'next_before_id' => $hasMore ? end($items)['id'] : null];
    }

    public function find(int $id): array
    {
        // Uma leitura evita misturar um status antigo com uma prova confirmada entre duas consultas.
        $row = $this->database->query('SELECT g.*, e.id AS exam_id, e.exam_json FROM generation_history g
            LEFT JOIN exams e ON e.generation_id = g.id WHERE g.id = :id'
            . ($this->userId === null ? '' : ' AND g.user_id = :owner'),
            [':id' => $id, ...($this->userId === null ? [] : [':owner' => $this->userId])])->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ImportException('not_found', 'Geração não encontrada.');
        }
        $examJson = $row['exam_json'];
        unset($row['exam_json']);
        $result = $this->decode($row);
        $result['response'] = null;
        $result['response_format'] = 'unavailable';
        if ($result['status'] === 'succeeded' && $examJson !== null) {
            $result['response'] = ['exam' => json_decode($examJson, true, flags: JSON_THROW_ON_ERROR),
                'answer_key' => $this->answerKey($result['exam_id'])['answer_key']];
            $result['response_format'] = 'validated';
        } elseif ($result['rejected_response_json'] !== null) {
            // String exata, com limite de 128 KiB. Não é uma prova aprovada nem um envelope HTTP do fornecedor.
            $result['response'] = $result['rejected_response_json'];
            $result['response_format'] = 'unvalidated_json';
        }
        unset($result['rejected_response_json']);
        return $result;
    }

    public function exam(int $id): array
    {
        // Não seleciona gabarito, pedido ou prompt na rota destinada à prova do aluno.
        $row = $this->database->query('SELECT e.id, e.generation_id, e.created_at, e.exam_json FROM exams e
            INNER JOIN generation_history g ON g.id = e.generation_id WHERE e.id = :id'
            . ($this->userId === null ? '' : ' AND g.user_id = :owner'),
            [':id' => $id, ...($this->userId === null ? [] : [':owner' => $this->userId])])->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ImportException('not_found', 'Prova não encontrada.');
        }
        return ['exam' => ['id' => $row['id'], ...json_decode($row['exam_json'], true, flags: JSON_THROW_ON_ERROR)],
            'generation_id' => $row['generation_id'], 'created_at' => $this->utc($row['created_at'])];
    }

    public function answerKey(int $id): array
    {
        $json = $this->database->query('SELECT a.answer_key_json FROM exam_answers a
            INNER JOIN exams e ON e.id = a.exam_id INNER JOIN generation_history g ON g.id = e.generation_id
            WHERE a.exam_id = :id' . ($this->userId === null ? '' : ' AND g.user_id = :owner'),
            [':id' => $id, ...($this->userId === null ? [] : [':owner' => $this->userId])])->fetchColumn();
        if ($json === false) {
            throw new ImportException('not_found', 'Gabarito não encontrado.');
        }
        return ['exam_id' => $id, 'answer_key' => json_decode($json, true, flags: JSON_THROW_ON_ERROR)];
    }

    public static function positiveId(mixed $value, int $max = 4294967295): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]{0,9}$/D', (string) $value)
            || (int) $value > $max) {
            throw new ExamGenerationException('invalid_input', 'Identificadores e limites devem ser inteiros positivos dentro do intervalo permitido.');
        }
        return (int) $value;
    }

    private function responseFields(AIResponse $response): array
    {
        return ['provider' => $response->provider, 'model' => $response->model,
            'rejected_response_json' => $response->json, 'response_sha256' => hash('sha256', $response->json),
            'provider_request_id' => $response->providerRequestId, 'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens, 'provider_duration_ms' => $response->durationMs];
    }

    private function updateRunning(int $id, array $fields): void
    {
        if ($this->database->update('generation_history', $fields, ['id' => $id, 'status' => 'running'])->rowCount() !== 1) {
            throw new \LogicException('A geração não está disponível para atualização.');
        }
    }

    private function decode(array $row): array
    {
        foreach (['request_json' => 'request', 'topics_json' => 'topics', 'ai_request_json' => 'ai_request',
            'cost_json' => 'estimated_cost', 'result_metadata_json' => 'result_metadata'] as $column => $key) {
            if (array_key_exists($column, $row)) {
                $row[$key] = $row[$column] === null ? null : json_decode($row[$column], true, flags: JSON_THROW_ON_ERROR);
                if ($key === 'request' && is_array($row[$key])) {
                    // O decode associativo representa {} e [] como o mesmo array vazio.
                    // Estes campos são validados como objetos antes da gravação.
                    foreach (['exam_header', 'formatting'] as $field) {
                        if (array_key_exists($field, $row[$key]) && $row[$key][$field] === []) {
                            $row[$key][$field] = new \stdClass();
                        }
                    }
                }
                unset($row[$column]);
            }
        }
        $row['requester_verified'] = ($row['user_id'] ?? null) !== null;
        unset($row['user_id']);
        $row['started_at'] = $this->utc($row['started_at']);
        $row['finished_at'] = $row['finished_at'] === null ? null : $this->utc($row['finished_at']);
        return $row;
    }

    private function utc(string $date): string
    {
        return str_replace(' ', 'T', $date) . 'Z';
    }

    private static function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
