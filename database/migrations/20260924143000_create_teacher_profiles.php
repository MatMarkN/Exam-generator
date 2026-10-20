<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTeacherProfiles extends AbstractMigration
{
    public function up(): void
    {
        $options = ['engine' => 'InnoDB', 'collation' => 'utf8mb4_0900_ai_ci'];

        $this->table('teachers', $options)
            ->addColumn('name', 'string', ['limit' => 160, 'null' => false])
            ->addColumn('email', 'string', ['limit' => 254, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['email'], ['unique' => true, 'name' => 'uq_teachers_email'])
            ->create();

        $this->execute("ALTER TABLE teachers
            ADD CONSTRAINT chk_teachers_name CHECK (CHAR_LENGTH(TRIM(name)) > 0),
            ADD CONSTRAINT chk_teachers_email CHECK (email IS NULL OR CHAR_LENGTH(TRIM(email)) > 0)");

        // A mesma coluna identifica as preferências e aponta para o professor.
        $this->table('teacher_settings', [
            ...$options,
            'id' => false,
            'primary_key' => ['teacher_id'],
        ])
            ->addColumn('teacher_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('school_name', 'string', ['limit' => 180, 'null' => true])
            ->addColumn('default_font_family', 'string', ['limit' => 80, 'null' => false, 'default' => 'Arial'])
            ->addColumn('default_font_size', 'smallinteger', ['signed' => false, 'null' => false, 'default' => 12])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
            ])
            ->addForeignKey('teacher_id', 'teachers', 'id', [
                'delete' => 'CASCADE',
                'update' => 'RESTRICT',
                'constraint' => 'fk_teacher_settings_teacher',
            ])
            ->create();

        $this->execute("ALTER TABLE teacher_settings
            ADD CONSTRAINT chk_teacher_settings_school CHECK (school_name IS NULL OR CHAR_LENGTH(TRIM(school_name)) > 0),
            ADD CONSTRAINT chk_teacher_settings_font CHECK (CHAR_LENGTH(TRIM(default_font_family)) > 0),
            ADD CONSTRAINT chk_teacher_settings_font_size CHECK (default_font_size > 0)");
    }

    public function down(): void
    {
        $this->table('teacher_settings')->drop()->save();
        $this->table('teachers')->drop()->save();
    }
}
