<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$databaseSettings = require __DIR__ . '/database.php';
$connections = new DatabaseConnectionFactory($databaseSettings);

$registerRoutes = require __DIR__ . '/routes.php';
$registerRoutes($app, $connections);

$app->addRoutingMiddleware();

$displayErrorDetails = filter_var(
    getenv('APP_DEBUG') ?: 'false',
    FILTER_VALIDATE_BOOLEAN,
);

// O middleware adicionado por último envolve os anteriores e captura seus erros.
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true);
$errorMiddleware->getDefaultErrorHandler()->forceContentType('application/json');

return $app;
