<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\AI\AIResponse;
use App\Application\Exams\ExamGenerationException;
use App\Application\Exams\ExamGenerationRequest;
use App\Application\Exams\GenerationCostEstimator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\ExamFixture;

final class GenerationCostEstimatorTest extends TestCase
{
    public function testConfiguredRatesAreSnapshottedWithTheEstimate(): void
    {
        // Valores SINTÉTICOS de teste, sem relação com tarifas de qualquer fornecedor.
        $rates = ['input_usd_per_million' => '2.50', 'output_usd_per_million' => '10.00'];
        $estimator = new GenerationCostEstimator(['fixture' => ['model-v1' => $rates]]);
        $cost = $estimator->estimate(new AIResponse('{}', 'fixture', 'model-v1', 1, 1000, 200));
        self::assertSame('0.00450000', $cost['amount']);
        self::assertSame($rates, $cost['rates']);
        self::assertSame('configured_token_rates', $cost['basis']);
        self::assertSame('USD', $cost['currency']);
        self::assertNull($estimator->estimate(new AIResponse('{}', 'fixture', 'model-v2', 1, 1000, 200))['amount']);
        self::assertNull($estimator->estimate(new AIResponse('{}', 'another', 'model-v1', 1, 1000, 200))['amount']);
        self::assertSame('usage_unavailable', $estimator->estimate(new AIResponse('{}', 'fixture', 'model-v1', 1, null, 200))['basis']);
    }

    public function testUnknownAndZeroCostAreDifferent(): void
    {
        $estimator = new GenerationCostEstimator();
        self::assertNull($estimator->estimate(null)['amount']);
        self::assertSame('pricing_not_configured', $estimator->estimate(new AIResponse('{}', 'gemini', 'model', 0, 0, 0))['basis']);
        self::assertSame('simulated', $estimator->estimate(new AIResponse('{}', 'fake', 'fixture', 0, 0, 0))['basis']);
        $free = new GenerationCostEstimator(['fixture' => ['free' => ['input_usd_per_million' => 0, 'output_usd_per_million' => 0]]]);
        self::assertSame('0.00000000', $free->estimate(new AIResponse('{}', 'fixture', 'free', 0, 300, 200))['amount']);
    }

    #[DataProvider('invalidRates')]
    public function testInvalidRatesAreNotAccepted(mixed $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GenerationCostEstimator(['fixture' => ['model' => ['input_usd_per_million' => $value, 'output_usd_per_million' => 0]]]);
    }

    public static function invalidRates(): array
    {
        return [[-1], [10001], ['not a price'], [null], [INF], [NAN]];
    }

    #[DataProvider('invalidTeachers')]
    public function testTeacherIdentityMustBeAnOptionalPositiveInteger(mixed $id): void
    {
        $this->expectException(ExamGenerationException::class);
        ExamGenerationRequest::fromArray(ExamFixture::input(['teacher_id' => $id]));
    }

    public static function invalidTeachers(): array
    {
        return [[0], [-1], [4294967296], ['1'], [true], [1.5], [[]]];
    }

    public function testTeacherIdentityIsNotSentInPedagogicalInput(): void
    {
        $request = ExamGenerationRequest::fromArray(ExamFixture::input(['teacher_id' => 7]));
        self::assertSame(7, $request->teacherId);
        self::assertArrayNotHasKey('teacher_id', $request->toArray());
        self::assertNull(ExamGenerationRequest::fromArray(ExamFixture::input())->teacherId);
    }
}
