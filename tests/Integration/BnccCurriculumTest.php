<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Bncc\Bncc2018Profile;
use App\Application\Bncc\ImportException;
use App\Infrastructure\Persistence\BnccCurriculumRepository;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Medoo\Medoo;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\CurriculumFixture;
use Tests\Support\AuthFixture;

final class BnccCurriculumTest extends TestCase
{
    private Medoo $database;
    private CurriculumFixture $fixtures;
    private BnccCurriculumRepository $repository;
    private array $documents = [];
    private array $session;

    protected function setUp(): void
    {
        $this->database = (new DatabaseConnectionFactory(require __DIR__ . '/../../config/database.php'))->create();
        $this->session = (new AuthFixture($this->database))->create();
        $this->fixtures = new CurriculumFixture($this->database);
        $this->repository = new BnccCurriculumRepository($this->database);
    }

    protected function tearDown(): void
    {
        foreach ($this->documents as $document) {
            $this->fixtures->remove($document['id']);
        }
        (new AuthFixture($this->database))->remove($this->session);
    }

    public function testStructurePreservesTheOriginalAndCreatesPendingItemsWithGradesAndProvenance(): void
    {
        $document = $this->document();
        $before = $this->repository->sourcePages($document['id']);
        $overview = $this->save($document);
        self::assertSame('draft', $overview['status']);
        self::assertSame(1, $overview['review']['skills']['pending']);
        self::assertSame(1, $overview['review']['competencies']['pending']);
        self::assertSame(5, $overview['report']['areas']);
        self::assertSame(9, $overview['report']['components']);
        self::assertSame($before, $this->repository->sourcePages($document['id']));
        $skill = $this->repository->skill($document['id'], 'EF15AR01');
        self::assertSame([1, 2, 3, 4, 5], $skill['grades']);
        self::assertSame('arte', $skill['subject']['code']);
        self::assertStringContainsString('SINTÉTICA', $skill['contexts'][0]['source_excerpt']);
        self::assertSame(Bncc2018Profile::NAME, $skill['contexts'][0]['context_metadata']['profile']);
    }

    public function testStructuringAgainDoesNotOverwriteAnExistingReview(): void
    {
        $document = $this->document();
        $this->save($document);
        $this->repository->review($document['id'], 'skill', 'EF15AR01', 'approved', 'Revisão SINTÉTICA de teste.', null);
        try {
            $this->save($document);
            self::fail('A revisão não deve ser sobrescrita.');
        } catch (ImportException $exception) {
            self::assertSame('already_structured', $exception->reason);
        }
        self::assertSame('approved', $this->repository->skill($document['id'], 'EF15AR01')['review_status']);
    }

    public function testInvalidPlanRollsBackAllNewCurricularRows(): void
    {
        $document = $this->document();
        $plan = $this->fixtures->plan();
        $plan['skills'][0]['grades'] = [9];
        try {
            $this->repository->save($document['id'], $document['sha256'], $plan, new Bncc2018Profile());
            self::fail('O vínculo incompatível deveria falhar.');
        } catch (ImportException) {
            self::assertSame(0, $this->database->count('bncc_sections', ['document_id' => $document['id']]));
            self::assertSame(0, $this->database->count('bncc_competencies', ['document_id' => $document['id']]));
            self::assertNull($this->database->get('bncc_documents', 'structured_at', ['id' => $document['id']]));
            self::assertCount(600, $this->repository->sourcePages($document['id']));
        }
    }

    public function testReviewAllowsOnlyWhitespaceAndHyphenCorrections(): void
    {
        $document = $this->document();
        $this->save($document);
        $result = $this->repository->review($document['id'], 'skill', 'EF15AR01', 'approved', 'Conferido na fonte SINTÉTICA.', 'Descrição  SINTÉTICA de teste.');
        self::assertSame('approved', $result['review_status']);
        self::assertNotNull($result['reviewed_at']);
        try {
            $this->repository->review($document['id'], 'skill', 'EF15AR01', 'approved', 'Teste.', 'Texto inventado sem fonte.');
            self::fail('A alteração de conteúdo deveria falhar.');
        } catch (ImportException $exception) {
            self::assertSame('invalid_input', $exception->reason);
        }
        self::assertSame($result['description'], $this->repository->skill($document['id'], 'EF15AR01')['description']);
    }

    public function testReviewCannotApproveSkillWithoutYearLinks(): void
    {
        $document = $this->document();
        $this->save($document);
        $skill = $this->repository->skill($document['id'], 'EF15AR01');
        $this->database->delete('bncc_skill_grades', ['skill_id' => $skill['id']]);
        $this->expectException(ImportException::class);
        $this->repository->review($document['id'], 'skill', 'EF15AR01', 'approved', 'Teste sem vínculo.', null);
    }

