<?php

declare(strict_types=1);

namespace App\Application\Teachers;

final class TeacherInput
{
    public static function profile(array $input, bool $creating = false): array
    {
        self::fields($input, ['name', 'email']);
        if ($creating && !array_key_exists('name', $input)) {
            throw new TeacherException('invalid_input', 'Informe o nome do professor.');
        }
        $data = [];
        if (array_key_exists('name', $input)) {
            $data['name'] = self::text($input['name'], 'name', 160);
        }
        if (array_key_exists('email', $input)) {
            $email = $input['email'] === null ? null : self::text($input['email'], 'email', 254);
            if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new TeacherException('invalid_input', 'email deve ser um endereço válido ou null.');
            }
            $data['email'] = $email;
        }
        return $data;
    }

    public static function settings(array $input): array
    {
        self::fields($input, ['school_name', 'default_font_family', 'default_font_size']);
        $data = [];
        if (array_key_exists('school_name', $input)) {
            $data['school_name'] = $input['school_name'] === null ? null : self::text($input['school_name'], 'school_name', 180);
        }
        if (array_key_exists('default_font_family', $input)) {
            $data['default_font_family'] = self::text($input['default_font_family'], 'default_font_family', 80);
        }
        if (array_key_exists('default_font_size', $input)) {
            $size = $input['default_font_size'];
            if (!is_int($size) || $size < 8 || $size > 36) {
                throw new TeacherException('invalid_input', 'default_font_size deve ser um inteiro entre 8 e 36.');
            }
            $data['default_font_size'] = $size;
        }
        return $data;
    }

    private static function fields(array $input, array $allowed): void
    {
        if ($input === [] || array_is_list($input) || array_diff(array_keys($input), $allowed) !== []) {
            throw new TeacherException('invalid_input', 'Envie um objeto não vazio com apenas os campos documentados.');
        }
    }

    private static function text(mixed $value, string $field, int $limit): string
    {
        if (!is_string($value) || !preg_match('//u', $value)
            || preg_match('/[\p{Cc}\p{Zl}\p{Zp}]/u', $value)) {
            throw new TeacherException('invalid_input', "$field deve ser texto UTF-8 em uma única linha.");
        }
        $value = preg_replace('/^\s+|\s+$/u', '', $value);
        $length = preg_match_all('/./us', $value);
        if ($length < 1 || $length > $limit) {
            throw new TeacherException('invalid_input', "$field deve ter entre 1 e $limit caracteres.");
        }
        return $value;
    }
}
