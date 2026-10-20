<?php
declare(strict_types=1);
namespace Tests\Support;

use App\Application\Auth\AuthenticatedUser;
use App\Infrastructure\Persistence\AuthRepository;
use Medoo\Medoo;
use Psr\Http\Message\ServerRequestInterface;

final class AuthFixture
{
    public function __construct(private readonly Medoo $database)
    {
    }

    public function create(string $role = 'admin'): array
    {
        $email = 'fixture-' . bin2hex(random_bytes(8)) . '@example.test';
        $user = (new AuthRepository($this->database))->register(['name' => 'Usuário SINTÉTICO', 'email' => $email,
            'password' => 'FixturePassword123!']);
        if ($role === 'admin') {
            $this->database->update('users', ['role' => 'admin'], ['id' => $user->id]);
            $user = new AuthenticatedUser($user->id, $user->name, $user->email, 'admin');
        }
        $session = (new AuthRepository($this->database))->createSession($user);
        return [...$session, 'cookie' => 'prova_clara_session=' . $session['token']];
    }

    public function authorize(ServerRequestInterface $request, array $session): ServerRequestInterface
    {
        return $request->withHeader('Host', 'localhost')->withCookieParams(['prova_clara_session' => $session['token']])
            ->withHeader('Cookie', $session['cookie'])->withHeader('X-CSRF-Token', $session['csrf_token']);
    }

    public function remove(array $session): void
    {
        // Restore only this fixture's reservations in the disposable database; production never refunds attempts.
        foreach ($this->database->select('generation_quota_days', ['quota_date', 'requests'],
            ['scope' => 'user', 'user_id' => $session['user']->id]) as $reservation) {
            $this->database->query('UPDATE generation_quota_days SET requests = GREATEST(0, requests - :used)
                WHERE quota_date = :date AND scope = :scope AND user_id = 0',
                [':used' => $reservation['requests'], ':date' => $reservation['quota_date'], ':scope' => 'global']);
        }
        $this->database->delete('generation_quota_days', ['scope' => 'user', 'user_id' => $session['user']->id]);
        $this->database->delete('generation_quota_users', ['user_id' => $session['user']->id]);
        $this->database->delete('auth_sessions', ['user_id' => $session['user']->id]);
        $this->database->delete('users', ['id' => $session['user']->id]);
    }
}
