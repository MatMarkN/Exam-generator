<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Bncc\BnccContextQuery;
use App\Application\Bncc\ImportException;
use Medoo\Medoo;
use PDO;

final class BnccContextRepository
{
    public function __construct(private readonly Medoo $database)
    {
    }

    public function load(BnccContextQuery $query): array
    {
        // Todas as leituras enxergam a mesma versão dos dados, mesmo que outra requisição libere uma revisão.
        $pdo = $this->database->pdo;
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
        $pdo->beginTransaction();
        try {
            $result = $this->snapshot($query);
            $pdo->commit();
            return $result;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function snapshot(BnccContextQuery $query): array
    {
        $subject = $this->database->get('subjects', ['id', 'code', 'name'], ['code' => $query->subject])
            ?? throw new ImportException('unknown_subject', 'A matéria informada não existe no catálogo.');
        $grade = $this->database->get('school_grades', ['id', 'year_number', 'name', 'education_stage'], [
            'education_stage' => 'ensino_fundamental', 'year_number' => $query->grade,
        ]) ?? throw new ImportException('missing_catalog', 'O ano escolar não está cadastrado.');
        $columns = ['id', 'title', 'version_label', 'revision', 'sha256', 'page_count', 'status', 'ready_at', 'structured_at', 'structure_profile'];
        if ($query->documentId !== null) {
            $document = $this->database->get('bncc_documents', $columns, ['id' => $query->documentId])
                ?? throw new ImportException('not_found', 'Documento não encontrado.');
        } else {
            $document = $this->database->get('bncc_documents', $columns, [
                'status' => 'ready', 'ORDER' => ['ready_at' => 'DESC', 'id' => 'DESC'],
            ]) ?? throw new ImportException('curriculum_not_ready', 'Ainda não há uma revisão da BNCC liberada. Conclua a revisão e a liberação antes de consultar o contexto.');
        }
        if ($document['status'] !== 'ready' || $document['structured_at'] === null) {
            throw new ImportException('document_not_ready', 'O documento solicitado ainda não está disponível para uso curricular.');
        }
        $component = $this->database->query('SELECT c.id, a.id AS area_id, a.code AS area_code, a.name AS area_name
            FROM bncc_components c INNER JOIN bncc_areas a ON a.document_id = c.document_id AND a.id = c.area_id
            WHERE c.document_id = :document AND c.subject_id = :subject',
            [':document' => $document['id'], ':subject' => $subject['id']])->fetch(PDO::FETCH_ASSOC);
        if ($component === false) {
            throw new ImportException('subject_not_available', 'Esta revisão da BNCC não contém a matéria solicitada.');
        }
        $rows = $this->database->query('SELECT s.id, s.code, s.description
            FROM bncc_skills s INNER JOIN bncc_skill_grades g ON g.skill_id = s.id
            WHERE s.document_id = :document AND s.component_id = :component
            AND s.review_status = :approved AND g.school_grade_id = :grade ORDER BY s.code', [
                ':document' => $document['id'], ':component' => $component['id'], ':approved' => 'approved', ':grade' => $grade['id'],
            ])->fetchAll(PDO::FETCH_ASSOC);
        $skills = [];
        foreach ($rows as $row) {
            $skills[$row['id']] = [...$row, 'grades' => [], 'sources' => []];
        }
        if ($skills !== []) {
            $ids = array_keys($skills);
            foreach ($this->database->select('bncc_skill_grades', ['[>]school_grades' => ['school_grade_id' => 'id']],
                ['skill_id', 'school_grades.year_number'], ['skill_id' => $ids, 'ORDER' => ['school_grades.year_number' => 'ASC']]) as $row) {
                $skills[$row['skill_id']]['grades'][] = $row['year_number'];
            }
            $sources = $this->database->select('bncc_skill_contexts', ['id', 'skill_id', 'section_id', 'page_start', 'page_end',
                'printed_page_label', 'source_excerpt', 'thematic_unit', 'knowledge_objects', 'context_metadata'], [
                    'document_id' => $document['id'], 'skill_id' => $ids, 'ORDER' => ['position' => 'ASC', 'id' => 'ASC'],
                ]);
            foreach ($sources as $source) {
                $metadata = $source['context_metadata'] === null ? [] : json_decode($source['context_metadata'], true, flags: JSON_THROW_ON_ERROR);
                $skillId = $source['skill_id'];
                unset($source['skill_id'], $source['context_metadata']);
                $skills[$skillId]['sources'][] = [...$source, 'description_page' => $metadata['description_page'] ?? null,
                    'language_practice' => $metadata['language_practice'] ?? null, 'field_of_activity' => $metadata['field_of_activity'] ?? null];
            }
        }
        $competencies = $this->database->query('SELECT id, scope, area_id, component_id, number, description,
            source_section_id, page_start, page_end, source_excerpt FROM bncc_competencies
            WHERE document_id = :document AND review_status = :approved AND (scope = :general
            OR (scope = :area AND area_id = :area_id) OR (scope = :component AND component_id = :component_id))
            ORDER BY scope, number, id', [':document' => $document['id'], ':approved' => 'approved', ':general' => 'general',
                ':area' => 'area', ':area_id' => $component['area_id'], ':component' => 'component', ':component_id' => $component['id'],
            ])->fetchAll(PDO::FETCH_ASSOC);
        return ['document' => $document, 'subject' => $subject, 'grade' => $grade,
            'area' => ['id' => $component['area_id'], 'code' => $component['area_code'], 'name' => $component['area_name']],
            'skills' => array_values(array_filter($skills, static fn (array $skill): bool => $skill['sources'] !== [])),
            'competencies' => $competencies];
    }
}
