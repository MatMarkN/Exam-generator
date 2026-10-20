<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../vendor/autoload.php';
$email = \App\Application\Auth\AuthInput::email($argv[1] ?? null);
$database = (new \App\Infrastructure\Persistence\DatabaseConnectionFactory(require __DIR__ . '/../config/database.php'))->create();
$user = $database->get('users', ['id', 'role'], ['email' => $email]);
if ($user === null) {
    fwrite(STDERR, "Conta não encontrada. Cadastre-se pela aplicação antes de promover.\n");
    exit(1);
}
$database->update('users', ['role' => 'admin'], ['id' => $user['id']]);
fwrite(STDOUT, "Papel administrativo atribuído. Atualize a sessão no navegador.\n");
