<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateSchoolCatalogs extends AbstractMigration
{
    public function up(): void
    {
        $options = ['engine' => 'InnoDB', 'collation' => 'utf8mb4_0900_ai_ci'];

        $this->table('subjects', $options)
            ->addColumn('code', 'string', ['limit' => 40, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 80, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['code'], ['unique' => true, 'name' => 'uq_subjects_code'])
            ->create();

        $this->execute("ALTER TABLE subjects
            ADD CONSTRAINT chk_subjects_code CHECK (CHAR_LENGTH(TRIM(code)) > 0),
            ADD CONSTRAINT chk_subjects_name CHECK (CHAR_LENGTH(TRIM(name)) > 0)");

        $this->table('school_grades', $options)
            ->addColumn('code', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 80, 'null' => false])
            ->addColumn('education_stage', 'string', ['limit' => 40, 'null' => false])
            ->addColumn('year_number', 'smallinteger', ['signed' => false, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['code'], ['unique' => true, 'name' => 'uq_school_grades_code'])
            ->addIndex(['education_stage', 'year_number'], [
                'unique' => true,
                'name' => 'uq_school_grades_stage_year',
            ])
            ->create();

        $this->execute("ALTER TABLE school_grades
            ADD CONSTRAINT chk_school_grades_code CHECK (CHAR_LENGTH(TRIM(code)) > 0),
            ADD CONSTRAINT chk_school_grades_name CHECK (CHAR_LENGTH(TRIM(name)) > 0),
            ADD CONSTRAINT chk_school_grades_stage CHECK (CHAR_LENGTH(TRIM(education_stage)) > 0),
            ADD CONSTRAINT chk_school_grades_year CHECK (year_number BETWEEN 1 AND 9)");
    }

    public function down(): void
    {
        $this->table('school_grades')->drop()->save();
        $this->table('subjects')->drop()->save();
    }
}
