<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Exams\GenerationLimitException;
use LogicException;
use Medoo\Medoo;

final class GenerationQuotaRepository
{
    private readonly string $lockPrefix;

    public function __construct(private readonly Medoo $database)
    {
        $schema = (string) $database->query('SELECT DATABASE()')->fetchColumn();
        $this->lockPrefix = 'prova:ai:' . substr(hash('sha256', $schema), 0, 12) . ':';
    }

    public function lockName(string $suffix): string
    {
        return $this->lockPrefix . $suffix;
    }

    public function tryLock(string $name): bool
    {
        // GET_LOCK é reentrante na mesma conexão. Nunca reutilizar um slot já ocupado por outro guard.
        if ((int) $this->database->query('SELECT IS_FREE_LOCK(:name)', [':name' => $name])->fetchColumn() !== 1) {
            return false;
        }
        return (int) $this->database->query('SELECT GET_LOCK(:name, 0)', [':name' => $name])->fetchColumn() === 1;
    }

    public function releaseLock(string $name): void
    {
        $this->database->query('SELECT RELEASE_LOCK(:name)', [':name' => $name])->fetchColumn();
    }

    /** A transação termina antes da chamada HTTP; uma tentativa reservada não é reembolsada. */
    public function reserve(int $userId, array $limits, int $now): void
    {
        $pdo = $this->database->pdo;
        if ($pdo->inTransaction()) {
            throw new LogicException('A reserva de geração não pode participar de uma transação externa.');
        }
        $day = gmdate('Y-m-d', $now);
        $untilTomorrow = (intdiv($now, 86400) + 1) * 86400 - $now;
        $pdo->beginTransaction();
        try {
            // Ordem única global -> usuário -> intervalo evita inversões de locks entre admissões.
            $global = $this->lockedDay($day, 'global', 0);
            if ($global >= $limits['global_daily_limit']) {
                throw new GenerationLimitException('generation_daily_global_limit',
                    'O limite diário de gerações do sistema foi atingido. A cota é renovada à meia-noite UTC.', $untilTomorrow);
            }
            $user = $this->lockedDay($day, 'user', $userId);
            if ($user >= $limits['user_daily_limit']) {
                throw new GenerationLimitException('generation_daily_user_limit',
                    'Seu limite diário de gerações foi atingido. A cota é renovada à meia-noite UTC.', $untilTomorrow);
            }
            $this->database->query('INSERT INTO generation_quota_users (user_id, last_admitted_at) VALUES (:user, NULL)
                ON DUPLICATE KEY UPDATE user_id = user_id', [':user' => $userId]);
            $previous = $this->database->query('SELECT last_admitted_at FROM generation_quota_users WHERE user_id = :user FOR UPDATE',
                [':user' => $userId])->fetchColumn();
            if ($previous !== null && $previous !== false && $now - (int) $previous < $limits['min_interval_seconds']) {
                $wait = min(86400, max(1, $limits['min_interval_seconds'] - ($now - (int) $previous)));
                throw new GenerationLimitException('generation_interval_limit',
                    'Aguarde o intervalo mínimo entre gerações antes de enviar outro pedido.', $wait);
            }
            $this->database->query('UPDATE generation_quota_days SET requests = requests + 1
                WHERE quota_date = :date AND scope = :scope AND user_id = :user', [':date' => $day, ':scope' => 'global', ':user' => 0]);
            $this->database->query('UPDATE generation_quota_days SET requests = requests + 1
                WHERE quota_date = :date AND scope = :scope AND user_id = :user', [':date' => $day, ':scope' => 'user', ':user' => $userId]);
            $this->database->update('generation_quota_users', ['last_admitted_at' => $now], ['user_id' => $userId]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function lockedDay(string $day, string $scope, int $userId): int
    {
        $bindings = [':date' => $day, ':scope' => $scope, ':user' => $userId];
        $this->database->query('INSERT INTO generation_quota_days (quota_date, scope, user_id, requests)
            VALUES (:date, :scope, :user, 0) ON DUPLICATE KEY UPDATE requests = requests', $bindings);
        return (int) $this->database->query('SELECT requests FROM generation_quota_days
            WHERE quota_date = :date AND scope = :scope AND user_id = :user FOR UPDATE', $bindings)->fetchColumn();
    }
}
