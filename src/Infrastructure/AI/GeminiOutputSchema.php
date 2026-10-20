<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Application\AI\AIErrorReason;
use App\Application\AI\AIProviderException;
use stdClass;

/** Adapta somente a cópia enviada ao Google; o validador local usa o schema completo. */
final class GeminiOutputSchema
{
    public static function adapt(stdClass $schema): stdClass
    {
        $result = new stdClass();
        foreach (get_object_vars($schema) as $key => $value) {
            if (in_array($key, ['minLength', 'maxLength', 'pattern', 'uniqueItems'], true)) {
                // Restrições mantidas no contrato original e verificadas pelo backend.
                continue;
            }
            if (in_array($key, ['properties', '$defs'], true) && $value instanceof stdClass) {
                $result->$key = new stdClass();
                foreach (get_object_vars($value) as $name => $child) {
                    if (!$child instanceof stdClass) {
                        throw new AIProviderException(AIErrorReason::UnsupportedFeature);
                    }
                    $result->$key->$name = self::adapt($child);
                }
            } elseif (in_array($key, ['items', 'additionalProperties'], true) && $value instanceof stdClass) {
                $result->$key = self::adapt($value);
            } elseif (in_array($key, ['anyOf', 'prefixItems'], true) && is_array($value)) {
                $result->$key = array_map(static function ($child): stdClass {
                    if (!$child instanceof stdClass) {
                        throw new AIProviderException(AIErrorReason::UnsupportedFeature);
                    }
                    return self::adapt($child);
                }, $value);
            } elseif (in_array($key, ['$id', '$ref', '$anchor', 'type', 'format', 'title', 'description', 'enum',
                'minItems', 'maxItems', 'minimum', 'maximum', 'required', 'additionalProperties'], true)) {
                $result->$key = $value;
            } else {
                throw new AIProviderException(AIErrorReason::UnsupportedFeature);
            }
        }
        return $result;
    }
}
