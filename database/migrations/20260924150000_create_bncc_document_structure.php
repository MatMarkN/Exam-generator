<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateBnccDocumentStructure extends AbstractMigration
{
    public function up(): void
    {
        $options = ['engine' => 'InnoDB', 'collation' => 'utf8mb4_0900_ai_ci'];

        $this->table('bncc_documents', $options)
            ->addColumn('title', 'string', ['limit' => 180, 'null' => false])
            ->addColumn('version_label', 'string', ['limit' => 80, 'null' => false])
            ->addColumn('revision', 'integer', ['signed' => false, 'null' => false, 'default' => 1])
            ->addColumn('original_filename', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('storage_path', 'string', ['limit' => 512, 'null' => false])
            ->addColumn('sha256', 'char', [
                'limit' => 64, 'encoding' => 'ascii', 'collation' => 'ascii_bin', 'null' => false,
            ])
            ->addColumn('page_count', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('status', 'enum', [
                'values' => ['draft', 'ready', 'archived'], 'null' => false, 'default' => 'draft',
            ])
            ->addColumn('ready_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['sha256', 'revision'], ['unique' => true, 'name' => 'uq_bncc_document_revision'])
            ->addIndex(['status'], ['name' => 'idx_bncc_document_status'])
            ->create();

        $this->execute("ALTER TABLE bncc_documents
            ADD CONSTRAINT chk_bncc_document_title CHECK (CHAR_LENGTH(TRIM(title)) > 0),
            ADD CONSTRAINT chk_bncc_document_version CHECK (CHAR_LENGTH(TRIM(version_label)) > 0),
            ADD CONSTRAINT chk_bncc_document_revision CHECK (revision > 0),
            ADD CONSTRAINT chk_bncc_document_filename CHECK (CHAR_LENGTH(TRIM(original_filename)) > 0),
            ADD CONSTRAINT chk_bncc_document_path CHECK (CHAR_LENGTH(TRIM(storage_path)) > 0),
            ADD CONSTRAINT chk_bncc_document_hash CHECK (REGEXP_LIKE(sha256, '^[0-9a-f]{64}$', 'c')),
            ADD CONSTRAINT chk_bncc_document_pages CHECK (page_count > 0),
            ADD CONSTRAINT chk_bncc_document_ready CHECK (status <> 'ready' OR ready_at IS NOT NULL)");

        $this->table('bncc_sections', $options)
            ->addColumn('document_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('parent_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('section_key', 'string', ['limit' => 120, 'null' => false])
            ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('page_start', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('page_end', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('printed_page_label', 'string', ['limit' => 40, 'null' => true])
            ->addColumn('source_text', 'text', ['limit' => 16777215, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['document_id', 'id'], ['unique' => true, 'name' => 'uq_bncc_section_document_id'])
            ->addIndex(['document_id', 'section_key'], ['unique' => true, 'name' => 'uq_bncc_section_key'])
            ->addForeignKey('document_id', 'bncc_documents', 'id', [
                'delete' => 'RESTRICT', 'update' => 'RESTRICT', 'constraint' => 'fk_bncc_section_document',
            ])
            ->create();

        $this->table('bncc_sections')
            ->addForeignKey(['document_id', 'parent_id'], 'bncc_sections', ['document_id', 'id'], [
                'delete' => 'RESTRICT', 'update' => 'RESTRICT', 'constraint' => 'fk_bncc_section_parent',
            ])
            ->update();

        $this->execute("ALTER TABLE bncc_sections
            ADD CONSTRAINT chk_bncc_section_key CHECK (CHAR_LENGTH(TRIM(section_key)) > 0),
            ADD CONSTRAINT chk_bncc_section_title CHECK (CHAR_LENGTH(TRIM(title)) > 0),
            ADD CONSTRAINT chk_bncc_section_pages CHECK (page_start > 0 AND page_end >= page_start)");

        $this->table('bncc_areas', $options)
            ->addColumn('document_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('source_section_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('code', 'string', ['limit' => 40, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 120, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['document_id', 'id'], ['unique' => true, 'name' => 'uq_bncc_area_document_id'])
            ->addIndex(['document_id', 'code'], ['unique' => true, 'name' => 'uq_bncc_area_code'])
            ->addForeignKey(['document_id', 'source_section_id'], 'bncc_sections', ['document_id', 'id'], [
                'delete' => 'RESTRICT', 'update' => 'RESTRICT', 'constraint' => 'fk_bncc_area_source',
            ])
            ->create();

        $this->execute("ALTER TABLE bncc_areas
            ADD CONSTRAINT chk_bncc_area_code CHECK (CHAR_LENGTH(TRIM(code)) > 0),
            ADD CONSTRAINT chk_bncc_area_name CHECK (CHAR_LENGTH(TRIM(name)) > 0)");

        // subjects é o catálogo da aplicação; esta tabela registra sua organização nesta revisão da BNCC.
        $this->table('bncc_components', $options)
            ->addColumn('document_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('subject_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('area_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('source_section_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['document_id', 'id'], ['unique' => true, 'name' => 'uq_bncc_component_document_id'])
            ->addIndex(['document_id', 'subject_id'], ['unique' => true, 'name' => 'uq_bncc_component_subject'])
            ->addForeignKey('subject_id', 'subjects', 'id', [
                'delete' => 'RESTRICT', 'update' => 'RESTRICT', 'constraint' => 'fk_bncc_component_subject',
            ])
            ->addForeignKey(['document_id', 'area_id'], 'bncc_areas', ['document_id', 'id'], [
                'delete' => 'RESTRICT', 'update' => 'RESTRICT', 'constraint' => 'fk_bncc_component_area',
            ])
            ->addForeignKey(['document_id', 'source_section_id'], 'bncc_sections', ['document_id', 'id'], [
                'delete' => 'RESTRICT', 'update' => 'RESTRICT', 'constraint' => 'fk_bncc_component_source',
            ])
            ->create();

        $this->table('bncc_competencies', $options)
            ->addColumn('document_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('scope', 'enum', ['values' => ['general', 'area', 'component'], 'null' => false])
            ->addColumn('area_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('component_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('number', 'smallinteger', ['signed' => false, 'null' => false])
            ->addColumn('description', 'text', ['null' => false])
            ->addColumn('source_section_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('page_start', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('page_end', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('source_excerpt', 'text', ['null' => false])
            ->addColumn('review_status', 'enum', [
                'values' => ['pending', 'approved', 'rejected'], 'null' => false, 'default' => 'pending',
            ])
            ->addColumn('reviewed_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey(['document_id', 'area_id'], 'bncc_areas', ['document_id', 'id'], [
                'delete' => 'RESTRICT', 'update' => 'RESTRICT', 'constraint' => 'fk_bncc_competency_area',
            ])
            ->addForeignKey(['document_id', 'component_id'], 'bncc_components', ['document_id', 'id'], [
                'delete' => 'RESTRICT', 'update' => 'RESTRICT', 'constraint' => 'fk_bncc_competency_component',
            ])
            ->addForeignKey(['document_id', 'source_section_id'], 'bncc_sections', ['document_id', 'id'], [
                'delete' => 'RESTRICT', 'update' => 'RESTRICT', 'constraint' => 'fk_bncc_competency_source',
            ])
            ->create();

        // O número 1 pode existir em escopos distintos. O campo gerado também cobre o escopo geral, sem NULL no índice.
        $this->execute("ALTER TABLE bncc_competencies
            ADD COLUMN scope_ref INT UNSIGNED GENERATED ALWAYS AS (COALESCE(area_id, component_id, 0)) STORED,
            ADD UNIQUE INDEX uq_bncc_competency_number (document_id, scope, scope_ref, number),
            ADD CONSTRAINT chk_bncc_competency_scope CHECK (
                (scope = 'general' AND area_id IS NULL AND component_id IS NULL)
                OR (scope = 'area' AND area_id IS NOT NULL AND component_id IS NULL)
                OR (scope = 'component' AND component_id IS NOT NULL AND area_id IS NULL)
            ),
            ADD CONSTRAINT chk_bncc_competency_number CHECK (number > 0),
            ADD CONSTRAINT chk_bncc_competency_description CHECK (CHAR_LENGTH(TRIM(description)) > 0),
            ADD CONSTRAINT chk_bncc_competency_excerpt CHECK (CHAR_LENGTH(TRIM(source_excerpt)) > 0),
            ADD CONSTRAINT chk_bncc_competency_pages CHECK (page_start > 0 AND page_end >= page_start),
            ADD CONSTRAINT chk_bncc_competency_review CHECK (review_status <> 'approved' OR reviewed_at IS NOT NULL)");
    }

    public function down(): void
    {
        $this->table('bncc_competencies')->drop()->save();
        $this->table('bncc_components')->drop()->save();
        $this->table('bncc_areas')->drop()->save();
        $this->table('bncc_sections')->dropForeignKey(['document_id', 'parent_id'], 'fk_bncc_section_parent')->update();
        $this->table('bncc_sections')->drop()->save();
        $this->table('bncc_documents')->drop()->save();
    }
}
