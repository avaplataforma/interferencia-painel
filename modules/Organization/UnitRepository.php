<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

use PDO;
use RuntimeException;
use Throwable;

final readonly class UnitRepository
{
    public function __construct(private PDO $database)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->database->query('SELECT u.id, u.code, u.name, u.city, u.is_active, COUNT(DISTINCT s.user_id) AS user_count FROM units u LEFT JOIN user_unit_scopes s ON s.unit_id = u.id GROUP BY u.id ORDER BY u.is_active DESC, u.name')->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->database->prepare('SELECT id, code, name, city, is_active FROM units WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $unit = $statement->fetch();

        return is_array($unit) ? $unit : null;
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM units WHERE code = :code';
        $parameters = ['code' => $code];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters['id'] = $exceptId;
        }
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn() > 0;
    }

    public function nameExists(string $name, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM units WHERE LOWER(name) = LOWER(:name)';
        $parameters = ['name' => trim($name)];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters['id'] = $exceptId;
        }
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn() > 0;
    }

    public function create(string $code, string $name, string $city, bool $active): int
    {
        $this->database->beginTransaction();
        try {
            $statement = $this->database->prepare('INSERT INTO units (code, name, city, is_active) VALUES (:code, :name, :city, :active)');
            $statement->execute(['code' => $code, 'name' => $name, 'city' => $city, 'active' => (int) $active]);
            $id = (int) $this->database->lastInsertId();
            $scope = $this->database->prepare("INSERT IGNORE INTO user_unit_scopes (user_id, unit_id) SELECT DISTINCT ur.user_id, :unit_id FROM user_roles ur INNER JOIN role_permissions rp ON rp.role_id = ur.role_id INNER JOIN permissions p ON p.id = rp.permission_id WHERE p.code = 'units.access_all'");
            $scope->execute(['unit_id' => $id]);
            $this->database->commit();

            return $id;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw new RuntimeException('Não foi possível criar a unidade.', 0, $exception);
        }
    }

    public function update(int $id, string $name, string $city, bool $active): void
    {
        $statement = $this->database->prepare('UPDATE units SET name = :name, city = :city, is_active = :active WHERE id = :id');
        $statement->execute(['id' => $id, 'name' => $name, 'city' => $city, 'active' => (int) $active]);
    }
}
