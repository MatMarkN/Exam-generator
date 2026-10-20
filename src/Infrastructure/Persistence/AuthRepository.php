<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence;

use App\Application\Auth\AuthenticatedUser;
use App\Application\Auth\AuthException;
use Medoo\Medoo;
use PDO;

final class AuthRepository
{
    private const DUMMY_HASH = '$argon2id$v=19$m=32768,t=3,p=1$NW5uMEMycmNqby40WWgyVg$TBgvH79hImbnLha9/Oy1gTp0/m+f8Mc8pJgX7DAFerA';

    public function __construct(private readonly Medoo $database)
    {
    }

    public function register(array $input): AuthenticatedUser
    {
        $hash = self::hashPassword($input['password']);
        try {
            $this->database->insert('users', ['name' => $input['name'], 'email' => $input['email'], 'password_hash' => $hash, 'role' => 'teacher']);
        } catch (\PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw new AuthException('email_unavailable', 'Não foi possível cadastrar esse e-mail. Use outra conta ou faça login.', 409);
            }
            throw $exception;
        }
        return new AuthenticatedUser((int) $this->database->id(), $input['name'], $input['email'], 'teacher');
    }

    public function login(string $email, string $password): AuthenticatedUser
    {
        $row = $this->database->get('users', ['id', 'name', 'email', 'role', 'password_hash'], ['email' => $email]);
        $valid = password_verify($password, $row['password_hash'] ?? self::DUMMY_HASH);
        if ($row === null || !$valid) {
            throw new AuthException('invalid_credentials', 'E-mail ou senha inválidos.');
        }
        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $options = defined('PASSWORD_ARGON2ID') ? ['memory_cost' => 32768, 'time_cost' => 3, 'threads' => 1] : ['cost' => 12];
        if (password_needs_rehash($row['password_hash'], $algorithm, $options)) {
            $this->database->update('users', ['password_hash' => self::hashPassword($password)], ['id' => $row['id']]);
        }
        return new AuthenticatedUser($row['id'], $row['name'], $row['email'], $row['role']);
    }

    /** O token em claro existe somente durante a criação e no cookie; o banco recebe apenas SHA-256. */
    public function createSession(AuthenticatedUser $user, int $seconds = 43200): array
    {
        $token = bin2hex(random_bytes(32));
        $csrf = bin2hex(random_bytes(32));
        $this->database->query('DELETE FROM auth_sessions WHERE expires_at <= :now LIMIT 100', [':now' => gmdate('Y-m-d H:i:s')]);
        $this->database->insert('auth_sessions', ['user_id' => $user->id, 'token_hash' => hash('sha256', $token),
            'csrf_token' => $csrf, 'expires_at' => gmdate('Y-m-d H:i:s', time() + $seconds)]);
        return ['user' => $user, 'token' => $token, 'csrf_token' => $csrf];
    }

    public function session(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new AuthException('auth_required', 'Entre na sua conta para continuar.');
        }
        $row = $this->database->query('SELECT u.id,u.name,u.email,u.role,s.csrf_token FROM auth_sessions s
            INNER JOIN users u ON u.id=s.user_id WHERE s.token_hash=:hash AND s.expires_at>:now',
            [':hash' => hash('sha256', $token), ':now' => gmdate('Y-m-d H:i:s')])->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new AuthException('auth_required', 'Sua sessão expirou. Entre novamente.');
        }
        return ['user' => new AuthenticatedUser($row['id'], $row['name'], $row['email'], $row['role']), 'csrf_token' => $row['csrf_token']];
    }

    public function revoke(string $token): void
    {
        $this->database->delete('auth_sessions', ['token_hash' => hash('sha256', $token)]);
    }

    public function limit(string $scope, int $maximum, int $seconds): void
    {
        $now = time();
        $end = (intdiv($now, $seconds) + 1) * $seconds;
        $key = hash('sha256', $scope . ':' . intdiv($now, $seconds));
        $this->database->query('DELETE FROM auth_rate_limits WHERE expires_at <= :now LIMIT 100', [':now' => gmdate('Y-m-d H:i:s', $now)]);
        $this->database->query('INSERT INTO auth_rate_limits (limit_key,attempts,expires_at) VALUES (:key,1,:expiry)
            ON DUPLICATE KEY UPDATE attempts=attempts+1', [':key' => $key, ':expiry' => gmdate('Y-m-d H:i:s', $end)]);
        $attempts = (int) $this->database->get('auth_rate_limits', 'attempts', ['limit_key' => $key]);
        if ($attempts > $maximum) {
            throw new AuthException('auth_rate_limited', 'Muitas tentativas. Aguarde antes de tentar novamente.', 429, max(1, $end - $now));
        }
    }

    public function clearLimit(string $scope, int $seconds): void
    {
        $this->database->delete('auth_rate_limits', ['limit_key' => hash('sha256', $scope . ':' . intdiv(time(), $seconds))]);
    }

    private static function hashPassword(string $password): string
    {
        return defined('PASSWORD_ARGON2ID')
            ? password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 32768, 'time_cost' => 3, 'threads' => 1])
            : password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}
