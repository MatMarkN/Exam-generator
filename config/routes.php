<?php

declare(strict_types=1);

use App\Http\Actions\HealthAction;
use App\Http\Actions\DatabaseHealthAction;
use App\Http\Actions\BnccDocumentAction;
use App\Http\Actions\BnccCurriculumAction;
use App\Http\Actions\BnccContextAction;
use App\Http\Actions\ExamGenerationAction;
use App\Http\Actions\ExamHistoryAction;
use App\Http\Actions\ApiDocsAction;
use App\Http\Actions\TeacherAction;
use App\Http\Actions\SchoolCatalogAction;
use App\Application\Exams\GenerationCostEstimator;
use App\Application\AI\AIProviderInterface;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Slim\App;

return static function (App $app, DatabaseConnectionFactory $connections, ?AIProviderInterface $provider = null): void {
    $docs = new ApiDocsAction(getenv('APP_ENV') === 'demo');
    $app->get('/docs', [$docs, 'ui']);
    $app->get('/docs/', [$docs, 'ui']);
    $app->get('/openapi.json', [$docs, 'specification']);
    $app->get('/health', new HealthAction());
    $app->get('/health/database', new DatabaseHealthAction($connections));
    $teachers = new TeacherAction($connections);
    $app->post('/teachers', [$teachers, 'create']);
    $app->get('/teachers', [$teachers, 'listing']);
    $app->get('/teachers/{id:[0-9]+}', [$teachers, 'show']);
    $app->patch('/teachers/{id:[0-9]+}', [$teachers, 'update']);
    $app->get('/teachers/{id:[0-9]+}/settings', [$teachers, 'settings']);
    $app->patch('/teachers/{id:[0-9]+}/settings', [$teachers, 'updateSettings']);
    $catalogs = new SchoolCatalogAction($connections);
    $app->get('/subjects', [$catalogs, 'subjects']);
    $app->get('/school-grades', [$catalogs, 'grades']);
    $app->post('/bncc/context', new BnccContextAction($connections));
    $app->post('/exams/generate', new ExamGenerationAction($connections, $provider ?? require __DIR__ . '/ai.php',
        new GenerationCostEstimator(require __DIR__ . '/generation-pricing.php')));
    $history = new ExamHistoryAction($connections);
    $app->get('/generations', [$history, 'listing']);
    $app->get('/generations/{id:[0-9]+}', [$history, 'generation']);
    $app->get('/exams/{id:[0-9]+}', [$history, 'exam']);
    $app->get('/exams/{id:[0-9]+}/answer-key', [$history, 'answers']);
    $documents = new BnccDocumentAction($connections, __DIR__ . '/../storage/bncc');
    $app->post('/bncc/documents', [$documents, 'import']);
    $app->get('/bncc/documents', [$documents, 'listing']);
    $app->get('/bncc/documents/{id:[0-9]+}', [$documents, 'show']);
    $app->get('/bncc/documents/{id:[0-9]+}/pages', [$documents, 'pages']);
    $app->post('/bncc/documents/{id:[0-9]+}/reprocess', [$documents, 'reprocess']);
    $curriculum = new BnccCurriculumAction($connections, __DIR__ . '/../storage/bncc');
    $app->post('/bncc/documents/{id:[0-9]+}/structure', [$curriculum, 'structure']);
    $app->get('/bncc/documents/{id:[0-9]+}/structure', [$curriculum, 'overview']);
    $app->get('/bncc/documents/{id:[0-9]+}/skills', [$curriculum, 'skills']);
    $app->get('/bncc/documents/{id:[0-9]+}/skills/{code:EF[0-9]{2}[A-Z]{2}[0-9]{2}}', [$curriculum, 'skill']);
    $app->get('/bncc/documents/{id:[0-9]+}/competencies', [$curriculum, 'competencies']);
    $app->patch('/bncc/documents/{id:[0-9]+}/skills/{code:EF[0-9]{2}[A-Z]{2}[0-9]{2}}/review', [$curriculum, 'reviewSkill']);
    $app->patch('/bncc/documents/{id:[0-9]+}/competencies/{competencyId:[0-9]+}/review', [$curriculum, 'reviewCompetency']);
    $app->post('/bncc/documents/{id:[0-9]+}/publish', [$curriculum, 'publish']);
    $app->get('/bncc/documents/{id:[0-9]+}/releases', [$curriculum, 'releases']);
    $app->post('/bncc/documents/{id:[0-9]+}/releases', [$curriculum, 'publishScope']);
};
