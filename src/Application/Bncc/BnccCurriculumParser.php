<?php

declare(strict_types=1);

namespace App\Application\Bncc;

final class BnccCurriculumParser
{
    public function __construct(private readonly Bncc2018Profile $profile)
    {
    }

    public function parse(array $layout): array
    {
        $pages = $layout['pages'];
        $skills = [];
        $competencies = [];
        $warnings = [];
        $components = $this->profile->components();
        foreach ($pages as $pageNumber => $page) {
            if ($pageNumber < 59 || $pageNumber > 462 || !$this->hasHeading($page, 'HABILIDADES')) {
                continue;
            }
            $candidates = [];
            foreach ($page['blocks'] as $block) {
                if (!preg_match('/^\(EF[0-9]{2}[A-Z]{2}[0-9]{2}\)/', $block['text'])) {
                    continue;
                }
                foreach ($this->numberedItems($block, '/^\((EF[0-9]{2}[A-Z]{2}[0-9]{2})\)\s*/') as $item) {
                    $code = $item['key'];
                    $component = substr($code, 4, 2);
                    $definition = $components[$component] ?? null;
                    if ($definition === null || $pageNumber < $definition['start'] || $pageNumber > $definition['end']) {
                        throw new ImportException('invalid_curriculum', "O código {$code} está fora da seção de seu componente.");
                    }
                    if (isset($skills[$code])) {
                        throw new ImportException('invalid_curriculum', "O código {$code} tem mais de uma descrição no perfil. É necessário revisar o extrator.");
                    }
                    if ($item['description'] === '' || preg_match('/\(EF[0-9]{2}[A-Z]{2}[0-9]{2}\)/', $item['description'])) {
                        throw new ImportException('invalid_curriculum', "Descrição vazia ou códigos misturados em {$code}.");
                    }
                    $candidates[] = [...$item, 'code' => $code, 'component' => $component,
                        'grades' => $this->profile->grades($code), 'page_number' => $pageNumber];
                }
            }
            foreach ($candidates as $candidate) {
                $context = $this->context($pages[$pageNumber - 1] ?? null, $candidate, $candidates);
                $flags = [];
                if (preg_match('/-\s*\n/u', $candidate['source_excerpt'])) {
                    $flags[] = 'line_hyphenation';
                }
                if (!preg_match('/[.!?][”"\)]?$/u', $candidate['description'])) {
                    $flags[] = 'check_sentence_ending';
                }
                if ($context['knowledge_objects'] === null) {
                    $flags[] = 'check_context_alignment';
                }
                if ($flags !== []) {
                    $warnings[] = ['type' => 'skill', 'code' => $candidate['code'], 'page' => $pageNumber, 'flags' => $flags];
                }
                $skills[$candidate['code']] = [...$candidate, 'context' => $context, 'flags' => $flags];
            }
        }

        foreach ($this->profile->competencies() as $group) {
            $found = [];
            foreach ($group['pages'] as $pageNumber) {
                foreach ($this->competencyItems($pages[$pageNumber] ?? ['blocks' => []]) as $item) {
                        $number = (int) $item['key'];
                        if ($number > $group['count'] || isset($found[$number]) || $item['description'] === '') {
                            throw new ImportException('invalid_curriculum', 'Numeração inconsistente no quadro de competências.');
                        }
                        $found[$number] = true;
                        $flags = preg_match('/-\s*\n/u', $item['source_excerpt']) ? ['line_hyphenation'] : [];
                        $competencies[] = [...$item, 'number' => $number, 'scope' => $group['scope'],
                            'ref' => $group['ref'], 'page_number' => $pageNumber, 'flags' => $flags];
                        if ($flags !== []) {
                            $warnings[] = ['type' => 'competency', 'scope' => $group['scope'], 'ref' => $group['ref'],
                                'number' => $number, 'page' => $pageNumber, 'flags' => $flags];
                        }
                }
            }
            if (count($found) !== $group['count']) {
                throw new ImportException('invalid_curriculum', 'O quadro de competências não foi extraído integralmente.');
            }
        }
        foreach ($components as $key => $component) {
            $actual = count(array_filter($skills, static fn (array $skill): bool => $skill['component'] === $key));
            if ($actual !== $component['expected']) {
                throw new ImportException('invalid_curriculum', "Quantidade de habilidades inesperada em {$component['name']}.",
                    ['expected' => $component['expected'], 'actual' => $actual]);
            }
        }
        return ['skills' => array_values($skills), 'competencies' => $competencies,
            'report' => ['skills' => count($skills), 'competencies' => count($competencies),
                'extractor_version' => $layout['extractor_version'],
                'removed_control_characters' => $layout['removed_control_characters'],
                'parser_reported_warnings' => $layout['parser_reported_warnings'], 'warnings' => $warnings]];
    }

    /** Separa números/códigos apenas no início de linhas de um mesmo bloco, nunca entre colunas. */
    public function numberedItems(array $block, string $pattern): array
    {
        $items = [];
        $current = null;
        foreach ($block['lines'] as $line) {
            if (preg_match($pattern, $line['text'], $match)) {
                if ($current !== null) {
                    $items[] = $this->finishItem($current);
                }
                $current = ['key' => $match[1], 'description_lines' => [substr($line['text'], strlen($match[0]))],
                    'source_lines' => [$line['text']], 'bbox' => array_intersect_key($line, array_flip(['xMin', 'yMin', 'xMax', 'yMax']))];
            } elseif ($current !== null) {
                $current['description_lines'][] = $line['text'];
                $current['source_lines'][] = $line['text'];
            }
            if ($current !== null) {
                $current['bbox']['xMax'] = max($current['bbox']['xMax'], $line['xMax']);
                $current['bbox']['yMax'] = max($current['bbox']['yMax'], $line['yMax']);
            }
        }
        if ($current !== null) {
            $items[] = $this->finishItem($current);
        }
        return $items;
    }

