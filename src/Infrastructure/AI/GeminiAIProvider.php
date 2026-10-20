<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Application\AI\AIErrorReason;
use App\Application\AI\AIProviderException;
use App\Application\AI\AIProviderInterface;
use App\Application\AI\AIProviderIdentity;
use App\Application\AI\AIRequest;
use App\Application\AI\AIResponse;
use App\Application\AI\JsonObject;
use App\Infrastructure\AI\Http\CurlHttpTransport;
use App\Infrastructure\AI\Http\HttpResponse;
use App\Infrastructure\AI\Http\HttpTransportInterface;
use stdClass;

final class GeminiAIProvider implements AIProviderInterface, AIProviderIdentity
{
    public const DEFAULT_MODEL = 'gemini-3.5-flash-lite';

    public function __construct(
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $model = self::DEFAULT_MODEL,
        private readonly HttpTransportInterface $http = new CurlHttpTransport(),
    ) {
    }

    public function __debugInfo(): array
    {
        return ['provider' => 'gemini', 'model' => $this->model, 'apiKey' => '[REDACTED]'];
    }

    public function identity(): array
    {
        return ['provider' => 'gemini', 'model' => preg_match('/^gemini-[a-z0-9][a-z0-9.-]{0,120}$/D', $this->model) ? $this->model : null];
    }

    public function generate(AIRequest $request): AIResponse
    {
        if (trim($this->apiKey) === '') {
            throw new AIProviderException(AIErrorReason::ConfigurationMissing);
        }
        if (!preg_match('/^[A-Za-z0-9_-]{1,256}$/D', $this->apiKey)) {
            throw new AIProviderException(AIErrorReason::AuthenticationFailed);
        }
        if (!preg_match('/^gemini-[a-z0-9][a-z0-9.-]{0,120}$/D', $this->model)) {
            throw new AIProviderException(AIErrorReason::InvalidRequest);
        }

        $payload = json_encode([
            'systemInstruction' => ['parts' => [['text' => $request->instructions]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $request->inputJson]]]],
            'generationConfig' => [
                'candidateCount' => 1,
                'maxOutputTokens' => $request->maxOutputTokens,
                'responseFormat' => ['text' => [
                    'mimeType' => 'APPLICATION_JSON',
                    'schema' => GeminiOutputSchema::adapt($request->outputSchema()),
                ]],
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $start = hrtime(true);
        // Host fixo; chave em header, nunca na URL. Uma chamada, sem fallback ou retry.
        $response = $this->http->post(
            'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent',
            ['Content-Type' => 'application/json', 'Accept' => 'application/json', 'x-goog-api-key' => $this->apiKey],
            $payload,
            $request->timeoutSeconds,
        );
        $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);
        if ($response->status !== 200) {
            throw $this->httpError($response);
        }
        try {
            $envelope = JsonObject::decode($response->body, CurlHttpTransport::MAX_RESPONSE_BYTES);
        } catch (\InvalidArgumentException) {
            throw new AIProviderException(AIErrorReason::InvalidResponse);
        }
        if (isset($envelope->error)) {
            throw new AIProviderException(AIErrorReason::InvalidResponse);
        }
        if (isset($envelope->promptFeedback)) {
            if (!$envelope->promptFeedback instanceof stdClass) {
                throw new AIProviderException(AIErrorReason::InvalidResponse);
            }
            $blocked = $envelope->promptFeedback->blockReason ?? null;
            if ($blocked !== null && $blocked !== 'BLOCK_REASON_UNSPECIFIED') {
                throw new AIProviderException(AIErrorReason::Refused);
            }
        }
        $candidates = $envelope->candidates ?? null;
        if (!is_array($candidates) || count($candidates) !== 1 || !$candidates[0] instanceof stdClass) {
            throw new AIProviderException(AIErrorReason::InvalidResponse);
        }
        $candidate = $candidates[0];
        $finish = $candidate->finishReason ?? null;
        if ($finish === 'MAX_TOKENS') {
            throw new AIProviderException(AIErrorReason::IncompleteResponse);
        }
        if (in_array($finish, ['SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT', 'SPII',
            'IMAGE_SAFETY', 'IMAGE_PROHIBITED_CONTENT', 'IMAGE_RECITATION'], true)) {
            throw new AIProviderException(AIErrorReason::Refused);
        }
        if ($finish !== 'STOP' || !isset($candidate->content) || !$candidate->content instanceof stdClass
            || ($candidate->content->role ?? 'model') !== 'model' || !is_array($candidate->content->parts ?? null)) {
            throw new AIProviderException(AIErrorReason::InvalidResponse);
        }
        $json = '';
        foreach ($candidate->content->parts as $part) {
            if (!$part instanceof stdClass || (isset($part->thought) && !is_bool($part->thought))) {
                throw new AIProviderException(AIErrorReason::InvalidResponse);
            }
            if (($part->thought ?? false) === true) {
                continue;
            }
            if (!is_string($part->text ?? null)
                || array_diff(array_keys(get_object_vars($part)), ['text', 'thought', 'thoughtSignature']) !== []) {
                throw new AIProviderException(AIErrorReason::InvalidResponse);
            }
            $json .= $part->text;
        }
        $usage = $envelope->usageMetadata ?? new stdClass();
        if (!$usage instanceof stdClass) {
            throw new AIProviderException(AIErrorReason::InvalidResponse);
        }
        $inputTokens = self::tokenCount($usage, 'promptTokenCount');
        $candidatesTokens = self::tokenCount($usage, 'candidatesTokenCount');
        $thoughtsTokens = self::tokenCount($usage, 'thoughtsTokenCount');
        $outputTokens = $candidatesTokens;
        if ($candidatesTokens !== null && $thoughtsTokens !== null) {
            if ($candidatesTokens > PHP_INT_MAX - $thoughtsTokens) {
                throw new AIProviderException(AIErrorReason::InvalidResponse);
            }
            $outputTokens += $thoughtsTokens;
        }
        $model = $envelope->modelVersion ?? $this->model;
        $id = $envelope->responseId ?? null;
        if (!is_string($model) || ($id !== null && !is_string($id))) {
            throw new AIProviderException(AIErrorReason::InvalidResponse);
        }
        return new AIResponse($json, 'gemini', $model, $durationMs, $inputTokens, $outputTokens, $id);
    }

