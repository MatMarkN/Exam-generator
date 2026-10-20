<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Bncc\ImportException;
use App\Infrastructure\Documents\LocalPdfStorage;
use App\Infrastructure\Documents\PopplerPdfExtractor;
use App\Infrastructure\Documents\ProcessRunner;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\UploadedFile;
use Tests\Support\PdfFixture;

final class PdfProcessingTest extends TestCase
{
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testExtractsPhysicalPagesUnicodeAndBlankPagesWithoutInventingSkills(): void
    {
        $pdf = $this->file(PdfFixture::bytes(['SINTÉTICO: Frações (EF06MA07)', '', 'SINTÉTICO: Artes (EF15AR01)']));
        $result = (new PopplerPdfExtractor(new ProcessRunner()))->extract($pdf);
        self::assertCount(3, $result['pages']);
        self::assertSame([1, 2, 3], array_column($result['pages'], 'page_number'));
        self::assertStringContainsString('Frações', $result['pages'][0]['source_text']);
        self::assertSame(['EF06MA07'], $result['pages'][0]['detected_skill_codes']);
        self::assertSame('', $result['pages'][1]['source_text']);
        self::assertSame([2], $result['warnings']['empty_pages']);
        self::assertSame(['EF15AR01'], $result['pages'][2]['detected_skill_codes']);
        self::assertTrue($result['warnings']['requires_curriculum_review']);
        self::assertFalse($result['warnings']['parser_reported_warnings']);
    }

    public function testExtractsSixHundredPagesWithStableNumbering(): void
    {
        $texts = array_map(static fn (int $page): string => "SINTETICO pagina $page", range(1, 600));
        $result = (new PopplerPdfExtractor(new ProcessRunner()))->extract($this->file(PdfFixture::bytes($texts)));
        self::assertCount(600, $result['pages']);
        self::assertStringContainsString('pagina 600', $result['pages'][599]['source_text']);
    }

    public function testRejectsAnUnreadablePdfEvenWithTheRightSignature(): void
    {
        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('Não foi possível ler');
        (new PopplerPdfExtractor(new ProcessRunner()))->extract($this->file("%PDF-1.7\nbroken\n%%EOF"));
    }

    public function testRejectsDocumentsWithoutExtractableText(): void
    {
        try {
            (new PopplerPdfExtractor(new ProcessRunner()))->extract($this->file(PdfFixture::bytes(['', ''])));
            self::fail('Um PDF sem texto não deve virar contexto curricular.');
        } catch (ImportException $exception) {
            self::assertSame('no_text', $exception->reason);
        }
    }

    public function testChecksPageLimitBeforeExtractingTheWholeDocument(): void
    {
        try {
            (new PopplerPdfExtractor(new ProcessRunner(), 1))->extract($this->file(PdfFixture::bytes(['a', 'b'])));
            self::fail('O limite de páginas precisa ser aplicado.');
        } catch (ImportException $exception) {
            self::assertSame('page_limit', $exception->reason);
        }
    }

    public function testProcessArgumentsAreLiteralWithoutAShell(): void
    {
        $literal = 'arquivo ; echo indevido $(touch /tmp/nao-executar)';
        $result = (new ProcessRunner())->run([PHP_BINARY, '-r', 'echo $argv[1];', $literal], 2, 4096);
        self::assertSame(0, $result['exit_code']);
        self::assertSame($literal, $result['stdout']);
    }

    public function testProcessTimeoutTerminatesTheChild(): void
    {
        $start = microtime(true);
        try {
            (new ProcessRunner())->run([PHP_BINARY, '-r', 'sleep(5);'], 0.1, 4096);
            self::fail('Era esperado um timeout.');
        } catch (ImportException $exception) {
            self::assertSame('extraction_timeout', $exception->reason);
            self::assertLessThan(2, microtime(true) - $start);
        }
    }

    public function testProcessOutputIsBounded(): void
    {
        try {
            (new ProcessRunner())->run([PHP_BINARY, '-r', 'echo str_repeat("x", 10000);'], 2, 100);
            self::fail('A saída deveria exceder o limite.');
        } catch (ImportException $exception) {
            self::assertSame('extraction_limit', $exception->reason);
        }
    }

    public function testUploadSizeErrorIsReportedBeforeReadingFile(): void
    {
        $storage = new LocalPdfStorage('/tmp/not-used-by-size-test');
        try {
            $storage->receive(new UploadedFile($this->file(''), 'x.pdf', 'application/pdf', 0, UPLOAD_ERR_INI_SIZE));
            self::fail('O upload deveria ser rejeitado.');
        } catch (ImportException $exception) {
            self::assertSame('file_too_large', $exception->reason);
        }
    }

    private function file(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bncc-fixture-');
        file_put_contents($path, $bytes);
        $this->paths[] = $path;
        return $path;
    }
}