    public function competencyItems(array $page): array
    {
        $items = [];
        foreach ($page['blocks'] as $block) {
            if (preg_match('/^(?:[1-9]|10)\.$/', $block['text'])) {
                // Em três quadros o número 1 está num bloco à esquerda do texto, na mesma linha.
                $neighbors = array_values(array_filter($page['blocks'], static fn (array $candidate): bool =>
                    abs($candidate['yMin'] - $block['yMin']) <= 1
                    && $candidate['xMin'] > $block['xMax'] && $candidate['xMin'] < $block['xMax'] + 40));
                if (count($neighbors) !== 1) {
                    throw new ImportException('invalid_curriculum', 'Número de competência sem descrição alinhada.');
                }
                $number = $block['text'];
                $numberX = $block['xMin'];
                $block = $neighbors[0];
                $block['lines'][0]['text'] = $number . ' ' . $block['lines'][0]['text'];
                $block['lines'][0]['xMin'] = $numberX;
                $block['text'] = $number . ' ' . $block['text'];
            }
            if (preg_match('/^(?:[1-9]|10)\.\s/', $block['text'])) {
                $items = [...$items, ...$this->numberedItems($block, '/^([1-9]|10)\.\s+/')];
            }
        }
        return $items;
    }

    private function finishItem(array $item): array
    {
        return ['key' => $item['key'], 'description' => $this->normalize(implode(' ', $item['description_lines'])),
            'source_excerpt' => implode("\n", $item['source_lines']), 'bbox' => $item['bbox']];
    }

    /** Os dois lados do quadro têm linhas alinhadas verticalmente. Associação incerta fica vazia. */
    public function context(?array $leftPage, array $skill, array $pageSkills): array
    {
        $result = ['page_start' => $skill['page_number'], 'page_end' => $skill['page_number'],
            'thematic_unit' => null, 'knowledge_objects' => null, 'metadata' => ['skill_bbox' => $skill['bbox']]];
        if ($leftPage === null) {
            return $result;
        }
        $objectsHeader = null;
        $organizerHeader = null;
        foreach ($leftPage['blocks'] as $block) {
            $text = $this->normalize($block['text']);
            if ($text === 'OBJETOS DE CONHECIMENTO') {
                $objectsHeader = $block;
            }
            if (in_array($text, ['UNIDADES TEMÁTICAS', 'PRÁTICAS DE LINGUAGEM'], true)) {
                $organizerHeader = [...$block, 'label' => $text];
            }
        }
        if ($objectsHeader === null) {
            return $result;
        }
        $result['page_start']--;
        $result['metadata']['context_page'] = $result['page_start'];
        $body = array_values(array_filter($leftPage['blocks'], static fn (array $block): bool =>
            $block['yMin'] > $objectsHeader['yMax'] && $block['yMax'] < $leftPage['height'] - 45));
        usort($body, static fn (array $a, array $b): int => $a['yMin'] <=> $b['yMin']);
        $rowStarts = array_column(array_column($pageSkills, 'bbox'), 'yMin');
        $groups = [];
        foreach ($body as $block) {
            if (abs($block['xMin'] - $objectsHeader['xMin']) > 5) {
                continue;
            }
            $aligned = min(array_map(static fn (float $y): float => abs($block['yMin'] - $y), $rowStarts)) <= 2.5;
            if ($aligned) {
                $groups[] = ['y' => $block['yMin'], 'texts' => [$block['text']], 'boxes' => [$this->onlyBox($block)]];
            } elseif ($groups !== []) {
                $index = count($groups) - 1;
                $groups[$index]['texts'][] = $block['text'];
                $groups[$index]['boxes'][] = $this->onlyBox($block);
            }
        }
        $skillY = $skill['bbox']['yMin'];
        foreach ($groups as $group) {
            if ($group['y'] <= $skillY + 2.5) {
                $result['knowledge_objects'] = $this->normalize(implode("\n", $group['texts']));
                $result['metadata']['knowledge_objects_source'] = implode("\n", $group['texts']);
                $result['metadata']['knowledge_objects_boxes'] = $group['boxes'];
            }
        }
        foreach ($body as $block) {
            if ($block['yMin'] > $skillY + 2.5 || $block['xMin'] >= $objectsHeader['xMin'] - 5) {
                continue;
            }
            $text = $this->normalize($block['text']);
            if (str_starts_with($text, 'CAMPO ') || $text === 'TODOS OS CAMPOS DE ATUAÇÃO') {
                $result['metadata']['field_of_activity'] = $text;
            } elseif ($organizerHeader !== null && abs($block['xMin'] - $organizerHeader['xMin']) <= 5) {
                if ($organizerHeader['label'] === 'UNIDADES TEMÁTICAS') {
                    $result['thematic_unit'] = $text;
                } else {
                    $result['metadata']['language_practice'] = $text;
                }
                $result['metadata']['organizer_source'] = $block['text'];
                $result['metadata']['organizer_bbox'] = $this->onlyBox($block);
            }
        }
        return $result;
    }

    private function onlyBox(array $block): array
    {
        return array_intersect_key($block, array_flip(['xMin', 'yMin', 'xMax', 'yMax']));
    }

    private function hasHeading(array $page, string $heading): bool
    {
        foreach ($page['blocks'] as $block) {
            if ($this->normalize($block['text']) === $heading) {
                return true;
            }
        }
        return false;
    }

    private function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
