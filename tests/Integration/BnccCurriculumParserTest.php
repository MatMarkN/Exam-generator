<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Bncc\Bncc2018Profile;
use App\Application\Bncc\BnccCurriculumParser;
use App\Application\Bncc\ImportException;
use App\Infrastructure\Documents\PopplerLayoutExtractor;
use App\Infrastructure\Documents\ProcessRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\PdfFixture;

final class BnccCurriculumParserTest extends TestCase
{
    #[DataProvider('gradeCases')]
    public function testOfficialYearBlocksAreMappedToExplicitYears(string $code, array $years): void
    {
        self::assertSame($years, (new Bncc2018Profile())->grades($code));
    }

    public static function gradeCases(): array
    {
        return [['EF06MA07', [6]], ['EF15AR01', [1, 2, 3, 4, 5]], ['EF69LP01', [6, 7, 8, 9]],
            ['EF12EF01', [1, 2]], ['EF35LP01', [3, 4, 5]], ['EF67EF01', [6, 7]], ['EF89EF01', [8, 9]]];
    }

    #[DataProvider('invalidCodes')]
    public function testUnknownBlocksAndComponentsAreRejected(string $code): void
    {
        $this->expectException(ImportException::class);
        (new Bncc2018Profile())->grades($code);
    }

    public static function invalidCodes(): array
    {
        return [['EF10MA01'], ['EF00MA01'], ['EF06ZZ01'], ['EM13MAT101'], ['EF01LI01']];
    }

    public function testProfileRequiresTheExactDocumentAndPageCount(): void
    {
        $this->expectException(ImportException::class);
        (new Bncc2018Profile())->assertSupported(['sha256' => hash('sha256', 'outro PDF'), 'page_count' => 600]);
    }

    public function testColumnsAreParsedIndependentlyEvenWhenTheirRowsOverlap(): void
    {
        $parser = new BnccCurriculumParser(new Bncc2018Profile());
        $left = $this->block(['(EF01LP22) Texto sintético da coluna esquerda.', 'Continuação exclusiva do primeiro ano.'], 50, 200);
        $right = $this->block(['(EF02LP22) Texto sintético da coluna direita.', 'Continuação exclusiva do segundo ano.'], 300, 200);
        $pattern = '/^\((EF[0-9]{2}[A-Z]{2}[0-9]{2})\)\s*/';
        $a = $parser->numberedItems($left, $pattern)[0];
        $b = $parser->numberedItems($right, $pattern)[0];
        self::assertStringContainsString('primeiro ano', $a['description']);
        self::assertStringNotContainsString('segundo ano', $a['description']);
        self::assertStringContainsString('segundo ano', $b['description']);
        self::assertStringNotContainsString('primeiro ano', $b['description']);
        self::assertSame(300.0, $b['bbox']['xMin']);
    }

    public function testDetachedCompetencyNumberIsJoinedOnlyToAlignedDescription(): void
    {
        $parser = new BnccCurriculumParser(new Bncc2018Profile());
        $page = ['blocks' => [$this->block(['1.'], 50, 150, 60), $this->block(['Descrição sintética.', 'Continuação.'], 75, 150.2),
            $this->block(['2. Outra competência.'], 50, 200)]];
        $items = $parser->competencyItems($page);
        self::assertSame(['1', '2'], array_column($items, 'key'));
        self::assertSame('Descrição sintética. Continuação.', $items[0]['description']);
        self::assertStringStartsWith('1. Descrição', $items[0]['source_excerpt']);
    }

    public function testAmbiguousDetachedNumberFailsInsteadOfGuessing(): void
    {
        $parser = new BnccCurriculumParser(new Bncc2018Profile());
        $this->expectException(ImportException::class);
        $parser->competencyItems(['blocks' => [$this->block(['1.'], 50, 150, 60),
            $this->block(['Texto A.'], 75, 150.2), $this->block(['Texto B.'], 80, 150.2)]]);
    }

    public function testSharedKnowledgeObjectIsLinkedAcrossSeveralSkillRows(): void
    {
        $parser = new BnccCurriculumParser(new Bncc2018Profile());
        $left = ['height' => 842, 'blocks' => [$this->block(['UNIDADES TEMÁTICAS'], 50, 100),
            $this->block(['OBJETOS DE CONHECIMENTO'], 300, 100), $this->block(['Números'], 50, 180),
            $this->block(['Frações: exemplo sintético.'], 300, 200), $this->block(['Decimais: exemplo sintético.'], 300, 300)]];
        $skills = array_map(static fn (int $y): array => ['page_number' => 303, 'bbox' => ['xMin' => 50, 'yMin' => $y]], [200, 240, 300]);
        $context = $parser->context($left, $skills[1], $skills);
        self::assertSame('Frações: exemplo sintético.', $context['knowledge_objects']);
        self::assertSame('Números', $context['thematic_unit']);
        self::assertSame(302, $context['page_start']);
        self::assertSame(303, $context['page_end']);
    }

    public function testAnIncompleteExtractionCannotPassTheProfileCoverageChecks(): void
    {
        $this->expectException(ImportException::class);
        (new BnccCurriculumParser(new Bncc2018Profile()))->parse(['pages' => [], 'removed_control_characters' => 0, 'parser_reported_warnings' => false]);
    }

    public function testLayoutExtractorUsesRealPopplerAndKeepsPhysicalPageNumbers(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bncc-layout-test-');
        try {
            file_put_contents($path, PdfFixture::bytes(['SINTETICO pagina 1', 'SINTETICO pagina 2']));
            $layout = (new PopplerLayoutExtractor(new ProcessRunner()))->extract($path, 2, 2);
            self::assertSame([2], array_keys($layout['pages']));
            self::assertStringContainsString('pagina 2', $layout['pages'][2]['blocks'][0]['text']);
            self::assertSame(0, $layout['removed_control_characters']);
            self::assertStringContainsString('/bbox-layout-cropbox-v1', $layout['extractor_version']);
        } finally {
            unlink($path);
        }
    }

    private function block(array $texts, float $x, float $y, float $right = 500): array
    {
        $lines = [];
        foreach ($texts as $i => $text) {
            $lines[] = ['text' => $text, 'xMin' => $x, 'yMin' => $y + 12 * $i, 'xMax' => $right, 'yMax' => $y + 12 * $i + 10];
        }
        return ['text' => implode("\n", $texts), 'xMin' => $x, 'yMin' => $y, 'xMax' => $right,
            'yMax' => end($lines)['yMax'], 'lines' => $lines];
    }
}
