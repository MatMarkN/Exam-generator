<?php

declare(strict_types=1);

namespace App\Application\AI;

use InvalidArgumentException;
use stdClass;

final readonly class AIRequest
{
    public function __construct(
        public string $instructions,
        public string $inputJson,
        public string $schemaName,
        public string $outputSchemaJson,
        public int $maxOutputTokens = 4096,
        public int $timeoutSeconds = 60,
    ) {
        if (trim($instructions) === '' || strlen($instructions) > 16 * 1024 || !preg_match('//u', $instructions)) {
            throw new InvalidArgumentException('As instruções devem ser texto UTF-8 não vazio, de até 16 KiB.');
        }
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/D', $schemaName)) {
            throw new InvalidArgumentException('O nome do schema deve ter de 1 a 64 caracteres: letras, números, hífen ou sublinhado, começando por letra.');
        }
        if ($maxOutputTokens < 1 || $maxOutputTokens > 32768 || $timeoutSeconds < 1 || $timeoutSeconds > 120) {
            throw new InvalidArgumentException('Use de 1 a 32768 tokens de saída e de 1 a 120 segundos de timeout.');
        }
        JsonObject::decode($inputJson, 256 * 1024);
        $schema = JsonObject::decode($outputSchemaJson, 32 * 1024);
        if (($schema->type ?? null) !== 'object' || !($schema->properties ?? null) instanceof stdClass || !is_array($schema->required ?? null) || !array_is_list($schema->required) || ($schema->additionalProperties ?? null) !== false) {
            throw new InvalidArgumentException('O schema deve declarar type object, properties, required e additionalProperties false.');
        }
        foreach ($schema->required as $name) {
            if (!is_string($name)) {
                throw new InvalidArgumentException('required deve conter nomes de propriedades como textos.');
            }
        }
        $required = $schema->required;
        $properties = array_map('strval', array_keys(get_object_vars($schema->properties)));
        sort($required, SORT_STRING);
        sort($properties, SORT_STRING);
        if ($required !== $properties) {
            throw new InvalidArgumentException('required deve listar cada propriedade da raiz exatamente uma vez.');
        }
    }

    /** Devolve uma cópia decodificada; alterações nela não modificam o pedido original. */
    public function input(): stdClass
    {
        return JsonObject::decode($this->inputJson, 256 * 1024);
    }

    public function outputSchema(): stdClass
    {
        return JsonObject::decode($this->outputSchemaJson, 32 * 1024);
    }
}
