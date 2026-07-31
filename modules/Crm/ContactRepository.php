<?php

declare(strict_types=1);

namespace Interferencia\Modules\Crm;

use PDO;
use RuntimeException;
use Throwable;

final readonly class ContactRepository
{
    public function __construct(private PDO $database) {}

    /** @return list<array<string, mixed>> */
    public function all(int $unitId, string $search = ''): array
    {
        $sql = "SELECT c.*, s.name status_name, s.color status_color, u.name responsible_name FROM crm_contacts c INNER JOIN crm_statuses s ON s.id=c.status_id LEFT JOIN users u ON u.id=c.responsible_user_id WHERE c.unit_id=:unit";
        $params = ['unit' => $unitId];
        if ($search !== '') { $sql .= ' AND (c.name LIKE :search OR c.phone LIKE :search OR c.email LIKE :search OR c.course LIKE :search)'; $params['search'] = '%' . $search . '%'; }
        $statement = $this->database->prepare($sql . ' ORDER BY c.registered_at DESC, c.id DESC');
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $unitId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM crm_contacts WHERE id=:id AND unit_id=:unit LIMIT 1');
        $statement->execute(['id' => $id, 'unit' => $unitId]); $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function statuses(): array { return $this->database->query('SELECT id, code, name, color FROM crm_statuses WHERE is_active=1 ORDER BY sort_order, name')->fetchAll(); }

    /** @return list<array<string, mixed>> */
    public function users(int $unitId): array
    {
        $statement = $this->database->prepare('SELECT DISTINCT u.id,u.name FROM users u INNER JOIN user_unit_scopes s ON s.user_id=u.id WHERE s.unit_id=:unit AND u.is_active=1 ORDER BY u.name');
        $statement->execute(['unit' => $unitId]); return $statement->fetchAll();
    }

    public function statusExists(int $id): bool { $s=$this->database->prepare('SELECT COUNT(*) FROM crm_statuses WHERE id=:id AND is_active=1'); $s->execute(['id'=>$id]); return (int)$s->fetchColumn()>0; }
    public function userBelongsToUnit(int $userId, int $unitId): bool { $s=$this->database->prepare('SELECT COUNT(*) FROM user_unit_scopes WHERE user_id=:user AND unit_id=:unit'); $s->execute(['user'=>$userId,'unit'=>$unitId]); return (int)$s->fetchColumn()>0; }

    /** @param array<string, mixed> $data */
    public function save(?int $id, int $unitId, int $creatorId, array $data): int
    {
        try {
            if ($id === null) {
                $sql = 'INSERT INTO crm_contacts (unit_id,status_id,responsible_user_id,name,phone,email,document,course,interest_score,origin_city,registration_source,registered_at,notes,is_active,created_by) VALUES (:unit_id,:status_id,:responsible_user_id,:name,:phone,:email,:document,:course,:interest_score,:origin_city,:registration_source,:registered_at,:notes,:is_active,:created_by)';
                $data += ['unit_id' => $unitId, 'created_by' => $creatorId];
            } else {
                $sql = 'UPDATE crm_contacts SET status_id=:status_id,responsible_user_id=:responsible_user_id,name=:name,phone=:phone,email=:email,document=:document,course=:course,interest_score=:interest_score,origin_city=:origin_city,registration_source=:registration_source,registered_at=:registered_at,notes=:notes,is_active=:is_active WHERE id=:id AND unit_id=:unit_id';
                $data += ['id' => $id, 'unit_id' => $unitId];
            }
            $statement = $this->database->prepare($sql); $statement->execute($data);
            return $id ?? (int) $this->database->lastInsertId();
        } catch (Throwable $exception) { throw new RuntimeException('Não foi possível salvar o contato.', 0, $exception); }
    }
}
