<?php

declare(strict_types=1);

namespace App\Application\Bncc;

use App\Infrastructure\Documents\LocalPdfStorage;
use App\Infrastructure\Documents\PopplerLayoutExtractor;
use App\Infrastructure\Persistence\BnccCurriculumRepository;
use App\Infrastructure\Persistence\BnccDocumentRepository;

final class StructureBnccDocument
{
    public function __construct(
        private readonly BnccDocumentRepository $documents,
        private readonly BnccCurriculumRepository $curriculum,
        private readonly LocalPdfStorage $storage,
        private readonly PopplerLayoutExtractor $extractor,
    ) {
    }

    public function structure(int $documentId): array
    {
        $document = $this->documents->find($documentId) ?? throw new ImportException('not_found', 'Documento não encontrado.');
        if ($document['status'] !== 'draft') {
            throw new ImportException('immutable_document', 'Somente rascunhos podem ser estruturados.');
        }
        if ($document['structured_at'] !== null) {
            throw new ImportException('already_structured', 'Esta revisão já foi estruturada. Reprocesse o arquivo para gerar outra.');
        }
        $profile = new Bncc2018Profile();
        $profile->assertSupported($document);
        $path = $this->storage->verifiedPath($document['storage_path'], $document['sha256']);
        $layout = $this->extractor->extract($path, 11, 462);
        $plan = (new BnccCurriculumParser($profile))->parse($layout);
        unset($layout);
        return $this->curriculum->save($documentId, $document['sha256'], $plan, $profile);
    }
}
