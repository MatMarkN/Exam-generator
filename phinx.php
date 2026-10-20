<?php

declare(strict_types=1);

$database = require __DIR__ . '/config/database.php';
$environment = match (getenv('APP_ENV')) {
    'test' => 'test',
    'demo' => 'demo',
    default => 'local',
};

$isolatedDatabases = ['test' => 'gerador_de_provas_test', 'demo' => 'gerador_de_provas_demo'];
if (isset($isolatedDatabases[$environment]) && $database['database'] !== $isolatedDatabases[$environment]) {
    throw new RuntimeException("O ambiente $environment exige o banco {$isolatedDatabases[$environment]}.");
}

return [
    'paths' => [
        'migrations' => __DIR__ . '/database/migrations',
        'seeds' => __DIR__ . '/database/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => $environment,
        $environment => [
            'adapter' => 'mysql',
            'host' => $database['host'],
            'port' => $database['port'],
            'name' => $database['database'],
            'user' => $database['username'],
            'pass' => $database['password'],
            'charset' => $database['charset'],
            'collation' => $database['collation'],
        ],
    ],
    'version_order' => 'creation',
];
