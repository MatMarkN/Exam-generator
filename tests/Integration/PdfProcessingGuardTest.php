<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Bncc\ImportException;
use App\Application\Bncc\PdfProcessingGuard;
use App\Infrastructure\Documents\ProcessRunner;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use PHPUnit\Framework\TestCase;

final class PdfProcessingGuardTest extends TestCase
{
    public function testASecondWorkerCannotProcessAPdfWhileTheFirstOneIsRunning(): void
    {
        $connections = new DatabaseConnectionFactory(require __DIR__ . '/../../config/database.php');
        $first = new PdfProcessingGuard($connections->create());
        $second = new PdfProcessingGuard($connections->create());
        $called = false;
        $first->run(function () use ($second, &$called): void {
            try {
                $second->run(function () use (&$called): void { $called = true; });
                self::fail('O segundo worker deveria aguardar outra tentativa.');
            } catch (ImportException $exception) {
                self::assertSame('pdf_processing_busy', $exception->reason);
            }
        });
        self::assertFalse($called);
        self::assertSame('available', $second->run(static fn (): string => 'available'));
    }

    public function testTheProcessingSlotIsReleasedWhenExtractionFails(): void
    {
        $connections = new DatabaseConnectionFactory(require __DIR__ . '/../../config/database.php');
        $first = new PdfProcessingGuard($connections->create());
        try {
            $first->run(static fn () => throw new \RuntimeException('Falha SINTÉTICA.'));
        } catch (\RuntimeException $exception) {
            self::assertSame('Falha SINTÉTICA.', $exception->getMessage());
        }
        self::assertTrue((new PdfProcessingGuard($connections->create()))->run(static fn (): bool => true));
    }

    public function testNativeChildrenReceiveAddressSpaceCpuAndFileDescriptorLimits(): void
    {
        $result = (new ProcessRunner())->run(['/bin/sh', '-c', 'ulimit -v; ulimit -t; ulimit -n'], 5, 1024);
        self::assertSame(0, $result['exit_code']);
        self::assertSame(['262144', '60', '128'], explode("\n", trim($result['stdout'])));
    }
}
