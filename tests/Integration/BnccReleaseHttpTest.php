<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Bncc\Bncc2018Profile;
use App\Infrastructure\Persistence\BnccCurriculumRepository;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Medoo\Medoo;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\CurriculumFixture;
use Tests\Support\OpenApiAssertions;

final class BnccReleaseHttpTest extends TestCase
{
    use OpenApiAssertions;

    private App $app;
    private Medoo $database;
    private CurriculumFixture $fixture;
    private BnccCurriculumRepository $repository;
    private int $documentId;

    protected function setUp(): void
    {
        $this->app = require __DIR__ . '/../../config/bootstrap.php';
        $this->database = (new DatabaseConnectionFactory(require __DIR__ . '/../../config/database.php'))->create();
        $this->fixture = new CurriculumFixture($this->database);
        $this->repository = new BnccCurriculumRepository($this->database);
        $document = $this->fixture->document();
        $this->documentId = $document['id'];
        // Somente dados SINTÉTICOS: dois anos de Matemática e os grupos de competências do perfil.
        $plan = $this->fixture->plan(true);
        $skills = array_values(array_filter($plan['skills'], static fn (array $skill): bool => $skill['component'] === 'MA'));
        $plan['skills'] = array_slice($skills, 0, 3);
        foreach (['EF06MA01', 'EF06MA02', 'EF07MA01'] as $index => $code) {
            $plan['skills'][$index]['code'] = $code;
            $plan['skills'][$index]['grades'] = [(int) substr($code, 2, 2)];
            $plan['skills'][$index]['source_excerpt'] = '(' . $code . ') Descrição SINTÉTICA de teste.';
        }
        $plan['report']['skills'] = 3;
        $this->repository->save($this->documentId, $document['sha256'], $plan, new Bncc2018Profile());
    }

    protected function tearDown(): void
    {
        if (isset($this->documentId)) {
            $this->fixture->remove($this->documentId);
        }
    }

    public function testListAndFiltersExposeOnlyTheSelectedScopeWithoutApprovingAnything(): void
    {
        [$status, $documents] = $this->request('GET', '/bncc/documents');
        self::assertSame(200, $status);
        $this->assertOpenApiSchema('BnccDocumentList', $documents);
        $ids = array_column($documents['items'], 'id');
        self::assertContains($this->documentId, $ids);
        $sorted = $ids; rsort($sorted);
        self::assertSame($sorted, $ids);
        foreach ($documents['items'] as $document) {
            self::assertArrayNotHasKey('storage_path', $document);
        }

        [$status, $releases] = $this->request('GET', $this->path('/releases'));
        self::assertSame(200, $status);
        $this->assertOpenApiSchema('BnccReleaseList', $releases);
        $mathSix = $this->scope($releases, 'matematica', 6);
        self::assertSame('pending', $mathSix['status']);
        self::assertNull($mathSix['released_at']);
        self::assertSame(2, $mathSix['review']['skills']['pending']);
        self::assertSame(18, $mathSix['review']['competencies']['pending']);

        [$status, $skills] = $this->request('GET', $this->path('/skills'), query: ['subject' => 'matematica', 'grade' => '6', 'limit' => '50']);
        self::assertSame(200, $status);
        $this->assertOpenApiSchema('Skills', $skills);
        self::assertSame(2, $skills['total']);
        self::assertSame(['EF06MA01', 'EF06MA02'], array_column($skills['skills'], 'code'));
        self::assertSame([false, false], array_column($skills['skills'], 'locked'));
        [$status, $skill] = $this->request('GET', $this->path('/skills/EF06MA01'));
        self::assertSame(200, $status);
        $this->assertOpenApiSchema('Skill', $skill);
        self::assertFalse($skill['locked']);
        [$status, $competencies] = $this->request('GET', $this->path('/competencies'), query: ['subject' => 'matematica', 'grade' => '6']);
        self::assertSame(200, $status);
        $this->assertOpenApiSchema('Competencies', $competencies);
        self::assertCount(18, $competencies['competencies']);
        self::assertSame(['general', 'area'], array_values(array_unique(array_column($competencies['competencies'], 'scope'))));
        self::assertSame(array_fill(0, 18, false), array_column($competencies['competencies'], 'locked'));

        [$status, $error] = $this->request('POST', $this->path('/releases'), ['subject' => 'matematica', 'grade' => 6]);
        self::assertSame(409, $status);
        self::assertSame('review_incomplete', $error['error']['code']);
        self::assertSame('pending', $this->repository->skill($this->documentId, 'EF06MA01')['review_status']);
    }

