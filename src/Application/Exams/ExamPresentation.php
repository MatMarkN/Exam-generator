<?php

declare(strict_types=1);

namespace App\Application\Exams;

/** Dados de apresentação são preenchidos pela aplicação, fora da geração pela IA. */
final readonly class ExamPresentation
{
    private function __construct(public ?array $header, public ?array $formatting)
    {
    }

    public static function fromInput(array $input): self
    {
        $header = array_key_exists('exam_header', $input)
            ? self::object($input['exam_header'], 'exam_header', ['school_name', 'teacher_name', 'student_name', 'class_name', 'date'])
            : null;
        foreach ($header ?? [] as $field => $value) {
            if ($value === null) {
                continue;
            }
            if ($field === 'date') {
                if (!is_string($value) || !preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $value, $parts)
                    || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                    throw new ExamGenerationException('invalid_input', 'exam_header.date deve ser uma data válida no formato YYYY-MM-DD ou null.');
                }
            } else {
                $header[$field] = self::text($value, 'exam_header.' . $field, match ($field) {
                    'school_name' => 180, 'class_name' => 80, default => 160,
                });
            }
        }
        $formatting = array_key_exists('formatting', $input)
            ? self::object($input['formatting'], 'formatting', ['font_family', 'font_size'])
            : null;
        if (array_key_exists('font_family', $formatting ?? [])) {
            $formatting['font_family'] = self::text($formatting['font_family'], 'formatting.font_family', 80);
        }
        if (array_key_exists('font_size', $formatting ?? [])) {
            if (!is_int($formatting['font_size']) || $formatting['font_size'] < 8 || $formatting['font_size'] > 36) {
                throw new ExamGenerationException('invalid_input', 'formatting.font_size deve ser um inteiro entre 8 e 36.');
            }
        }
        return new self($header, $formatting);
    }

    public function toArray(): array
    {
        $result = [];
        if ($this->header !== null) {
            $result['exam_header'] = $this->header === [] ? new \stdClass() : $this->header;
        }
        if ($this->formatting !== null) {
            $result['formatting'] = $this->formatting === [] ? new \stdClass() : $this->formatting;
        }
        return $result;
    }

    /** Retorna uma cópia dos valores efetivos, que será salva junto da prova. */
    public function resolve(?array $teacher, array $context): array
    {
        $settings = $teacher['settings'] ?? [];
        return [
            'header' => [
                ...array_replace([
                    'school_name' => $settings['school_name'] ?? null,
                    'teacher_name' => $teacher['name'] ?? null,
                    'student_name' => null,
                    'class_name' => null,
                    'date' => null,
                ], $this->header ?? []),
                // Matéria e ano vêm do contexto validado, sem cópias divergentes no pedido.
                'subject' => $context['subject']['name'],
                'grade' => $context['grade']['name'],
            ],
            'formatting' => array_replace([
                'font_family' => $settings['default_font_family'] ?? 'Arial',
                'font_size' => $settings['default_font_size'] ?? 12,
            ], $this->formatting ?? []),
        ];
    }

    private static function object(mixed $value, string $field, array $allowed): array
    {
        if ($value instanceof \stdClass) {
            $value = get_object_vars($value);
        } elseif (!is_array($value) || array_is_list($value)) {
            throw new ExamGenerationException('invalid_input', "$field deve ser um objeto JSON.");
        }
        if (array_diff(array_keys($value), $allowed) !== []) {
            throw new ExamGenerationException('invalid_input', "$field contém campos não reconhecidos.");
        }
        return $value;
    }

    private static function text(mixed $value, string $field, int $maximum): string
    {
        if (!is_string($value) || strlen($value) > $maximum * 4 || !preg_match('//u', $value)
            || preg_match('/[\p{Cc}\p{Zl}\p{Zp}]/u', $value)) {
            throw new ExamGenerationException('invalid_input', "$field deve ser texto UTF-8 em uma única linha, de 1 a $maximum caracteres.");
        }
        $value = preg_replace('/^\s+|\s+$/u', '', $value);
        if ($value === '' || preg_match_all('/./us', $value) > $maximum) {
            throw new ExamGenerationException('invalid_input', "$field deve ter de 1 a $maximum caracteres.");
        }
        return $value;
    }
}
