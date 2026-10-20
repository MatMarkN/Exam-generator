<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\AI\AIProviderInterface;
use App\Application\AI\AIRequest;
use App\Application\AI\AIResponse;
use App\Application\Bncc\BnccContextService;
use App\Application\Bncc\BnccTopicMatcher;
use App\Application\Bncc\ImportException;
use App\Application\Exams\ExamGenerationService;
use App\Application\Exams\GenerationGuard;
use App\Application\Exams\GenerationLimitException;
use App\Infrastructure\AI\FakeAIProvider;
use App\Infrastructure\Persistence\BnccContextRepository;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use App\Infrastructure\Persistence\GenerationQuotaRepository;
use Medoo\Medoo;
use PHPUnit\Framework\TestCase;
use Tests\Support\BnccFixtures;
use Tests\Support\CurriculumFixture;
use Tests\Support\ExamFixture;

final class GenerationQuotaTest extends TestCase
{
    private Medoo $database;
    private Medoo $other;
    private int $now;
    private int $userId;
    private array $guards = [];
    private array $documentIds = [];
    private const LIMITS = ['user_daily_limit' => 2, 'global_daily_limit' => 4, 'min_interval_seconds' => 10, 'max_concurrent' => 2];

    protected function setUp(): void
    {
        $connections = new DatabaseConnectionFactory(require __DIR__ . '/../../config/database.php');
        $this->database = $connections->create();
        $this->other = $connections->create();
        // Dia e IDs exclusivos de fixtures; não compartilha contadores com testes HTTP de autenticação.
        $this->now = 2209032000 + random_int(0, 100000) * 86400;
        $this->userId = random_int(1000000, 2000000);
    }

    protected function tearDown(): void
    {
        foreach ($this->guards as $guard) {
            $guard->release();
        }
        foreach ($this->documentIds as $documentId) {
            (new CurriculumFixture($this->database))->remove($documentId);
        }
        $this->database->delete('generation_quota_days', ['quota_date' => [gmdate('Y-m-d', $this->now), gmdate('Y-m-d', $this->now + 86400)]]);
        $this->database->delete('generation_quota_users', ['user_id' => [$this->userId, $this->userId + 1, $this->userId + 2]]);
    }

    public function testDailyUserLimitIsDurableAcrossConnectionsAndRejectedAttemptDoesNotSpendGlobalQuota(): void
    {
        $guard = $this->guard();
        $guard->acquire();
        self::assertFalse($this->database->pdo->inTransaction());
        $guard->release();
        $this->now += 10;
        $this->guard(database: $this->other)->acquire();
        $this->guards[array_key_last($this->guards)]->release();
        $this->now += 10;
        $failure = $this->expectLimit('generation_daily_user_limit', fn () => $guard->acquire());
        self::assertSame((intdiv($this->now, 86400) + 1) * 86400 - $this->now, $failure->retryAfterSeconds);
        self::assertSame(2, $this->quotaCount('global', 0));
        self::assertSame(2, $this->quotaCount('user', $this->userId));
    }

    public function testGlobalDailyQuotaCannotBeBypassedUsingAnotherUser(): void
    {
        $limits = [...self::LIMITS, 'global_daily_limit' => 2];
        foreach ([$this->userId, $this->userId + 1] as $userId) {
            $guard = $this->guard($userId, $limits);
            $guard->acquire();
            $guard->release();
        }
        $this->expectLimit('generation_daily_global_limit', fn () => $this->guard($this->userId + 2, $limits)->acquire());
        self::assertSame(2, $this->quotaCount('global', 0));
        self::assertSame(0, $this->quotaCount('user', $this->userId + 2));
    }

    public function testMinimumIntervalSurvivesNewConnectionAndRejectedCallDoesNotIncrementQuota(): void
    {
        $guard = $this->guard();
        $guard->acquire();
        $guard->release();
        $this->now += 3;
        $failure = $this->expectLimit('generation_interval_limit', fn () => $this->guard(database: $this->other)->acquire());
        self::assertSame(7, $failure->retryAfterSeconds);
        self::assertSame(1, $this->quotaCount('global', 0));
        self::assertFalse($this->other->pdo->inTransaction());
        $this->now += 7;
        $other = $this->guard(database: $this->other);
        $other->acquire();
        $other->release();
        self::assertSame(2, $this->quotaCount('global', 0));
    }

    public function testDailyWindowUsesUtcAndIntervalIsEnforcedAcrossMidnight(): void
    {
        $this->now = intdiv($this->now, 86400) * 86400 + 86398;
        $limits = [...self::LIMITS, 'user_daily_limit' => 1];
        $guard = $this->guard(limits: $limits);
        $guard->acquire();
        $guard->release();
        $previousDay = gmdate('Y-m-d', $this->now);
        $this->now += 4;
        $this->expectLimit('generation_interval_limit', fn () => $guard->acquire());
        $this->now += 6;
        $guard->acquire();
        $guard->release();
        self::assertSame(1, $this->quotaCount('user', $this->userId));
        self::assertSame(1, $this->database->get('generation_quota_days', 'requests', ['quota_date' => $previousDay, 'scope' => 'user', 'user_id' => $this->userId]));
        $this->database->delete('generation_quota_days', ['quota_date' => $previousDay]);
    }

    public function testOnlyOneGenerationForTheUserCanHoldASlotAcrossConnections(): void
    {
        $first = $this->guard();
        $first->acquire();
        $this->expectLimit('generation_user_busy', fn () => $this->guard(database: $this->other)->acquire());
        self::assertSame(1, $this->quotaCount('global', 0));
        $first->release();
        $this->now += 10;
        $next = $this->guard(database: $this->other);
        $next->acquire();
        $next->release();
    }

