<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Teachers\TeacherException;
use App\Application\Teachers\TeacherInput;
use Medoo\Medoo;

final class TeacherRepository
{
    public const DEFAULT_SETTINGS = ['school_name' => null, 'default_font_family' => 'Arial', 'default_font_size' => 12];

    /** userId null is reserved for trusted CLI/maintenance callers, never an HTTP request. */
    public function __construct(private readonly Medoo $database, private readonly ?int $userId = null)
    {
    }

    public function create(array $input): array
    {
        $data = TeacherInput::profile($input, true);
        return $this->transaction(function () use ($data): array {
            $this->database->insert('teachers', [...$data, 'user_id' => $this->userId]);
            $id = (int) $this->database->id();
            $this->database->insert('teacher_settings', ['teacher_id' => $id]);
            return $this->find($id);
        });
    }

    public function update(int $id, array $input): array
    {
        $data = TeacherInput::profile($input);
        return $this->transaction(function () use ($id, $data): array {
            $this->lock($id);
            $this->database->update('teachers', $data, ['id' => $id]);
            return $this->find($id);
        });
    }

    public function find(int $id): array
    {
        $row = $this->database->query('SELECT t.id, t.name, t.email, t.created_at, t.updated_at,
            s.school_name, s.default_font_family, s.default_font_size FROM teachers t
            LEFT JOIN teacher_settings s ON s.teacher_id = t.id WHERE t.id = :id'
            . ($this->userId === null ? '' : ' AND t.user_id = :owner'),
            [':id' => $id, ...($this->userId === null ? [] : [':owner' => $this->userId])])->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new TeacherException('not_found', 'Professor não encontrado.', 404);
        }
        return $this->profile($row);
    }

    public function settings(int $id): array
    {
        return $this->find($id)['settings'];
    }

    public function updateSettings(int $id, array $input): array
    {
        $data = TeacherInput::settings($input);
        return $this->transaction(function () use ($id, $data): array {
            // Serializa criação de preferências legadas e alterações concorrentes do mesmo professor.
            $this->lock($id);
            if (!$this->database->has('teacher_settings', ['teacher_id' => $id])) {
                $this->database->insert('teacher_settings', ['teacher_id' => $id, ...$data]);
            } else {
                $this->database->update('teacher_settings', $data, ['teacher_id' => $id]);
            }
            return $this->settings($id);
        });
    }

    public function listing(array $query): array
    {
        if (array_diff(array_keys($query), ['limit', 'before_id']) !== []) {
            throw new TeacherException('invalid_input', 'Filtro de professores não reconhecido.');
        }
        $limit = array_key_exists('limit', $query) ? self::positiveId($query['limit'], 100) : 20;
        $bindings = $this->userId === null ? [] : [':owner' => $this->userId];
        $clauses = $this->userId === null ? [] : ['t.user_id = :owner'];
        if (array_key_exists('before_id', $query)) {
            $bindings[':before_id'] = self::positiveId($query['before_id']);
            $clauses[] = 't.id < :before_id';
        }
        $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);
        $rows = $this->database->query('SELECT t.id, t.name, t.email, t.created_at, t.updated_at,
            s.school_name, s.default_font_family, s.default_font_size FROM teachers t
            LEFT JOIN teacher_settings s ON s.teacher_id = t.id' . $where . ' ORDER BY t.id DESC LIMIT ' . ($limit + 1), $bindings)->fetchAll(\PDO::FETCH_ASSOC);
        $hasMore = count($rows) > $limit;
        $items = array_map($this->profile(...), array_slice($rows, 0, $limit));
        return ['items' => $items, 'next_before_id' => $hasMore ? end($items)['id'] : null];
    }

    public static function positiveId(mixed $value, int $max = 4294967295): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]{0,9}$/D', (string) $value)
            || (int) $value > $max) {
            throw new TeacherException('invalid_input', 'Identificadores e limites devem ser inteiros positivos dentro do intervalo permitido.');
        }
        return (int) $value;
    }

    private function profile(array $row): array
    {
        return ['id' => (int) $row['id'], 'name' => $row['name'], 'email' => $row['email'],
            'settings' => ['school_name' => $row['school_name'],
                'default_font_family' => $row['default_font_family'] ?? self::DEFAULT_SETTINGS['default_font_family'],
                'default_font_size' => (int) ($row['default_font_size'] ?? self::DEFAULT_SETTINGS['default_font_size'])],
            'created_at' => str_replace(' ', 'T', $row['created_at']) . 'Z',
            'updated_at' => str_replace(' ', 'T', $row['updated_at']) . 'Z'];
    }

    private function lock(int $id): void
    {
        if ($this->database->query('SELECT id FROM teachers WHERE id = :id'
            . ($this->userId === null ? '' : ' AND user_id = :owner') . ' FOR UPDATE',
            [':id' => $id, ...($this->userId === null ? [] : [':owner' => $this->userId])])->fetchColumn() === false) {
            throw new TeacherException('not_found', 'Professor não encontrado.', 404);
        }
    }

    private function transaction(callable $operation): array
    {
        $pdo = $this->database->pdo;
        $pdo->beginTransaction();
        try {
            $result = $operation();
            $pdo->commit();
            return $result;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof \PDOException && (int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw new TeacherException('duplicate_email', 'O e-mail informado já pertence a outro professor.', 409);
            }
            throw $exception;
        }
    }
}
