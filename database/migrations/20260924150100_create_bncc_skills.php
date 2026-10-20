<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateBnccSkills extends AbstractMigration
{
    public function up(): void
    {
        $options = ['engine' => 'InnoDB', 'collation' => 'utf8mb4_0900_ai_ci'];

        $this->table('bncc_skills', $options)
            ->addColumn('document_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('component_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('code', 'string', [
                'limit' => 20, 'encoding' => 'ascii', 'collation' => 'ascii_bin', 'null' => false,
            ])
            ->addColumn('description', 'text', ['null' => false])
            ->addColumn('review_status', 'enum', [
                'values' => ['pending', 'approved', 'rejected'], 'null' => false, 'default' => 'pending',
            ])
            ->addColumn('reviewed_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['document_id', 'id'], ['unique' => true, 'name' => 'uq_bncc_skill_document_id'])
            ->addIndex(['document_id', 'code'], ['unique' => true, 'name' => 'uq_bncc_skill_code'])
            ->addIndex(['document_id', 'component_id', 'review_status'], ['name' => 'idx_bncc_skill_selection'])
            ->addForeignKey(['document_id', 'component_id'], 'bncc_components', ['document_id', 'id'], [
                'delete' => 'RESTRICT', 'update' => 'RESTRICT', 'constraint' => 'fk_bncc_skill_component',
            ])
            ->create();

        $this->execute("ALTER TABLE bncc_skills
            ADD CONSTRAINT chk_bncc_skill_code CHECK (REGEXP_LIKE(code, '^EF[0-9]{2}[A-Z]{2}[0-9]{2}$', 'c')),
            ADD CONSTRAINT chk_bncc_skill_description CHECK (CHAR_LENGTH(TRIM(description)) > 0),
            ADD CONSTRAINT chk_bncc_skill_review CHECK (review_status <> 'approved' OR reviewed_at IS NOT NULL)");

        $this->table('bncc_skill_grades', [
            ...$options, 'id' => false, 'primary_key' => ['skill_id', 'school_grade_id'],
        ])
            ->addColumn('skill_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('school_grade_id', 'integer', ['signed' => false, 'null' => false])
            ->addIndex(['school_grade_id', 'skill_id'], ['name' => 'idx_bncc_grade_skills'])
            ->addForeignKey('skill_id', 'bncc_skills', 'id', [
                'delete' => 'RESTRICT', 'update' => 'RESTRICT', 'constraint' => 'fk_bncc_skill_grade_skill',
            ])
            ->addForeignKey('school_grade_id', 'school_grades', 'id', [
                'delete' => 'RESTRICT', 'update' => 'RESTRICT', 'constraint' => 'fk_bncc_skill_grade_grade',
            ])
            ->create();

        // Uma habilidade pode aparecer em mais de um quadro, com contextos e páginas distintos.
        $this->table('bncc_skill_contexts', $options)
            ->addColumn('document_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('skill_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('section_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('position', 'integer', ['signed' => false, 'null' => false, 'default' => 1])
            ->addColumn('page_start', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('page_end', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('printed_page_label', 'string', ['limit' => 40, 'null' => true])
            ->addColumn('source_excerpt', 'text', ['null' => false])
            ->addColumn('thematic_unit', 'text', ['null' => true])
            ->addColumn('knowledge_objects', 'text', ['null' => true])
            ->addColumn('context_metadata', 'json', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['skill_id', 'section_id', 'position'], ['unique' => true, 'name' => 'uq_bncc_skill_context'])
            ->addForeignKey(['document_id', 'skill_id'], 'bncc_skills', ['document_id', 'id'], [
                'delete' => 'RESTRICT', 'update' => 'RESTRICT', 'constraint' => 'fk_bncc_skill_context_skill',
            ])
            ->addForeignKey(['document_id', 'section_id'], 'bncc_sections', ['document_id', 'id'], [
                'delete' => 'RESTRICT', 'update' => 'RESTRICT', 'constraint' => 'fk_bncc_skill_context_section',
            ])
            ->create();

        $this->execute("ALTER TABLE bncc_skill_contexts
            ADD CONSTRAINT chk_bncc_context_position CHECK (position > 0),
            ADD CONSTRAINT chk_bncc_context_pages CHECK (page_start > 0 AND page_end >= page_start),
            ADD CONSTRAINT chk_bncc_context_excerpt CHECK (CHAR_LENGTH(TRIM(source_excerpt)) > 0),
            ADD CONSTRAINT chk_bncc_context_metadata CHECK (context_metadata IS NULL OR JSON_TYPE(context_metadata) = 'OBJECT')");
    }

    public function down(): void
    {
        $this->table('bncc_skill_contexts')->drop()->save();
        $this->table('bncc_skill_grades')->drop()->save();
        $this->table('bncc_skills')->drop()->save();
    }
}
