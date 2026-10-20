<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Bncc\Bncc2018Profile;
use Medoo\Medoo;

/** Planos SINTÉTICOS: verificam persistência/revisão, não são conteúdo oficial nem passam pelo perfil de arquivo. */
final class CurriculumFixture
{
    public function __construct(private readonly Medoo $database)
    {
    }

    public function document(): array
    {
        $id = (new BnccFixtures($this->database))->document(['page_count' => 600]);
        $pages = [];
        for ($page = 1; $page <= 600; $page++) {
            $pages[] = ['document_id' => $id, 'page_number' => $page,
                'source_text' => 'ORIGEM SINTÉTICA de teste; página ' . $page, 'detected_skill_codes' => '[]'];
        }
        $this->database->insert('bncc_document_pages', $pages);
        return $this->database->get('bncc_documents', '*', ['id' => $id]);
    }

    public function plan(bool $full = false): array
    {
        $profile = new Bncc2018Profile();
        $skills = [];
        foreach ($profile->components() as $component => $definition) {
            if (!$full && $component !== 'AR') {
                continue;
            }
            $count = $full ? $definition['expected'] : 1;
            for ($i = 0; $i < $count; $i++) {
                $year = ($component === 'LI' ? 6 : 1) + intdiv($i, 99);
                $code = $full ? sprintf('EF%02d%s%02d', $year, $component, $i % 99 + 1) : 'EF15AR01';
                $page = $definition['start'] + 10;
                $skills[] = ['code' => $code, 'component' => $component, 'grades' => $profile->grades($code),
                    'description' => 'Descrição SINTÉTICA de teste.', 'source_excerpt' => "($code) Descrição SINTÉTICA de teste.",
                    'page_number' => $page, 'flags' => [],
                    'context' => ['page_start' => $page - 1, 'page_end' => $page, 'thematic_unit' => 'Tema SINTÉTICO',
                        'knowledge_objects' => 'Objeto SINTÉTICO', 'metadata' => ['fixture' => true]]];
            }
        }
        $competencies = [];
        foreach ($profile->competencies() as $group) {
            if (!$full && $group['scope'] !== 'general') {
                continue;
            }
            for ($number = 1; $number <= ($full ? $group['count'] : 1); $number++) {
                $competencies[] = ['scope' => $group['scope'], 'ref' => $group['ref'], 'number' => $number,
                    'description' => 'Competência SINTÉTICA de teste.', 'source_excerpt' => "$number. Competência SINTÉTICA de teste.",
                    'page_number' => $group['pages'][0], 'bbox' => ['xMin' => 10, 'yMin' => 100, 'xMax' => 500, 'yMax' => 150], 'flags' => []];
            }
        }
        return ['skills' => $skills, 'competencies' => $competencies, 'report' => ['skills' => count($skills),
            'competencies' => count($competencies), 'removed_control_characters' => 0, 'parser_reported_warnings' => false, 'warnings' => []]];
    }

    public function remove(int $documentId): void
    {
        $skills = $this->database->select('bncc_skills', 'id', ['document_id' => $documentId]);
        $this->database->delete('bncc_skill_contexts', ['document_id' => $documentId]);
        if ($skills !== []) {
            $this->database->delete('bncc_skill_grades', ['skill_id' => $skills]);
        }
        foreach (['bncc_skills', 'bncc_competencies', 'bncc_components', 'bncc_areas'] as $table) {
            $this->database->delete($table, ['document_id' => $documentId]);
        }
        foreach ($this->database->select('bncc_sections', 'id', ['document_id' => $documentId, 'ORDER' => ['id' => 'DESC']]) as $id) {
            $this->database->delete('bncc_sections', ['id' => $id]);
        }
        $this->database->delete('bncc_document_pages', ['document_id' => $documentId]);
        $this->database->delete('bncc_documents', ['id' => $documentId]);
    }
}
