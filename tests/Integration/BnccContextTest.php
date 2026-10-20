<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Bncc\BnccContextQuery;
use App\Application\Bncc\BnccContextService;
use App\Application\Bncc\BnccTopicMatcher;
use App\Application\Bncc\ImportException;
use App\Infrastructure\Persistence\BnccContextRepository;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Medoo\Medoo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\BnccFixtures;
use Tests\Support\AuthFixture;
use Tests\Support\CurriculumFixture;

final class BnccContextTest extends TestCase
{
    private Medoo $database;
    private BnccFixtures $fixtures;
    private BnccContextService $service;
    private array $documents = [];
    private array $session;

    protected function setUp(): void
    {
        $this->database = (new DatabaseConnectionFactory(require __DIR__ . '/../../config/database.php'))->create();
        $this->session = (new AuthFixture($this->database))->create();
        $this->fixtures = new BnccFixtures($this->database);
        $this->service = new BnccContextService(new BnccContextRepository($this->database), new BnccTopicMatcher());
    }

    protected function tearDown(): void
    {
        foreach ($this->documents as $id) {
            (new CurriculumFixture($this->database))->remove($id);
        }
        (new AuthFixture($this->database))->remove($this->session);
    }

    public function testSixthGradeFractionsKeepsGradeSubjectSourceAndApplicableCompetencyScopes(): void
    {
        $math = $this->curriculum();
        $art = $this->anotherComponent($math, 'arte');
        $this->skill($math, 'EF06MA07', [6], 'Comparar frações em um exercício SINTÉTICO.');
        $this->skill($math, 'EF06MA08', [6], 'Relacionar representações em um exercício SINTÉTICO.', ['knowledge_objects' => 'Frações']);
        $this->skill($math, 'EF07MA01', [7], 'Frações do ano seguinte em exemplo SINTÉTICO.');
        $this->skill($art, 'EF69AR01', [6, 7, 8, 9], 'Frações em uma descrição SINTÉTICA de outra matéria.');
        $general = $this->competency($math, 'general');
        $area = $this->competency($math, 'area');
        $component = $this->competency($math, 'component');
        $this->competency($art, 'component');
        $this->competency($art, 'area');
        $this->competency($math, 'general', ['number' => 2, 'review_status' => 'pending', 'reviewed_at' => null]);

        $result = $this->find(['document_id' => $math['document_id'], 'topics' => ['FRACOES']]);
        self::assertSame(['EF06MA07', 'EF06MA08'], array_column($result['skills'], 'code'));
        self::assertSame([6], $result['skills'][0]['grades']);
        self::assertSame('matematica', $result['subject']['code']);
        self::assertSame(6, $result['grade']['year_number']);
        self::assertSame([$general, $area, $component], array_column($result['competencies'], 'id'));
        self::assertSame($math['document_id'], $result['document']['id']);
        self::assertSame($math['section_id'], $result['skills'][0]['sources'][0]['section_id']);
        self::assertSame(5, $result['skills'][0]['sources'][0]['page_start']);
        self::assertStringContainsString('SINTÉTICO', $result['skills'][0]['sources'][0]['source_excerpt']);
        self::assertSame(['description'], $result['skills'][0]['matches'][0]['fields']);
        self::assertSame(['knowledge_objects'], $result['skills'][1]['matches'][0]['fields']);
        self::assertArrayNotHasKey('storage_path', $result['document']);
        self::assertFalse($result['selection']['truncated']);
        // A transação READ ONLY é encerrada; a conexão volta a permitir a limpeza do teste.
        self::assertFalse($this->database->pdo->inTransaction());
    }

    public function testSharedYearSkillsWorkForEveryLinkedYearWithoutDuplicatesFromSources(): void
    {
        $art = $this->curriculum('arte');
        $id = $this->skill($art, 'EF15AR01', [1, 2, 3, 4, 5], 'Artes visuais em exercício SINTÉTICO.');
        $this->fixtures->context($art, $id, ['position' => 2, 'source_excerpt' => 'Outra origem SINTÉTICA.']);
        $this->competency($art, 'general');
        foreach ([1, 3, 5] as $year) {
            $result = $this->find(['subject' => 'arte', 'grade' => $year, 'topics' => ['artes visuais'], 'document_id' => $art['document_id']]);
            self::assertCount(1, $result['skills']);
            self::assertSame([1, 2, 3, 4, 5], $result['skills'][0]['grades']);
            self::assertCount(2, $result['skills'][0]['sources']);
        }
        $this->expectReason('topics_without_matches', fn () => $this->find([
            'subject' => 'arte', 'grade' => 6, 'topics' => ['artes visuais'], 'document_id' => $art['document_id'],
        ]));
    }

