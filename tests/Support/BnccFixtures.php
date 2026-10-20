<?php

declare(strict_types=1);

namespace Tests\Support;

use Medoo\Medoo;
use RuntimeException;

/** Dados SINTÉTICOS exclusivos dos testes; não são uma importação da BNCC. */
final class BnccFixtures
{
    private int $sectionNumber = 0;

    public function __construct(private readonly Medoo $database)
    {
    }

    public function document(array $overrides = []): int
    {
        return $this->insert('bncc_documents', array_replace([
            'title' => 'Documento SINTÉTICO de teste',
            'version_label' => 'fixture',
            'original_filename' => 'fixture.pdf',
            'storage_path' => 'tests/fixture.pdf',
            'sha256' => hash('sha256', random_bytes(32)),
            'page_count' => 40,
        ], $overrides));
    }

    public function section(int $documentId, array $overrides = []): int
    {
        return $this->insert('bncc_sections', array_replace([
            'document_id' => $documentId,
            'section_key' => 'fixture_' . ++$this->sectionNumber,
            'title' => 'Seção SINTÉTICA',
            'page_start' => 1,
            'page_end' => 40,
            'source_text' => 'Texto sintético de origem, sem conteúdo curricular oficial.',
        ], $overrides));
    }

    public function area(int $documentId, int $sectionId, array $overrides = []): int
    {
        return $this->insert('bncc_areas', array_replace([
            'document_id' => $documentId,
            'source_section_id' => $sectionId,
            'code' => 'area_fixture',
            'name' => 'Área SINTÉTICA',
        ], $overrides));
    }

    public function component(int $documentId, int $areaId, int $sectionId, string $subjectCode = 'arte', array $overrides = []): int
    {
        $subjectId = $this->database->get('subjects', 'id', ['code' => $subjectCode]);

        if ($subjectId === null) {
            throw new RuntimeException('O catálogo inicial é necessário para este teste.');
        }

        return $this->insert('bncc_components', array_replace([
            'document_id' => $documentId,
            'area_id' => $areaId,
            'source_section_id' => $sectionId,
            'subject_id' => $subjectId,
        ], $overrides));
    }

    /** @return array{document_id: int, section_id: int, area_id: int, component_id: int} */
    public function curriculum(array $documentOverrides = []): array
    {
        $documentId = $this->document($documentOverrides);
        $sectionId = $this->section($documentId);
        $areaId = $this->area($documentId, $sectionId);
        $componentId = $this->component($documentId, $areaId, $sectionId);

        return ['document_id' => $documentId, 'section_id' => $sectionId, 'area_id' => $areaId, 'component_id' => $componentId];
    }

    public function skill(array $curriculum, array $overrides = []): int
    {
        return $this->insert('bncc_skills', array_replace([
            'document_id' => $curriculum['document_id'],
            'component_id' => $curriculum['component_id'],
            'code' => 'EF15AR01',
            'description' => 'Descrição SINTÉTICA usada somente para testar o armazenamento.',
        ], $overrides));
    }

    public function context(array $curriculum, int $skillId, array $overrides = []): int
    {
        return $this->insert('bncc_skill_contexts', array_replace([
            'document_id' => $curriculum['document_id'],
            'skill_id' => $skillId,
            'section_id' => $curriculum['section_id'],
            'page_start' => 5,
            'page_end' => 6,
            'printed_page_label' => '4–5',
            'source_excerpt' => 'Trecho SINTÉTICO: não corresponde ao texto oficial da habilidade.',
            'thematic_unit' => 'Unidade de teste',
            'knowledge_objects' => 'Objeto de conhecimento de teste',
        ], $overrides));
    }

    public function competency(array $curriculum, string $scope = 'general', array $overrides = []): int
    {
        return $this->insert('bncc_competencies', array_replace([
            'document_id' => $curriculum['document_id'],
            'scope' => $scope,
            'area_id' => $scope === 'area' ? $curriculum['area_id'] : null,
            'component_id' => $scope === 'component' ? $curriculum['component_id'] : null,
            'number' => 1,
            'description' => 'Competência SINTÉTICA para testar o escopo.',
            'source_section_id' => $curriculum['section_id'],
            'page_start' => 2,
            'page_end' => 2,
            'source_excerpt' => 'Trecho SINTÉTICO de competência para teste.',
        ], $overrides));
    }

    private function insert(string $table, array $data): int
    {
        $this->database->insert($table, $data);

        return (int) $this->database->id();
    }
}
