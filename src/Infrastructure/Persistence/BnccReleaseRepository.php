<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Bncc\Bncc2018Profile;
use App\Application\Bncc\ImportException;
use Medoo\Medoo;
use PDO;

/** Publicações independentes de matéria/ano; itens compartilhados ficam congelados ao primeiro uso. */
final class BnccReleaseRepository
{
    public function __construct(private readonly Medoo $database)
    {
    }

    public function listing(int $documentId): array
    {
        $document = $this->document($documentId);
        $skills = $this->database->query('SELECT s.component_id, s.review_status, g.id AS grade_id, g.year_number,
            g.name AS grade_name, g.education_stage, sub.id AS subject_id, sub.code AS subject_code,
            sub.name AS subject_name, c.area_id FROM bncc_skills s
            INNER JOIN bncc_skill_grades sg ON sg.skill_id = s.id
            INNER JOIN school_grades g ON g.id = sg.school_grade_id
            INNER JOIN bncc_components c ON c.id = s.component_id AND c.document_id = s.document_id
            INNER JOIN subjects sub ON sub.id = c.subject_id WHERE s.document_id = :document
            ORDER BY sub.name, g.year_number, s.code', [':document' => $documentId])->fetchAll(PDO::FETCH_ASSOC);
        $competencies = $this->database->select('bncc_competencies', ['scope', 'area_id', 'component_id', 'review_status'], ['document_id' => $documentId]);
        $releases = $this->database->select('bncc_curriculum_releases', '*', ['document_id' => $documentId]);
        $grouped = [];
        foreach ($skills as $skill) {
            $key = $skill['component_id'] . ':' . $skill['grade_id'];
            if (!isset($grouped[$key])) {
                $applicable = array_filter($competencies, static fn (array $item): bool => $item['scope'] === 'general'
                    || ($item['scope'] === 'area' && $item['area_id'] === $skill['area_id'])
                    || ($item['scope'] === 'component' && $item['component_id'] === $skill['component_id']));
                $releasedAt = null;
                foreach ($releases as $release) {
                    if ($release['component_id'] === $skill['component_id'] && $release['school_grade_id'] === $skill['grade_id']) {
                        $releasedAt = $release['released_at'];
                        break;
                    }
                }
                if ($document['status'] === 'ready') {
                    $releasedAt = $document['ready_at'];
                }
                $grouped[$key] = [
                    'subject' => ['id' => $skill['subject_id'], 'code' => $skill['subject_code'], 'name' => $skill['subject_name']],
                    'grade' => ['id' => $skill['grade_id'], 'year_number' => $skill['year_number'], 'name' => $skill['grade_name'], 'education_stage' => $skill['education_stage']],
                    'status' => $document['status'] !== 'archived' && $releasedAt !== null ? 'ready' : 'pending',
                    'released_at' => $document['status'] === 'archived' ? null : $releasedAt,
                    'review' => ['skills' => $this->counts([]), 'competencies' => $this->counts($applicable)],
                ];
            }
            $grouped[$key]['review']['skills'][$skill['review_status']]++;
            $grouped[$key]['review']['skills']['total']++;
        }
        return ['document_id' => $documentId, 'status' => $document['status'], 'items' => array_values($grouped)];
    }

    public function publish(int $documentId, string $subject, int $grade): array
    {
        $this->database->pdo->beginTransaction();
        try {
            // Revisar, publicar outro escopo e publicar tudo usam o mesmo lock de documento.
            $document = $this->document($documentId, true);
            if ($document['status'] === 'archived') {
                throw new ImportException('immutable_document', 'Documentos arquivados não podem receber novas liberações.');
            }
            $item = $this->scope($documentId, $subject, $grade);
            if ($item['status'] !== 'ready') {
                if ($document['structured_at'] === null || $document['structure_profile'] !== Bncc2018Profile::NAME) {
                    throw new ImportException('not_structured', 'Estruture o documento antes de liberar uma matéria e ano.');
                }
                $component = $this->database->get('bncc_components', ['id', 'area_id'], ['document_id' => $documentId, 'subject_id' => $item['subject']['id']]);
                $expectedCompetencies = $this->expectedCompetencies($subject);
                if ($item['review']['skills']['total'] === 0
                    || $item['review']['skills']['approved'] !== $item['review']['skills']['total']
                    || $item['review']['competencies']['total'] !== $expectedCompetencies
                    || $item['review']['competencies']['approved'] !== $expectedCompetencies) {
                    throw new ImportException('review_incomplete', 'Revise e aprove todas as habilidades deste ano e as competências aplicáveis à matéria antes da liberação.', $item['review']);
                }
                $this->assertSources($document, $component, $item['grade']['id'], $grade, $subject);
                $this->database->insert('bncc_curriculum_releases', ['document_id' => $documentId, 'component_id' => $component['id'], 'school_grade_id' => $item['grade']['id']]);
                $item = $this->scope($documentId, $subject, $grade);
            }
            $this->database->pdo->commit();
            return ['document_id' => $documentId, ...$item];
        } catch (\Throwable $exception) {
            if ($this->database->pdo->inTransaction()) {
                $this->database->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function assertItemMutable(int $documentId, string $type, array $item): void
    {
        if ($this->itemIsPublished($documentId, $type, $item['id'])) {
            throw new ImportException('immutable_document', 'Este item já integra uma matéria e ano liberados. Reprocesse o PDF para criar uma revisão sem alterar o conteúdo publicado.');
        }
    }

    public function itemIsPublished(int $documentId, string $type, int $itemId): bool
    {
        [$table, $alias] = match ($type) {
            'skill' => ['bncc_skills', 's'],
            'competency' => ['bncc_competencies', 'p'],
            default => throw new \InvalidArgumentException('Tipo de revisão inválido.'),
        };
        return (bool) $this->database->query('SELECT ' . self::publicationLockSql($type) . ' FROM ' . $table . ' ' . $alias
            . ' WHERE ' . $alias . '.document_id = :document AND ' . $alias . '.id = :id',
            [':document' => $documentId, ':id' => $itemId])->fetchColumn();
    }

    /** Expressões fixas reutilizadas na consulta individual e nas listagens, sem consulta por linha. */
    public static function publicationLockSql(string $type): string
    {
        return match ($type) {
            'skill' => 'EXISTS (SELECT 1 FROM bncc_curriculum_releases r
                INNER JOIN bncc_skill_grades g ON g.school_grade_id = r.school_grade_id
                WHERE r.document_id = s.document_id AND r.component_id = s.component_id AND g.skill_id = s.id)',
            'competency' => 'EXISTS (SELECT 1 FROM bncc_curriculum_releases r
                INNER JOIN bncc_components c ON c.id = r.component_id AND c.document_id = r.document_id
                WHERE r.document_id = p.document_id AND (p.scope = \'general\'
                OR (p.scope = \'area\' AND c.area_id = p.area_id)
                OR (p.scope = \'component\' AND c.id = p.component_id)))',
            default => throw new \InvalidArgumentException('Tipo de revisão inválido.'),
        };
    }

    private function scope(int $documentId, string $subject, int $grade): array
    {
        if ($this->database->get('subjects', 'id', ['code' => $subject]) === null) {
            throw new ImportException('unknown_subject', 'A matéria informada não existe no catálogo.');
        }
        foreach ($this->listing($documentId)['items'] as $item) {
            if ($item['subject']['code'] === $subject && $item['grade']['year_number'] === $grade) {
                return $item;
            }
        }
        throw new ImportException('subject_not_available', 'Esta revisão não contém habilidades para a matéria e o ano solicitados.');
    }

    private function expectedCompetencies(string $subject): int
    {
        $profile = new Bncc2018Profile();
        foreach ($profile->components() as $code => $component) {
            if ($component['subject'] === $subject) {
                return array_sum(array_column(array_filter($profile->competencies(), static fn (array $group): bool => $group['scope'] === 'general'
                    || ($group['scope'] === 'area' && $group['ref'] === $component['area'])
                    || ($group['scope'] === 'component' && $group['ref'] === $code)), 'count'));
            }
        }
        throw new ImportException('subject_not_available', 'A matéria não é compatível com o perfil curricular.');
    }

    private function assertSources(array $document, array $component, int $gradeId, int $year, string $subject): void
    {
        $skills = $this->database->query('SELECT s.id, s.code FROM bncc_skills s
            INNER JOIN bncc_skill_grades g ON g.skill_id = s.id WHERE s.document_id = :document AND s.component_id = :component AND g.school_grade_id = :grade',
            [':document' => $document['id'], ':component' => $component['id'], ':grade' => $gradeId])->fetchAll(PDO::FETCH_ASSOC);
        $profile = new Bncc2018Profile();
        foreach ($skills as $skill) {
            if (!in_array($year, $profile->grades($skill['code']), true)
                || $profile->components()[substr($skill['code'], 4, 2)]['subject'] !== $subject) {
                throw new ImportException('invalid_curriculum', 'Uma habilidade tem código incompatível com a matéria e o ano solicitados.');
            }
            $sources = $this->database->select('bncc_skill_contexts', ['page_start', 'page_end', 'source_excerpt'], ['document_id' => $document['id'], 'skill_id' => $skill['id']]);
            if ($sources === []) {
                throw new ImportException('invalid_curriculum', 'Uma habilidade deste ano não possui origem.');
            }
            foreach ($sources as $source) {
                $this->assertSource($source, $document['page_count']);
            }
        }
        $competencies = $this->database->query('SELECT page_start, page_end, source_excerpt FROM bncc_competencies WHERE document_id = :document
            AND (scope = \'general\' OR (scope = \'area\' AND area_id = :area) OR (scope = \'component\' AND component_id = :component))',
            [':document' => $document['id'], ':area' => $component['area_id'], ':component' => $component['id']])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($competencies as $source) {
            $this->assertSource($source, $document['page_count']);
        }
    }

    private function assertSource(array $source, int $pageCount): void
    {
        if ($source['page_start'] < 1 || $source['page_end'] < $source['page_start'] || $source['page_end'] > $pageCount || preg_match('/\S/u', $source['source_excerpt']) !== 1) {
            throw new ImportException('invalid_curriculum', 'Uma origem deste escopo possui páginas ou texto incompatíveis com o documento.');
        }
    }

    private function counts(array $items): array
    {
        $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => count($items)];
        foreach ($items as $item) {
            $counts[$item['review_status']]++;
        }
        return $counts;
    }

    private function document(int $documentId, bool $lock = false): array
    {
        $document = $this->database->query('SELECT * FROM bncc_documents WHERE id = :id' . ($lock ? ' FOR UPDATE' : ''), [':id' => $documentId])->fetch(PDO::FETCH_ASSOC);
        return $document === false ? throw new ImportException('not_found', 'Documento não encontrado.') : $document;
    }
}
