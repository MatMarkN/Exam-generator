<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddBnccStructuringReview extends AbstractMigration
{
    public function up(): void
    {
        $this->table('bncc_documents')
            ->addColumn('structure_profile', 'string', ['limit' => 80, 'null' => true])
            ->addColumn('structured_at', 'datetime', ['null' => true])
            ->addColumn('structure_report', 'json', ['null' => true])->update();
        $this->table('bncc_skills')->addColumn('review_note', 'text', ['null' => true])->update();
        $this->table('bncc_competencies')
            ->addColumn('review_note', 'text', ['null' => true])
            ->addColumn('source_metadata', 'json', ['null' => true])->update();
        $this->execute("ALTER TABLE bncc_documents ADD CONSTRAINT chk_bncc_structure_report
            CHECK (structure_report IS NULL OR JSON_TYPE(structure_report) = 'OBJECT')");
        $this->execute("ALTER TABLE bncc_competencies ADD CONSTRAINT chk_bncc_competency_metadata
            CHECK (source_metadata IS NULL OR JSON_TYPE(source_metadata) = 'OBJECT')");
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE bncc_competencies DROP CHECK chk_bncc_competency_metadata');
        $this->table('bncc_competencies')->removeColumn('source_metadata')->removeColumn('review_note')->update();
        $this->table('bncc_skills')->removeColumn('review_note')->update();
        $this->execute('ALTER TABLE bncc_documents DROP CHECK chk_bncc_structure_report');
        $this->table('bncc_documents')->removeColumn('structure_profile')->removeColumn('structured_at')
            ->removeColumn('structure_report')->update();
    }
}
