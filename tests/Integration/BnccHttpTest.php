<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Medoo\Medoo;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;
use Tests\Support\PdfFixture;

final class BnccHttpTest extends TestCase
{
    private App $app;
    private Medoo $database;
    private array $ids = [];
    private array $inputFiles = [];

    protected function setUp(): void
    {
        $this->app = require __DIR__ . '/../../config/bootstrap.php';
        $this->database = (new DatabaseConnectionFactory(require __DIR__ . '/../../config/database.php'))->create();
    }

    protected function tearDown(): void
    {
        if ($this->ids !== []) {
            $paths = $this->database->select('bncc_documents', 'storage_path', ['id' => $this->ids]);
            $this->database->delete('bncc_document_pages', ['document_id' => $this->ids]);
            $this->database->delete('bncc_documents', ['id' => $this->ids]);
            foreach (array_unique($paths) as $path) {
                unlink(__DIR__ . '/../../storage/bncc/' . $path);
            }
        }
        foreach ($this->inputFiles as $path) {
            unlink($path);
        }
    }

    public function testUploadMetadataPagesAndReprocessingThroughTheRegisteredRoutes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bncc-http-');
        $this->inputFiles[] = $path;
        file_put_contents($path, PdfFixture::bytes(['SINTÉTICO: Frações EF06MA07 ' . bin2hex(random_bytes(4))]));
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/bncc/documents')
            ->withHeader('Content-Type', 'multipart/form-data; boundary=test')
            ->withParsedBody(['title' => 'SINTÉTICO HTTP', 'version_label' => 'teste'])
            ->withUploadedFiles(['pdf' => new UploadedFile($path, 'sample.pdf', 'application/pdf', filesize($path))]);
        $response = $this->app->handle($request);
        $document = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        if (isset($document['id'])) {
            $this->ids[] = $document['id'];
        }
        self::assertSame(201, $response->getStatusCode(), json_encode($document));
        self::assertArrayNotHasKey('storage_path', $document);
        self::assertSame('draft', $document['status']);
        self::assertSame('/bncc/documents/' . $document['id'], $response->getHeaderLine('Location'));

        $pages = $this->app->handle((new ServerRequestFactory())->createServerRequest('GET', '/bncc/documents/' . $document['id'] . '/pages'));
        self::assertSame(200, $pages->getStatusCode());
        $body = json_decode((string) $pages->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['EF06MA07'], $body['pages'][0]['detected_skill_codes']);

        $duplicate = $this->app->handle($request);
        self::assertSame(409, $duplicate->getStatusCode());

        $reprocessed = $this->app->handle((new ServerRequestFactory())->createServerRequest('POST', '/bncc/documents/' . $document['id'] . '/reprocess'));
        $new = json_decode((string) $reprocessed->getBody(), true, flags: JSON_THROW_ON_ERROR);
        if (isset($new['id'])) {
            $this->ids[] = $new['id'];
        }
        self::assertSame(201, $reprocessed->getStatusCode(), json_encode($new));
        self::assertSame(2, $new['revision']);
        self::assertSame('draft', $new['status']);
    }

    public function testMissingFieldsWrongMediaAndOversizeRequestsHaveSpecificJsonErrors(): void
    {
        $factory = new ServerRequestFactory();
        $cases = [
            [$factory->createServerRequest('POST', '/bncc/documents')->withHeader('Content-Type', 'application/json'), 415],
            [$factory->createServerRequest('POST', '/bncc/documents')->withHeader('Content-Type', 'multipart/form-data; boundary=x'), 422],
            [$factory->createServerRequest('POST', '/bncc/documents')->withHeader('Content-Length', (string) (53 * 1024 * 1024)), 413],
            [$factory->createServerRequest('GET', '/bncc/documents/4294967295'), 404],
            [$factory->createServerRequest('GET', '/bncc/documents/0'), 422],
            [$factory->createServerRequest('GET', '/bncc/documents/1/pages')->withQueryParams(['limit' => ['1']]), 422],
            [$factory->createServerRequest('GET', '/storage/bncc/document.pdf'), 404],
        ];
        foreach ($cases as [$request, $expected]) {
            $response = $this->app->handle($request);
            self::assertSame($expected, $response->getStatusCode());
            self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
            self::assertIsArray(json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR));
        }
    }
}
