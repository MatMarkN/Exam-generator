<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateAuthentication extends AbstractMigration
{
    public function up(): void
    {
        $options = ['engine' => 'InnoDB', 'collation' => 'utf8mb4_0900_ai_ci'];
        $this->table('users', $options)
            ->addColumn('name', 'string', ['limit' => 160, 'null' => false])
            ->addColumn('email', 'string', ['limit' => 254, 'null' => false])
            ->addColumn('password_hash', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('role', 'string', ['limit' => 16, 'default' => 'teacher', 'null' => false])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addIndex(['email'], ['unique' => true, 'name' => 'uq_users_email'])->create();
        $this->execute("ALTER TABLE users ADD CONSTRAINT chk_users_role CHECK (role IN ('teacher','admin'))");
        $this->table('auth_sessions', $options)
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('token_hash', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('csrf_token', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addIndex(['token_hash'], ['unique' => true, 'name' => 'uq_auth_session_token'])
            ->addIndex(['expires_at'], ['name' => 'ix_auth_session_expiry'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'RESTRICT', 'constraint' => 'fk_auth_session_user'])->create();
        $this->table('auth_rate_limits', [...$options, 'id' => false, 'primary_key' => ['limit_key']])
            ->addColumn('limit_key', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('attempts', 'integer', ['signed' => false, 'null' => false, 'default' => 1])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addIndex(['expires_at'], ['name' => 'ix_auth_rate_expiry'])->create();
    }

    public function down(): void
    {
        $this->table('auth_rate_limits')->drop()->save();
        $this->table('auth_sessions')->drop()->save();
        $this->table('users')->drop()->save();
    }
}
