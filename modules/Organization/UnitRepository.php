<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

use PDO;
use RuntimeException;
use Throwable;

final readonly class UnitRepository
{
    public function __construct(private PDO $database, private ?int $organizationId = null)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        if ($this->organizationId === null) return $this->database->query('SELECT u.id, u.code, u.name, u.city, u.is_active, COUNT(DISTINCT s.user_id) AS user_count FROM units u LEFT JOIN user_unit_scopes s ON s.unit_id = u.id GROUP BY u.id ORDER BY u.is_active DESC, u.name')->fetchAll();
        $statement=$this->database->prepare('SELECT u.id, u.code, u.name, u.city, u.is_active, COUNT(DISTINCT s.user_id) AS user_count FROM units u LEFT JOIN user_unit_scopes s ON s.unit_id = u.id WHERE u.organization_id=? GROUP BY u.id ORDER BY u.is_active DESC, u.name');
        $statement->execute([$this->organizationId]);
        return $statement->fetchAll();
    }

    /** @param list<string> $codes @return list<array<string, mixed>> */
    public function activeByCodes(array $codes): array
    {
        if ($codes === []) return [];
        $placeholders = implode(', ', array_fill(0, count($codes), '?'));
        $organization = $this->organizationId === null ? '' : ' AND organization_id = ?';
        $statement = $this->database->prepare("SELECT id, code, name, city FROM units WHERE is_active = 1 AND code IN ({$placeholders}){$organization} ORDER BY name");
        $statement->execute($this->organizationId === null ? $codes : [...$codes,$this->organizationId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $sql='SELECT id, code, name, city, is_active FROM units WHERE id = :id';$parameters=['id'=>$id];
        if($this->organizationId!==null){$sql.=' AND organization_id=:organization';$parameters['organization']=$this->organizationId;}
        $statement = $this->database->prepare($sql.' LIMIT 1');
        $statement->execute($parameters);
        $unit = $statement->fetch();

        return is_array($unit) ? $unit : null;
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM units WHERE code = :code';
        $parameters = ['code' => $code];
        if($this->organizationId!==null){$sql.=' AND organization_id=:organization';$parameters['organization']=$this->organizationId;}
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
        if($this->organizationId!==null){$sql.=' AND organization_id=:organization';$parameters['organization']=$this->organizationId;}
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
            if($this->organizationId===null)throw new RuntimeException('Contexto da organização é obrigatório para criar unidades.');
            $statement = $this->database->prepare('INSERT INTO units (organization_id, code, name, city, is_active) VALUES (:organization, :code, :name, :city, :active)');
            $statement->execute(['organization'=>$this->organizationId,'code' => $code, 'name' => $name, 'city' => $city, 'active' => (int) $active]);
            $id = (int) $this->database->lastInsertId();
            $scope = $this->database->prepare("INSERT IGNORE INTO user_unit_scopes (user_id, unit_id) SELECT DISTINCT ur.user_id, :unit_id FROM user_roles ur INNER JOIN role_permissions rp ON rp.role_id = ur.role_id INNER JOIN permissions p ON p.id = rp.permission_id INNER JOIN organization_users membership ON membership.user_id=ur.user_id AND membership.organization_id=:organization AND membership.status='active' WHERE p.code = 'units.access_all'");
            $scope->execute(['unit_id' => $id,'organization'=>$this->organizationId]);
            $this->database->commit();

            return $id;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw new RuntimeException('Não foi possível criar a unidade.', 0, $exception);
        }
    }

    public function update(int $id, string $name, string $city, bool $active): void
    {
        $sql='UPDATE units SET name = :name, city = :city, is_active = :active WHERE id = :id';$parameters=['id'=>$id,'name'=>$name,'city'=>$city,'active'=>(int)$active];
        if($this->organizationId!==null){$sql.=' AND organization_id=:organization';$parameters['organization']=$this->organizationId;}
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);
    }
}
