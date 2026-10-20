<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BnccFixtures;
use Tests\Support\DatabaseTestCase;

final class BnccSchemaTest extends DatabaseTestCase
{
    private BnccFixtures $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures = new BnccFixtures($this->database);
    }

    public function testMigrationsAndCatalogSeedDoNotInventBnccContent(): void
    {
        foreach (['bncc_documents', 'bncc_document_pages', 'bncc_sections', 'bncc_areas', 'bncc_components', 'bncc_competencies', 'bncc_skills', 'bncc_skill_grades', 'bncc_skill_contexts'] as $table) {
            self::assertSame(0, $this->database->count($table), $table);
        }
    }

    public function testOneSkillCanApplyToAllFiveInitialYearsWithoutDuplicatingItsDescription(): void
    {
        $curriculum = $this->fixtures->curriculum();
        $skillId = $this->fixtures->skill($curriculum);
        $grades = $this->database->select('school_grades', ['id', 'year_number'], [
            'education_stage' => 'ensino_fundamental', 'year_number[<=]' => 5,
        ]);

        foreach ($grades as $grade) {
            $this->database->insert('bncc_skill_grades', ['skill_id' => $skillId, 'school_grade_id' => $grade['id']]);
        }

        $years = $this->database->select('bncc_skill_grades', ['[>]school_grades' => ['school_grade_id' => 'id']],
            'school_grades.year_number', ['skill_id' => $skillId, 'ORDER' => ['school_grades.year_number' => 'ASC']]);

        self::assertSame(range(1, 5), $years);
        self::assertSame(1, $this->database->count('bncc_skills'));
    }

    public function testNewRevisionCanReuseTheFileAndCodeWithoutChangingTheOldDescription(): void
    {
        $hash = hash('sha256', 'arquivo sintético compartilhado');
        $old = $this->fixtures->curriculum(['sha256' => $hash, 'revision' => 1]);
        $oldSkill = $this->fixtures->skill($old, ['description' => 'Texto sintético da revisão anterior.']);
        $before = $this->database->get('bncc_skills', '*', ['id' => $oldSkill]);

        $new = $this->fixtures->curriculum(['sha256' => $hash, 'revision' => 2]);
        $newSkill = $this->fixtures->skill($new, ['description' => 'Texto sintético revisado.']);

        self::assertNotSame($oldSkill, $newSkill);
        self::assertSame($before, $this->database->get('bncc_skills', '*', ['id' => $oldSkill]));
        self::assertSame(2, $this->database->count('bncc_skills', ['code' => 'EF15AR01']));
    }

    public function testFileHashAndRevisionCannotBeDuplicated(): void
    {
        $data = ['sha256' => hash('sha256', 'arquivo sintético')];
        $this->fixtures->document($data);
        $this->assertMysqlFailure(1062, fn () => $this->fixtures->document($data));
    }

    #[DataProvider('invalidDocuments')]
    public function testInvalidDocumentMetadataIsRejected(array $data): void
    {
        $this->assertMysqlFailure(3819, fn () => $this->fixtures->document($data));
    }

    public static function invalidDocuments(): array
    {
        return [
            'hash inválido' => [['sha256' => str_repeat('z', 64)]],
            'revisão zero' => [['revision' => 0]],
            'sem páginas' => [['page_count' => 0]],
            'sem caminho' => [['storage_path' => '   ']],
            'pronto sem data' => [['status' => 'ready']],
        ];
    }

    public function testSkillCodeMustBeUniqueWithinOneDocumentRevision(): void
    {
        $curriculum = $this->fixtures->curriculum();
        $this->fixtures->skill($curriculum);
        $this->assertMysqlFailure(1062, fn () => $this->fixtures->skill($curriculum));
    }

    #[DataProvider('invalidSkillCodes')]
    public function testOnlyTheFundamentalCodeFormatIsAccepted(string $code): void
    {
        $curriculum = $this->fixtures->curriculum();
        $this->assertMysqlFailure(3819, fn () => $this->fixtures->skill($curriculum, ['code' => $code]));
    }

    public static function invalidSkillCodes(): array
    {
        return ['minúsculas' => ['ef15ar01'], 'outro segmento' => ['EM13MAT101'], 'dígito extra' => ['EF15AR011']];
    }

    public function testTheSameCompetencyNumberIsValidInDifferentScopesButNotTwiceInOneScope(): void
    {
        $curriculum = $this->fixtures->curriculum();

        foreach (['general', 'area', 'component'] as $scope) {
            $this->fixtures->competency($curriculum, $scope);
            $this->assertMysqlFailure(1062, fn () => $this->fixtures->competency($curriculum, $scope));
        }

        self::assertSame(3, $this->database->count('bncc_competencies'));
    }

    #[DataProvider('inconsistentScopes')]
    public function testCompetencyScopeRequiresTheCorrectReference(string $scope, bool $withArea, bool $withComponent): void
    {
        $curriculum = $this->fixtures->curriculum();
        $this->assertMysqlFailure(3819, fn () => $this->fixtures->competency($curriculum, $scope, [
            'area_id' => $withArea ? $curriculum['area_id'] : null,
            'component_id' => $withComponent ? $curriculum['component_id'] : null,
        ]));
    }

    public static function inconsistentScopes(): array
    {
        return [
            'geral com área' => ['general', true, false],
            'área sem referência' => ['area', false, false],
            'componente sem referência' => ['component', false, false],
            'ambos os escopos' => ['component', true, true],
        ];
    }

    public function testSectionParentCannotBelongToAnotherDocument(): void
    {
        $first = $this->fixtures->curriculum();
        $second = $this->fixtures->curriculum();
        $this->assertMysqlFailure(1452, fn () => $this->fixtures->section($first['document_id'], ['parent_id' => $second['section_id']]));
    }

    public function testAreaSourceCannotBelongToAnotherDocument(): void
    {
        $first = $this->fixtures->curriculum();
        $second = $this->fixtures->curriculum();
        $this->assertMysqlFailure(1452, fn () => $this->fixtures->area($first['document_id'], $second['section_id'], ['code' => 'outra_area']));
    }

    public function testComponentCannotUseAnAreaFromAnotherDocument(): void
    {
        $first = $this->fixtures->curriculum();
        $second = $this->fixtures->curriculum();
        $this->assertMysqlFailure(1452, fn () => $this->fixtures->component($first['document_id'], $second['area_id'], $first['section_id'], 'matematica'));
    }

    public function testCompetencyCannotUseAScopeFromAnotherDocument(): void
    {
        $first = $this->fixtures->curriculum();
        $second = $this->fixtures->curriculum();
        $this->assertMysqlFailure(1452, fn () => $this->fixtures->competency($first, 'area', ['area_id' => $second['area_id']]));
        $this->assertMysqlFailure(1452, fn () => $this->fixtures->competency($first, 'component', ['component_id' => $second['component_id']]));
    }

    public function testSkillCannotUseAComponentFromAnotherDocument(): void
    {
        $first = $this->fixtures->curriculum();
        $second = $this->fixtures->curriculum();
        $this->assertMysqlFailure(1452, fn () => $this->fixtures->skill($first, ['component_id' => $second['component_id']]));
    }

    public function testContextCannotMixSkillsAndSectionsFromDifferentDocuments(): void
    {
        $first = $this->fixtures->curriculum();
        $second = $this->fixtures->curriculum();
        $firstSkill = $this->fixtures->skill($first);
        $secondSkill = $this->fixtures->skill($second);

        $this->assertMysqlFailure(1452, fn () => $this->fixtures->context($first, $firstSkill, ['section_id' => $second['section_id']]));
        $this->assertMysqlFailure(1452, fn () => $this->fixtures->context($first, $secondSkill));
    }

    public function testRepeatedOccurrencesKeepTheirPagesExcerptsAndLanguageSpecificMetadata(): void
    {
        $curriculum = $this->fixtures->curriculum();
        $skillId = $this->fixtures->skill($curriculum);
        $firstId = $this->fixtures->context($curriculum, $skillId, [
            'context_metadata' => json_encode(['campo_de_atuacao' => 'Campo SINTÉTICO'], JSON_THROW_ON_ERROR),
        ]);
        $secondId = $this->fixtures->context($curriculum, $skillId, [
            'position' => 2, 'page_start' => 15, 'page_end' => 15,
            'source_excerpt' => 'Segunda ocorrência SINTÉTICA da mesma habilidade.',
        ]);

        $first = $this->database->get('bncc_skill_contexts', '*', ['id' => $firstId]);
        $second = $this->database->get('bncc_skill_contexts', '*', ['id' => $secondId]);
        self::assertSame(2, $this->database->count('bncc_skill_contexts', ['skill_id' => $skillId]));
        self::assertSame(5, $first['page_start']);
        self::assertSame('4–5', $first['printed_page_label']);
        self::assertSame(['campo_de_atuacao' => 'Campo SINTÉTICO'], json_decode($first['context_metadata'], true, flags: JSON_THROW_ON_ERROR));
        self::assertSame(15, $second['page_start']);
        self::assertNotSame($first['source_excerpt'], $second['source_excerpt']);
        self::assertSame(1, $this->database->count('bncc_skills'));
        $this->assertMysqlFailure(1062, fn () => $this->fixtures->context($curriculum, $skillId));
    }

    public function testSkillGradeLinksRejectUnknownAndRepeatedGrades(): void
    {
        $curriculum = $this->fixtures->curriculum();
        $skillId = $this->fixtures->skill($curriculum);
        $gradeId = $this->database->get('school_grades', 'id', ['code' => 'ef_01']);
        $row = ['skill_id' => $skillId, 'school_grade_id' => $gradeId];
        $this->database->insert('bncc_skill_grades', $row);

        $this->assertMysqlFailure(1062, fn () => $this->database->insert('bncc_skill_grades', $row));
        $this->assertMysqlFailure(1452, fn () => $this->database->insert('bncc_skill_grades', ['skill_id' => $skillId, 'school_grade_id' => 4294967295]));
    }

    #[DataProvider('invalidContexts')]
    public function testInvalidSourceContextsAreRejected(array $overrides): void
    {
        $curriculum = $this->fixtures->curriculum();
        $skillId = $this->fixtures->skill($curriculum);
        $this->assertMysqlFailure(3819, fn () => $this->fixtures->context($curriculum, $skillId, $overrides));
    }

    public static function invalidContexts(): array
    {
        return [
            'página zero' => [['page_start' => 0]],
            'páginas invertidas' => [['page_start' => 8, 'page_end' => 7]],
            'trecho vazio' => [['source_excerpt' => '   ']],
            'metadados sem objeto' => [['context_metadata' => '[1,2]']],
        ];
    }

    public function testImportedDataStartsPendingAndApprovalRequiresAReviewDate(): void
    {
        $curriculum = $this->fixtures->curriculum();
        $skillId = $this->fixtures->skill($curriculum);
        $competencyId = $this->fixtures->competency($curriculum);

        self::assertSame('draft', $this->database->get('bncc_documents', 'status', ['id' => $curriculum['document_id']]));
        self::assertSame('pending', $this->database->get('bncc_skills', 'review_status', ['id' => $skillId]));
        self::assertSame('pending', $this->database->get('bncc_competencies', 'review_status', ['id' => $competencyId]));
        $this->assertMysqlFailure(3819, fn () => $this->database->update('bncc_skills', ['review_status' => 'approved'], ['id' => $skillId]));
        $this->assertMysqlFailure(3819, fn () => $this->database->update('bncc_competencies', ['review_status' => 'approved'], ['id' => $competencyId]));
    }

    public function testAReferencedDocumentCannotBeDeleted(): void
    {
        $curriculum = $this->fixtures->curriculum();
        $this->assertMysqlFailure(1451, fn () => $this->database->delete('bncc_documents', ['id' => $curriculum['document_id']]));
    }

    private function assertMysqlFailure(int $expectedCode, callable $operation): void
    {
        try {
            $operation();
        } catch (PDOException $exception) {
            self::assertSame($expectedCode, (int) $exception->errorInfo[1]);

            return;
        }

        self::fail('O banco deveria ter rejeitado a operação inválida.');
    }
}
