<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Bncc\Bncc2018Profile;
use App\Application\Bncc\BnccContextQuery;
use App\Application\Bncc\BnccContextService;
use App\Application\Bncc\BnccTopicMatcher;
use App\Application\Bncc\ImportException;
use App\Application\AI\AIProviderInterface;
use App\Application\AI\AIRequest;
use App\Application\AI\AIResponse;
use App\Application\Exams\ExamGenerationService;
use App\Infrastructure\AI\FakeAIProvider;
use App\Infrastructure\Persistence\BnccContextRepository;
use App\Infrastructure\Persistence\BnccCurriculumRepository;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Medoo\Medoo;
use PHPUnit\Framework\TestCase;
use Tests\Support\CurriculumFixture;
use Tests\Support\ExamFixture;

final class BnccScopedReleaseTest extends TestCase
{
    private Medoo $database;
    private CurriculumFixture $fixtures;
    private BnccCurriculumRepository $repository;
    private array $documents = [];

    protected function setUp(): void
    {
        $this->database = (new DatabaseConnectionFactory(require __DIR__ . '/../../config/database.php'))->create();
        $this->fixtures = new CurriculumFixture($this->database);
        $this->repository = new BnccCurriculumRepository($this->database);
    }

    protected function tearDown(): void
    {
        foreach ($this->documents as $document) {
            $this->fixtures->remove($document['id']);
        }
    }

    public function testListingGroupsOnlyAvailableSubjectsAndYearsWithApplicableCompetencies(): void
    {
        $document = $this->document();
        $list = $this->repository->releases($document['id']);
        self::assertSame('draft', $list['status']);
        self::assertCount(6, $list['items']); // Matemática 6 e 7; Arte 6 a 9.
        $math = $this->scope($document['id'], 'matematica', 6);
        self::assertSame(['pending' => 1, 'approved' => 0, 'rejected' => 0, 'total' => 1], $math['review']['skills']);
        self::assertSame(18, $math['review']['competencies']['pending']);
        self::assertNull($math['released_at']);
        self::assertSame(25, $this->scope($document['id'], 'arte', 6)['review']['competencies']['total']);
        self::assertSame(1, $this->repository->skills($document['id'], 0, 50, 'matematica', 6)['total']);
        self::assertSame('EF06MA07', $this->repository->skills($document['id'], 0, 50, 'matematica', 6)['skills'][0]['code']);
        self::assertFalse($this->repository->skills($document['id'], 0, 50, 'matematica', 6)['skills'][0]['locked']);
        self::assertFalse($this->repository->skill($document['id'], 'EF06MA07')['locked']);
        self::assertCount(18, $this->repository->competencies($document['id'], 'matematica', 6)['competencies']);
        self::assertSame([false], array_values(array_unique(array_column($this->repository->competencies($document['id'], 'matematica')['competencies'], 'locked'))));
    }

    public function testPendingAndRejectedItemsPreventReleaseWithoutChangingReviews(): void
    {
        $document = $this->document();
        $this->expectReason('review_incomplete', fn () => $this->repository->publishScope($document['id'], 'matematica', 6));
        self::assertSame(0, $this->database->count('bncc_curriculum_releases', ['document_id' => $document['id']]));
        $this->approve($document['id'], 'matematica', 6);
        $this->repository->review($document['id'], 'skill', 'EF06MA07', 'rejected', 'Fixture SINTÉTICA rejeitada.', null);
        $this->expectReason('review_incomplete', fn () => $this->repository->publishScope($document['id'], 'matematica', 6));
        self::assertSame('pending', $this->repository->skill($document['id'], 'EF07MA07')['review_status']);
    }

    public function testPublicationReleasesOnlySelectedScopeAndIsIdempotent(): void
    {
        $document = $this->document();
        $this->approve($document['id'], 'matematica', 6);
        $result = $this->repository->publishScope($document['id'], 'matematica', 6);
        self::assertSame('ready', $result['status']);
        self::assertNotNull($result['released_at']);
        self::assertSame('draft', $this->repository->overview($document['id'])['status']);
        self::assertSame('pending', $this->scope($document['id'], 'matematica', 7)['status']);
        self::assertSame('pending', $this->scope($document['id'], 'arte', 6)['status']);
        self::assertSame($result, $this->repository->publishScope($document['id'], 'matematica', 6));
        self::assertSame(1, $this->database->count('bncc_curriculum_releases', ['document_id' => $document['id']]));
        $context = $this->find('matematica', 6, $document['id']);
        self::assertSame('draft', $context['document']['status']);
        self::assertSame('matematica', $context['document']['release']['subject']);
        self::assertSame(6, $context['document']['release']['grade']);
        self::assertSame(['EF06MA07'], array_column($context['skills'], 'code'));
        $this->expectReason('document_not_ready', fn () => $this->find('matematica', 7, $document['id']));
        $this->expectReason('document_not_ready', fn () => $this->find('arte', 6, $document['id']));
    }

