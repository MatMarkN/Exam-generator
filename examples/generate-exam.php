<?php

declare(strict_types=1);

use App\Application\AI\AIProviderException;
use App\Application\Bncc\BnccContextService;
use App\Application\Bncc\BnccTopicMatcher;
use App\Application\Bncc\ImportException;
use App\Application\Exams\ExamGenerationException;
use App\Application\Exams\ExamGenerationRequest;
use App\Application\Exams\ExamGenerationService;
use App\Infrastructure\AI\FakeAIProvider;
use App\Infrastructure\Persistence\BnccContextRepository;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use App\Infrastructure\Persistence\GenerationHistoryRepository;
use Examples\Demo\DemoCurriculum;
use Examples\Demo\FractionsExam;

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/demo/DemoCurriculum.php';
require __DIR__ . '/demo/FractionsExam.php';

$phase = 'validar a configuração';
try {
    DemoCurriculum::assertEnvironment();
    if ($argc > 3 || (isset($argv[1]) && !preg_match('/\A[1-9][0-9]?\z/', $argv[1]))
        || (isset($argv[2]) && !preg_match('/\A[1-9][0-9]?\z/', $argv[2]))) {
        throw new InvalidArgumentException('Uso: php examples/generate-exam.php [alternativas: 2..6] [afirmações: 1..10]. Padrão: 5 e 3.');
    }
    $input = FractionsExam::input((int) ($argv[1] ?? 5), (int) ($argv[2] ?? 3));
    $settings = ExamGenerationRequest::fromArray($input);

    $phase = 'preparar o banco descartável';
    $database = (new DatabaseConnectionFactory(require dirname(__DIR__) . '/config/database.php'))->create();
    $input['bncc_document_id'] = DemoCurriculum::prepare($database);
    $bncc = new BnccContextService(new BnccContextRepository($database), new BnccTopicMatcher());
    $fake = new FakeAIProvider(json_encode(FractionsExam::response($settings), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

    $phase = 'consultar o contexto e gerar a prova simulada';
    $history = new GenerationHistoryRepository($database);
    $result = (new ExamGenerationService($bncc, $fake, history: $history))->generate($input);
    $sent = $fake->lastRequest() ?? throw new RuntimeException('O provedor não recebeu o pedido.');

    // A exportação só começa depois que o serviço validou a resposta completa.
    $artifacts = [
        'pedido.json' => $input,
        'contexto-bncc.json' => $result->bnccContext(),
        'requisicao-ia.json' => [
            'instructions' => $sent->instructions,
            'input' => $sent->input(),
            'schema_name' => $sent->schemaName,
            'output_schema' => $sent->outputSchema(),
            'max_output_tokens' => $sent->maxOutputTokens,
            'timeout_seconds' => $sent->timeoutSeconds,
        ],
        'prova.json' => $result->exam(),
        'gabarito.json' => $result->answerKey(),
        'geracao.json' => [
            ...$result->generation(),
            'demonstration' => ['synthetic_curriculum' => true, 'official_bncc' => false, 'provider_calls' => $fake->callCount()],
        ],
        'historico.json' => $history->find($result->generation()['id']),
    ];
    $encoded = [];
    foreach ($artifacts as $filename => $data) {
        $encoded[$filename] = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }

    $phase = 'exportar os arquivos da demonstração';
    $relative = 'storage/demo/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
    $directory = dirname(__DIR__) . '/' . $relative;
    if (!mkdir($directory, 0775, true)) {
        throw new RuntimeException('Não foi possível criar o diretório da demonstração.');
    }
    foreach ($encoded as $filename => $json) {
        if (file_put_contents($directory . '/' . $filename, $json, LOCK_EX) !== strlen($json)) {
            throw new RuntimeException('Não foi possível concluir a exportação.');
        }
    }

    echo json_encode([
        'status' => 'ok',
        'notice' => 'Demonstração com currículo SINTÉTICO e respostas escritas à mão. Nenhuma API de IA foi chamada.',
        'questions' => count($result->exam()['questions']),
        'alternatives_count' => $settings->alternativesCount,
        'true_false_statements' => $settings->trueFalseStatements,
        'selected_skills' => array_column($result->bnccContext()['skills'], 'code'),
        'provider_calls' => $fake->callCount(),
        'generation_id' => $result->generation()['id'],
        'exam_id' => $result->exam()['id'],
        'output_directory' => $relative,
        'files' => array_keys($artifacts),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
} catch (InvalidArgumentException | ExamGenerationException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit($exception instanceof ExamGenerationException && $exception->reason !== 'invalid_input' ? 1 : 2);
} catch (ImportException | AIProviderException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} catch (Throwable $exception) {
    // Não exibir a mensagem bruta de conexão nem configurações privadas.
    fwrite(STDERR, "Falha ao $phase. Confira o Compose de demonstração e o guia da etapa 10.\n");
    exit(1);
}
