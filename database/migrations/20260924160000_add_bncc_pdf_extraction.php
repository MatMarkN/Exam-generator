<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddBnccPdfExtraction extends AbstractMigration
{
    public function up(): void
    {
        // Nulos permitem manter documentos anteriores que ainda não passaram pelo importador.
        $this->table('bncc_documents')
            ->addColumn('file_size', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('extractor_version', 'string', ['limit' => 80, 'null' => true])
            ->addColumn('extracted_at', 'datetime', ['null' => true])
            ->addColumn('extraction_warnings', 'json', ['null' => true])
            ->update();

        $this->table('bncc_document_pages', [
            'id' => false, 'primary_key' => ['document_id', 'page_number'],
            'engine' => 'InnoDB', 'collation' => 'utf8mb4_0900_ai_ci',
        ])
            ->addColumn('document_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('page_number', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('source_text', 'text', ['limit' => 16777215, 'null' => false])
            ->addColumn('detected_skill_codes', 'json', ['null' => false])
            ->addForeignKey('document_id', 'bncc_documents', 'id', [
                'delete' => 'RESTRICT', 'update' => 'RESTRICT', 'constraint' => 'fk_bncc_page_document',
            ])
            ->create();

        $this->execute("ALTER TABLE bncc_document_pages
            ADD CONSTRAINT chk_bncc_page_number CHECK (page_number > 0),
            ADD CONSTRAINT chk_bncc_page_codes CHECK (JSON_TYPE(detected_skill_codes) = 'ARRAY')");
        $this->execute("ALTER TABLE bncc_documents
            ADD CONSTRAINT chk_bncc_file_size CHECK (file_size IS NULL OR file_size > 0),
            ADD CONSTRAINT chk_bncc_extraction_warnings CHECK (
                extraction_warnings IS NULL OR JSON_TYPE(extraction_warnings) = 'OBJECT'
            )");
    }

    public function down(): void
    {
        $this->table('bncc_document_pages')->drop()->save();
        $this->execute('ALTER TABLE bncc_documents DROP CHECK chk_bncc_file_size, DROP CHECK chk_bncc_extraction_warnings');
        $this->table('bncc_documents')
            ->removeColumn('file_size')
            ->removeColumn('extractor_version')
            ->removeColumn('extracted_at')
            ->removeColumn('extraction_warnings')
            ->update();
    }
}