    public function testPublishedItemsAndSharedCompetenciesAreFrozenWhileOtherItemsRemainEditable(): void
    {
        $document = $this->document();
        $this->approve($document['id'], 'matematica', 6);
        $this->repository->publishScope($document['id'], 'matematica', 6);
        self::assertTrue($this->repository->skill($document['id'], 'EF06MA07')['locked']);
        self::assertTrue($this->repository->skills($document['id'], 0, 50, 'matematica', 6)['skills'][0]['locked']);
        self::assertFalse($this->repository->skill($document['id'], 'EF07MA07')['locked']);
        self::assertFalse($this->repository->skills($document['id'], 0, 50, 'matematica', 7)['skills'][0]['locked']);
        $this->expectReason('immutable_document', fn () => $this->repository->review($document['id'], 'skill', 'EF06MA07', 'pending', 'Não pode alterar.', null));
        foreach ($this->repository->competencies($document['id'], 'matematica')['competencies'] as $competency) {
            self::assertTrue($competency['locked']);
            $this->expectReason('immutable_document', fn () => $this->repository->review($document['id'], 'competency', $competency['id'], 'rejected', 'Não pode alterar.', null));
        }
        self::assertSame('approved', $this->repository->review($document['id'], 'skill', 'EF07MA07', 'approved', 'Outro ano SINTÉTICO conferido.', null)['review_status']);
        $art = array_values(array_filter($this->repository->competencies($document['id'], 'arte')['competencies'], static fn (array $item): bool => $item['scope'] === 'component'))[0];
        self::assertFalse($art['locked']);
        $general = array_values(array_filter($this->repository->competencies($document['id'], 'arte')['competencies'], static fn (array $item): bool => $item['scope'] === 'general'))[0];
        self::assertTrue($general['locked']);
        self::assertSame('rejected', $this->repository->review($document['id'], 'competency', $art['id'], 'rejected', 'Outra matéria SINTÉTICA.', null)['review_status']);
    }

    public function testSharedYearSkillIsFrozenAfterAnyOfItsYearsIsPublished(): void
    {
        $document = $this->document();
        $this->approve($document['id'], 'arte', 6);
        $this->repository->publishScope($document['id'], 'arte', 6);
        self::assertTrue($this->repository->skill($document['id'], 'EF69AR01')['locked']);
        self::assertTrue($this->repository->skills($document['id'], 0, 50, 'arte', 7)['skills'][0]['locked']);
        $this->expectReason('immutable_document', fn () => $this->repository->review($document['id'], 'skill', 'EF69AR01', 'rejected', 'Afetaria vários anos.', null));
        self::assertSame('pending', $this->scope($document['id'], 'arte', 7)['status']);
        self::assertSame('ready', $this->repository->publishScope($document['id'], 'arte', 7)['status']);
    }

    public function testMissingRequiredCompetencyOrInvalidSourcePreventsPublication(): void
    {
        $document = $this->document();
        $this->approve($document['id'], 'matematica', 6);
        $skill = $this->repository->skill($document['id'], 'EF06MA07');
        $this->database->update('bncc_skill_contexts', ['page_end' => 601], ['skill_id' => $skill['id']]);
        $this->expectReason('invalid_curriculum', fn () => $this->repository->publishScope($document['id'], 'matematica', 6));
        $this->database->update('bncc_skill_contexts', ['page_end' => 280], ['skill_id' => $skill['id']]);
        $competency = $this->repository->competencies($document['id'], 'matematica')['competencies'][0];
        $this->database->delete('bncc_competencies', ['id' => $competency['id']]);
        $this->expectReason('review_incomplete', fn () => $this->repository->publishScope($document['id'], 'matematica', 6));
        self::assertSame(0, $this->database->count('bncc_curriculum_releases', ['document_id' => $document['id']]));
    }