    public function testCompetencyReviewCannotReachAnotherDocument(): void
    {
        $a = $this->document(); $b = $this->document();
        $this->save($a); $this->save($b);
        $other = $this->repository->competencies($b['id'])['competencies'][0];
        try {
            $this->repository->review($a['id'], 'competency', $other['id'], 'approved', 'Não pode atravessar documentos.', null);
            self::fail('A referência a outro documento deveria falhar.');
        } catch (ImportException $exception) {
            self::assertSame('not_found', $exception->reason);
        }
        self::assertSame('pending', $this->repository->competencies($b['id'])['competencies'][0]['review_status']);
    }

    public function testPublicationRequiresCompleteApprovalAndFreezesTheRevision(): void
    {
        $document = $this->document();
        $this->save($document, true);
        try {
            $this->repository->publish($document['id']);
            self::fail('Itens pendentes deveriam impedir a liberação.');
        } catch (ImportException $exception) {
            self::assertSame('review_incomplete', $exception->reason);
            self::assertSame(1304, $exception->details['skills']['pending']);
        }
        // Simula revisão humana concluída somente no banco descartável, com textos SINTÉTICOS.
        foreach (['bncc_skills', 'bncc_competencies'] as $table) {
            $this->database->update($table, ['review_status' => 'approved', 'reviewed_at' => gmdate('Y-m-d H:i:s'),
                'review_note' => 'Fixture SINTÉTICA aprovada para testar a transição.'], ['document_id' => $document['id']]);
        }
        self::assertSame('ready', $this->repository->publish($document['id'])['status']);
        self::assertNotNull($this->database->get('bncc_documents', 'ready_at', ['id' => $document['id']]));
        try {
            $this->repository->review($document['id'], 'skill', 'EF01AR01', 'rejected', 'Tentativa depois da liberação.', null);
            self::fail('Revisões liberadas devem ser imutáveis.');
        } catch (ImportException $exception) {
            self::assertSame('immutable_document', $exception->reason);
        }
        self::assertSame('approved', $this->repository->skill($document['id'], 'EF01AR01')['review_status']);
    }

    public function testHttpInspectionAndReviewRoutesValidateJsonAndKeepDocumentInDraft(): void
    {
        $document = $this->document();
        $this->save($document);
        $app = require __DIR__ . '/../../config/bootstrap.php';
        $factory = new ServerRequestFactory();
        $prefix = '/bncc/documents/' . $document['id'];
        foreach (['/structure', '/skills?limit=1', '/skills/EF15AR01', '/competencies'] as $suffix) {
            $response = $app->handle($this->authorize($factory->createServerRequest('GET', $prefix . $suffix)));
            self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        }
        $request = $factory->createServerRequest('PATCH', $prefix . '/skills/EF15AR01/review')->withHeader('Content-Type', 'application/json');
        $request->getBody()->write(json_encode(['status' => 'rejected', 'note' => 'Revisão SINTÉTICA requer ajuste.']));
        $request->getBody()->rewind();
        self::assertSame(200, $app->handle($this->authorize($request))->getStatusCode());
        self::assertSame('rejected', $this->repository->skill($document['id'], 'EF15AR01')['review_status']);
        self::assertSame(409, $app->handle($this->authorize($factory->createServerRequest('POST', $prefix . '/publish')))->getStatusCode());
        $bad = $factory->createServerRequest('PATCH', $prefix . '/skills/EF15AR01/review')->withHeader('Content-Type', 'application/json');
        $bad->getBody()->write('{invalid'); $bad->getBody()->rewind();
        self::assertSame(422, $app->handle($this->authorize($bad))->getStatusCode());
        self::assertSame('draft', $this->repository->overview($document['id'])['status']);
    }

    public function testHttpStructuringRejectsUnrecognizedPdfBeforeExtraction(): void
    {
        $document = $this->document();
        $app = require __DIR__ . '/../../config/bootstrap.php';
        $response = $app->handle($this->authorize((new ServerRequestFactory())->createServerRequest('POST', '/bncc/documents/' . $document['id'] . '/structure')));
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('unsupported_document', json_decode((string) $response->getBody(), true)['error']['code']);
        self::assertSame(0, $this->database->count('bncc_sections', ['document_id' => $document['id']]));
    }

    private function document(): array
    {
        $document = $this->fixtures->document();
        $this->documents[] = $document;
        return $document;
    }

    private function authorize(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ServerRequestInterface
    {
        return (new AuthFixture($this->database))->authorize($request, $this->session);
    }

    private function save(array $document, bool $full = false): array
    {
        return $this->repository->save($document['id'], $document['sha256'], $this->fixtures->plan($full), new Bncc2018Profile());
    }
}
