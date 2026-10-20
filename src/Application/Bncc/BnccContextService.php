<?php

declare(strict_types=1);

namespace App\Application\Bncc;

use App\Infrastructure\Persistence\BnccContextRepository;

final class BnccContextService
{
    public function __construct(private readonly BnccContextRepository $repository, private readonly BnccTopicMatcher $matcher)
    {
    }

    /** Reconsulta o banco sem trocar a revisão enviada ao provedor pela revisão mais recente. */
    public function assertUnchanged(BnccContextQuery $query, array $sentContext): void
    {
        $pinnedQuery = BnccContextQuery::fromArray([
            'subject' => $query->subject, 'grade' => $query->grade, 'topics' => $query->topics,
            'limit' => $query->limit, 'document_id' => $sentContext['document']['id'],
        ]);
        try {
            $current = $this->find($pinnedQuery);
        } catch (ImportException) {
            throw new ImportException('curriculum_changed',
                'Não foi possível confirmar o mesmo contexto da BNCC após a geração. Confira a revisão curricular antes de gerar novamente.');
        }
        if ($current !== $sentContext) {
            throw new ImportException('curriculum_changed',
                'O contexto da BNCC mudou durante a geração. Confira a revisão curricular antes de gerar novamente.');
        }
    }

    public function find(BnccContextQuery $query): array
    {
        $data = $this->repository->load($query);
        $ranked = [];
        foreach ($data['skills'] as $skill) {
            $match = $this->matcher->match($skill, $query);
            if ($match['matches'] !== []) {
                $ranked[] = [...$skill, ...$match];
            }
        }
        usort($ranked, static fn (array $a, array $b): int =>
            (count($b['matches']) <=> count($a['matches'])) ?: ($b['score'] <=> $a['score']) ?: strcmp($a['code'], $b['code']));
        // Reserva pelo menos um resultado para cada tópico antes de completar o limite pela ordenação.
        $selected = [];
        $unmatched = [];
        foreach ($query->topics as $topic) {
            $found = false;
            foreach ($ranked as $skill) {
                if (in_array($topic, array_column($skill['matches'], 'topic'), true)) {
                    $selected[$skill['id']] = true;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $unmatched[] = $topic;
            }
        }
        if ($unmatched !== []) {
            throw new ImportException('topics_without_matches', 'Não foi possível montar o contexto para todos os tópicos. Ajuste os termos da busca.',
                ['unmatched_topics' => $unmatched, 'matched_skill_count' => count($ranked), 'document_id' => $data['document']['id']]);
        }
        if ($data['competencies'] === []) {
            throw new ImportException('invalid_curriculum', 'Não há competências aprovadas para compor o contexto solicitado.');
        }
        foreach ($ranked as $skill) {
            if (count($selected) >= $query->limit) {
                break;
            }
            $selected[$skill['id']] = true;
        }
        $data['skills'] = array_values(array_filter($ranked, static fn (array $skill): bool => isset($selected[$skill['id']])));
        foreach ($data['skills'] as &$skill) {
            unset($skill['score']);
        }
        unset($skill);
        $result = ['schema_version' => 'bncc-context-v1', ...$data, 'topics' => $query->topics,
            'selection' => ['method' => 'lexical-v1', 'limit' => $query->limit, 'total_matches' => count($ranked),
                'returned_skills' => count($selected), 'truncated' => count($ranked) > count($selected), 'all_topics_covered' => true]];
        if (strlen(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)) > 128 * 1024) {
            throw new ImportException('context_too_large', 'O contexto excedeu 128 KiB. Reduza os tópicos ou o limite de habilidades.');
        }
        return $result;
    }
}
