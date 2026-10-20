<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateExamHistory extends AbstractMigration
{
    public function up(): void
    {
        $options = ['engine' => 'InnoDB', 'collation' => 'utf8mb4_0900_ai_ci'];
        $this->table('generation_history', $options)
            ->addColumn('teacher_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('requester_name', 'string', ['limit' => 160, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 16, 'default' => 'running', 'null' => false])
            ->addColumn('stage', 'string', ['limit' => 32, 'default' => 'context', 'null' => false])
            ->addColumn('started_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addColumn('finished_at', 'datetime', ['null' => true])
            ->addColumn('subject', 'string', ['limit' => 40, 'null' => false])
            ->addColumn('grade', 'smallinteger', ['signed' => false, 'null' => false])
            ->addColumn('question_count', 'smallinteger', ['signed' => false, 'null' => false])
            ->addColumn('request_json', 'json', ['null' => false])
            ->addColumn('provider', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('requested_model', 'string', ['limit' => 200, 'null' => true])
            ->addColumn('model', 'string', ['limit' => 200, 'null' => true])
            // Identificadores históricos, sem FK: o snapshot deve sobreviver à remoção de uma fonte.
            ->addColumn('bncc_document_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('bncc_revision', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('ai_request_json', 'json', ['null' => true])
            ->addColumn('rejected_response_json', 'text', ['limit' => 16777215, 'null' => true])
            ->addColumn('response_sha256', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('provider_request_id', 'string', ['limit' => 200, 'null' => true])
            ->addColumn('input_tokens', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('output_tokens', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('provider_duration_ms', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('total_duration_ms', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('cost_json', 'json', ['null' => true])
            ->addColumn('result_metadata_json', 'json', ['null' => true])
            ->addColumn('error_code', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('error_message', 'string', ['limit' => 500, 'null' => true])
            ->addIndex(['status', 'id'], ['name' => 'ix_generation_status_id'])
            ->addIndex(['teacher_id', 'id'], ['name' => 'ix_generation_teacher_id'])
            ->addForeignKey('teacher_id', 'teachers', 'id', ['delete' => 'SET_NULL', 'update' => 'RESTRICT',
                'constraint' => 'fk_generation_teacher'])
            ->create();
        $this->execute("ALTER TABLE generation_history
            ADD CONSTRAINT chk_generation_status CHECK (status IN ('running', 'succeeded', 'failed')),
            ADD CONSTRAINT chk_generation_grade CHECK (grade BETWEEN 1 AND 9),
            ADD CONSTRAINT chk_generation_count CHECK (question_count BETWEEN 1 AND 30),
            ADD CONSTRAINT chk_generation_completion CHECK (
                (status = 'running' AND finished_at IS NULL AND error_code IS NULL)
                OR (status = 'succeeded' AND finished_at IS NOT NULL AND error_code IS NULL AND rejected_response_json IS NULL)
                OR (status = 'failed' AND finished_at IS NOT NULL AND error_code IS NOT NULL AND error_message IS NOT NULL)),
            ADD CONSTRAINT chk_generation_response_size CHECK (rejected_response_json IS NULL OR OCTET_LENGTH(rejected_response_json) <= 131072)");

        $this->table('exams', $options)
            ->addColumn('generation_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('exam_json', 'json', ['null' => false])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addIndex(['generation_id'], ['unique' => true, 'name' => 'uq_exam_generation'])
            ->addForeignKey('generation_id', 'generation_history', 'id', ['delete' => 'RESTRICT', 'update' => 'RESTRICT',
                'constraint' => 'fk_exam_generation'])
            ->create();
        $this->table('exam_answers', [...$options, 'id' => false, 'primary_key' => ['exam_id']])
            ->addColumn('exam_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('answer_key_json', 'json', ['null' => false])
            ->addForeignKey('exam_id', 'exams', 'id', ['delete' => 'CASCADE', 'update' => 'RESTRICT',
                'constraint' => 'fk_exam_answer_exam'])
            ->create();
    }

    public function down(): void
    {
        $this->table('exam_answers')->drop()->save();
        $this->table('exams')->drop()->save();
        $this->table('generation_history')->drop()->save();
    }
}
