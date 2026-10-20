<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddResourceOwners extends AbstractMigration
{
    public function change(): void
    {
        foreach (['teachers', 'generation_history'] as $table) {
            $this->table($table)
                ->addColumn('user_id', 'integer', ['signed' => false, 'null' => true, 'default' => null])
                ->addIndex(['user_id', 'id'], ['name' => 'ix_' . $table . '_owner'])
                ->addForeignKey('user_id', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'RESTRICT',
                    'constraint' => 'fk_' . $table . '_owner'])
                ->update();
        }
    }
}
