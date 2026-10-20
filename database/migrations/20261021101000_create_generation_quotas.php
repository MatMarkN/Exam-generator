<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGenerationQuotas extends AbstractMigration
{
    public function change(): void
    {
        $options = ['engine' => 'InnoDB', 'collation' => 'utf8mb4_0900_ai_ci', 'id' => false];
        // user_id=0 identifica somente o contador global; não representa uma conta.
        $this->table('generation_quota_days', [...$options, 'primary_key' => ['quota_date', 'scope', 'user_id']])
            ->addColumn('quota_date', 'date', ['null' => false])
            ->addColumn('scope', 'string', ['limit' => 16, 'null' => false])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('requests', 'integer', ['signed' => false, 'null' => false, 'default' => 0])
            ->create();
        $this->table('generation_quota_users', [...$options, 'primary_key' => ['user_id']])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('last_admitted_at', 'biginteger', ['signed' => false, 'null' => true])
            ->create();
    }
}
