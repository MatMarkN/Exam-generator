<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Bncc\ImportBnccDocument;
use App\Application\Bncc\ImportException;
use App\Infrastructure\Documents\LocalPdfStorage;
use App\Infrastructure\Documents\PopplerPdfExtractor;
use App\Infrastructure\Documents\ProcessRunner;
use App\Infrastructure\Persistence\BnccDocumentRepository;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Medoo\Medoo;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\UploadedFile;
use Tests\Support\PdfFixture;

final class BnccImportTest extends TestCase
{
    private Medoo $database;
    private BnccDocumentRepository $repository;
    private ImportBnccDocument $service;
    private string $root;
    private array $inputFiles = [];
    private array $ids = [];

    protected function setUp(): void
    {
        $this->database = (new DatabaseConnectionFactory(require __DIR__ . '/../../config/database.php'))->create();
        $this->repository = new BnccDocumentRepository($this->database);
        $this->root = sys_get_temp_dir() . '/bncc-import-test-' . bin2hex(random_bytes(8));
        $this->service = new ImportBnccDocument(
            new LocalPdfStorage($this->root), new PopplerPdfExtractor(new ProcessRunner()), $this->repository,
        );
    }

    protected function tearDown(): void
    {
        if ($this->ids !== []) {
            $this->database->delete('bncc_document_pages', ['document_id' => $this->ids]);
            $this->database->delete('bncc_documents', ['id' => $this->ids]);
        }
        foreach ([...$this->inputFiles, ...(glob($this->root . '/*') ?: [])] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function testStoresOriginalBytesHashAndEveryPageInADraft(): void
    {
        $bytes = PdfFixture::bytes(['SINTÉTICO: Frações EF06MA07', '', 'SINTÉTICO: final']);
        $document = $this->import($bytes, '../../original.pdf');
        self::assertSame('draft', $document['status']);
        self::assertNull($document['ready_at']);
        self::assertSame(3, $document['page_count']);
        self::assertSame(1, $document['revision']);
        self::assertSame(hash('sha256', $bytes), $document['sha256']);
        self::assertSame(strlen($bytes), $document['file_size']);
        self::assertSame('original.pdf', $document['original_filename']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}\.pdf$/', $document['storage_path']);
        self::assertSame($bytes, file_get_contents($this->root . '/' . $document['storage_path']));
        $pages = $this->service->pages($document['id'], 2, 1);
        self::assertSame(2, $pages['pages'][0]['page_number']);
        self::assertSame('', $pages['pages'][0]['source_text']);
        self::assertSame(3, $pages['next_page']);
        self::assertFalse($this->database->has('bncc_skills', ['document_id' => $document['id']]));
    }

    public function testDuplicateUploadReturnsExistingIdAndDoesNotLeaveAnotherFile(): void
    {
        $bytes = PdfFixture::bytes(['SINTETICO ' . bin2hex(random_bytes(5))]);
        $document = $this->import($bytes);
        try {
            $this->import($bytes);
            self::fail('Não deve duplicar o mesmo documento.');
        } catch (ImportException $exception) {
            self::assertSame('duplicate_document', $exception->reason);
            self::assertSame($document['id'], $exception->details['document_id']);
        }
        self::assertCount(1, glob($this->root . '/*'));
        self::assertSame(1, $this->database->count('bncc_documents', ['sha256' => $document['sha256']]));
    }

    public function testReprocessCreatesADraftWithoutChangingAPreviousReadyRevision(): void
    {
        $old = $this->import(PdfFixture::bytes(['SINTETICO ' . bin2hex(random_bytes(5))]));
        $this->database->update('bncc_documents', ['status' => 'ready', 'ready_at' => '2026-09-24 12:00:00'], ['id' => $old['id']]);
        $before = $this->service->get($old['id']);
        $pages = $this->service->pages($old['id'], 1, 20);
        $new = $this->service->reprocess($old['id']);
        $this->ids[] = $new['id'];
        self::assertSame(2, $new['revision']);
        self::assertSame('draft', $new['status']);
        self::assertSame($old['sha256'], $new['sha256']);
        self::assertSame($old['storage_path'], $new['storage_path']);
        self::assertSame($before, $this->service->get($old['id']));
        self::assertSame($pages, $this->service->pages($old['id'], 1, 20));
        self::assertCount(1, glob($this->root . '/*'));
    }

    public function testReprocessRefusesAChangedOriginalFile(): void
    {
        $document = $this->import(PdfFixture::bytes(['SINTETICO ' . bin2hex(random_bytes(5))]));
        file_put_contents($this->root . '/' . $document['storage_path'], 'changed');
        try {
            $this->service->reprocess($document['id']);
            self::fail('A integridade do arquivo precisa ser conferida.');
        } catch (ImportException $exception) {
            self::assertSame('stored_file_changed', $exception->reason);
        }
        self::assertSame(1, $this->database->count('bncc_documents', ['sha256' => $document['sha256']]));
    }

    public function testUnreadablePdfLeavesNoRecordOrFile(): void
    {
        $before = $this->database->count('bncc_documents');
        try {
            $this->import("%PDF-1.7\nbroken\n%%EOF");
            self::fail('Era esperada rejeição do PDF.');
        } catch (ImportException $exception) {
            self::assertSame('invalid_pdf', $exception->reason);
        }
        self::assertSame($before, $this->database->count('bncc_documents'));
        self::assertSame([], glob($this->root . '/*'));
    }

    public function testFileContentWinsOverFilenameAndDeclaredMimeType(): void
    {
        try {
            $this->import('Este arquivo não é um PDF.', 'bncc.pdf');
            self::fail('A extensão não comprova o formato.');
        } catch (ImportException $exception) {
            self::assertSame('invalid_pdf', $exception->reason);
        }
        self::assertSame([], glob($this->root . '/*'));
    }

    public function testRepositoryRollsBackDocumentAndEarlierBatchesWhenAPageFails(): void
    {
        $hash = hash('sha256', random_bytes(16));
        $pages = array_fill(0, 26, ['page_number' => 1, 'source_text' => 'SINTETICO', 'detected_skill_codes' => []]);
        foreach ($pages as $index => &$page) {
            $page['page_number'] = $index + 1;
        }
        unset($page);
        $pages[25]['page_number'] = 0;
        try {
            $this->repository->create([
                'title' => 'SINTETICO', 'version_label' => 'teste', 'revision' => 1,
                'original_filename' => 'test.pdf', 'storage_path' => 'test.pdf', 'sha256' => $hash, 'file_size' => 1,
            ], ['pages' => $pages, 'extractor_version' => 'test', 'warnings' => ['test' => true]]);
            self::fail('A página inválida deveria falhar.');
        } catch (\PDOException) {
            self::assertFalse($this->database->has('bncc_documents', ['sha256' => $hash]));
            self::assertFalse($this->database->pdo->inTransaction());
        }
    }

    private function import(string $bytes, string $filename = 'test.pdf'): array
    {
        $path = tempnam(sys_get_temp_dir(), 'bncc-upload-');
        $this->inputFiles[] = $path;
        file_put_contents($path, $bytes);
        $document = $this->service->import(new UploadedFile($path, $filename, 'application/pdf', strlen($bytes)), 'Documento SINTÉTICO', 'teste');
        $this->ids[] = $document['id'];
        return $document;
    }
}
