<?php

declare(strict_types=1);

namespace App\Http\OpenApi;

use App\Application\Exams\ExamGenerationRequest;
use App\Application\Exams\ExamOutputSchema;

/** Contratos HTTP. Regras que dependem de outros campos são explicadas nas descrições. */
final class ApiSchemas
{
    public static function ref(string $name): array
    {
        return ['$ref' => '#/components/schemas/' . $name];
    }

    public static function object(array $properties, ?array $required = null, bool $additional = false): array
    {
        return ['type' => 'object', 'properties' => $properties, 'required' => $required ?? array_keys($properties), 'additionalProperties' => $additional];
    }

    public static function integer(int $minimum = 1, int $maximum = 4294967295): array
    {
        return ['type' => 'integer', 'minimum' => $minimum, 'maximum' => $maximum];
    }

    public static function list(array $items): array
    {
        return ['type' => 'array', 'items' => $items];
    }

    public static function nullable(array $schema): array
    {
        return ['anyOf' => [$schema, ['type' => 'null']]];
    }

    public static function build(): array
    {
        $id = self::integer();
        $count = self::integer(0);
        $text = ['type' => 'string'];
        $bool = ['type' => 'boolean'];
        $nullText = self::nullable($text);
        $nullId = self::nullable($id);
        $nullCount = self::nullable(['type' => 'integer', 'minimum' => 0]);
        $metadata = self::nullable(['type' => ['object', 'array']]);
        $timestamp = ['type' => 'string', 'description' => 'Data UTC no formato YYYY-MM-DD HH:MM:SS.'];
        $isoDate = ['type' => 'string', 'format' => 'date-time'];
        $line = static fn (int $limit): array => ['type' => 'string', 'minLength' => 1, 'maxLength' => $limit,
            'pattern' => '^(?=.*\\S)[^\\x00-\\x1f\\x7f]*$', 'description' => 'Texto UTF-8 em uma linha; espaços nas extremidades são removidos.'];
        $headerProperties = ['school_name' => self::nullable($line(180)), 'teacher_name' => self::nullable($line(160)),
            'student_name' => self::nullable($line(160)), 'class_name' => self::nullable($line(80)),
            'date' => self::nullable(['type' => 'string', 'format' => 'date', 'pattern' => '^[0-9]{4}-[0-9]{2}-[0-9]{2}$',
                'description' => 'Data real no formato YYYY-MM-DD.'])];
        $formattingProperties = ['font_family' => $line(80), 'font_size' => self::integer(8, 36)];
        $settingsProperties = ['school_name' => self::nullable($line(180)), 'default_font_family' => $line(80),
            'default_font_size' => self::integer(8, 36)];
        $storedFontSize = [...self::integer(1, 65535),
            'description' => 'Valor preservado no banco ou herdado do cadastro. Dados legados podem ficar fora de 8 a 36; novos valores enviados pela API devem respeitar essa faixa.'];
        $teacherProperties = ['name' => $line(160), 'email' => self::nullable(['type' => 'string', 'format' => 'email', 'maxLength' => 254])];
        $reviewStatus = ['type' => 'string', 'enum' => ['pending', 'approved', 'rejected']];
        $code = ['type' => 'string', 'pattern' => '^EF[0-9]{2}[A-Z]{2}[0-9]{2}$', 'examples' => ['EF06MA07']];
        $topics = [...self::list(['type' => 'string', 'minLength' => 1, 'maxLength' => 120]), 'minItems' => 1, 'maxItems' => 8,
            'description' => 'Tópicos não vazios; a busca normaliza palavras e acentos, sem interpretar sinônimos.'];
        $baseInput = ['subject' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_]{1,39}$', 'examples' => ['matematica'],
                'description' => 'Código da matéria cadastrada. Ex.: matematica, lingua_portuguesa, ciencias.'],
            'grade' => self::integer(1, 9), 'topics' => $topics];
        $distribution = self::object(array_fill_keys(ExamGenerationRequest::QUESTION_TYPES, self::integer(0, 30)));
        $requestProperties = [...$baseInput,
            'bncc_document_id' => [...$id, 'description' => 'Opcional. Revisão específica liberada para a matéria e o ano solicitados. Omitir seleciona a revisão liberada mais recente para esse par. Não envie null.'],
            'context_limit' => [...self::integer(1, 30), 'default' => 12, 'description' => 'Deve ser pelo menos o número de tópicos distintos.'],
            'question_count' => self::integer(1, 30),
            'question_mode' => ['type' => 'string', 'enum' => ['automatic', 'manual'], 'default' => 'automatic'],
            'question_distribution' => [...$distribution, 'description' => 'Obrigatório no modo manual; a soma deve ser question_count. No automático, omita este campo por completo.'],
            'alternatives_count' => [...self::integer(2, 6), 'default' => 4],
            'true_false_statements' => [...self::integer(1, 10), 'default' => 1],
            'image_mode' => ['type' => 'string', 'enum' => ['none', 'required', 'auto'], 'default' => 'none',
                'description' => 'Solicita descrições de imagens; esta API ainda não gera arquivos de imagem.'],
            'teacher_notes' => [...$text, 'default' => '', 'description' => 'Instruções em linguagem natural. Até 8192 bytes UTF-8; permite tabulação e quebras de linha.'],
            'reference_text' => [...$text, 'default' => '', 'description' => 'Referência de estilo, linguagem e extensão dos enunciados. Até 16384 bytes UTF-8; permite tabulação e quebras de linha.'],
            'teacher_id' => [...$nullId, 'description' => 'Opcional. Deve existir em teachers. Preenche nome, escola e fontes a partir do cadastro. Associação declarada, sem autenticação.'],
            'exam_header' => [...self::object($headerProperties, []), 'description' => 'Opcional. Os campos enviados prevalecem sobre o cadastro; null deixa o campo em branco. Matéria e ano vêm da consulta curricular. Os dados do cabeçalho são adicionados pelo backend e não são enviados à IA.'],
            'formatting' => [...self::object($formattingProperties, []), 'description' => 'Opcional. Valores enviados prevalecem sobre as preferências do professor. Sem professor, os padrões são Arial e 12. Metadados para futura apresentação; não gera PDF.']];
        $schemas = [
            'Health' => self::object(['status' => ['type' => 'string', 'enum' => ['ok', 'unavailable']]]),
            'Error' => self::object(['error' => self::object(['code' => $text, 'message' => $text,
                'generation_id' => [...$id, 'description' => 'Presente quando a tentativa foi registrada no histórico.'],
                'retryable' => $bool, 'retry_after_seconds' => $nullCount,
                'unmatched_topics' => self::list($text)], ['code', 'message'], true)]),
            'ContextRequest' => self::object([...$baseInput, 'document_id' => $id,
                'limit' => [...self::integer(1, 30), 'default' => 12, 'description' => 'Pelo menos o número de tópicos distintos.']], ['subject', 'grade', 'topics']),
            'ExamRequest' => [...self::object($requestProperties, ['subject', 'grade', 'topics', 'question_count']),
                'oneOf' => [
                    ['properties' => ['question_mode' => ['const' => 'manual']], 'required' => ['question_mode', 'question_distribution']],
                    ['properties' => ['question_mode' => ['const' => 'automatic']], 'not' => ['required' => ['question_distribution']]],
                ]],
            'ReviewRequest' => self::object(['status' => $reviewStatus,
                'note' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 1000, 'pattern' => '\\S'],
                'description' => ['type' => 'string', 'minLength' => 1, 'description' => 'Opcional: somente ajustes de espaços/hífens, preservando o conteúdo da fonte.']], ['status', 'note']),
            'ReviewResult' => self::object(['id' => $id, 'description' => $text, 'review_status' => $reviewStatus,
                'reviewed_at' => self::nullable($timestamp), 'review_note' => $nullText]),
            'Subject' => self::object(['id' => $id, 'code' => $text, 'name' => $text]),
            'Grade' => self::object(['id' => $id, 'year_number' => self::integer(1, 9), 'name' => $text, 'education_stage' => $text]),
            'SubjectList' => self::object(['items' => self::list(self::ref('Subject'))]),
            'SchoolGrade' => self::object(['id' => $id, 'code' => $text, 'name' => $text, 'education_stage' => $text, 'year_number' => self::integer(1, 9)]),
            'SchoolGradeList' => self::object(['items' => self::list(self::ref('SchoolGrade'))]),
            'TeacherCreate' => self::object($teacherProperties, ['name']),
            'TeacherUpdate' => [...self::object($teacherProperties, []), 'minProperties' => 1],
            'TeacherSettings' => self::object([...$settingsProperties, 'default_font_size' => $storedFontSize]),
            'TeacherSettingsUpdate' => [...self::object($settingsProperties, []), 'minProperties' => 1],
            'Teacher' => self::object(['id' => $id, ...$teacherProperties, 'settings' => self::ref('TeacherSettings'),
                'created_at' => $isoDate, 'updated_at' => $isoDate]),
            'TeacherList' => self::object(['items' => self::list(self::ref('Teacher')), 'next_before_id' => $nullId]),
            'ExamHeaderInput' => self::object($headerProperties, []),
            'ExamHeader' => self::object([...$headerProperties, 'subject' => $text, 'grade' => $text]),
            'ExamFormatting' => self::object([...$formattingProperties, 'font_size' => $storedFontSize]),
            'Cost' => self::object(['amount' => [...$nullText, 'description' => 'Decimal como texto; null significa desconhecido.'],
                'currency' => ['const' => 'USD'], 'basis' => ['type' => 'string', 'enum' => ['simulated', 'usage_unavailable', 'pricing_not_configured', 'configured_token_rates']],
                'rates' => self::object(['input_usd_per_million' => ['type' => ['number', 'string']], 'output_usd_per_million' => ['type' => ['number', 'string']]]),
                'provider' => $text, 'model' => $text], ['amount', 'currency', 'basis']),
        ];

        // A prova HTTP aceita toda a faixa de quantidades. Reaproveita os três tipos do schema canônico.
        $output = json_decode(ExamOutputSchema::json(ExamGenerationRequest::fromArray([
            'subject' => 'matematica', 'grade' => 6, 'topics' => ['frações'], 'question_count' => 30,
            'alternatives_count' => 6, 'true_false_statements' => 10,
        ])), true, flags: JSON_THROW_ON_ERROR)['properties'];
        $exam = $output['exam'];
        $exam['properties']['questions']['minItems'] = 1;
        $exam['properties']['questions']['items']['anyOf'][0]['properties']['alternatives']['minItems'] = 2;
        $exam['properties']['questions']['items']['anyOf'][2]['properties']['statements']['minItems'] = 1;
        $exam['properties'] += ['subject' => self::ref('Subject'), 'grade' => self::ref('Grade'), 'topics' => $topics];
        $exam['required'] = array_keys($exam['properties']);
        // Provas anteriores à etapa 15 continuam consultáveis sem estes metadados.
        $exam['properties'] += ['header' => self::ref('ExamHeader'), 'formatting' => self::ref('ExamFormatting')];
        $schemas['ExamContent'] = $exam;
        $exam['properties']['id'] = $id;
        $exam['required'][] = 'id';
        $schemas['SavedExam'] = $exam;
        $answers = $output['answer_key'];
        $answers['minItems'] = 1;
        $answers['items']['anyOf'][2]['properties']['answers']['minItems'] = 1;
        $schemas['AnswerKey'] = $answers;

        $document = ['id' => $id, 'title' => $text, 'version_label' => $text, 'revision' => $id,
            'sha256' => ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'], 'page_count' => $id,
            'status' => ['type' => 'string', 'enum' => ['draft', 'ready', 'archived']],
            'ready_at' => self::nullable($timestamp), 'structured_at' => self::nullable($timestamp), 'structure_profile' => $nullText];
        $curriculumRelease = self::object([
            'id' => $id, 'subject' => $baseInput['subject'], 'grade' => $baseInput['grade'], 'released_at' => $timestamp,
        ]);
        $schemas['ContextDocument'] = self::object([...$document, 'release' => $curriculumRelease], array_keys($document));
        $schemas['Document'] = self::object([...$document, 'original_filename' => $text,
            'curriculum_status' => ['type' => 'string', 'enum' => ['ready', 'awaiting_review', 'awaiting_structuring_and_review']],
            'created_at' => $timestamp, 'extracted_at' => self::nullable($timestamp), 'extractor_version' => $nullText,
            'extraction_warnings' => $metadata, 'structure_report' => $metadata], null, true);
        $schemas['BnccDocumentList'] = self::object(['items' => self::list(self::ref('Document'))]);
        $schemas['Pages'] = self::object(['document_id' => $id, 'total_pages' => $id,
            'pages' => self::list(self::object(['page_number' => $id, 'source_text' => $text, 'detected_skill_codes' => self::list($code)])),
            'next_page' => $nullId]);
        $source = ['id' => $id, 'section_id' => $id, 'page_start' => $id, 'page_end' => $id,
            'printed_page_label' => $nullText, 'source_excerpt' => $text, 'thematic_unit' => $nullText,
            'knowledge_objects' => $nullText, 'description_page' => $nullId, 'language_practice' => $nullText, 'field_of_activity' => $nullText];
        $schemas['Source'] = self::object($source);
        $competency = ['id' => $id, 'scope' => ['type' => 'string', 'enum' => ['general', 'area', 'component']],
            'area_id' => $nullId, 'component_id' => $nullId, 'number' => $id, 'description' => $text,
            'source_section_id' => $id, 'page_start' => $id, 'page_end' => $id, 'source_excerpt' => $text];
        $schemas['ContextCompetency'] = self::object($competency);
        $schemas['BnccContext'] = self::object(['schema_version' => ['const' => 'bncc-context-v1'],
            'document' => self::ref('ContextDocument'), 'subject' => self::ref('Subject'), 'grade' => self::ref('Grade'),
            'area' => self::object(['id' => $id, 'code' => $text, 'name' => $text]), 'topics' => $topics,
            'skills' => self::list(self::object(['id' => $id, 'code' => $code, 'description' => $text,
                'grades' => self::list(self::integer(1, 9)), 'sources' => self::list(self::ref('Source')),
                'matches' => self::list(self::object(['topic' => $text, 'fields' => self::list($text)]))])),
            'competencies' => self::list(self::ref('ContextCompetency')),
            'selection' => self::object(['method' => ['const' => 'lexical-v1'], 'limit' => self::integer(1, 30),
                'total_matches' => $count, 'returned_skills' => $count, 'truncated' => $bool, 'all_topics_covered' => ['const' => true]])]);
        $reviewResult = $schemas['ReviewResult']['properties'];
        $locked = [...$bool, 'description' => 'true quando o item está imutável: documento ready/archived ou conteúdo compartilhado por alguma matéria/ano já liberados.'];
        $skillSummary = [...$reviewResult, 'code' => $code, 'locked' => $locked];
        $schemas['Skills'] = self::object(['document_id' => $id, 'total' => $count, 'offset' => $count, 'limit' => self::integer(1, 50),
            'skills' => self::list(self::object($skillSummary))]);
        $schemas['Skill'] = self::object([...$skillSummary, 'document_id' => $id, 'component_id' => $id,
            'grades' => self::list(self::integer(1, 9)), 'subject' => self::object(['code' => $text, 'name' => $text]),
            'contexts' => self::list(self::object([
                'id' => $id, 'document_id' => $id, 'skill_id' => $id, 'section_id' => $id, 'page_start' => $id, 'page_end' => $id,
                'source_excerpt' => $text, 'context_metadata' => $metadata,
            ], null, true))], null, true);
        $schemas['Competencies'] = self::object(['document_id' => $id, 'competencies' => self::list(self::object([
            ...$competency, ...$reviewResult, 'document_id' => $id, 'source_metadata' => $metadata, 'locked' => $locked,
        ], null, true))]);
        $reviewCounts = self::object(array_fill_keys(['pending', 'approved', 'rejected', 'total'], $count));
        $release = ['subject' => self::ref('Subject'), 'grade' => self::ref('Grade'),
            'status' => ['type' => 'string', 'enum' => ['pending', 'ready']], 'released_at' => self::nullable($timestamp),
            'review' => self::object(['skills' => $reviewCounts, 'competencies' => $reviewCounts])];
        $schemas['BnccReleaseSummary'] = self::object($release);
        $schemas['BnccRelease'] = self::object(['document_id' => $id, ...$release]);
        $schemas['BnccReleaseList'] = self::object(['document_id' => $id, 'status' => $document['status'],
            'items' => self::list(self::ref('BnccReleaseSummary'))]);
        $schemas['BnccReleaseRequest'] = self::object(array_intersect_key($baseInput, array_flip(['subject', 'grade'])));
        $schemas['Structure'] = self::object(['document_id' => $id, 'status' => $document['status'], 'structure_profile' => $nullText,
            'structured_at' => self::nullable($timestamp), 'report' => $metadata,
            'review' => self::object(['skills' => $reviewCounts, 'competencies' => $reviewCounts]),
            'sections' => self::list(self::object(['id' => $id, 'parent_id' => $nullId, 'section_key' => $text,
                'title' => $text, 'page_start' => $id, 'page_end' => $id]))]);
        $schemas['PublishedDocument'] = self::object(['document_id' => $id, 'status' => ['const' => 'ready']]);

        $schemas['BnccValidation'] = self::object(['status' => ['const' => 'passed'], 'database_rechecked' => ['const' => true],
            'version' => $text, 'document_id' => $id, 'document_revision' => $id,
            'document_sha256' => $document['sha256'], 'context_sha256' => $document['sha256'],
            'questions' => self::list(self::object(['question_number' => $id, 'skill_ids' => self::list($id)])),
            'skills' => self::list(self::object(['id' => $id, 'code' => $code, 'question_numbers' => self::list($id), 'source_ids' => self::list($id)])),
            'topic_references' => self::list(self::object(['topic' => $text, 'skill_codes' => self::list($code), 'question_numbers' => self::list($id)])),
            'unused_context_skill_codes' => self::list($code), 'semantic_review' => ['const' => 'not_performed'],
            'teacher_review_required' => ['const' => true]]);
        $schemas['BnccValidation']['properties']['curriculum_release'] = $curriculumRelease;
        $normalizedRequest = $requestProperties;
        unset($normalizedRequest['bncc_document_id'], $normalizedRequest['context_limit'], $normalizedRequest['teacher_id']);
        $normalizedRequest['question_distribution'] = self::nullable($distribution);
        $normalizedRequired = array_values(array_diff(array_keys($normalizedRequest), ['exam_header', 'formatting']));
        $schemas['NormalizedRequest'] = self::object($normalizedRequest, $normalizedRequired);
        $generation = ['schema_name' => $text, 'prompt_version' => $text, 'provider' => $text, 'model' => $text,
            'duration_ms' => $count, 'input_tokens' => $nullCount, 'output_tokens' => $nullCount, 'provider_request_id' => $nullText,
            'request' => self::ref('NormalizedRequest'), 'bncc_validation' => self::ref('BnccValidation'), 'estimated_cost' => self::ref('Cost'),
            'id' => $id, 'exam_id' => $id, 'total_duration_ms' => $count, 'status' => ['const' => 'succeeded']];
        $schemas['GenerationResult'] = self::object(['exam' => self::ref('SavedExam'), 'answer_key' => self::ref('AnswerKey'),
            'bncc_context' => self::ref('BnccContext'), 'generation' => self::object($generation)]);
        $schemas['ExamLookup'] = self::object(['exam' => self::ref('SavedExam'), 'generation_id' => $id, 'created_at' => $isoDate]);
        $schemas['AnswerKeyLookup'] = self::object(['exam_id' => $id, 'answer_key' => self::ref('AnswerKey')]);
        $history = ['id' => $id, 'teacher_id' => $nullId, 'requester_name' => $nullText, 'requester_verified' => ['const' => false],
            'status' => ['type' => 'string', 'enum' => ['running', 'succeeded', 'failed']], 'stage' => $text,
            'started_at' => $isoDate, 'finished_at' => self::nullable($isoDate), 'subject' => $text,
            'grade' => self::integer(1, 9), 'question_count' => self::integer(1, 30), 'topics' => $topics,
            'provider' => $nullText, 'requested_model' => $nullText, 'model' => $nullText,
            'bncc_document_id' => $nullId, 'bncc_revision' => $nullId, 'input_tokens' => $nullCount, 'output_tokens' => $nullCount,
            'provider_duration_ms' => $nullCount, 'total_duration_ms' => $nullCount,
            'estimated_cost' => self::nullable(self::ref('Cost')), 'error_code' => $nullText, 'exam_id' => $nullId];
        $schemas['GenerationSummary'] = self::object($history);
        $schemas['GenerationList'] = self::object(['items' => self::list(self::ref('GenerationSummary')), 'next_before_id' => $nullId]);
        unset($history['topics']);
        $schemas['GenerationDetail'] = self::object([...$history, 'error_message' => $nullText, 'response_sha256' => $nullText,
            'provider_request_id' => $nullText,
            'request' => self::object([...$normalizedRequest, 'teacher_id' => $nullId, 'bncc_document_id' => $nullId, 'context_limit' => self::integer(1, 30)],
                [...$normalizedRequired, 'teacher_id', 'bncc_document_id', 'context_limit']),
            'ai_request' => self::nullable(self::object(['instructions' => $text, 'input_json' => $text, 'output_schema_json' => $text,
                'schema_name' => $text, 'max_output_tokens' => $id, 'timeout_seconds' => $id])),
            'result_metadata' => $metadata,
            'response_format' => ['type' => 'string', 'enum' => ['unavailable', 'validated', 'unvalidated_json']],
            'response' => ['description' => 'Objeto validado no sucesso; string JSON não validada em certas falhas; null se indisponível.',
                'anyOf' => [self::object(['exam' => self::ref('ExamContent'), 'answer_key' => self::ref('AnswerKey')]), $text, ['type' => 'null']]]]);
        return $schemas;
    }
}