    public function testDraftArchivedAndUnreviewedDataCannotEnterContext(): void
    {
        $ready = $this->curriculum();
        $draft = $this->curriculum(overrides: ['status' => 'draft', 'ready_at' => null]);
        $archived = $this->curriculum(overrides: ['status' => 'archived']);
        $this->skill($ready, 'EF06MA07', [6], 'Frações SINTÉTICAS aprovadas.');
        $this->skill($ready, 'EF06MA08', [6], 'Frações SINTÉTICAS pendentes.', status: 'pending');
        $this->skill($ready, 'EF06MA09', [6], 'Frações SINTÉTICAS rejeitadas.', status: 'rejected');
        $this->skill($ready, 'EF06MA10', [6], 'Frações SINTÉTICAS sem origem.', withSource: false);
        $this->skill($ready, 'EF06MA11', [], 'Frações SINTÉTICAS sem ano.');
        $this->skill($draft, 'EF06MA07', [6], 'Frações SINTÉTICAS no rascunho.');
        $this->skill($archived, 'EF06MA07', [6], 'Frações SINTÉTICAS arquivadas.');
        $this->competency($ready, 'general');
        self::assertSame(['EF06MA07'], array_column($this->find()['skills'], 'code'));
        foreach ([$draft, $archived] as $unavailable) {
            $this->expectReason('document_not_ready', fn () => $this->find(['document_id' => $unavailable['document_id']]));
        }
    }

    public function testDefaultUsesLastReleaseAndExplicitDocumentPinsAllSourcesToOneRevision(): void
    {
        $first = $this->curriculum(overrides: ['ready_at' => '2026-01-01 00:00:00']);
        $hash = $this->database->get('bncc_documents', 'sha256', ['id' => $first['document_id']]);
        $second = $this->curriculum(overrides: ['sha256' => $hash, 'revision' => 2, 'ready_at' => '2026-02-01 00:00:00']);
        $this->curriculum(overrides: ['sha256' => $hash, 'revision' => 3, 'status' => 'draft', 'ready_at' => null]);
        foreach ([$first, $second] as $curriculum) {
            $this->skill($curriculum, 'EF06MA07', [6], 'Frações SINTÉTICAS da versão ' . $curriculum['document_id']);
            $this->competency($curriculum, 'general');
        }
        $automatic = $this->find();
        self::assertSame($second['document_id'], $automatic['document']['id']);
        self::assertSame(2, $automatic['document']['revision']);
        $pinned = $this->find(['document_id' => $first['document_id']]);
        self::assertSame($first['document_id'], $pinned['document']['id']);
        self::assertSame($first['section_id'], $pinned['skills'][0]['sources'][0]['section_id']);
        self::assertSame($first['section_id'], $pinned['competencies'][0]['source_section_id']);
    }

    public function testLimitCoversEveryTopicAndReportsTruncationWithStableOrdering(): void
    {
        $math = $this->curriculum();
        $this->competency($math, 'general');
        foreach (['EF06MA07', 'EF06MA08', 'EF06MA09'] as $code) {
            $this->skill($math, $code, [6], 'Frações SINTÉTICAS.');
        }
        $this->skill($math, 'EF06MA10', [6], 'Polígonos SINTÉTICOS.');
        $result = $this->find(['topics' => ['frações', 'polígonos'], 'limit' => 2]);
        self::assertSame(['EF06MA07', 'EF06MA10'], array_column($result['skills'], 'code'));
        self::assertSame(4, $result['selection']['total_matches']);
        self::assertSame(2, $result['selection']['returned_skills']);
        self::assertTrue($result['selection']['truncated']);
        self::assertTrue($result['selection']['all_topics_covered']);
        self::assertSame($result, $this->find(['topics' => ['frações', 'polígonos'], 'limit' => 2]));
    }

    public function testMissingTopicRejectsPartialContextAndNeverExpandsToOtherGrades(): void
    {
        $math = $this->curriculum();
        $this->competency($math, 'general');
        $this->skill($math, 'EF06MA07', [6], 'Frações SINTÉTICAS.');
        $this->skill($math, 'EF07MA01', [7], 'Polígonos SINTÉTICOS.');
        try {
            $this->find(['topics' => ['frações', 'polígonos']]);
            self::fail('O tópico ausente deve ser informado.');
        } catch (ImportException $exception) {
            self::assertSame('topics_without_matches', $exception->reason);
            self::assertSame(['polígonos'], $exception->details['unmatched_topics']);
            self::assertSame(1, $exception->details['matched_skill_count']);
        }
    }

    public function testMissingCatalogReleaseAndCompetenciesHaveExplicitErrors(): void
    {
        $this->expectReason('unknown_subject', fn () => $this->find(['subject' => 'inexistente']));
        $this->expectReason('curriculum_not_ready', fn () => $this->find());
        $this->expectReason('not_found', fn () => $this->find(['document_id' => 4294967295]));
        $math = $this->curriculum();
        $this->skill($math, 'EF06MA07', [6], 'Frações SINTÉTICAS.');
        $this->expectReason('invalid_curriculum', fn () => $this->find());
        $this->expectReason('subject_not_available', fn () => $this->find(['subject' => 'arte']));
    }

