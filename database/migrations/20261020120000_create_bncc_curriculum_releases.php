<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateBnccCurriculumReleases extends AbstractMigration
{
    public function change(): void
    {
        $this->table('bncc_curriculum_releases', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_0900_ai_ci'])
            ->addColumn('document_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('component_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('school_grade_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('released_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['document_id', 'component_id', 'school_grade_id'], ['unique' => true, 'name' => 'uq_bncc_curriculum_release'])
            ->addIndex(['school_grade_id', 'released_at'], ['name' => 'idx_bncc_release_selection'])
            ->addForeignKey(['document_id', 'component_id'], 'bncc_components', ['document_id', 'id'], [
                'delete' => 'RESTRICT', 'update' => 'RESTRICT', 'constraint' => 'fk_bncc_release_component',
            ])
            ->addForeignKey('school_grade_id', 'school_grades', 'id', [
                'delete' => 'RESTRICT', 'update' => 'RESTRICT', 'constraint' => 'fk_bncc_release_grade',
            ])
            ->create();
    }
}