    public function testGlobalSlotsBoundConcurrentUsersAndFailureReleasesTheRejectedUserLock(): void
    {
        $limits = [...self::LIMITS, 'max_concurrent' => 1];
        $first = $this->guard(limits: $limits);
        $first->acquire();
        $second = $this->guard($this->userId + 1, $limits, $this->other);
        $this->expectLimit('generation_global_busy', fn () => $second->acquire());
        self::assertSame(0, $this->quotaCount('user', $this->userId + 1));
        $first->release();
        $second->acquire();
        $second->release();
    }

    public function testTwoGuardsSharingAConnectionCannotReenterAnAlreadyOccupiedSlot(): void
    {
        $first = $this->guard();
        $first->acquire();
        $this->expectLimit('generation_user_busy', fn () => $this->guard()->acquire());
        $this->expectLimit('generation_global_busy', fn () => $this->guard($this->userId + 1, [...self::LIMITS, 'max_concurrent' => 1])->acquire());
        $first->release();
    }

    public function testServiceDoesNotConsumeQuotaWhenTheContextFailsBeforeProvider(): void
    {
        $fixtures = new BnccFixtures($this->database);
        $documentId = $fixtures->document();
        $this->documentIds[] = $documentId;
        $fake = new FakeAIProvider(json_encode(ExamFixture::response(), JSON_THROW_ON_ERROR));
        try {
            $this->service($fake)->generate(ExamFixture::input(['bncc_document_id' => $documentId]));
            self::fail('A revisão não liberada deveria falhar.');
        } catch (ImportException) {
            self::assertSame(0, $fake->callCount());
            self::assertSame(0, $this->quotaCount('global', 0));
        }
    }

    public function testProviderErrorKeepsAttemptCountButFinallyReleasesBothLocksAndNoTransactionSurroundsProvider(): void
    {
        $documentId = $this->readyCurriculum();
        $database = $this->database;
        $provider = new class($database) implements AIProviderInterface {
            public function __construct(private Medoo $database) {}
            public function generate(AIRequest $request): AIResponse
            {
                TestCase::assertFalse($this->database->pdo->inTransaction());
                throw new \RuntimeException('Falha SINTÉTICA depois da admissão.');
            }
        };
        try {
            $this->service($provider)->generate(ExamFixture::input(['bncc_document_id' => $documentId]));
            self::fail('O fornecedor sintético deveria falhar.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Falha SINTÉTICA depois da admissão.', $exception->getMessage());
        }
        self::assertSame(1, $this->quotaCount('user', $this->userId));
        $this->now += 10;
        $next = $this->guard(database: $this->other);
        $next->acquire();
        $next->release();
        self::assertSame(2, $this->quotaCount('user', $this->userId));
    }

    private function guard(?int $userId = null, ?array $limits = null, ?Medoo $database = null): GenerationGuard
    {
        $guard = new GenerationGuard(new GenerationQuotaRepository($database ?? $this->database), $userId ?? $this->userId,
            $limits ?? self::LIMITS, fn (): int => $this->now);
        $this->guards[] = $guard;
        return $guard;
    }

    private function service(AIProviderInterface $provider): ExamGenerationService
    {
        return new ExamGenerationService(new BnccContextService(new BnccContextRepository($this->database), new BnccTopicMatcher()),
            $provider, guard: $this->guard());
    }

    private function readyCurriculum(): int
    {
        $fixtures = new BnccFixtures($this->database);
        $documentId = $fixtures->document(['status' => 'ready', 'ready_at' => '2026-01-01 00:00:00',
            'structured_at' => '2026-01-01 00:00:00', 'structure_profile' => 'synthetic-quota-fixture']);
        $this->documentIds[] = $documentId;
        $section = $fixtures->section($documentId);
        $area = $fixtures->area($documentId, $section, ['code' => 'matematica']);
        $curriculum = ['document_id' => $documentId, 'section_id' => $section, 'area_id' => $area,
            'component_id' => $fixtures->component($documentId, $area, $section, 'matematica')];
        $skillId = $fixtures->skill($curriculum, ['code' => 'EF06MA07', 'description' => 'Frações SINTÉTICAS.',
            'review_status' => 'approved', 'reviewed_at' => '2026-01-01 00:00:00']);
        $this->database->insert('bncc_skill_grades', ['skill_id' => $skillId,
            'school_grade_id' => $this->database->get('school_grades', 'id', ['year_number' => 6])]);
        $fixtures->context($curriculum, $skillId);
        $fixtures->competency($curriculum, overrides: ['review_status' => 'approved', 'reviewed_at' => '2026-01-01 00:00:00']);
        return $documentId;
    }

    private function quotaCount(string $scope, int $userId): int
    {
        return (int) ($this->database->get('generation_quota_days', 'requests',
            ['quota_date' => gmdate('Y-m-d', $this->now), 'scope' => $scope, 'user_id' => $userId]) ?? 0);
    }

    private function expectLimit(string $reason, callable $operation): GenerationLimitException
    {
        try {
            $operation();
            self::fail('A geração deveria ter sido limitada.');
        } catch (GenerationLimitException $exception) {
            self::assertSame($reason, $exception->reason);
            self::assertGreaterThan(0, $exception->retryAfterSeconds);
            return $exception;
        }
    }
}
