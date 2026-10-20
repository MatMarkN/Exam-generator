<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

final class InitialSchoolCatalogs extends AbstractSeed
{
    public function run(): void
    {
        // Catálogo inicial de nomes, sem importar habilidades nem regras da BNCC.
        $subjects = [
            'lingua_portuguesa' => 'Língua Portuguesa',
            'arte' => 'Arte',
            'educacao_fisica' => 'Educação Física',
            'lingua_inglesa' => 'Língua Inglesa',
            'matematica' => 'Matemática',
            'ciencias' => 'Ciências',
            'geografia' => 'Geografia',
            'historia' => 'História',
            'ensino_religioso' => 'Ensino Religioso',
        ];

        foreach ($subjects as $code => $name) {
            // Repetir a carga preserva IDs, nomes editados e datas dos registros existentes.
            $this->execute('INSERT INTO subjects (code, name) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE id = id', [$code, $name]);
        }

        for ($year = 1; $year <= 9; $year++) {
            $this->execute('INSERT INTO school_grades (code, name, education_stage, year_number)
                VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE id = id', [
                sprintf('ef_%02d', $year),
                $year . 'º ano',
                'ensino_fundamental',
                $year,
            ]);
        }
    }
}
