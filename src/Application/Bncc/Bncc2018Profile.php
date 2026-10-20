<?php

declare(strict_types=1);

namespace App\Application\Bncc;

final class Bncc2018Profile
{
    public const NAME = 'bncc-2018-600-pages-v1';
    // Cópia recuperada do PDF fornecido. Outra edição exige outro perfil, não uma suposição de paginação.
    public const SHA256 = 'cf032f2e8fc0ddabe4839395ec5927ab8fe6a2d15cdf7b62899d582efcfeefd6';

    public function assertSupported(array $document): void
    {
        if ($document['sha256'] !== self::SHA256 || $document['page_count'] !== 600) {
            throw new ImportException('unsupported_document', 'Este PDF ainda não possui um perfil de estruturação validado.');
        }
    }

    /** Limites físicos conferidos no sumário (páginas 5–6); rótulos impressos têm deslocamento de 2. */
    public function areas(): array
    {
        return [
            'linguagens' => ['name' => 'Linguagens', 'start' => 65, 'end' => 266],
            'matematica' => ['name' => 'Matemática', 'start' => 267, 'end' => 322],
            'ciencias_natureza' => ['name' => 'Ciências da Natureza', 'start' => 323, 'end' => 354],
            'ciencias_humanas' => ['name' => 'Ciências Humanas', 'start' => 355, 'end' => 436],
            'ensino_religioso' => ['name' => 'Ensino Religioso', 'start' => 437, 'end' => 462],
        ];
    }

    public function components(): array
    {
        return [
            'LP' => ['subject' => 'lingua_portuguesa', 'area' => 'linguagens', 'name' => 'Língua Portuguesa', 'start' => 69, 'end' => 194, 'expected' => 391],
            'AR' => ['subject' => 'arte', 'area' => 'linguagens', 'name' => 'Arte', 'start' => 195, 'end' => 214, 'expected' => 61],
            'EF' => ['subject' => 'educacao_fisica', 'area' => 'linguagens', 'name' => 'Educação Física', 'start' => 215, 'end' => 242, 'expected' => 69],
            'LI' => ['subject' => 'lingua_inglesa', 'area' => 'linguagens', 'name' => 'Língua Inglesa', 'start' => 243, 'end' => 266, 'expected' => 88],
            'MA' => ['subject' => 'matematica', 'area' => 'matematica', 'name' => 'Matemática', 'start' => 270, 'end' => 322, 'expected' => 247],
            'CI' => ['subject' => 'ciencias', 'area' => 'ciencias_natureza', 'name' => 'Ciências', 'start' => 327, 'end' => 354, 'expected' => 111],
            'GE' => ['subject' => 'geografia', 'area' => 'ciencias_humanas', 'name' => 'Geografia', 'start' => 361, 'end' => 398, 'expected' => 123],
            'HI' => ['subject' => 'historia', 'area' => 'ciencias_humanas', 'name' => 'História', 'start' => 399, 'end' => 436, 'expected' => 151],
            'ER' => ['subject' => 'ensino_religioso', 'area' => 'ensino_religioso', 'name' => 'Ensino Religioso', 'start' => 440, 'end' => 462, 'expected' => 63],
        ];
    }

    public function competencies(): array
    {
        return [
            ['scope' => 'general', 'ref' => null, 'pages' => [11, 12], 'count' => 10],
            ['scope' => 'area', 'ref' => 'linguagens', 'pages' => [67], 'count' => 6],
            ['scope' => 'component', 'ref' => 'LP', 'pages' => [89], 'count' => 10],
            ['scope' => 'component', 'ref' => 'AR', 'pages' => [200], 'count' => 9],
            ['scope' => 'component', 'ref' => 'EF', 'pages' => [225], 'count' => 10],
            ['scope' => 'component', 'ref' => 'LI', 'pages' => [248], 'count' => 6],
            ['scope' => 'area', 'ref' => 'matematica', 'pages' => [269], 'count' => 8],
            ['scope' => 'area', 'ref' => 'ciencias_natureza', 'pages' => [326], 'count' => 8],
            ['scope' => 'area', 'ref' => 'ciencias_humanas', 'pages' => [359], 'count' => 7],
            ['scope' => 'component', 'ref' => 'GE', 'pages' => [368], 'count' => 7],
            ['scope' => 'component', 'ref' => 'HI', 'pages' => [404], 'count' => 7],
            ['scope' => 'area', 'ref' => 'ensino_religioso', 'pages' => [439], 'count' => 6],
        ];
    }

    /** Convenção explicitada pela BNCC na página física 32; não representa dificuldade. */
    public function grades(string $code): array
    {
        if (!preg_match('/^EF([0-9]{2})([A-Z]{2})[0-9]{2}$/D', $code, $match)
            || !isset($this->components()[$match[2]])) {
            throw new ImportException('invalid_curriculum', 'Código de habilidade incompatível com o perfil.');
        }
        $years = match ($match[1]) {
            '01', '02', '03', '04', '05', '06', '07', '08', '09' => [(int) $match[1]],
            '15' => range(1, 5), '69' => range(6, 9), '12' => [1, 2],
            '35' => [3, 4, 5], '67' => [6, 7], '89' => [8, 9],
            default => throw new ImportException('invalid_curriculum', 'Bloco de anos não reconhecido na BNCC.'),
        };
        if ($match[2] === 'LI' && min($years) < 6) {
            throw new ImportException('invalid_curriculum', 'O perfil prevê Língua Inglesa somente nos anos finais.');
        }
        return $years;
    }
}
