<?php

declare(strict_types=1);

namespace App\Http\Actions;

use App\Application\Bncc\BnccContextQuery;
use App\Application\Bncc\BnccContextService;
use App\Application\Bncc\BnccTopicMatcher;
use App\Application\Bncc\ImportException;
use App\Http\JsonResponder;
use App\Infrastructure\Persistence\BnccContextRepository;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class BnccContextAction
{
    public function __construct(private readonly DatabaseConnectionFactory $connections)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponder::run($response, function () use ($request): array {
            if (strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0])) !== 'application/json') {
                throw new ImportException('unsupported_media_type', 'Envie a consulta como application/json.');
            }
            $raw = $request->getBody()->read(8193);
            if (strlen($raw) > 8192) {
                throw new ImportException('invalid_input', 'A consulta deve ter no máximo 8 KiB.');
            }
            try {
                $input = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new ImportException('invalid_input', 'JSON de consulta inválido.');
            }
            if (!is_array($input) || array_is_list($input)) {
                throw new ImportException('invalid_input', 'Envie um objeto JSON com subject, grade e topics.');
            }
            $query = BnccContextQuery::fromArray($input);
            $service = new BnccContextService(new BnccContextRepository($this->connections->create()), new BnccTopicMatcher());
            return $service->find($query);
        });
    }
}
