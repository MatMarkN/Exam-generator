<?php

declare(strict_types=1);

namespace App\Application\Exams;

use App\Infrastructure\Persistence\GenerationQuotaRepository;
use Closure;
use InvalidArgumentException;
use LogicException;

/** Reserva uma tentativa antes do fornecedor e contém concorrência até terminar a geração. */
final class GenerationGuard
{
    /** @var list<string> */
    private array $locks = [];

    public function __construct(
        private readonly GenerationQuotaRepository $repository,
        private readonly int $userId,
        private readonly array $limits,
        private readonly ?Closure $clock = null,
    ) {
        if ($userId < 1) {
            throw new InvalidArgumentException('A cota exige um usuário autenticado.');
        }
        $maximums = ['user_daily_limit' => 1000000, 'global_daily_limit' => 1000000,
            'min_interval_seconds' => 3600, 'max_concurrent' => 32];
        if (array_diff(array_keys($limits), array_keys($maximums)) !== []) {
            throw new InvalidArgumentException('Configuração de cotas não reconhecida.');
        }
        foreach ($maximums as $key => $maximum) {
            if (!is_int($limits[$key] ?? null) || $limits[$key] < 1 || $limits[$key] > $maximum) {
                throw new InvalidArgumentException('Os limites de geração precisam ser inteiros positivos dentro da faixa permitida.');
            }
        }
    }

    public function acquire(): void
    {
        if ($this->locks !== []) {
            throw new LogicException('Esta geração já possui uma reserva de concorrência.');
        }
        try {
            $userLock = $this->repository->lockName('user:' . $this->userId);
            if (!$this->repository->tryLock($userLock)) {
                throw new GenerationLimitException('generation_user_busy',
                    'Você já possui uma geração em andamento. Aguarde a conclusão e consulte o histórico.', 2);
            }
            $this->locks[] = $userLock;
            $globalSlot = false;
            for ($slot = 0; $slot < $this->limits['max_concurrent']; ++$slot) {
                $name = $this->repository->lockName('slot:' . $slot);
                if ($this->repository->tryLock($name)) {
                    $this->locks[] = $name;
                    $globalSlot = true;
                    break;
                }
            }
            if (!$globalSlot) {
                throw new GenerationLimitException('generation_global_busy',
                    'O limite de gerações simultâneas foi atingido. Aguarde e tente novamente.', 2);
            }
            $now = $this->clock !== null ? ($this->clock)() : time();
            if (!is_int($now) || $now < 0) {
                throw new LogicException('O relógio da cota deve produzir um timestamp válido.');
            }
            $this->repository->reserve($this->userId, $this->limits, $now);
        } catch (\Throwable $exception) {
            $this->release();
            throw $exception;
        }
    }

    public function release(): void
    {
        foreach (array_reverse($this->locks) as $name) {
            try {
                $this->repository->releaseLock($name);
            } catch (\Throwable) {
                // Não mascara a falha original. A conexão não persistente libera locks ao fechar.
                error_log('generation_lock_release_failed');
            }
        }
        $this->locks = [];
    }

    public function __destruct()
    {
        $this->release();
    }
}