    public function testContextSizeLimitDoesNotCutDescriptionsSilently(): void
    {
        $math = $this->curriculum();
        $this->competency($math, 'general');
        foreach (['EF06MA07', 'EF06MA08', 'EF06MA09'] as $code) {
            $this->skill($math, $code, [6], 'Frações SINTÉTICAS. ' . str_repeat('x', 50000));
        }
        $this->expectReason('context_too_large', fn () => $this->find());
    }

    public function testHttpContextIsReadOnlyAndPreservesProvenanceInJson(): void
    {
        $math = $this->curriculum();
        $this->skill($math, 'EF06MA07', [6], 'Frações SINTÉTICAS.');
        $this->competency($math, 'general');
        $before = $this->database->get('bncc_documents', '*', ['id' => $math['document_id']]);
        $response = $this->request(json_encode(['subject' => 'matematica', 'grade' => 6, 'topics' => ['frações']]));
        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('bncc-context-v1', $body['schema_version']);
        self::assertSame('EF06MA07', $body['skills'][0]['code']);
        self::assertSame($before, $this->database->get('bncc_documents', '*', ['id' => $math['document_id']]));
    }

    #[DataProvider('badHttpBodies')]
    public function testHttpRejectsMalformedInputBeforeConsultingTheDatabase(string $body, string $media, int $status): void
    {
        self::assertSame($status, $this->request($body, $media)->getStatusCode());
    }

    public static function badHttpBodies(): array
    {
        return [
            ['{}', 'text/plain', 415], ['{broken', 'application/json', 422], ['[]', 'application/json', 422],
            [str_repeat(' ', 8193), 'application/json', 422],
            ['{"subject":"matematica","grade":"6","topics":["frações"]}', 'application/json', 422],
            ['{"subject":"matematica","grade":6,"topics":["frações"],"include_draft":true}', 'application/json', 422],
        ];
    }

    private function curriculum(string $subject = 'matematica', array $overrides = []): array
    {
        // Apenas fixtures pequenas e SINTÉTICAS: não simulam uma publicação real pelo endpoint administrativo.
        $id = $this->fixtures->document(array_replace(['status' => 'ready', 'ready_at' => '2026-01-01 00:00:00',
            'structured_at' => '2026-01-01 00:00:00', 'structure_profile' => 'synthetic-context-fixture'], $overrides));
        $this->documents[] = $id;
        $section = $this->fixtures->section($id);
        $area = $this->fixtures->area($id, $section, ['code' => $subject]);
        $component = $this->fixtures->component($id, $area, $section, $subject);
        return ['document_id' => $id, 'section_id' => $section, 'area_id' => $area, 'component_id' => $component];
    }

    private function anotherComponent(array $curriculum, string $subject): array
    {
        $section = $this->fixtures->section($curriculum['document_id']);
        $area = $this->fixtures->area($curriculum['document_id'], $section, ['code' => $subject]);
        return ['document_id' => $curriculum['document_id'], 'section_id' => $section, 'area_id' => $area,
            'component_id' => $this->fixtures->component($curriculum['document_id'], $area, $section, $subject)];
    }

    private function skill(array $curriculum, string $code, array $years, string $description, array $source = [],
        string $status = 'approved', bool $withSource = true): int
    {
        $id = $this->fixtures->skill($curriculum, ['code' => $code, 'description' => $description,
            'review_status' => $status, 'reviewed_at' => $status === 'pending' ? null : '2026-01-01 00:00:00']);
        foreach ($years as $year) {
            $gradeId = $this->database->get('school_grades', 'id', ['education_stage' => 'ensino_fundamental', 'year_number' => $year]);
            $this->database->insert('bncc_skill_grades', ['skill_id' => $id, 'school_grade_id' => $gradeId]);
        }
        if ($withSource) {
            $this->fixtures->context($curriculum, $id, $source);
        }
        return $id;
    }

    private function competency(array $curriculum, string $scope, array $overrides = []): int
    {
        return $this->fixtures->competency($curriculum, $scope, array_replace([
            'review_status' => 'approved', 'reviewed_at' => '2026-01-01 00:00:00',
        ], $overrides));
    }

    private function find(array $overrides = []): array
    {
        return $this->service->find(BnccContextQuery::fromArray(array_replace([
            'subject' => 'matematica', 'grade' => 6, 'topics' => ['frações'],
        ], $overrides)));
    }

    private function expectReason(string $reason, callable $call): void
    {
        try {
            $call();
            self::fail('A consulta deveria falhar: ' . $reason);
        } catch (ImportException $exception) {
            self::assertSame($reason, $exception->reason);
        }
    }

    private function request(string $body, string $media = 'application/json'): \Psr\Http\Message\ResponseInterface
    {
        $app = require __DIR__ . '/../../config/bootstrap.php';
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/bncc/context')->withHeader('Content-Type', $media);
        $request->getBody()->write($body);
        $request->getBody()->rewind();
        $request = (new AuthFixture($this->database))->authorize($request, $this->session);
        return $app->handle($request);
    }
}
