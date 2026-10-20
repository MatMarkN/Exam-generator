<?php

declare(strict_types=1);

namespace App\Http\Actions;

use App\Application\AI\AIProviderInterface;
use App\Application\Auth\AuthenticatedUser;
use App\Application\Bncc\BnccContextService;
use App\Application\Bncc\BnccTopicMatcher;
use App\Application\Bncc\ImportException;
use App\Application\Exams\ExamGenerationRequest;
use App\Application\Exams\ExamGenerationService;
use App\Application\Exams\GenerationCostEstimator;
use App\Application\Exams\GenerationGuard;
use App\Http\JsonResponder;
use App\Infrastructure\Persistence\BnccContextRepository;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use App\Infrastructure\Persistence\GenerationHistoryRepository;
use App\Infrastructure\Persistence\GenerationQuotaRepository;
use App\Infrastructure\Persistence\TeacherRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ExamGenerationAction
{
    public function __construct(
        private readonly DatabaseConnectionFactory $connections,
        private readonly AIProviderInterface $provider,
        private readonly GenerationCostEstimator $costs = new GenerationCostEstimator(),
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponder::run($response, function () use ($request): array {
            $user = AuthenticatedUser::fromRequest($request);
            if (strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0])) !== 'application/json') {
                throw new ImportException('unsupported_media_type', 'Envie o pedido como application/json.');
            }
            $raw = $request->getBody()->read(65537);
            if (strlen($raw) > 65536) {
                throw new ImportException('payload_too_large', 'O pedido deve ter no máximo 64 KiB.');
            }
            try {
                $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new ImportException('invalid_input', 'JSON de pedido inválido.');
            }
            if (!is_array($input) || array_is_list($input)) {
                throw new ImportException('invalid_input', 'Envie um objeto JSON com a configuração da prova.');
            }
            // Distingue os objetos {} das listas [] nesses campos opcionais.
            $objects = json_decode($raw, false, 32, JSON_THROW_ON_ERROR);
            foreach (['exam_header', 'formatting'] as $field) {
                if (array_key_exists($field, $input)) {
                    if (!$objects->{$field} instanceof \stdClass) {
                        throw new ImportException('invalid_input', "$field deve ser um objeto JSON.");
                    }
                    $input[$field] = $objects->{$field};
                }
            }
            // Valida antes de abrir a conexão; o serviço mantém a mesma proteção para usos via CLI.
            ExamGenerationRequest::fromArray($input);
            $database = $this->connections->create();
            $bncc = new BnccContextService(new BnccContextRepository($database), new BnccTopicMatcher());
            $result = (new ExamGenerationService($bncc, $this->provider,
                history: new GenerationHistoryRepository($database, $user->id), costs: $this->costs,
                teachers: new TeacherRepository($database, $user->id),
                guard: new GenerationGuard(new GenerationQuotaRepository($database), $user->id,
                    require __DIR__ . '/../../../config/generation-limits.php')))->generate($input);
            return ['exam' => $result->exam(), 'answer_key' => $result->answerKey(),
                'bncc_context' => $result->bnccContext(), 'generation' => $result->generation()];
        });
    }
}
