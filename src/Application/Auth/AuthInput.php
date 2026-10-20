<?php
declare(strict_types=1);
namespace App\Application\Auth;

final class AuthInput
{
    public static function register(array $input): array
    {
        self::keys($input, ['name', 'email', 'password', 'password_confirmation']);
        $name = $input['name'] ?? null;
        if (!is_string($name) || preg_match('//u', $name) !== 1 || preg_match('/[\x00-\x1f\x7f]/u', $name)
            || preg_match_all('/./us', trim($name)) < 2 || preg_match_all('/./us', trim($name)) > 160) {
            throw new AuthException('invalid_input', 'Informe um nome com 2 a 160 caracteres em uma linha.', 422);
        }
        $email = self::email($input['email'] ?? null);
        $password = self::password($input['password'] ?? null);
        if (!is_string($input['password_confirmation'] ?? null) || !hash_equals($password, $input['password_confirmation'])) {
            throw new AuthException('invalid_input', 'A confirmação da senha deve corresponder à senha.', 422);
        }
        return ['name' => trim($name), 'email' => $email, 'password' => $password];
    }

    public static function login(array $input): array
    {
        self::keys($input, ['email', 'password']);
        $email = self::email($input['email'] ?? null);
        $password = $input['password'] ?? null;
        if (!is_string($password) || $password === '' || strlen($password) > 512 || str_contains($password, "\0")) {
            throw new AuthException('invalid_credentials', 'E-mail ou senha inválidos.');
        }
        return ['email' => $email, 'password' => $password];
    }

    public static function email(mixed $email): string
    {
        if (!is_string($email) || strlen($email = trim($email)) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AuthException('invalid_input', 'Informe um e-mail válido com até 254 caracteres.', 422);
        }
        return strtolower($email);
    }

    private static function password(mixed $password): string
    {
        if (!is_string($password) || strlen($password) > 512 || str_contains($password, "\0") || preg_match('//u', $password) !== 1
            || ($length = preg_match_all('/./us', $password)) < 12 || $length > 128
            || (!defined('PASSWORD_ARGON2ID') && strlen($password) > 72)) {
            throw new AuthException('invalid_input', 'Use uma senha com 12 a 128 caracteres (até 512 bytes).', 422);
        }
        return $password;
    }

    private static function keys(array $input, array $allowed): void
    {
        if (array_is_list($input) || array_diff(array_keys($input), $allowed) !== []) {
            throw new AuthException('invalid_input', 'O cadastro ou login contém campos não permitidos.', 422);
        }
    }
}
