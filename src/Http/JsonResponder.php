<?php

declare(strict_types=1);

namespace App\Http;

use App\Application\AI\AIErrorReason;
use App\Application\AI\AIProviderException;
use App\Application\Bncc\ImportException;
use App\Application\Exams\ExamGenerationException;
use App\Application\Exams\RecordedGenerationException;
use App\Application\Teachers\TeacherException;
use App\Application\Auth\AuthException;
use App\Application\Exams\GenerationLimitException;
use Psr\Http\Message\ResponseInterface;

final class JsonResponder
{
    public static function run(ResponseInterface $response, callable $operation, int $status = 200, string $locationPrefix = '/bncc/documents/'): ResponseInterface
    {
        $generationId = null;
        try {
            try {
                $data = $operation();
            } catch (RecordedGenerationException $exception) {
                $generationId = $exception->generationId;
                throw $exception->failure;
            }
            if ($status === 201 && isset($data['id'])) {
                $response = $response->withHeader('Location', $locationPrefix . $data['id']);
            }
        } catch (AuthException $exception) {
            $status = $exception->status;
            $data = ['error' => ['code' => $exception->reason, 'message' => $exception->getMessage()]];
            if ($exception->retryAfterSeconds !== null) {
                $response = $response->withHeader('Retry-After', (string) $exception->retryAfterSeconds);
                $data['error']['retry_after_seconds'] = $exception->retryAfterSeconds;
            }
        } catch (GenerationLimitException $exception) {
            $status = 429;
            $response = $response->withHeader('Retry-After', (string) $exception->retryAfterSeconds);
            $data = ['error' => ['code' => $exception->reason, 'message' => $exception->getMessage(),
                'retry_after_seconds' => $exception->retryAfterSeconds, 'retryable' => true]];
        } catch (AIProviderException $exception) {
            $status = match ($exception->reason) {
                AIErrorReason::ConfigurationMissing, AIErrorReason::BillingRequired, AIErrorReason::Unavailable => 503,
                AIErrorReason::RateLimited => 429,
                AIErrorReason::Timeout => 504,
                AIErrorReason::Refused => 422,
                default => 502,
            };
            $data = ['error' => ['code' => 'ai_' . $exception->reason->value, 'message' => $exception->getMessage(),
                'retryable' => $exception->isTransient(), 'retry_after_seconds' => $exception->retryAfterSeconds]];
            if ($exception->retryAfterSeconds !== null) {
                $response = $response->withHeader('Retry-After', (string) $exception->retryAfterSeconds);
            }
        } catch (TeacherException $exception) {
            $status = $exception->status;
            $data = ['error' => ['code' => $exception->reason, 'message' => $exception->getMessage()]];
        } catch (ExamGenerationException $exception) {
            $status = $exception->reason === 'invalid_input' ? 422 : 502;
            $data = ['error' => ['code' => $exception->reason, 'message' => $exception->getMessage(), ...$exception->details]];
        } catch (ImportException $exception) {
            $status = match ($exception->reason) {
                'not_found' => 404,
                'duplicate_document', 'revision_conflict', 'stored_file_changed', 'invalid_storage_path',
                'already_structured', 'immutable_document', 'not_structured', 'review_incomplete',
                'curriculum_not_ready', 'document_not_ready', 'curriculum_changed', 'pdf_processing_busy' => 409,
                'file_too_large', 'payload_too_large' => 413,
                'unsupported_media_type' => 415,
                'extraction_timeout' => 504,
                default => 422,
            };
            $data = ['error' => ['code' => $exception->reason, 'message' => $exception->getMessage(), ...$exception->details]];
            if ($exception->reason === 'pdf_processing_busy') {
                $response = $response->withHeader('Retry-After', '5');
            }
        } catch (\Throwable $exception) {
            error_log('request_failed exception=' . $exception::class);
            $status = $exception instanceof \PDOException ? 503 : 500;
            $data = ['error' => ['code' => 'import_unavailable', 'message' => 'Não foi possível concluir a operação. Tente novamente.']];
        }
        if ($generationId !== null) {
            $data['error']['generation_id'] = $generationId;
        }
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');
    }
}
