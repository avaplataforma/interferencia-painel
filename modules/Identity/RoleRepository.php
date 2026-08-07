<?php

declare(strict_types=1);

namespace Interferencia\Modules\Identity;

use PDO;
use RuntimeException;
use Throwable;

final readonly class RoleRepository
{
    public function __construct(private PDO $database, private bool $platform = false)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->database->query('SELECT r.id,r.code,r.name,COUNT(DISTINCT ur.user_id) user_count,GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ", ") permissions FROM '.$this->rolesTable().' r LEFT JOIN '.$this->userRolesTable().' ur ON ur.role_id=r.id LEFT JOIN '.$this->rolePermissionsTable().' rp ON rp.role_id=r.id LEFT JOIN '.$this->permissionsTable().' p ON p.id=rp.permission_id GROUP BY r.id ORDER BY r.name')->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->database->prepare('SELECT id, code, name FROM '.$this->rolesTable().' WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $role = $statement->fetch();

        return is_array($role) ? $role : null;
    }

    /** @return list<array{id: int, code: string, name: string}> */
    public function permissions(): array
    {
        return $this->database->query('SELECT id, code, name FROM '.$this->permissionsTable().' ORDER BY name')->fetchAll();
    }

    /** @return list<int> */
    public function permissionIds(int $roleId): array
    {
        $statement = $this->database->prepare('SELECT permission_id FROM '.$this->rolePermissionsTable().' WHERE role_id = :id');
        $statement->execute(['id' => $roleId]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function codeExists(string $code): bool
    {
        $statement = $this->database->prepare('SELECT COUNT(*) FROM '.$this->rolesTable().' WHERE code = :code');
        $statement->execute(['code' => $code]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function nameExists(string $name, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM '.$this->rolesTable().' WHERE LOWER(name) = LOWER(:name)';
        $parameters = ['name' => trim($name)];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters['id'] = $exceptId;
        }
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn() > 0;
    }

    /** @param list<int> $permissionIds */
    public function create(string $code, string $name, array $permissionIds): int
    {
        $this->database->beginTransaction();
        try {
            $statement = $this->database->prepare('INSERT INTO '.$this->rolesTable().' (code, name) VALUES (:code, :name)');
            $statement->execute(['code' => $code, 'name' => trim($name)]);
            $id = (int) $this->database->lastInsertId();
            $this->syncPermissions($id, $permissionIds);
            $this->database->commit();

            return $id;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw new RuntimeException('Não foi possível criar o perfil.', 0, $exception);
        }
    }

    /** @param list<int> $permissionIds */
    public function update(int $id, string $name, array $permissionIds): void
    {
        $this->database->beginTransaction();
        try {
            $statement = $this->database->prepare('UPDATE '.$this->rolesTable().' SET name = :name WHERE id = :id');
            $statement->execute(['id' => $id, 'name' => trim($name)]);
            $this->syncPermissions($id, $permissionIds);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw new RuntimeException('Não foi possível atualizar o perfil.', 0, $exception);
        }
    }

    /** @param list<int> $permissionIds */
    private function syncPermissions(int $roleId, array $permissionIds): void
    {
        $delete = $this->database->prepare('DELETE FROM '.$this->rolePermissionsTable().' WHERE role_id = :id');
        $delete->execute(['id' => $roleId]);
        $insert = $this->database->prepare('INSERT INTO '.$this->rolePermissionsTable().' (role_id, permission_id) VALUES (:role, :permission)');
        foreach ($permissionIds as $permissionId) $insert->execute(['role' => $roleId, 'permission' => $permissionId]);
    }
    private function rolesTable(): string { return $this->platform ? 'platform_roles' : 'roles'; }
    private function permissionsTable(): string { return $this->platform ? 'platform_permissions' : 'permissions'; }
    private function userRolesTable(): string { return $this->platform ? 'platform_user_roles' : 'user_roles'; }
    private function rolePermissionsTable(): string { return $this->platform ? 'platform_role_permissions' : 'role_permissions'; }
}
