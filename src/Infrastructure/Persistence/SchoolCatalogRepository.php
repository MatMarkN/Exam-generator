<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use Medoo\Medoo;

final class SchoolCatalogRepository
{
    public function __construct(private readonly Medoo $database)
    {
    }

    public function subjects(): array
    {
        return ['items' => $this->database->select('subjects', ['id', 'code', 'name'], ['ORDER' => ['name' => 'ASC', 'id' => 'ASC']])];
    }

    public function grades(): array
    {
        return ['items' => $this->database->select('school_grades', ['id', 'code', 'name', 'education_stage', 'year_number'],
            ['ORDER' => ['year_number' => 'ASC', 'id' => 'ASC']])];
    }
}
