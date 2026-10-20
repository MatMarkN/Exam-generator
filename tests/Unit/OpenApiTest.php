<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Exams\ExamGenerationRequest;
use App\Http\Actions\ApiDocsAction;
use App\Http\OpenApi\OpenApiDefinition;
use App\Infrastructure\AI\FakeAIProvider;
use App\Infrastructure\Persistence\DatabaseConnectionFactory;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\OpenApiAssertions;

final class OpenApiTest extends TestCase
{
    use OpenApiAssertions;

    public function testSpecificationPassesOfficialOpenApi31SchemaInBothEnvironments(): void
    {
        $source = file_get_contents(__DIR__ . '/../Fixtures/openapi/schema.json');
        // Opis 2.6 resolve a âncora dinâmica aninhada como a raiz. Como não há
        // extensão desse meta-schema, o alvo estático equivalente é $defs.schema.
        // A fixture oficial permanece intacta; os schemas dos dados são testados à parte.
        $schema = json_decode(str_replace('"$dynamicRef": "#meta"', '"$ref": "#/$defs/schema"', $source), flags: JSON_THROW_ON_ERROR);
        $validator = new Validator();
        $validator->parser()->setOption('allowDefaults', false)->setOption('allowKeywordsAlongsideRef', true);
        foreach ([false, true] as $demo) {
            $spec = json_decode(json_encode(OpenApiDefinition::build($demo), JSON_THROW_ON_ERROR));
            $result = $validator->validate($spec, $schema);
            self::assertTrue($result->isValid(), $result->isValid() ? '' : json_encode((new ErrorFormatter())->format($result->error())));
            self::assertSame('/', $spec->servers[0]->url);
        }
        $invalid = json_decode(json_encode(OpenApiDefinition::build(), JSON_THROW_ON_ERROR));
        $invalid->paths->{'/exams/generate'}->post->responses->{'200'}->description = 123;
        self::assertFalse($validator->validate($invalid, $schema)->isValid());
    }

    public function testEveryRegisteredApiOperationIsDocumentedWithUniqueId(): void
    {
        $app = AppFactory::create();
        (require __DIR__ . '/../../config/routes.php')($app, new DatabaseConnectionFactory([]), new FakeAIProvider('{}'));
        $actual = [];
        foreach ($app->getRouteCollector()->getRoutes() as $route) {
            if (in_array($route->getPattern(), ['/docs', '/docs/', '/openapi.json'], true)) {
                continue;
            }
            $path = preg_replace('/\{([A-Za-z]+):.+?\}(?=\/|$)/', '{$1}', $route->getPattern());
            foreach ($route->getMethods() as $method) {
                $actual[] = strtolower($method) . ' ' . $path;
            }
        }
        $documented = $ids = [];
        $spec = OpenApiDefinition::build();
        foreach ($spec['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $documented[] = $method . ' ' . $path;
                $ids[] = $operation['operationId'];
            }
        }
        sort($actual);
        sort($documented);
        self::assertSame($actual, $documented);
        self::assertCount(count($ids), array_unique($ids));
        preg_match_all('~"\$ref":"#/components/schemas/([^"]+)"~', json_encode($spec, JSON_UNESCAPED_SLASHES), $matches);
        foreach (array_unique($matches[1]) as $name) {
            self::assertArrayHasKey($name, $spec['components']['schemas']);
        }
    }

    public function testRequestExamplesMatchBothTheSchemaAndGenerationInputContract(): void
    {
        $spec = OpenApiDefinition::build(true);
        foreach ($spec['paths'] as $operations) {
            foreach ($operations as $operation) {
                $body = $operation['requestBody']['content']['application/json'] ?? null;
                if ($body === null) {
                    continue;
                }
                $schema = basename($body['schema']['$ref']);
                foreach ($body['examples'] as $example) {
                    $this->assertOpenApiSchema($schema, $example['value']);
                    if ($schema === 'ExamRequest') {
                        self::assertSame(3, ExamGenerationRequest::fromArray($example['value'])->questionCount);
                    }
                }
            }
        }
    }

    public function testDocumentationWorksWithoutDatabaseAndDoesNotAcceptRemoteSpecFromQueryString(): void
    {
        $factory = new ResponseFactory();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/docs?url=https://example.invalid/spec.json');
        foreach ([false, true] as $demo) {
            $action = new ApiDocsAction($demo);
            $ui = $action->ui($request, $factory->createResponse());
            self::assertSame(200, $ui->getStatusCode());
            self::assertStringContainsString($demo ? 'SIMULAÇÃO' : 'API PRINCIPAL', (string) $ui->getBody());
            self::assertStringNotContainsString('example.invalid', (string) $ui->getBody());
            self::assertStringContainsString("connect-src 'self'", $ui->getHeaderLine('Content-Security-Policy'));
            $response = $action->specification($request, $factory->createResponse());
            self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
            self::assertSame('3.1.0', json_decode((string) $response->getBody(), true)['openapi']);
        }
        $initializer = file_get_contents(__DIR__ . '/../../public/swagger-ui/init.js');
        self::assertStringContainsString('validatorUrl: null', $initializer);
        self::assertStringContainsString('queryConfigEnabled: false', $initializer);
    }

