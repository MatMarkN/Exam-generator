<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\DatabaseConnectionFactory;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../vendor/autoload.php';
$options = getopt('', ['email:']);
$email = strtolower(trim(is_string($options['email'] ?? null) ? $options['email'] : ''));
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Uso: php bin/claim-legacy-data.php --email=administrador@exemplo.com\n");
    exit(1);
}
$database = (new DatabaseConnectionFactory(require __DIR__ . '/../config/database.php'))->create();
$user = $database->get('users', ['id', 'role'], ['email' => $email]);
if ($user === null || $user['role'] !== 'admin') {
    fwrite(STDERR, "Cadastre a conta e promova-a a administrador antes de atribuir dados antigos.\n");
    exit(1);
}
$database->pdo->beginTransaction();
try {
    $teachers = $database->update('teachers', ['user_id' => $user['id']], ['user_id' => null])->rowCount();
    $generations = $database->update('generation_history', ['user_id' => $user['id']], ['user_id' => null])->rowCount();
    $database->pdo->commit();
    echo json_encode(['claimed_teachers' => $teachers, 'claimed_generations' => $generations], JSON_THROW_ON_ERROR), "\n";
} catch (Throwable $failure) {
    if ($database->pdo->inTransaction()) {
        $database->pdo->rollBack();
    }
    throw $failure;
}