    public function testAutomaticSelectionUsesLatestReleaseForRequestedScopeAndPinsRechecks(): void
    {
        $first = $this->document();
        $second = $this->document();
        foreach ([$first, $second] as $document) {
            $this->approve($document['id'], 'matematica', 6);
            $this->repository->publishScope($document['id'], 'matematica', 6);
        }
        $this->database->update('bncc_curriculum_releases', ['released_at' => '2026-01-01 00:00:00'], ['document_id' => $first['id']]);
        $this->database->update('bncc_curriculum_releases', ['released_at' => '2026-02-01 00:00:00'], ['document_id' => $second['id']]);
        self::assertSame($second['id'], $this->find('matematica', 6)['document']['id']);
        $pinned = $this->find('matematica', 6, $first['id']);
        $query = BnccContextQuery::fromArray(['subject' => 'matematica', 'grade' => 6, 'topics' => ['frações']]);
        $this->service()->assertUnchanged($query, $pinned);
        $this->approve($first['id'], 'matematica', 7);
        $this->repository->publishScope($first['id'], 'matematica', 7);
        $this->service()->assertUnchanged($query, $pinned);
        $this->database->delete('bncc_curriculum_releases', ['document_id' => $first['id'], 'school_grade_id' => $pinned['grade']['id']]);
        $this->expectReason('curriculum_changed', fn () => $this->service()->assertUnchanged($query, $pinned));
        self::assertSame($first['id'], $this->find('matematica', 7)['document']['id']);
    }

    public function testArchivedAndUnknownScopesCannotBePublished(): void
    {
        $document = $this->document();
        $this->expectReason('unknown_subject', fn () => $this->repository->publishScope($document['id'], 'inexistente', 6));
        $this->expectReason('subject_not_available', fn () => $this->repository->publishScope($document['id'], 'matematica', 9));
        $this->approve($document['id'], 'matematica', 6);
        $this->repository->publishScope($document['id'], 'matematica', 6);
        $this->database->update('bncc_documents', ['status' => 'archived'], ['id' => $document['id']]);
        self::assertSame('pending', $this->scope($document['id'], 'matematica', 6)['status']);
        $this->expectReason('immutable_document', fn () => $this->repository->publishScope($document['id'], 'matematica', 6));
        $this->expectReason('document_not_ready', fn () => $this->find('matematica', 6, $document['id']));
    }

    public function testGenerationUsesScopedContextAndRejectsOtherGradesBeforeProvider(): void
    {
        $document = $this->document();
        $this->approve($document['id'], 'matematica', 6);
        $this->repository->publishScope($document['id'], 'matematica', 6);
        $fake = new FakeAIProvider(json_encode(ExamFixture::response(), JSON_THROW_ON_ERROR));
        $service = new ExamGenerationService($this->service(), $fake);
        $result = $service->generate(ExamFixture::input(['bncc_document_id' => $document['id']]));
        self::assertSame(1, $fake->callCount());
        self::assertCount(3, $result->exam()['questions']);
        self::assertSame('passed', $result->generation()['bncc_validation']['status']);
        self::assertTrue($result->generation()['bncc_validation']['database_rechecked']);
        self::assertSame('draft', $result->bnccContext()['document']['status']);
        self::assertSame(6, $result->bnccContext()['document']['release']['grade']);
        $this->expectReason('document_not_ready', fn () => $service->generate(ExamFixture::input(['grade' => 7, 'bncc_document_id' => $document['id']])));
        self::assertSame(1, $fake->callCount());
    }

    public function testReleaseRemovedByAnotherConnectionDuringProviderRejectsTheExam(): void
    {
        $document = $this->document();
        $this->approve($document['id'], 'matematica', 6);
        $this->repository->publishScope($document['id'], 'matematica', 6);
        $fake = new FakeAIProvider(json_encode(ExamFixture::response(), JSON_THROW_ON_ERROR));
        $concurrent = (new DatabaseConnectionFactory(require __DIR__ . '/../../config/database.php'))->create();
        $provider = new class($fake, $concurrent, $document['id']) implements AIProviderInterface {
            public function __construct(private FakeAIProvider $fake, private Medoo $concurrent, private int $documentId) {}
            public function generate(AIRequest $request): AIResponse
            {
                $this->concurrent->delete('bncc_curriculum_releases', ['document_id' => $this->documentId]);
                return $this->fake->generate($request);
            }
        };
        $this->expectReason('curriculum_changed', fn () => (new ExamGenerationService($this->service(), $provider))->generate(
            ExamFixture::input(['bncc_document_id' => $document['id']])));
        self::assertSame(1, $fake->callCount());
    }

