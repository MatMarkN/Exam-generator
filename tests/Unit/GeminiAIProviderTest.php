<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\AI\AIErrorReason;
use App\Application\AI\AIProviderException;
use App\Application\AI\AIRequest;
use App\Application\Exams\ExamGenerationException;
use App\Application\Exams\ExamGenerationRequest;
use App\Application\Exams\ExamPromptBuilder;
use App\Application\Exams\ExamResponseValidator;
use App\Infrastructure\AI\GeminiAIProvider;
use App\Infrastructure\AI\Http\HttpResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\ExamFixture;
use Tests\Support\StubHttpTransport;

final class GeminiAIProviderTest extends TestCase
{
    public function testRequestUsesSystemInstructionStructuredJsonLimitsAndSecretHeader(): void
    {
        $http = new StubHttpTransport(StubHttpTransport::success());
        $request = $this->request();
        $result = (new GeminiAIProvider('test-key', http: $http))->generate($request);
        self::assertCount(1, $http->calls);
        $call = $http->calls[0];
        self::assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent', $call['url']);
        self::assertSame('test-key', $call['headers']['x-goog-api-key']);
        self::assertStringNotContainsString('test-key', $call['url'] . $call['json']);
        self::assertSame(17, $call['timeoutSeconds']);
        $payload = json_decode($call['json'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($request->instructions, $payload['systemInstruction']['parts'][0]['text']);
        self::assertSame($request->inputJson, $payload['contents'][0]['parts'][0]['text']);
        self::assertSame('user', $payload['contents'][0]['role']);
        self::assertSame(2048, $payload['generationConfig']['maxOutputTokens']);
        self::assertSame(1, $payload['generationConfig']['candidateCount']);
        self::assertSame('APPLICATION_JSON', $payload['generationConfig']['responseFormat']['text']['mimeType']);
        self::assertSame(json_decode($request->outputSchemaJson, true), $payload['generationConfig']['responseFormat']['text']['schema']);
        self::assertArrayNotHasKey('tools', $payload);
        self::assertArrayNotHasKey('temperature', $payload['generationConfig']);
        self::assertSame('{"received":true}', $result->json);
        self::assertSame('gemini', $result->provider);
        self::assertSame('gemini-3.5-flash-lite-fixture', $result->model);
        self::assertSame('synthetic-request', $result->providerRequestId);
        self::assertSame(120, $result->inputTokens);
        self::assertSame(40, $result->outputTokens);
        self::assertGreaterThanOrEqual(0, $result->durationMs);
    }

    public function testSplitsTextSkipsThoughtsAndKeepsUnknownUsageNull(): void
    {
        $envelope = $this->envelope();
        unset($envelope['modelVersion'], $envelope['responseId'], $envelope['usageMetadata']);
        $envelope['candidates'][0]['content']['parts'] = [
            ['text' => 'pensamento sintético ignorado', 'thought' => true],
            ['text' => '{"received":', 'thoughtSignature' => 'ignored'], ['text' => 'true}'],
        ];
        $result = $this->generate($envelope);
        self::assertTrue($result->data()->received);
        self::assertSame(GeminiAIProvider::DEFAULT_MODEL, $result->model);
        self::assertNull($result->inputTokens);
        self::assertNull($result->outputTokens);
        self::assertNull($result->providerRequestId);
    }

    #[DataProvider('finishReasons')]
    public function testIncompleteRefusedOrUnknownFinishCannotReturnAnExam(?string $finish, AIErrorReason $expected): void
    {
        $envelope = $this->envelope();
        $envelope['candidates'][0]['finishReason'] = $finish;
        $this->assertFailure($envelope, $expected);
    }

    public static function finishReasons(): array
    {
        return [['MAX_TOKENS', AIErrorReason::IncompleteResponse], ['SAFETY', AIErrorReason::Refused],
            ['RECITATION', AIErrorReason::Refused], ['BLOCKLIST', AIErrorReason::Refused],
            ['PROHIBITED_CONTENT', AIErrorReason::Refused], ['SPII', AIErrorReason::Refused],
            ['IMAGE_SAFETY', AIErrorReason::Refused], [null, AIErrorReason::InvalidResponse],
            ['OTHER', AIErrorReason::InvalidResponse], ['MALFORMED_FUNCTION_CALL', AIErrorReason::InvalidResponse]];
    }

    public function testPromptBlockedWithoutAnyCandidateIsARefusal(): void
    {
        $this->assertFailure(['promptFeedback' => ['blockReason' => 'SAFETY']], AIErrorReason::Refused);
    }

    #[DataProvider('httpErrors')]
    public function testHttpFailuresAreControlledAndNeverRetried(int $status, AIErrorReason $reason, ?int $retry): void
    {
        $http = new StubHttpTransport(new HttpResponse($status, '{"error":{"message":"PRIVATE: test-key"}}', ['retry-after' => '12']));
        try {
            (new GeminiAIProvider('test-key', http: $http))->generate($this->request());
            self::fail('Era esperada uma falha.');
        } catch (AIProviderException $exception) {
            self::assertSame($reason, $exception->reason);
            self::assertSame($retry, $exception->retryAfterSeconds);
            self::assertStringNotContainsString('test-key', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertCount(1, $http->calls);
        }
    }

    public static function httpErrors(): array
    {
        return [[400, AIErrorReason::InvalidRequest, null], [401, AIErrorReason::AuthenticationFailed, null],
            [403, AIErrorReason::AuthenticationFailed, null], [402, AIErrorReason::BillingRequired, null],
            [404, AIErrorReason::UnsupportedFeature, null], [408, AIErrorReason::Timeout, null],
            [429, AIErrorReason::RateLimited, 12], [500, AIErrorReason::Unavailable, 12],
            [503, AIErrorReason::Unavailable, 12], [504, AIErrorReason::Timeout, null],
            [302, AIErrorReason::InvalidResponse, null], [204, AIErrorReason::InvalidResponse, null]];
    }

    public function testGoogleInvalidKeyReasonInsideBadRequestAndSafeDateRetryHints(): void
    {
        $http = new StubHttpTransport(new HttpResponse(400, '{"error":{"details":[{"reason":"API_KEY_INVALID"}]}}'));
        try {
            (new GeminiAIProvider('test-key', http: $http))->generate($this->request());
            self::fail('Chave inválida.');
        } catch (AIProviderException $exception) {
            self::assertSame(AIErrorReason::AuthenticationFailed, $exception->reason);
        }
        foreach ([['-1', null], ['private', null], ['9999999999', 86400], [gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT', 3600]] as [$header, $expected]) {
            $http->result = new HttpResponse(429, '{}', ['retry-after' => $header]);
            try {
                (new GeminiAIProvider('test-key', http: $http))->generate($this->request());
                self::fail('Limite de uso.');
            } catch (AIProviderException $exception) {
                if ($expected === 3600) {
                    self::assertGreaterThanOrEqual(3598, $exception->retryAfterSeconds);
                    self::assertLessThanOrEqual(3600, $exception->retryAfterSeconds);
                } else {
                    self::assertSame($expected, $exception->retryAfterSeconds);
                }
            }
        }
    }

    #[DataProvider('invalidEnvelopes')]
    public function testMalformedEnvelopeOrGeneratedJsonNeverPasses(string $body): void
    {
        $http = new StubHttpTransport(new HttpResponse(200, $body));
        $this->expectException(AIProviderException::class);
        (new GeminiAIProvider('test-key', http: $http))->generate($this->request());
    }

    public static function invalidEnvelopes(): array
    {
        $base = json_decode(StubHttpTransport::success()->body, true);
        $cases = ['{broken', '[]', '{}', str_repeat('x', 1048577)];
        foreach (['[]', 'null', '```json {"received":true} ```', '{"private":"unfinished', '{"large":"' . str_repeat('x', 131072) . '"}'] as $text) {
            $data = $base;
            $data['candidates'][0]['content']['parts'] = [['text' => $text]];
            $cases[] = json_encode($data);
        }
        foreach ([[], [['text' => 7]], [['functionCall' => ['name' => 'bad']]], [['text' => '{}', 'inlineData' => []]], [['thought' => true, 'text' => 'private']], [['thought' => 'false', 'text' => '{}']]] as $parts) {
            $data = $base;
            $data['candidates'][0]['content']['parts'] = $parts;
            $cases[] = json_encode($data);
        }
        foreach ([['candidates' => [$base['candidates'][0], $base['candidates'][0]]], ['candidates' => [null]],
            ['usageMetadata' => []], ['usageMetadata' => ['promptTokenCount' => -1]], ['usageMetadata' => ['candidatesTokenCount' => '10']],
            ['usageMetadata' => ['candidatesTokenCount' => PHP_INT_MAX, 'thoughtsTokenCount' => 1]],
            ['modelVersion' => 42], ['responseId' => "injected\nheader"], ['promptFeedback' => []], ['error' => ['message' => 'fail']]] as $replace) {
            $cases[] = json_encode(array_replace($base, $replace));
        }
        return array_map(static fn ($value) => [$value], $cases);
    }

    #[DataProvider('badConfiguration')]
    public function testConfigurationFailsBeforeHttp(string $key, string $model, AIErrorReason $reason): void
    {
        $http = new StubHttpTransport(StubHttpTransport::success());
        try {
            (new GeminiAIProvider($key, $model, $http))->generate($this->request());
            self::fail('Configuração inválida.');
        } catch (AIProviderException $exception) {
            self::assertSame($reason, $exception->reason);
            self::assertCount(0, $http->calls);
        }
    }

    public static function badConfiguration(): array
    {
        return [['', GeminiAIProvider::DEFAULT_MODEL, AIErrorReason::ConfigurationMissing],
            ["secret\nInjected: value", GeminiAIProvider::DEFAULT_MODEL, AIErrorReason::AuthenticationFailed],
            ['test-key', '../wrong?key=x', AIErrorReason::InvalidRequest]];
    }

    public function testTransportTimeoutPropagatesWithoutRetryAndDebugHidesKey(): void
    {
        $http = new StubHttpTransport(new AIProviderException(AIErrorReason::Timeout));
        $provider = new GeminiAIProvider('test-private-key', http: $http);
        self::assertStringNotContainsString('test-private-key', print_r($provider, true));
        try {
            $provider->generate($this->request());
            self::fail('Timeout esperado.');
        } catch (AIProviderException $exception) {
            self::assertSame(AIErrorReason::Timeout, $exception->reason);
            self::assertCount(1, $http->calls);
        }
    }

    public function testGeminiSchemaAdaptationPreservesOriginalAndLocalRulesStillRejectOversizeText(): void
    {
        $exam = ExamGenerationRequest::fromArray(ExamFixture::input());
        $request = (new ExamPromptBuilder())->build($exam, ExamFixture::context());
        $before = $request->outputSchemaJson;
        $data = ExamFixture::response();
        $data['exam']['title'] = str_repeat('a', 201);
        $http = new StubHttpTransport(StubHttpTransport::success(json_encode($data)));
        $response = (new GeminiAIProvider('test-key', http: $http))->generate($request);
        $sent = json_decode($http->calls[0]['json'], true)['generationConfig']['responseFormat']['text']['schema'];
        self::assertArrayNotHasKey('maxLength', $sent['properties']['exam']['properties']['title']);
        self::assertSame(3, $sent['properties']['exam']['properties']['questions']['minItems']);
        self::assertSame(5, $sent['properties']['exam']['properties']['questions']['items']['anyOf'][0]['properties']['alternatives']['minItems']);
        self::assertSame($before, $request->outputSchemaJson);
        self::assertSame(200, $request->outputSchema()->properties->exam->properties->title->maxLength);
        $this->expectException(ExamGenerationException::class);
        (new ExamResponseValidator())->validate($response, $request, $exam, ExamFixture::context());
    }

    public function testUnsupportedSchemaKeywordFailsBeforeNetworkInsteadOfSilentlyDroppingIt(): void
    {
        $http = new StubHttpTransport(StubHttpTransport::success());
        $schema = $this->request()->outputSchema();
        $schema->allOf = [(object) []];
        try {
            (new GeminiAIProvider('test-key', http: $http))->generate($this->request(json_encode($schema)));
            self::fail('Recurso não suportado.');
        } catch (AIProviderException $exception) {
            self::assertSame(AIErrorReason::UnsupportedFeature, $exception->reason);
            self::assertSame([], $http->calls);
        }
    }

    private function envelope(): array
    {
        return json_decode(StubHttpTransport::success()->body, true);
    }

    private function generate(array $envelope): \App\Application\AI\AIResponse
    {
        return (new GeminiAIProvider('test-key', http: new StubHttpTransport(new HttpResponse(200,
            json_encode($envelope, JSON_THROW_ON_ERROR)))))->generate($this->request());
    }

    private function assertFailure(array $envelope, AIErrorReason $reason): void
    {
        try {
            $this->generate($envelope);
            self::fail('A resposta deveria falhar.');
        } catch (AIProviderException $exception) {
            self::assertSame($reason, $exception->reason);
        }
    }

    private function request(?string $schema = null): AIRequest
    {
        return new AIRequest('Responda em JSON conforme as instruções confiáveis.', '{"teacher_notes":"Dados do professor"}',
            'receipt', $schema ?? '{"type":"object","properties":{"received":{"type":"boolean"}},"required":["received"],"additionalProperties":false}',
            maxOutputTokens: 2048, timeoutSeconds: 17);
    }
}
