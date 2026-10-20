<?php

declare(strict_types=1);

namespace App\Application\Bncc;

/** Correspondência lexical: as pontuações ordenam resultados, não medem adequação pedagógica. */
final class BnccTopicMatcher
{
    private const STOP_WORDS = ['a', 'o', 'as', 'os', 'de', 'da', 'do', 'das', 'dos', 'e', 'em',
        'na', 'no', 'nas', 'nos', 'para', 'por', 'com', 'um', 'uma', 'uns', 'umas'];

    public static function terms(string $text): array
    {
        // Acentos portugueses, inclusive texto com acentos decompostos; sem depender de outra extensão.
        $text = strtr($text, [
            'Á' => 'a', 'À' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Ä' => 'a',
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'É' => 'e', 'È' => 'e', 'Ê' => 'e', 'Ë' => 'e', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'Í' => 'i', 'Ì' => 'i', 'Î' => 'i', 'Ï' => 'i', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'Ó' => 'o', 'Ò' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ö' => 'o',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'Ú' => 'u', 'Ù' => 'u', 'Û' => 'u', 'Ü' => 'u', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'Ç' => 'c', 'ç' => 'c',
        ]);
        $text = strtolower(preg_replace('/\p{M}+/u', '', $text));
        preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches);
        return array_values(array_unique(array_diff($matches[0], self::STOP_WORDS)));
    }

    public function match(array $skill, BnccContextQuery $query): array
    {
        $fields = ['description' => ['weight' => 4, 'terms' => [self::terms($skill['description'])]]];
        foreach (['knowledge_objects' => 3, 'thematic_unit' => 1, 'language_practice' => 1, 'field_of_activity' => 1] as $name => $weight) {
            $fields[$name] = ['weight' => $weight, 'terms' => []];
            foreach ($skill['sources'] as $source) {
                $fields[$name]['terms'][] = self::terms($source[$name] ?? '');
            }
        }
        $matches = [];
        $score = 0;
        foreach ($query->topicTerms as $index => $terms) {
            $matchedFields = [];
            foreach ($fields as $name => $field) {
                foreach ($field['terms'] as $haystack) {
                    // Todas as palavras do tópico devem aparecer no mesmo campo de uma mesma origem.
                    if (array_diff($terms, $haystack) === []) {
                        $matchedFields[] = $name;
                        $score += $field['weight'];
                        break;
                    }
                }
            }
            if ($matchedFields !== []) {
                $matches[] = ['topic' => $query->topics[$index], 'fields' => $matchedFields];
            }
        }
        return ['matches' => $matches, 'score' => $score];
    }
}