    private static function tokenCount(stdClass $usage, string $name): ?int
    {
        $value = $usage->$name ?? null;
        if ($value !== null && (!is_int($value) || $value < 0)) {
            throw new AIProviderException(AIErrorReason::InvalidResponse);
        }
        return $value;
    }

    private function httpError(HttpResponse $response): AIProviderException
    {
        $reason = match (true) {
            in_array($response->status, [401, 403], true) => AIErrorReason::AuthenticationFailed,
            $response->status === 402 => AIErrorReason::BillingRequired,
            $response->status === 429 => AIErrorReason::RateLimited,
            in_array($response->status, [408, 504], true) => AIErrorReason::Timeout,
            $response->status >= 500 && $response->status <= 599 => AIErrorReason::Unavailable,
            $response->status === 404 => AIErrorReason::UnsupportedFeature,
            $response->status >= 400 && $response->status <= 499 => AIErrorReason::InvalidRequest,
            default => AIErrorReason::InvalidResponse,
        };
        if ($response->status === 400 && strlen($response->body) <= CurlHttpTransport::MAX_RESPONSE_BYTES) {
            $data = json_decode($response->body, true, 32);
            $details = is_array($data) && is_array($data['error'] ?? null) ? ($data['error']['details'] ?? []) : [];
            foreach (is_array($details) ? $details : [] as $detail) {
                if (is_array($detail) && in_array($detail['reason'] ?? null, ['API_KEY_INVALID', 'API_KEY_EXPIRED'], true)) {
                    $reason = AIErrorReason::AuthenticationFailed;
                }
            }
        }
        $retry = null;
        if (in_array($reason, [AIErrorReason::RateLimited, AIErrorReason::Unavailable], true)) {
            $value = $response->headers['retry-after'] ?? '';
            if (is_string($value) && preg_match('/^[0-9]{1,10}$/D', $value)) {
                $retry = min(86400, (int) $value);
            } elseif (is_string($value) && ($date = \DateTimeImmutable::createFromFormat('!D, d M Y H:i:s \G\M\T', $value, new \DateTimeZone('UTC'))) !== false) {
                $retry = min(86400, max(0, $date->getTimestamp() - time()));
            }
        }
        return new AIProviderException($reason, $retry);
    }
}
