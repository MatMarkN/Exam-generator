<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Exams\ExamGenerationException;
use App\Application\Exams\ExamGenerationRequest;
use App\Application\Exams\ExamPromptBuilder;
use App\Http\OpenApi\OpenApiDefinition;
use Examples\Demo\DemoAIProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../examples/demo/FractionsExam.php';
require_once __DIR__ . '/../../examples/demo/DemoAIProvider.php';

final class DemoAIProviderTest extends TestCase
{
    #[DataProvider('sizes')]
    public function testDemoSupportsLimitsAndAutomaticModeWithoutExternalCalls(int $alternatives, int $statements, string $mode): void
    {
        $input = [...OpenApiDefinition::examExample(), 'alternatives_count' => $alternatives, 'true_false_statements' => $statements, 'question_mode' => $mode];
        if ($mode === 'automatic') {
            unset($input['question_distribution']);
        }
        $request = (new ExamPromptBuilder())->build(ExamGenerationRequest::fromArray($input), []);
        $provider = new DemoAIProvider();
        $response = $provider->generate($request);
        $exam = json_decode($response->json, true);
        self::assertSame('fake', $response->provider);
        self::assertSame(0, $response->inputTokens);
        self::assertSame(0, $response->outputTokens);
        self::assertSame(['provider' => 'fake', 'model' => 'static-fixture-v1'], $provider->identity());
        self::assertCount($alternatives, $exam['exam']['questions'][0]['alternatives']);
        self::assertCount($statements, $exam['exam']['questions'][2]['statements']);
        self::assertCount($statements, $exam['answer_key'][2]['answers']);
    }

    public static function sizes(): array
    {
        return [[2, 1, 'manual'], [6, 10, 'manual'], [5, 3, 'automatic']];
    }

    public function testUnsupportedScenarioFailsExplicitlyInsteadOfPretendingToGenerateNewContent(): void
    {
        $request = ExamGenerationRequest::fromArray([...OpenApiDefinition::examExample(), 'image_mode' => 'required']);
        $this->expectException(ExamGenerationException::class);
        $this->expectExceptionMessage('A simulação usa o exemplo fixo');
        (new DemoAIProvider())->generate((new ExamPromptBuilder())->build($request, []));
    }

    public function testDemoConfigurationRefusesTheNormalDatabase(): void
    {
        $environment = getenv('APP_ENV');
        try {
            putenv('APP_ENV=demo');
            // DB_DATABASE continua gerador_de_provas_test: nem este banco pode receber o bootstrap demo.
            $this->expectException(\InvalidArgumentException::class);
            require __DIR__ . '/../../config/ai.php';
        } finally {
            putenv('APP_ENV=' . $environment);
        }
    }
}
