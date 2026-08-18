<?php

declare(strict_types=1);

namespace Interferencia\Modules\Storage;

use PDO;
use RuntimeException;

final readonly class DocumentTypeRepository
{
    public function __construct(private PDO $db) {}

    /** @return list<array<string,mixed>> */
    public function all(bool $activeOnly = false, ?int $organizationId = null): array
    {
        $sql = "SELECT * FROM document_types WHERE scope='franchise'";
        $params = [];
        if ($organizationId !== null) {
            $sql .= ' AND organization_id=:org';
            $params['org'] = $organizationId;
        }
        if ($activeOnly) $sql .= ' AND is_active=1';
        $sql .= ' ORDER BY sort_order,name,id';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id, ?int $organizationId = null): ?array
    {
        $sql = "SELECT * FROM document_types WHERE id=:id AND scope='franchise'";
        $params = ['id' => $id];
        if ($organizationId !== null) {
            $sql .= ' AND organization_id=:org';
            $params['org'] = $organizationId;
        }
        $sql .= ' LIMIT 1';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,string> */
    public function categories(?int $organizationId = null): array
    {
        $categories = [];
        foreach ($this->all(true, $organizationId) as $type) $categories[(string) $type['code']] = (string) $type['name'];
        return $categories;
    }

    public function save(?int $id, string $name, bool $required, bool $active, int $sortOrder, int $organizationId): int
    {
        $name = trim($name);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) throw new RuntimeException('Informe um nome entre 2 e 120 caracteres.');
        $sortOrder = max(0, min(9999, $sortOrder));
        if ($id !== null) {
            if ($this->find($id, $organizationId) === null) throw new RuntimeException('Tipo de documento não encontrado.');
            $statement = $this->db->prepare("UPDATE document_types SET name=:name,is_required=:required,is_active=:active,sort_order=:sort_order WHERE id=:id AND scope='franchise' AND organization_id=:org");
            $statement->execute(['name' => $name, 'required' => (int) $required, 'active' => (int) $active, 'sort_order' => $sortOrder, 'id' => $id, 'org' => $organizationId]);
            return $id;
        }
        $code = $this->uniqueCode($name, $organizationId);
        $statement = $this->db->prepare("INSERT INTO document_types(scope,organization_id,code,name,is_required,is_active,sort_order) VALUES('franchise',:org,:code,:name,:required,:active,:sort_order)");
        $statement->execute(['org' => $organizationId, 'code' => $code, 'name' => $name, 'required' => (int) $required, 'active' => (int) $active, 'sort_order' => $sortOrder]);
        return (int) $this->db->lastInsertId();
    }

    private function uniqueCode(string $name, int $organizationId): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $base = strtolower(is_string($ascii) ? $ascii : $name);
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '_', $base), '_');
        $base = mb_substr($base !== '' ? $base : 'documento', 0, 48);
        $code = $base;
        $suffix = 2;
        $statement = $this->db->prepare("SELECT COUNT(*) FROM document_types WHERE scope='franchise' AND organization_id=:org AND code=:code");
        while (true) {
            $statement->execute(['org' => $organizationId, 'code' => $code]);
            if ((int) $statement->fetchColumn() === 0) return $code;
            $code = mb_substr($base, 0, 44) . '_' . $suffix;
            $suffix++;
        }
    }
}
