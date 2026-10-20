<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Examples\Demo\DemoCurriculum;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/demo/DemoCurriculum.php';

DemoCurriculum::assertEnvironment();
$connections = new DatabaseConnectionFactory(require __DIR__ . '/../config/database.php');
$id = DemoCurriculum::prepare($connections->create());
echo "Currículo SINTÉTICO pronto no banco demo. Documento: $id. Nenhuma chamada de IA.\n";
