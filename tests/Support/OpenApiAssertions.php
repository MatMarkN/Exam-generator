<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Http\OpenApi\ApiSchemas;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Assert;

trait OpenApiAssertions
{
    private function assertOpenApiSchema(string $name, mixed $value): void
    {
        $schema = json_decode(json_encode(['$schema' => 'https://json-schema.org/draft/2020-12/schema',
            '$ref' => '#/components/schemas/' . $name, 'components' => ['schemas' => ApiSchemas::build()]], JSON_THROW_ON_ERROR));
        $validator = new Validator();
        $validator->parser()->setOption('allowDefaults', false)->setOption('allowKeywordsAlongsideRef', true);
        $result = $validator->validate(json_decode(json_encode($value, JSON_THROW_ON_ERROR)), $schema);
        Assert::assertTrue($result->isValid(), $name . ': ' . ($result->isValid() ? '' : json_encode((new ErrorFormatter())->format($result->error()))));
    }
}