    public function testPublishingOneReviewedScopeLeavesOtherYearsPendingAndFreezesSharedItems(): void
    {
        foreach (['EF06MA01', 'EF06MA02'] as $code) {
            [$status] = $this->request('PATCH', $this->path('/skills/' . $code . '/review'), ['status' => 'approved', 'note' => 'Conferência de fixture SINTÉTICA.']);
            self::assertSame(200, $status);
        }
        $competencies = $this->repository->competencies($this->documentId, 'matematica')['competencies'];
        foreach ($competencies as $competency) {
            [$status] = $this->request('PATCH', $this->path('/competencies/' . $competency['id'] . '/review'), ['status' => 'approved', 'note' => 'Conferência de fixture SINTÉTICA.']);
            self::assertSame(200, $status);
        }
        [$status, $release] = $this->request('POST', $this->path('/releases'), ['subject' => 'matematica', 'grade' => 6]);
        self::assertSame(200, $status, json_encode($release));
        $this->assertOpenApiSchema('BnccRelease', $release);
        self::assertSame('ready', $release['status']);
        self::assertSame($this->documentId, $release['document_id']);
        self::assertSame('draft', $this->repository->overview($this->documentId)['status']);
        [, $releases] = $this->request('GET', $this->path('/releases'));
        self::assertSame('ready', $this->scope($releases, 'matematica', 6)['status']);
        self::assertSame('pending', $this->scope($releases, 'matematica', 7)['status']);
        [, $releasedSkills] = $this->request('GET', $this->path('/skills'), query: ['subject' => 'matematica', 'grade' => '6']);
        $this->assertOpenApiSchema('Skills', $releasedSkills);
        self::assertSame([true, true], array_column($releasedSkills['skills'], 'locked'));
        [, $releasedDetail] = $this->request('GET', $this->path('/skills/EF06MA01'));
        $this->assertOpenApiSchema('Skill', $releasedDetail);
        self::assertTrue($releasedDetail['locked']);
        [, $otherSkills] = $this->request('GET', $this->path('/skills'), query: ['subject' => 'matematica', 'grade' => '7']);
        self::assertSame([false], array_column($otherSkills['skills'], 'locked'));
        [, $sharedCompetencies] = $this->request('GET', $this->path('/competencies'), query: ['subject' => 'matematica', 'grade' => '7']);
        $this->assertOpenApiSchema('Competencies', $sharedCompetencies);
        self::assertSame(array_fill(0, 18, true), array_column($sharedCompetencies['competencies'], 'locked'));

        foreach (['/skills/EF06MA01/review', '/competencies/' . $competencies[0]['id'] . '/review'] as $suffix) {
            [$status, $error] = $this->request('PATCH', $this->path($suffix), ['status' => 'rejected', 'note' => 'Tentativa sobre item já liberado.']);
            self::assertSame(409, $status);
            self::assertSame('immutable_document', $error['error']['code']);
        }
        [$status] = $this->request('PATCH', $this->path('/skills/EF07MA01/review'), ['status' => 'approved', 'note' => 'Conferência de outro ano ainda editável.']);
        self::assertSame(200, $status);
    }

    public function testReleaseRequestsValidateMediaSizeJsonAndScopeBeforePublishing(): void
    {
        foreach ([
            ['{}', 'text/plain', 415],
            ['{invalid', 'application/json', 422],
            ['[]', 'application/json', 422],
            ['null', 'application/json', 422],
            ['{}', 'application/json', 422],
            ['{"subject":"matematica","grade":"6"}', 'application/json', 422],
            ['{"subject":"matematica","grade":0}', 'application/json', 422],
            ['{"subject":"matematica","grade":10}', 'application/json', 422],
            ['{"subject":[],"grade":6}', 'application/json', 422],
            ['{"subject":"Matematica","grade":6}', 'application/json', 422],
            ['{"subject":"matematica","grade":6,"approve_all":true}', 'application/json', 422],
            [str_repeat(' ', 8193), 'application/json', 413],
        ] as [$raw, $mediaType, $expected]) {
            [$status, $body] = $this->request('POST', $this->path('/releases'), raw: $raw, mediaType: $mediaType);
            self::assertSame($expected, $status, $raw);
            $this->assertOpenApiSchema('Error', $body);
        }
        foreach (['/skills', '/competencies'] as $suffix) {
            foreach ([['subject' => ['matematica']], ['grade' => ['6']], ['grade' => '10'], ['grade' => '0'], ['subject' => '']] as $query) {
                [$status] = $this->request('GET', $this->path($suffix), query: $query);
                self::assertSame(422, $status);
            }
        }
        [, $releases] = $this->request('GET', $this->path('/releases'));
        self::assertSame('pending', $this->scope($releases, 'matematica', 6)['status']);
    }

    private function path(string $suffix): string
    {
        return '/bncc/documents/' . $this->documentId . $suffix;
    }

    private function scope(array $releases, string $subject, int $grade): array
    {
        foreach ($releases['items'] as $scope) {
            if ($scope['subject']['code'] === $subject && $scope['grade']['year_number'] === $grade) {
                return $scope;
            }
        }
        self::fail('Escopo não encontrado.');
    }

    private function request(string $method, string $path, ?array $body = null, array $query = [], ?string $raw = null, string $mediaType = 'application/json'): array
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path)->withQueryParams($query);
        if ($body !== null || $raw !== null) {
            $request = $request->withHeader('Content-Type', $mediaType);
            $request->getBody()->write($raw ?? json_encode($body, JSON_THROW_ON_ERROR));
            $request->getBody()->rewind();
        }
        $response = $this->app->handle($request);
        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)];
    }
}
