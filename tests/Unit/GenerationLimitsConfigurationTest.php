<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GenerationLimitsConfigurationTest extends TestCase
{
    private const VARIABLES = ['GENERATION_USER_DAILY_LIMIT', 'GENERATION_GLOBAL_DAILY_LIMIT',
        'GENERATION_MIN_INTERVAL_SECONDS', 'GENERATION_MAX_CONCURRENT'];
    private array $previous = [];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name) {
            $this->previous[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->previous as $name => $value) {
            putenv($value === false ? $name : $name . '=' . $value);
        }
    }

    public function testDefaultsAreFinitePositiveLimits(): void
    {
        self::assertSame(['user_daily_limit' => 20, 'global_daily_limit' => 100,
            'min_interval_seconds' => 10, 'max_concurrent' => 2], require __DIR__ . '/../../config/generation-limits.php');
    }

    #[DataProvider('invalidLimits')]
    public function testInvalidConfigurationFailsClosedInsteadOfDisablingLimits(string $name, string $value): void
    {
        putenv($name . '=' . $value);
        $this->expectException(\RuntimeException::class);
        require __DIR__ . '/../../config/generation-limits.php';
    }

    public static function invalidLimits(): array
    {
        return [
            ['GENERATION_USER_DAILY_LIMIT', '0'], ['GENERATION_GLOBAL_DAILY_LIMIT', '-1'],
            ['GENERATION_MAX_CONCURRENT', '33'], ['GENERATION_MAX_CONCURRENT', 'unlimited'],
            ['GENERATION_MIN_INTERVAL_SECONDS', '3601'], ['GENERATION_USER_DAILY_LIMIT', '1.5'],
            ['GENERATION_GLOBAL_DAILY_LIMIT', '1000001'], ['GENERATION_USER_DAILY_LIMIT', ' 20'],
        ];
    }
}
