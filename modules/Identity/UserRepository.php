<?php

declare(strict_types=1);

namespace Interferencia\Modules\Identity;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final readonly class UserRepository
{
    public function __construct(private PDO $database, private ?int $organizationId = null, private bool $platform = false)
    {
    }

    public function findByEmail(string $email): ?User
    {
        $sql='SELECT u.* FROM '.$this->usersTable().' u WHERE u.email=:email';$parameters=['email'=>strtolower(trim($email))];
        if($this->organizationId!==null){$sql.=" AND EXISTS(SELECT 1 FROM organization_users membership WHERE membership.user_id=u.id AND membership.organization_id=:organization AND membership.status='active')";$parameters['organization']=$this->organizationId;}
        $statement = $this->database->prepare($sql.' LIMIT 1');
        $statement->execute($parameters);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findById(int $id): ?User
    {
        $sql='SELECT u.* FROM '.$this->usersTable().' u WHERE u.id=:id';$parameters=['id'=>$id];
        if($this->organizationId!==null){$sql.=" AND EXISTS(SELECT 1 FROM organization_users membership WHERE membership.user_id=u.id AND membership.organization_id=:organization AND membership.status='active')";$parameters['organization']=$this->organizationId;}
        $statement = $this->database->prepare($sql.' LIMIT 1');
        $statement->execute($parameters);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function createAdministrator(string $name, string $email, string $passwordHash): int
    {
        $this->database->beginTransaction();

        try {
            $statement = $this->database->prepare('INSERT INTO `'.$this->usersTable().'` (`name`, `email`, `password_hash`) VALUES (:name, :email, :password_hash)');
            $statement->execute([
                'name' => trim($name),
                'email' => strtolower(trim($email)),
                'password_hash' => $passwordHash,
            ]);
            $userId = (int) $this->database->lastInsertId();
            if(!$this->platform&&$this->organizationId!==null){$membership=$this->database->prepare("INSERT INTO organization_users(organization_id,user_id,status,is_owner) VALUES(?,?,'active',1)");$membership->execute([$this->organizationId,$userId]);}
            $role = $this->database->prepare('INSERT INTO `'.$this->userRolesTable().'` (`user_id`, `role_id`) SELECT :user_id, `id` FROM `'.$this->rolesTable()."` WHERE `code` = 'super_admin'");
            $role->execute(['user_id' => $userId]);
            if(!$this->platform){$scopes = $this->database->prepare('INSERT INTO `user_unit_scopes` (`user_id`, `unit_id`) SELECT :user_id, `id` FROM `units`');$scopes->execute(['user_id' => $userId]);}
            $this->database->commit();

            return $userId;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw new RuntimeException('Não foi possível criar o administrador.', 0, $exception);
        }
    }

    /** @return list<array<string, mixed>> */
    public function allForManagement(): array
    {
        if ($this->platform) {
            return $this->database->query("SELECT u.id, u.name, u.email, u.is_active, u.last_login_at, GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS roles, 0 AS unit_count FROM platform_users u LEFT JOIN platform_user_roles ur ON ur.user_id = u.id LEFT JOIN platform_roles r ON r.id = ur.role_id GROUP BY u.id ORDER BY u.name")->fetchAll();
        }
        if($this->organizationId===null)return $this->database->query("SELECT u.id, u.name, u.email, u.is_active, u.last_login_at, GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS roles, COUNT(DISTINCT s.unit_id) AS unit_count FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id LEFT JOIN user_unit_scopes s ON s.user_id = u.id GROUP BY u.id ORDER BY u.name")->fetchAll();
        $statement=$this->database->prepare("SELECT u.id,u.name,u.email,u.is_active,u.last_login_at,GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') roles,COUNT(DISTINCT scoped.id) unit_count,EXISTS(SELECT 1 FROM user_roles master_ur INNER JOIN roles master_r ON master_r.id=master_ur.role_id WHERE master_ur.user_id=u.id AND master_r.code IN ('super_admin','headquarters')) is_master FROM users u INNER JOIN organization_users membership ON membership.user_id=u.id AND membership.organization_id=? AND membership.status='active' LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id LEFT JOIN user_unit_scopes s ON s.user_id=u.id LEFT JOIN units scoped ON scoped.id=s.unit_id AND scoped.organization_id=membership.organization_id GROUP BY u.id ORDER BY u.name");$statement->execute([$this->organizationId]);return$statement->fetchAll();
    }

    /** @param list<int> $unitIds @return list<array<string,mixed>> */
    public function activeForUnits(array $unitIds): array
    {
        if($this->platform||$unitIds===[])return[];$marks=implode(',',array_fill(0,count($unitIds),'?'));$statement=$this->database->prepare("SELECT u.id,u.name,u.email,GROUP_CONCAT(DISTINCT s.unit_id ORDER BY s.unit_id) unit_ids FROM users u INNER JOIN user_unit_scopes s ON s.user_id=u.id WHERE u.is_active=1 AND s.unit_id IN ($marks) GROUP BY u.id ORDER BY u.name");$statement->execute($unitIds);return$statement->fetchAll();
    }

    public function activeInUnit(int $userId,int $unitId): bool
    {
        if($this->platform)return false;$statement=$this->database->prepare('SELECT COUNT(*) FROM users u INNER JOIN user_unit_scopes s ON s.user_id=u.id WHERE u.id=:user AND s.unit_id=:unit AND u.is_active=1');$statement->execute(['user'=>$userId,'unit'=>$unitId]);return(int)$statement->fetchColumn()===1;
    }

    /** @return list<array{id: int, code: string, name: string}> */
    public function availableRoles(): array
    {
        $roles=$this->database->query('SELECT id, code, name FROM '.$this->rolesTable().' ORDER BY name')->fetchAll();
        if($this->platform)return $roles;
        return array_values(array_filter($roles,static fn(array$role):bool=>!in_array($role['code'],['super_admin','headquarters'],true)));
    }

    /** @return list<array{id:int,code:string,name:string}> Todos os papéis, inclusive os reservados à Central. */
    public function allRoles(): array
    {
        return $this->database->query('SELECT id, code, name FROM '.$this->rolesTable().' ORDER BY name')->fetchAll();
    }

    /** Papéis selecionados concedem acesso a todas as unidades? */
    public function roleGrantsAllUnits(array $roleIds): bool
    {
        $roleIds=array_values(array_unique(array_filter(array_map('intval',$roleIds),static fn(int$id):bool=>$id>0)));
        if($roleIds===[])return false;
        $marks=implode(',',array_fill(0,count($roleIds),'?'));
        $statement=$this->database->prepare("SELECT COUNT(*) FROM role_permissions rp INNER JOIN permissions p ON p.id=rp.permission_id WHERE p.code='units.access_all' AND rp.role_id IN ({$marks})");
        $statement->execute($roleIds);
        return (int)$statement->fetchColumn()>0;
    }

    /** Usuário master da franquia (papel Gestor ou Admin), gerido somente pela Central. */
    public function isMasterUser(int $userId): bool
    {
        $statement=$this->database->prepare("SELECT COUNT(*) FROM user_roles ur INNER JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=:user AND r.code IN ('super_admin','headquarters')");
        $statement->execute(['user'=>$userId]);
        return (int)$statement->fetchColumn()>0;
    }

    /** @return list<array{id: int, code: string, name: string}> */
    public function availableUnits(): array
    {
        if($this->platform)return[];if($this->organizationId===null)return $this->database->query('SELECT id, code, name FROM units WHERE is_active = 1 ORDER BY name')->fetchAll();
        $statement=$this->database->prepare('SELECT id,code,name FROM units WHERE is_active=1 AND organization_id=? ORDER BY name');$statement->execute([$this->organizationId]);return$statement->fetchAll();
    }

    /** @return list<int> */
    public function roleIds(int $userId): array
    {
        $statement = $this->database->prepare('SELECT role_id FROM '.$this->userRolesTable().' WHERE user_id = :id');
        $statement->execute(['id' => $userId]);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<int> */
    public function unitIds(int $userId): array
    {
        if($this->platform)return[];$sql='SELECT s.unit_id FROM user_unit_scopes s INNER JOIN units u ON u.id=s.unit_id WHERE s.user_id=:id';$parameters=['id'=>$userId];
        if($this->organizationId!==null){$sql.=' AND u.organization_id=:organization';$parameters['organization']=$this->organizationId;}
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function isSuperAdmin(int $userId): bool
    {
        $statement = $this->database->prepare('SELECT COUNT(*) FROM '.$this->userRolesTable().' ur INNER JOIN '.$this->rolesTable()." r ON r.id=ur.role_id WHERE ur.user_id=:id AND r.code='super_admin'");
        $statement->execute(['id' => $userId]);
        return (int) $statement->fetchColumn() > 0;
    }

    public function activeSuperAdminCount(): int
    {
        return (int) $this->database->query('SELECT COUNT(DISTINCT u.id) FROM '.$this->usersTable().' u INNER JOIN '.$this->userRolesTable().' ur ON ur.user_id=u.id INNER JOIN '.$this->rolesTable()." r ON r.id=ur.role_id WHERE u.is_active=1 AND r.code='super_admin'")->fetchColumn();
    }

    /** @param list<int> $roleIds @param list<int> $unitIds */
    public function createManaged(string $name, string $email, string $passwordHash, bool $active, array $roleIds, array $unitIds): int
    {
        $this->database->beginTransaction();
        try {
            $statement = $this->database->prepare('INSERT INTO '.$this->usersTable().' (name, email, password_hash, is_active) VALUES (:name, :email, :hash, :active)');
            $statement->execute(['name' => trim($name), 'email' => strtolower(trim($email)), 'hash' => $passwordHash, 'active' => (int) $active]);
            $id = (int) $this->database->lastInsertId();
            if(!$this->platform&&$this->organizationId!==null){$membership=$this->database->prepare("INSERT INTO organization_users(organization_id,user_id,status) VALUES(?,?,'active')");$membership->execute([$this->organizationId,$id]);}
            $this->syncRelations($id, $roleIds, $unitIds);
            $this->database->commit();
            return $id;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw new RuntimeException('Não foi possível criar o usuário.', 0, $exception);
        }
    }

    /** @param list<int> $roleIds @param list<int> $unitIds */
    public function updateManaged(int $id, string $name, string $email, bool $active, array $roleIds, array $unitIds, ?string $passwordHash): void
    {
        $this->database->beginTransaction();
        try {
            $sql = 'UPDATE '.$this->usersTable().' SET name = :name, email = :email, is_active = :active';
            $params = ['id' => $id, 'name' => trim($name), 'email' => strtolower(trim($email)), 'active' => (int) $active];
            if ($passwordHash !== null) { $sql .= ', password_hash = :hash'; $params['hash'] = $passwordHash; }
            $statement = $this->database->prepare($sql . ' WHERE id = :id');
            $statement->execute($params);
            $this->syncRelations($id, $roleIds, $unitIds);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw new RuntimeException('Não foi possível atualizar o usuário.', 0, $exception);
        }
    }

    /** @param list<int> $roleIds @param list<int> $unitIds */
    private function syncRelations(int $userId, array $roleIds, array $unitIds): void
    {
        $relationTables = $this->platform
            ? ['platform_user_roles']
            : ['user_roles', 'user_unit_scopes'];

        foreach ($relationTables as $table) {
            $statement = $this->database->prepare("DELETE FROM {$table} WHERE user_id = :id");
            $statement->execute(['id' => $userId]);
        }
        $role = $this->database->prepare('INSERT INTO '.$this->userRolesTable().' (user_id, role_id) VALUES (:user, :related)');
        foreach ($roleIds as $id) $role->execute(['user' => $userId, 'related' => $id]);
        if(!$this->platform){$unit = $this->database->prepare('INSERT INTO user_unit_scopes (user_id, unit_id) VALUES (:user, :related)');foreach ($unitIds as $id) $unit->execute(['user' => $userId, 'related' => $id]);}
    }

    /** @return list<string> */
    public function permissions(int $userId): array
    {
        $statement = $this->database->prepare('SELECT DISTINCT p.code FROM '.$this->permissionsTable().' p INNER JOIN '.$this->rolePermissionsTable().' rp ON rp.permission_id=p.id INNER JOIN '.$this->userRolesTable().' ur ON ur.role_id=rp.role_id WHERE ur.user_id=:user_id ORDER BY p.code');
        $statement->execute(['user_id' => $userId]);

        return array_values(array_filter($statement->fetchAll(PDO::FETCH_COLUMN), 'is_string'));
    }

    /** @return list<string> */
    public function unitScopes(int $userId): array
    {
        if($this->platform)return[];$sql='SELECT u.code FROM units u INNER JOIN user_unit_scopes s ON s.unit_id = u.id WHERE s.user_id = :user_id AND u.is_active = 1';$parameters=['user_id'=>$userId];if($this->organizationId!==null){$sql.=' AND u.organization_id=:organization';$parameters['organization']=$this->organizationId;}$statement=$this->database->prepare($sql.' ORDER BY u.code');$statement->execute($parameters);

        return array_values(array_filter($statement->fetchAll(PDO::FETCH_COLUMN), 'is_string'));
    }

    /** @return list<string> */
    public function activeUnitCodes(): array
    {
        if($this->platform)return[];if($this->organizationId===null)$rows=$this->database->query('SELECT code FROM units WHERE is_active = 1 ORDER BY code')->fetchAll(PDO::FETCH_COLUMN);else{$statement=$this->database->prepare('SELECT code FROM units WHERE is_active=1 AND organization_id=? ORDER BY code');$statement->execute([$this->organizationId]);$rows=$statement->fetchAll(PDO::FETCH_COLUMN);}return array_values(array_filter($rows,'is_string'));
    }

    public function recordFailedLogin(int $userId, int $lockAfter = 5, int $lockMinutes = 15): void
    {
        $lockedUntil = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify(sprintf('+%d minutes', $lockMinutes))
            ->format('Y-m-d H:i:s');
        $statement = $this->database->prepare('UPDATE '.$this->usersTable().' SET failed_login_attempts = failed_login_attempts + 1, locked_until = CASE WHEN failed_login_attempts + 1 >= :lock_after THEN :locked_until ELSE locked_until END WHERE id = :id');
        $statement->execute(['lock_after' => $lockAfter, 'locked_until' => $lockedUntil, 'id' => $userId]);
    }

    public function recordSuccessfulLogin(int $userId, ?string $newHash = null): void
    {
        $sql = 'UPDATE '.$this->usersTable().' SET failed_login_attempts = 0, locked_until = NULL, last_login_at = UTC_TIMESTAMP()';
        $parameters = ['id' => $userId];

        if ($newHash !== null) {
            $sql .= ', password_hash = :password_hash';
            $parameters['password_hash'] = $newHash;
        }

        $statement = $this->database->prepare($sql . ' WHERE id = :id');
        $statement->execute($parameters);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): User
    {
        $locked = is_string($row['locked_until'] ?? null)
            ? new DateTimeImmutable($row['locked_until'] . ' UTC')
            : null;

        return new User(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['email'],
            (string) $row['password_hash'],
            (bool) $row['is_active'],
            (int) $row['failed_login_attempts'],
            $locked,
        );
    }

    public function requiresUnits(): bool
    {
        return !$this->platform;
    }

    private function usersTable(): string { return $this->platform ? 'platform_users' : 'users'; }
    private function rolesTable(): string { return $this->platform ? 'platform_roles' : 'roles'; }
    private function permissionsTable(): string { return $this->platform ? 'platform_permissions' : 'permissions'; }
    private function userRolesTable(): string { return $this->platform ? 'platform_user_roles' : 'user_roles'; }
    private function rolePermissionsTable(): string { return $this->platform ? 'platform_role_permissions' : 'role_permissions'; }
}
