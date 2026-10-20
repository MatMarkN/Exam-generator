<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Application\Auth\AuthException;
use App\Application\Auth\AuthInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthInputTest extends TestCase
{
    public function testRegistrationNormalizesIdentityWithoutTrimmingPasswordOrAcceptingRole(): void
    {
        $input = AuthInput::register(['name' => '  Professora Teste  ', 'email' => ' Teste@Example.test ',
            'password' => ' Senha longa para teste ', 'password_confirmation' => ' Senha longa para teste ']);
        self::assertSame('Professora Teste', $input['name']);
        self::assertSame('teste@example.test', $input['email']);
        self::assertSame(' Senha longa para teste ', $input['password']);
        self::assertArrayNotHasKey('role', $input);
    }

    #[DataProvider('invalidRegistrations')]
    public function testRegistrationRejectsPrivilegeEscalationAndInvalidCredentials(array $overrides): void
    {
        $input = array_replace(['name' => 'Professora Teste', 'email' => 'teste@example.test',
            'password' => 'SenhaDeTeste123!', 'password_confirmation' => 'SenhaDeTeste123!'], $overrides);
        $this->expectException(AuthException::class);
        AuthInput::register($input);
    }

    public static function invalidRegistrations(): array
    {
        return [
            [['role' => 'admin']], [['user_id' => 1]], [['name' => "Nome\nQuebrado"]], [['name' => 'A']],
            [['email' => 'invalido']], [['password' => 'curta', 'password_confirmation' => 'curta']],
            [['password_confirmation' => 'Diferente123456!']], [['password' => str_repeat('x', 129)]],
            [['password' => "SenhaLonga123\0", 'password_confirmation' => "SenhaLonga123\0"]],
        ];
    }
}