    public function testResponseExamplesAreValidAndExplicitlySynthetic(): void
    {
        $spec = OpenApiDefinition::build();
        foreach ($spec['paths'] as $operations) {
            foreach ($operations as $operation) {
                foreach ($operation['responses'] as $response) {
                    $body = $response['content']['application/json'];
                    foreach ($body['examples'] ?? [] as $example) {
                        $this->assertOpenApiSchema(basename($body['schema']['$ref']), $example['value']);
                    }
                }
            }
        }
        $example = $spec['paths']['/exams/generate']['post']['responses']['200']['content']['application/json']['examples']['sample']['value'];
        self::assertSame('fake', $example['generation']['provider']);
        self::assertSame('synthetic-exam-demo-v1', $example['bncc_context']['document']['structure_profile']);
    }

    public function testTeacherContractsAndExamMetadataPreserveHistoricalResponses(): void
    {
        $this->assertOpenApiSchema('Teacher', ['id' => 42, 'name' => 'Carolina Silva', 'email' => null,
            'settings' => ['school_name' => 'Escola Exemplo', 'default_font_family' => 'Arial', 'default_font_size' => 14],
            'created_at' => '2026-10-20T12:00:00Z', 'updated_at' => '2026-10-20T12:00:00Z']);
        $this->assertOpenApiSchema('TeacherSettings', ['school_name' => null, 'default_font_family' => 'Arial', 'default_font_size' => 40]);
        $this->assertOpenApiSchema('ExamFormatting', ['font_family' => 'Arial', 'font_size' => 40]);
        $historical = json_decode(file_get_contents(__DIR__ . '/../../config/openapi/simulated-response.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertOpenApiSchema('GenerationResult', $historical);
        self::assertArrayNotHasKey('header', $historical['exam']);
        $spec = OpenApiDefinition::build();
        $current = $spec['paths']['/exams/generate']['post']['responses']['200']['content']['application/json']['examples']['sample']['value'];
        $this->assertOpenApiSchema('GenerationResult', $current);
        self::assertSame('6º A', $current['exam']['header']['class_name']);
        self::assertSame(12, $current['exam']['formatting']['font_size']);
        self::assertArrayNotHasKey('teacher_id', OpenApiDefinition::examExample(), 'O exemplo executável não deve presumir um professor já cadastrado.');
    }

    public function testMetadataSchemasRejectUnknownHeaderFieldsAndInvalidPreferenceUpdates(): void
    {
        $spec = OpenApiDefinition::build();
        $validator = new Validator();
        $validator->parser()->setOption('allowDefaults', false)->setOption('allowKeywordsAlongsideRef', true);
        foreach ([
            ['ExamRequest', [...OpenApiDefinition::examExample(), 'exam_header' => ['subject' => 'outra matéria']]],
            ['ExamRequest', [...OpenApiDefinition::examExample(), 'exam_header' => ['school_name' => "Escola\nExemplo"]]],
            ['TeacherSettingsUpdate', ['default_font_size' => 0]],
            ['TeacherSettingsUpdate', ['default_font_size' => 40]],
            ['ExamRequest', [...OpenApiDefinition::examExample(), 'formatting' => ['font_size' => 40]]],
            ['TeacherSettingsUpdate', ['default_font_family' => null]],
            ['TeacherUpdate', (object) []],
        ] as [$name, $value]) {
            $schema = json_decode(json_encode(['$schema' => 'https://json-schema.org/draft/2020-12/schema',
                '$ref' => '#/components/schemas/' . $name, 'components' => $spec['components']], JSON_THROW_ON_ERROR));
            self::assertFalse($validator->validate(json_decode(json_encode($value, JSON_THROW_ON_ERROR)), $schema)->isValid(), $name);
        }
    }

    public function testScopedCurriculumMetadataPreservesGlobalAndHistoricalContracts(): void
    {
        $result = json_decode(file_get_contents(__DIR__ . '/../../config/openapi/simulated-response.json'), true, flags: JSON_THROW_ON_ERROR);
        $release = ['id' => 9, 'subject' => 'matematica', 'grade' => 6, 'released_at' => '2026-10-20 15:00:00'];
        $result['bncc_context']['document']['status'] = 'draft';
        $result['bncc_context']['document']['ready_at'] = null;
        $result['bncc_context']['document']['release'] = $release;
        $result['generation']['bncc_validation']['curriculum_release'] = $release;
        $this->assertOpenApiSchema('GenerationResult', $result);
        $this->assertOpenApiSchema('BnccReleaseRequest', ['subject' => 'matematica', 'grade' => 6]);
        $validator = new Validator();
        foreach ([['subject' => 'matematica', 'grade' => '6'], ['subject' => 'matematica', 'grade' => 10],
            ['subject' => 'matematica', 'grade' => 6, 'approve_all' => true]] as $input) {
            $spec = OpenApiDefinition::build();
            $schema = json_decode(json_encode(['$schema' => 'https://json-schema.org/draft/2020-12/schema',
                '$ref' => '#/components/schemas/BnccReleaseRequest', 'components' => $spec['components']], JSON_THROW_ON_ERROR));
            self::assertFalse($validator->validate(json_decode(json_encode($input, JSON_THROW_ON_ERROR)), $schema)->isValid());
        }
    }
}
