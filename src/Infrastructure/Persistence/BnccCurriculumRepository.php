<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Bncc\Bncc2018Profile;
use App\Application\Bncc\ImportException;
use Medoo\Medoo;
use PDO;

final class BnccCurriculumRepository
{
    public function __construct(private readonly Medoo $database)
    {
    }

    public function sourcePages(int $documentId): array
    {
        $rows = $this->database->select('bncc_document_pages', ['page_number', 'source_text'], [
            'document_id' => $documentId, 'ORDER' => ['page_number' => 'ASC'],
        ]);
        return array_column($rows, 'source_text', 'page_number');
    }

    public function save(int $documentId, string $expectedHash, array $plan, Bncc2018Profile $profile): array
    {
        $this->database->pdo->beginTransaction();
        try {
            $document = $this->lockDraft($documentId);
            if (!hash_equals($document['sha256'], $expectedHash)) {
                throw new ImportException('revision_conflict', 'O documento foi alterado durante a estruturação.');
            }
            if ($document['structured_at'] !== null) {
                throw new ImportException('already_structured', 'Esta revisão já foi estruturada. Reprocesse o PDF para criar outra revisão.');
            }
            foreach (['bncc_sections', 'bncc_areas', 'bncc_components', 'bncc_competencies', 'bncc_skills'] as $table) {
                if ($this->database->has($table, ['document_id' => $documentId])) {
                    throw new ImportException('already_structured', 'A revisão já contém dados curriculares que precisam ser preservados.');
                }
            }
            $source = $this->sourcePages($documentId);
            if (array_keys($source) !== range(1, $document['page_count'])) {
                throw new ImportException('invalid_curriculum', 'As páginas de origem estão incompletas.');
            }
            $subjectIds = array_column($this->database->select('subjects', ['id', 'code']), 'id', 'code');
            $gradeIds = array_column($this->database->select('school_grades', ['id', 'year_number'], [
                'education_stage' => 'ensino_fundamental',
            ]), 'id', 'year_number');
            if (count($gradeIds) !== 9) {
                throw new ImportException('missing_catalog', 'Cadastre os nove anos do Ensino Fundamental antes de estruturar a BNCC.');
            }

            $sections = [];
            $section = function (string $key, string $title, ?string $parent, int $start, int $end, bool $includeText = false)
                use (&$sections, $documentId, $document, $source): int {
                if ($start < 1 || $end < $start || $end > $document['page_count']
                    || ($parent !== null && (!isset($sections[$parent]) || $start < $sections[$parent]['start'] || $end > $sections[$parent]['end']))) {
                    throw new ImportException('invalid_curriculum', 'Intervalo ou hierarquia de seção incompatível com a origem.');
                }
                $texts = [];
                if ($includeText) {
                    for ($page = $start; $page <= $end; $page++) {
                        $texts[] = $source[$page];
                    }
                }
                $this->database->insert('bncc_sections', [
                    'document_id' => $documentId, 'parent_id' => $parent === null ? null : $sections[$parent]['id'],
                    'section_key' => $key, 'title' => $title, 'page_start' => $start, 'page_end' => $end,
                    'printed_page_label' => $start >= 3 ? (string) ($start - 2) : null,
                    'source_text' => $includeText ? implode("\n\f\n", $texts) : null,
                ]);
                $id = (int) $this->database->id();
                $sections[$key] = ['id' => $id, 'start' => $start, 'end' => $end];
                return $id;
            };
            $section('document', 'Base Nacional Comum Curricular', null, 1, $document['page_count']);
            $section('general', 'Competências gerais da Educação Básica', 'document', 11, 12, true);
            $section('fundamental', 'Ensino Fundamental', 'document', 59, 462);
            $areas = [];
            foreach ($profile->areas() as $code => $area) {
                $id = $section('area:' . $code, $area['name'], 'fundamental', $area['start'], $area['end']);
                $this->database->insert('bncc_areas', ['document_id' => $documentId, 'code' => $code,
                    'name' => $area['name'], 'source_section_id' => $id]);
                $areas[$code] = (int) $this->database->id();
            }
            $components = [];
            foreach ($profile->components() as $code => $component) {
                if (!isset($subjectIds[$component['subject']])) {
                    throw new ImportException('missing_catalog', 'Uma matéria necessária está ausente do catálogo.');
                }
                $id = $section('component:' . $code, $component['name'], 'area:' . $component['area'], $component['start'], $component['end']);
                $this->database->insert('bncc_components', ['document_id' => $documentId,
                    'subject_id' => $subjectIds[$component['subject']], 'area_id' => $areas[$component['area']], 'source_section_id' => $id]);
                $components[$code] = (int) $this->database->id();
            }
            foreach ($plan['competencies'] as $item) {
                $sectionKey = $item['scope'] === 'general' ? 'general' : $item['scope'] . ':' . $item['ref'];
                if ($item['page_number'] < $sections[$sectionKey]['start'] || $item['page_number'] > $sections[$sectionKey]['end']) {
                    throw new ImportException('invalid_curriculum', 'Competência fora de sua seção.');
                }
                $this->database->insert('bncc_competencies', [
                    'document_id' => $documentId, 'scope' => $item['scope'],
                    'area_id' => $item['scope'] === 'area' ? $areas[$item['ref']] : null,
                    'component_id' => $item['scope'] === 'component' ? $components[$item['ref']] : null,
                    'number' => $item['number'], 'description' => $item['description'],
                    'source_section_id' => $sections[$sectionKey]['id'], 'page_start' => $item['page_number'],
                    'page_end' => $item['page_number'], 'source_excerpt' => $item['source_excerpt'],
                    'source_metadata' => $this->json(['bbox' => $item['bbox'], 'flags' => $item['flags']]),
                ]);
            }
            foreach ($plan['skills'] as $item) {
                if ($profile->grades($item['code']) !== $item['grades'] || substr($item['code'], 4, 2) !== $item['component']) {
                    throw new ImportException('invalid_curriculum', 'Código, componente e anos incompatíveis.');
                }
                $context = $item['context'];
                $sectionKey = 'table:' . $context['page_start'] . ':' . $context['page_end'];
                if (!isset($sections[$sectionKey])) {
                    $section($sectionKey, 'Quadro curricular — páginas ' . $context['page_start'] . ' a ' . $context['page_end'],
                        'component:' . $item['component'], $context['page_start'], $context['page_end'], true);
                }
                $this->database->insert('bncc_skills', ['document_id' => $documentId,
                    'component_id' => $components[$item['component']], 'code' => $item['code'], 'description' => $item['description']]);
                $skillId = (int) $this->database->id();
                foreach ($item['grades'] as $year) {
                    $this->database->insert('bncc_skill_grades', ['skill_id' => $skillId, 'school_grade_id' => $gradeIds[$year]]);
                }
                $this->database->insert('bncc_skill_contexts', [
                    'document_id' => $documentId, 'skill_id' => $skillId, 'section_id' => $sections[$sectionKey]['id'],
                    'page_start' => $context['page_start'], 'page_end' => $context['page_end'],
                    'printed_page_label' => (string) ($item['page_number'] - 2), 'source_excerpt' => $item['source_excerpt'],
                    'thematic_unit' => $context['thematic_unit'], 'knowledge_objects' => $context['knowledge_objects'],
                    'context_metadata' => $this->json([...$context['metadata'], 'description_page' => $item['page_number'],
                        'flags' => $item['flags'], 'profile' => Bncc2018Profile::NAME]),
                ]);
            }
            $report = [...$plan['report'], 'sections' => count($sections), 'areas' => count($areas), 'components' => count($components)];
            $this->database->update('bncc_documents', ['structure_profile' => Bncc2018Profile::NAME,
                'structured_at' => gmdate('Y-m-d H:i:s'), 'structure_report' => $this->json($report)], ['id' => $documentId]);
            $result = $this->overview($documentId);
            $this->database->pdo->commit();
            return $result;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function overview(int $documentId): array
    {
        $document = $this->document($documentId);
        $skills = $this->reviewCounts('bncc_skills', $documentId);
        $competencies = $this->reviewCounts('bncc_competencies', $documentId);
        return ['document_id' => $documentId, 'status' => $document['status'],
            'structure_profile' => $document['structure_profile'], 'structured_at' => $document['structured_at'],
            'report' => $document['structure_report'] === null ? null : json_decode($document['structure_report'], true, flags: JSON_THROW_ON_ERROR),
            'review' => ['skills' => $skills, 'competencies' => $competencies],
            'sections' => $this->database->select('bncc_sections', ['id', 'parent_id', 'section_key', 'title', 'page_start', 'page_end'], [
                'document_id' => $documentId, 'ORDER' => ['page_start' => 'ASC', 'id' => 'ASC'],
            ])];
    }

    public function skills(int $documentId, int $offset, int $limit, ?string $subject = null, ?int $grade = null): array
    {
        $document = $this->document($documentId);
        $where = ' WHERE s.document_id = :document';
        $parameters = [':document' => $documentId];
        if ($subject !== null) {
            $where .= ' AND sub.code = :subject';
            $parameters[':subject'] = $subject;
        }
        if ($grade !== null) {
            $where .= ' AND EXISTS (SELECT 1 FROM bncc_skill_grades sg INNER JOIN school_grades g ON g.id = sg.school_grade_id
                WHERE sg.skill_id = s.id AND g.year_number = :grade AND g.education_stage = \'ensino_fundamental\')';
            $parameters[':grade'] = $grade;
        }
        $from = ' FROM bncc_skills s INNER JOIN bncc_components c ON c.id = s.component_id AND c.document_id = s.document_id
            INNER JOIN subjects sub ON sub.id = c.subject_id';
        $rows = $this->database->query('SELECT s.id, s.code, s.description, s.review_status, s.reviewed_at, s.review_note, '
            . BnccReleaseRepository::publicationLockSql('skill') . ' AS locked' . $from . $where
            . ' ORDER BY s.code LIMIT ' . $limit . ' OFFSET ' . $offset, $parameters)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['locked'] = $document['status'] !== 'draft' || (bool) $row['locked'];
        }
        return ['document_id' => $documentId, 'total' => (int) $this->database->query('SELECT COUNT(*)' . $from . $where, $parameters)->fetchColumn(),
            'offset' => $offset, 'limit' => $limit, 'skills' => $rows];
    }

    public function skill(int $documentId, string $code): array
    {
        $document = $this->document($documentId);
        $skill = $this->database->get('bncc_skills', '*', ['document_id' => $documentId, 'code' => $code])
            ?? throw new ImportException('not_found', 'Habilidade não encontrada nessa revisão.');
        $skill['grades'] = $this->database->select('bncc_skill_grades', ['[>]school_grades' => ['school_grade_id' => 'id']],
            'school_grades.year_number', ['skill_id' => $skill['id'], 'ORDER' => ['school_grades.year_number' => 'ASC']]);
        $skill['contexts'] = $this->database->select('bncc_skill_contexts', '*', ['skill_id' => $skill['id'], 'ORDER' => ['position' => 'ASC']]);
        foreach ($skill['contexts'] as &$context) {
            $context['context_metadata'] = $context['context_metadata'] === null ? null : json_decode($context['context_metadata'], true, flags: JSON_THROW_ON_ERROR);
        }
        $component = $this->database->get('bncc_components', ['subject_id', 'area_id'], ['id' => $skill['component_id']]);
        $skill['subject'] = $this->database->get('subjects', ['code', 'name'], ['id' => $component['subject_id']]);
        $skill['locked'] = $document['status'] !== 'draft' || (new BnccReleaseRepository($this->database))->itemIsPublished($documentId, 'skill', $skill['id']);
        return $skill;
    }

    public function competencies(int $documentId, ?string $subject = null, ?int $grade = null): array
    {
        $document = $this->document($documentId);
        $where = ' WHERE p.document_id = :document';
        $parameters = [':document' => $documentId];
        if ($subject !== null) {
            $component = $this->database->query('SELECT c.id, c.area_id FROM bncc_components c INNER JOIN subjects s ON s.id = c.subject_id
                WHERE c.document_id = :document AND s.code = :subject', [':document' => $documentId, ':subject' => $subject])->fetch(PDO::FETCH_ASSOC);
            if ($component === false) {
                return ['document_id' => $documentId, 'competencies' => []];
            }
            $where .= ' AND (p.scope = \'general\' OR (p.scope = \'area\' AND p.area_id = :area) OR (p.scope = \'component\' AND p.component_id = :component))';
            $parameters[':area'] = $component['area_id'];
            $parameters[':component'] = $component['id'];
        }
        $rows = $this->database->query('SELECT p.*, ' . BnccReleaseRepository::publicationLockSql('competency')
            . ' AS locked FROM bncc_competencies p' . $where . ' ORDER BY p.id', $parameters)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['source_metadata'] = $row['source_metadata'] === null ? null : json_decode($row['source_metadata'], true, flags: JSON_THROW_ON_ERROR);
            $row['locked'] = $document['status'] !== 'draft' || (bool) $row['locked'];
        }
        return ['document_id' => $documentId, 'competencies' => $rows];
    }

    public function review(int $documentId, string $type, string|int $key, string $status, string $note, ?string $description): array
    {
        if (!in_array($type, ['skill', 'competency'], true)) {
            throw new \InvalidArgumentException('Tipo de revisão inválido.');
        }
        $this->database->pdo->beginTransaction();
        try {
            $document = $this->lockDraft($documentId);
            if ($document['structured_at'] === null) {
                throw new ImportException('not_structured', 'Estruture o documento antes de revisar.');
            }
            $table = $type === 'skill' ? 'bncc_skills' : 'bncc_competencies';
            $where = ['document_id' => $documentId, $type === 'skill' ? 'code' : 'id' => $key];
            $item = $this->database->get($table, '*', $where)
                ?? throw new ImportException('not_found', 'Item não encontrado nessa revisão.');
            (new BnccReleaseRepository($this->database))->assertItemMutable($documentId, $type, $item);
            if ($description !== null) {
                // A revisão pode recompor espaçamento/hifens de quebra de linha, preservando letras e pontuação.
                $canonical = static fn (string $text): string => preg_replace('/[\s-]+/u', '', $text);
                if ($canonical($description) !== $canonical($item['description'])) {
                    throw new ImportException('invalid_input', 'A correção deve preservar o texto da fonte; apenas espaços e hifens podem ser ajustados.');
                }
            }
            if ($status === 'approved' && $type === 'skill') {
                if (!$this->database->has('bncc_skill_contexts', ['skill_id' => $item['id'], 'document_id' => $documentId])
                    || !$this->database->has('bncc_skill_grades', ['skill_id' => $item['id']])) {
                    throw new ImportException('invalid_curriculum', 'A habilidade precisa de origem e anos antes da aprovação.');
                }
            }
            $this->database->update($table, [
                'review_status' => $status, 'review_note' => $note,
                'reviewed_at' => $status === 'pending' ? null : gmdate('Y-m-d H:i:s'),
                'description' => $description ?? $item['description'],
            ], $where);
            $result = $this->database->get($table, ['id', 'description', 'review_status', 'reviewed_at', 'review_note'], $where);
            $this->database->pdo->commit();
            return $result;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function publish(int $documentId): array
    {
        $this->database->pdo->beginTransaction();
        try {
            $document = $this->lockDraft($documentId);
            $skills = $this->reviewCounts('bncc_skills', $documentId);
            $competencies = $this->reviewCounts('bncc_competencies', $documentId);
            $expectedSkills = array_sum(array_column((new Bncc2018Profile())->components(), 'expected'));
            if ($document['structure_profile'] !== Bncc2018Profile::NAME || $document['structured_at'] === null
                || $skills['approved'] !== $expectedSkills || $skills['total'] !== $expectedSkills
                || $competencies['approved'] !== 94 || $competencies['total'] !== 94) {
                throw new ImportException('review_incomplete', 'Todas as habilidades e competências precisam ser revisadas e aprovadas antes da liberação.',
                    ['skills' => $skills, 'competencies' => $competencies]);
            }
            $missing = (int) $this->database->query('SELECT COUNT(*) FROM bncc_skills s WHERE s.document_id = :document AND (
                NOT EXISTS (SELECT 1 FROM bncc_skill_grades g WHERE g.skill_id = s.id)
                OR NOT EXISTS (SELECT 1 FROM bncc_skill_contexts c WHERE c.skill_id = s.id AND c.document_id = s.document_id))',
                [':document' => $documentId])->fetchColumn();
            if ($missing !== 0) {
                throw new ImportException('invalid_curriculum', 'Existem habilidades sem anos ou contexto de origem.');
            }
            $this->database->update('bncc_documents', ['status' => 'ready', 'ready_at' => gmdate('Y-m-d H:i:s')], ['id' => $documentId]);
            $this->database->pdo->commit();
            return ['document_id' => $documentId, 'status' => 'ready'];
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function releases(int $documentId): array
    {
        return (new BnccReleaseRepository($this->database))->listing($documentId);
    }

    public function publishScope(int $documentId, string $subject, int $grade): array
    {
        return (new BnccReleaseRepository($this->database))->publish($documentId, $subject, $grade);
    }

    private function document(int $documentId): array
    {
        return $this->database->get('bncc_documents', '*', ['id' => $documentId])
            ?? throw new ImportException('not_found', 'Documento não encontrado.');
    }

    private function lockDraft(int $documentId): array
    {
        // Todas as mutações de currículo/revisão usam o mesmo lock: publicar e editar não podem correr juntas.
        $document = $this->database->query('SELECT * FROM bncc_documents WHERE id = :id FOR UPDATE', [':id' => $documentId])->fetch(PDO::FETCH_ASSOC);
        if ($document === false) {
            throw new ImportException('not_found', 'Documento não encontrado.');
        }
        if ($document['status'] !== 'draft') {
            throw new ImportException('immutable_document', 'Esta revisão não é editável. Reprocesse o PDF para criar um novo rascunho.');
        }
        return $document;
    }

    private function reviewCounts(string $table, int $documentId): array
    {
        $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];
        foreach ($this->database->select($table, 'review_status', ['document_id' => $documentId]) as $status) {
            $counts[$status]++;
            $counts['total']++;
        }
        return $counts;
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function rollback(): void
    {
        if ($this->database->pdo->inTransaction()) {
            $this->database->pdo->rollBack();
        }
    }
}