    public function testMismatchedSubjectCodeCannotBePublishedEvenWithApprovedRows(): void
    {
        $document = $this->document();
        $this->approve($document['id'], 'matematica', 6);
        $this->database->update('bncc_skills', ['code' => 'EF06CI01'], ['document_id' => $document['id'], 'code' => 'EF06MA07']);
        $this->expectReason('invalid_curriculum', fn () => $this->repository->publishScope($document['id'], 'matematica', 6));
    }

    public function testGloballyReadyAndArchivedDocumentsExposeAllItemsAsLockedWithoutScopedReleases(): void
    {
        $document = $this->document();
        self::assertSame(0, $this->database->count('bncc_curriculum_releases', ['document_id' => $document['id']]));
        foreach (['ready', 'archived'] as $status) {
            $this->database->update('bncc_documents', ['status' => $status, 'ready_at' => '2026-01-01 00:00:00'], ['id' => $document['id']]);
            self::assertTrue($this->repository->skill($document['id'], 'EF06MA07')['locked']);
            foreach ($this->repository->skills($document['id'], 0, 50)['skills'] as $skill) {
                self::assertTrue($skill['locked']);
            }
            foreach ($this->repository->competencies($document['id'])['competencies'] as $competency) {
                self::assertTrue($competency['locked']);
            }
        }
    }

    private function document(): array
    {
        $document = $this->fixtures->document();
        $this->documents[] = $document;
        $plan = $this->fixtures->plan(true);
        $math = array_values(array_filter($plan['skills'], static fn (array $skill): bool => $skill['component'] === 'MA'))[0];
        $art = array_values(array_filter($plan['skills'], static fn (array $skill): bool => $skill['component'] === 'AR'))[0];
        $plan['skills'] = [
            [...$math, 'code' => 'EF06MA07', 'grades' => [6], 'description' => 'Frações SINTÉTICAS para o sexto ano.'],
            [...$math, 'code' => 'EF07MA07', 'grades' => [7], 'description' => 'Frações SINTÉTICAS para o sétimo ano.'],
            [...$art, 'code' => 'EF69AR01', 'grades' => [6, 7, 8, 9], 'description' => 'Frações e imagens SINTÉTICAS de arte.'],
        ];
        $this->repository->save($document['id'], $document['sha256'], $plan, new Bncc2018Profile());
        return $document;
    }

    private function approve(int $documentId, string $subject, int $grade): void
    {
        foreach ($this->repository->skills($documentId, 0, 50, $subject, $grade)['skills'] as $skill) {
            if ($skill['review_status'] !== 'approved') {
                $this->repository->review($documentId, 'skill', $skill['code'], 'approved', 'Fixture SINTÉTICA conferida.', null);
            }
        }
        foreach ($this->repository->competencies($documentId, $subject)['competencies'] as $competency) {
            if ($competency['review_status'] !== 'approved') {
                $this->repository->review($documentId, 'competency', $competency['id'], 'approved', 'Fixture SINTÉTICA conferida.', null);
            }
        }
    }

    private function scope(int $documentId, string $subject, int $grade): array
    {
        return array_values(array_filter($this->repository->releases($documentId)['items'], static fn (array $item): bool => $item['subject']['code'] === $subject && $item['grade']['year_number'] === $grade))[0];
    }

    private function service(): BnccContextService
    {
        return new BnccContextService(new BnccContextRepository($this->database), new BnccTopicMatcher());
    }

    private function find(string $subject, int $grade, ?int $documentId = null): array
    {
        return $this->service()->find(BnccContextQuery::fromArray(['subject' => $subject, 'grade' => $grade, 'topics' => ['frações'], ...($documentId === null ? [] : ['document_id' => $documentId])]));
    }

    private function expectReason(string $reason, callable $action): void
    {
        try {
            $action();
            self::fail('A operação deveria falhar: ' . $reason);
        } catch (ImportException $exception) {
            self::assertSame($reason, $exception->reason);
        }
    }
}
