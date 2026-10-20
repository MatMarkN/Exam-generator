<?php

declare(strict_types=1);

namespace Examples\Demo;

use InvalidArgumentException;
use Medoo\Medoo;
use RuntimeException;
use Throwable;

/** Currículo SINTÉTICO: não importa, reproduz ou aprova o PDF oficial. */
final class DemoCurriculum
{
    private const PROFILE = 'synthetic-exam-demo-v1';

    public static function assertEnvironment(): void
    {
        if (getenv('APP_ENV') !== 'demo' || getenv('DB_DATABASE') !== 'gerador_de_provas_demo') {
            throw new InvalidArgumentException('Use compose.demo.yaml: a demonstração exige APP_ENV=demo e DB_DATABASE=gerador_de_provas_demo.');
        }
    }

    public static function prepare(Medoo $database): int
    {
        self::assertEnvironment();
        if ($database->query('SELECT DATABASE()')->fetchColumn() !== 'gerador_de_provas_demo') {
            throw new InvalidArgumentException('A conexão não aponta para o banco descartável da demonstração.');
        }

        // O hash identifica ESTE arquivo PHP de fixture, não um PDF da BNCC.
        $hash = hash_file('sha256', __FILE__);
        $existing = $database->get('bncc_documents', ['id', 'sha256', 'status', 'structure_profile'], []);
        if ($existing !== null) {
            if ($database->count('bncc_documents') !== 1 || $existing['sha256'] !== $hash
                || $existing['structure_profile'] !== self::PROFILE || $existing['status'] !== 'ready') {
                throw new InvalidArgumentException('O banco demo já contém outro currículo. Execute docker compose -f compose.demo.yaml down e repita a demonstração.');
            }
            return $existing['id'];
        }

        $subjectId = $database->get('subjects', 'id', ['code' => 'matematica']);
        $grades = [];
        foreach ([6, 7] as $year) {
            $grades[$year] = $database->get('school_grades', 'id', [
                'education_stage' => 'ensino_fundamental', 'year_number' => $year,
            ]) ?? throw new RuntimeException('Execute o seed dos catálogos antes da demonstração.');
        }
        if ($subjectId === null) {
            throw new RuntimeException('Execute o seed dos catálogos antes da demonstração.');
        }

        $insert = static function (string $table, array $data) use ($database): int {
            $database->insert($table, $data);
            return (int) $database->id();
        };
        $now = gmdate('Y-m-d H:i:s');
        $notice = 'Conteúdo SINTÉTICO de demonstração; não é texto oficial da BNCC.';

        $database->pdo->beginTransaction();
        try {
            $documentId = $insert('bncc_documents', [
                'title' => 'Currículo SINTÉTICO da demonstração — não é a BNCC oficial',
                'version_label' => 'demo-sintetica-v1',
                'original_filename' => 'DemoCurriculum.php',
                'storage_path' => 'examples/demo/DemoCurriculum.php',
                'sha256' => $hash,
                'page_count' => 1,
                'status' => 'ready',
                'ready_at' => $now,
                'structured_at' => $now,
                'structure_profile' => self::PROFILE,
                'structure_report' => json_encode(['synthetic' => true, 'official_bncc' => false], JSON_THROW_ON_ERROR),
            ]);
            $sectionId = $insert('bncc_sections', [
                'document_id' => $documentId, 'section_key' => 'demo',
                'title' => 'Seção SINTÉTICA; página virtual de demonstração',
                'page_start' => 1, 'page_end' => 1, 'source_text' => $notice,
            ]);
            $areaId = $insert('bncc_areas', [
                'document_id' => $documentId, 'source_section_id' => $sectionId,
                'code' => 'matematica', 'name' => 'Matemática — área SINTÉTICA de demonstração',
            ]);
            $componentId = $insert('bncc_components', [
                'document_id' => $documentId, 'area_id' => $areaId,
                'source_section_id' => $sectionId, 'subject_id' => $subjectId,
            ]);

            // Os códigos exercitam o formato do sistema; as descrições abaixo são inventadas para o exemplo.
            foreach ([
                ['code' => 'EF06MA07', 'year' => 6, 'topic' => 'Frações',
                    'description' => 'Comparar frações positivas, reconhecer equivalências e justificar comparações.'],
                ['code' => 'EF07MA07', 'year' => 7, 'topic' => 'Frações',
                    'description' => 'Resolver problemas de frações no conjunto de dados do 7º ano.'],
                ['code' => 'EF06MA18', 'year' => 6, 'topic' => 'Geometria',
                    'description' => 'Identificar características de figuras geométricas.'],
            ] as $skill) {
                $description = $notice . ' ' . $skill['description'];
                $skillId = $insert('bncc_skills', [
                    'document_id' => $documentId, 'component_id' => $componentId,
                    'code' => $skill['code'], 'description' => $description,
                    'review_status' => 'approved', 'reviewed_at' => $now,
                    'review_note' => 'Estado de teste somente neste banco descartável. ' . $notice,
                ]);
                $database->insert('bncc_skill_grades', ['skill_id' => $skillId, 'school_grade_id' => $grades[$skill['year']]]);
                $insert('bncc_skill_contexts', [
                    'document_id' => $documentId, 'skill_id' => $skillId, 'section_id' => $sectionId,
                    'page_start' => 1, 'page_end' => 1, 'printed_page_label' => 'virtual-demo',
                    'source_excerpt' => '(' . $skill['code'] . ') ' . $description,
                    'thematic_unit' => 'Unidade SINTÉTICA de demonstração',
                    'knowledge_objects' => $skill['topic'] . ' — objeto SINTÉTICO',
                ]);
            }

            $insert('bncc_competencies', [
                'document_id' => $documentId, 'scope' => 'component', 'component_id' => $componentId,
                'number' => 1, 'description' => $notice . ' Explicar raciocínios matemáticos com clareza.',
                'source_section_id' => $sectionId, 'page_start' => 1, 'page_end' => 1,
                'source_excerpt' => $notice . ' Competência de demonstração: comunicar a justificativa.',
                'review_status' => 'approved', 'reviewed_at' => $now, 'review_note' => $notice,
            ]);
            $database->pdo->commit();
            return $documentId;
        } catch (Throwable $exception) {
            if ($database->pdo->inTransaction()) {
                $database->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
