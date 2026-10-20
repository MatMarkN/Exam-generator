<?php

declare(strict_types=1);

namespace App\Application\AI;

use InvalidArgumentException;
use JsonException;
use stdClass;

/** Verifica o transporte JSON; não é um validador de JSON Schema nem de conteúdo pedagógico. */
final class JsonObject
{
    public static function decode(string $json, int $maxBytes): stdClass
    {
        if (strlen($json) > $maxBytes) {
            throw new InvalidArgumentException('O objeto JSON excedeu o limite de tamanho.');
        }
        try {
            $value = json_decode($json, false, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            // O texto recebido pode conter dados privados; não o inclua na exceção.
            throw new InvalidArgumentException('O conteúdo deve ser JSON válido em UTF-8.');
        }
        if (!$value instanceof stdClass) {
            throw new InvalidArgumentException('A raiz do JSON deve ser um objeto.');
        }
        return $value;
    }
}
