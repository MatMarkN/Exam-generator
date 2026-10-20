<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Bncc\BnccContextQuery;
use App\Application\Bncc\BnccTopicMatcher;
use App\Application\Bncc\ImportException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BnccTopicMatcherTest extends TestCase
{
    public function testAccentsCaseAndStopWordsAreNormalizedIncludingDecomposedAccents(): void
    {
        self::assertSame(['numeros', 'decimais'], BnccTopicMatcher::terms('NÚMEROS e decimais'));
        self::assertSame(['fracoes'], BnccTopicMatcher::terms("FRAC\u{0327}O\u{0303}ES"));
        $query = $this->query(['topics' => ['frações', 'FRACOES', '  Números   decimais  ']]);
        self::assertSame(['frações', 'Números decimais'], $query->topics);
    }

    public function testMatchesWholeWordsAndDoesNotGuessSingularFormsOrSynonyms(): void
    {
        $skill = ['description' => 'Transformações e números decimais.', 'sources' => []];
        foreach (['ações', 'decimal', 'frações', "' OR 1=1 --"] as $topic) {
            self::assertSame([], (new BnccTopicMatcher())->match($skill, $this->query(['topics' => [$topic]]))['matches']);
        }
        self::assertSame(['description'], (new BnccTopicMatcher())->match($skill,
            $this->query(['topics' => ['numeros decimais']]))['matches'][0]['fields']);
    }

    public function testTermsCannotBeAssembledFromDifferentFieldsOrDifferentSources(): void
    {
        $skill = ['description' => 'Números', 'sources' => [
            ['knowledge_objects' => 'Decimais'], ['knowledge_objects' => 'Números'],
        ]];
        self::assertSame([], (new BnccTopicMatcher())->match($skill,
            $this->query(['topics' => ['números decimais']]))['matches']);
    }

    public function testPortugueseLanguageContextIsSearchableButRawExcerptsAndNotesAreNot(): void
    {
        $skill = ['description' => 'Descrição SINTÉTICA.', 'sources' => [[
            'language_practice' => 'Produção de textos', 'field_of_activity' => 'CAMPO ARTÍSTICO-LITERÁRIO',
            'source_excerpt' => 'Palavra exclusiva: frações', 'review_note' => 'polígonos',
        ]]];
        $matches = (new BnccTopicMatcher())->match($skill, $this->query(['topics' => ['produção de textos', 'artistico literario']]))['matches'];
        self::assertSame(['language_practice'], $matches[0]['fields']);
        self::assertSame(['field_of_activity'], $matches[1]['fields']);
        self::assertSame([], (new BnccTopicMatcher())->match($skill, $this->query(['topics' => ['frações', 'polígonos']]))['matches']);
    }

    #[DataProvider('invalidQueries')]
    public function testInvalidQueryIsRejected(array $overrides): void
    {
        $this->expectException(ImportException::class);
        $this->query($overrides);
    }

    public static function invalidQueries(): array
    {
        return [
            [['grade' => 0]], [['grade' => 10]], [['grade' => '6']], [['grade' => true]], [['grade' => 6.0]],
            [['subject' => "matematica' OR 1=1"]], [['topics' => []]], [['topics' => 'frações']],
            [['topics' => ['de e os']]], [['topics' => ['%_*']]], [['topics' => [null]]],
            [['topics' => [str_repeat('a', 121)]]], [['topics' => ["frações\npolígonos"]]],
            [['topics' => array_fill(0, 9, 'frações')]], [['topics' => ['frações', 'polígonos'], 'limit' => 1]],
            [['limit' => 31]], [['limit' => null]], [['limit' => '12']],
            [['document_id' => 0]], [['document_id' => null]], [['document_id' => 4294967296]],
            [['difficulty' => 'easy']], [['include_draft' => true]],
        ];
    }

    private function query(array $overrides): BnccContextQuery
    {
        return BnccContextQuery::fromArray(array_replace(['subject' => 'matematica', 'grade' => 6, 'topics' => ['frações']], $overrides));
    }
}
