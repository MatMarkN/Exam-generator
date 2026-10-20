<?php

declare(strict_types=1);

namespace App\Application\Bncc;

final readonly class BnccContextQuery
{
    private function __construct(
        public string $subject,
        public int $grade,
        public array $topics,
        public array $topicTerms,
        public ?int $documentId,
        public int $limit,
    ) {
    }

    public static function fromArray(array $input): self
    {
        if (array_diff(array_keys($input), ['subject', 'grade', 'topics', 'document_id', 'limit']) !== []
            || !is_string($input['subject'] ?? null) || !preg_match('/^[a-z][a-z0-9_]{1,39}$/D', $input['subject'])
            || !is_int($input['grade'] ?? null) || $input['grade'] < 1 || $input['grade'] > 9
            || !is_array($input['topics'] ?? null) || !array_is_list($input['topics'])
            || count($input['topics']) < 1 || count($input['topics']) > 8) {
            throw new ImportException('invalid_input', 'Informe subject como código da matéria, grade como inteiro de 1 a 9 e topics como lista de 1 a 8 textos.');
        }
        $documentId = $input['document_id'] ?? null;
        if (array_key_exists('document_id', $input)
            && (!is_int($documentId) || $documentId < 1 || $documentId > 4294967295)) {
            throw new ImportException('invalid_input', 'document_id deve ser um inteiro positivo válido.');
        }
        $topics = [];
        $topicTerms = [];
        $seen = [];
        foreach ($input['topics'] as $topic) {
            if (!is_string($topic) || !preg_match('//u', $topic) || preg_match_all('/./us', $topic) > 120
                || preg_match('/[\x00-\x1f\x7f]/u', $topic)) {
                throw new ImportException('invalid_input', 'Cada tópico deve ter de 1 a 120 caracteres, sem caracteres de controle.');
            }
            $topic = trim(preg_replace('/\s+/u', ' ', $topic));
            $terms = BnccTopicMatcher::terms($topic);
            if ($terms === []) {
                throw new ImportException('invalid_input', 'Cada tópico precisa conter palavras ou números pesquisáveis.');
            }
            $signature = $terms;
            sort($signature);
            $signature = implode(' ', $signature);
            if (!isset($seen[$signature])) {
                $seen[$signature] = true;
                $topics[] = $topic;
                $topicTerms[] = $terms;
            }
        }
        $limit = array_key_exists('limit', $input) ? $input['limit'] : 12;
        if (!is_int($limit) || $limit < count($topics) || $limit > 30) {
            throw new ImportException('invalid_input', 'limit deve ser um inteiro entre a quantidade de tópicos distintos e 30; o padrão é 12.');
        }
        return new self($input['subject'], $input['grade'], $topics, $topicTerms, $documentId, $limit);
    }
}
