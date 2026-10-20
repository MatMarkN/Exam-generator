<?php

declare(strict_types=1);

use App\Application\AI\AIProviderException;
use App\Application\Bncc\BnccContextService;
use App\Application\Bncc\BnccTopicMatcher;
use App\Application\Bncc\ImportException;
use App\Application\Exams\ExamGenerationException;
use App\Application\Exams\ExamGenerationRequest;
use App\Application\Exams\ExamGenerationService;
use App\Application\Exams\ExamPromptBuilder;
use App\Infrastructure\Persistence\BnccContextRepository;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use App\Infrastructure\Persistence\GenerationHistoryRepository;
use App\Application\Exams\GenerationCostEstimator;
use Examples\Demo\DemoCurriculum;
use Examples\Demo\FractionsExam;

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/demo/DemoCurriculum.php';
require __DIR__ . '/demo/FractionsExam.php';

if (($argv[1] ?? '') !== '--send') {
    echo "Demonstração Gemini preparada. Nenhuma chamada foi feita. Consulte docs/etapa-11-integracao-ia.md.\n";
    exit(0);
}
try {
    DemoCurriculum::assertEnvironment();
    if ($argc > 4 || (isset($argv[2]) && !preg_match('/\A[1-9][0-9]?\z/', $argv[2]))
        || (isset($argv[3]) && !preg_match('/\A[1-9][0-9]?\z/', $argv[3]))) {
        throw new InvalidArgumentException('Uso: generate-exam-gemini.php --send [alternativas: 2..6] [afirmações: 1..10].');
    }
    $input = FractionsExam::input((int) ($argv[2] ?? 5), (int) ($argv[3] ?? 3));
    $settings = ExamGenerationRequest::fromArray($input);
    if (trim(getenv('GEMINI_API_KEY') ?: '') === '') {
        throw new AIProviderException(\App\Application\AI\AIErrorReason::ConfigurationMissing);
    }
    $database = (new DatabaseConnectionFactory(require dirname(__DIR__) . '/config/database.php'))->create();
    $input['bncc_document_id'] = DemoCurriculum::prepare($database);
    $bncc = new BnccContextService(new BnccContextRepository($database), new BnccTopicMatcher());
    $provider = require dirname(__DIR__) . '/config/ai.php';
    $history = new GenerationHistoryRepository($database);
    $result = (new ExamGenerationService($bncc, $provider, history: $history,
        costs: new GenerationCostEstimator(require dirname(__DIR__) . '/config/generation-pricing.php')))->generate($input);
    // Reconstrução determinística a partir do mesmo snapshot retornado pelo serviço; sem nova consulta.
    $sent = (new ExamPromptBuilder())->build($settings, $result->bnccContext());
    $artifacts = [
        'pedido.json' => $input,
        'contexto-bncc.json' => $result->bnccContext(),
        'requisicao-ia.json' => ['instructions' => $sent->instructions, 'input' => $sent->input(),
            'schema_name' => $sent->schemaName, 'output_schema' => $sent->outputSchema(),
            'max_output_tokens' => $sent->maxOutputTokens, 'timeout_seconds' => $sent->timeoutSeconds],
        'prova.json' => $result->exam(),
        'gabarito.json' => $result->answerKey(),
        'geracao.json' => [...$result->generation(), 'demonstration' => [
            'synthetic_curriculum' => true, 'official_bncc' => false, 'provider_calls' => 1]],
        'historico.json' => $history->find($result->generation()['id']),
    ];
    $encoded = [];
    foreach ($artifacts as $name => $data) {
        $encoded[$name] = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
    $relative = 'storage/demo/gemini-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
    $directory = dirname(__DIR__) . '/' . $relative;
    if (!mkdir($directory, 0775, true)) {
        throw new RuntimeException();
    }
    foreach ($encoded as $name => $json) {
        if (file_put_contents($directory . '/' . $name, $json, LOCK_EX) !== strlen($json)) {
            throw new RuntimeException();
        }
    }
    echo json_encode(['status' => 'ok', 'notice' => 'IA real com currículo SINTÉTICO; exige revisão pedagógica.',
        'output_directory' => $relative, 'files' => array_keys($artifacts), 'generation' => $result->generation()],
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
} catch (InvalidArgumentException | ExamGenerationException | ImportException | AIProviderException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, "Falha na demonstração Gemini. Confira o guia da etapa 11.\n");
    exit(1);
}
