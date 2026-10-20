<?php
declare(strict_types=1);
namespace App\Application\Auth;

use Psr\Http\Message\ServerRequestInterface;

final readonly class AuthenticatedUser
{
    public function __construct(public int $id, public string $name, public string $email, public string $role)
    {
    }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $user = $request->getAttribute('auth_user');
        return $user instanceof self ? $user : throw new AuthException('auth_required', 'Entre na sua conta para continuar.');
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'role' => $this->role];
    }
}
