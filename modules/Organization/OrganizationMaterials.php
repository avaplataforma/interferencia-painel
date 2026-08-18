<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

use PDO;
use RuntimeException;

final readonly class OrganizationMaterials
{
    public function __construct(private PDO $database) {}

    /** @return list<array<string,mixed>> */
    public function list(int $organizationId): array
    {
        $s = $this->database->prepare('SELECT id,title,file_name,mime_type,file_size,storage_path,is_active,created_at FROM organization_materials WHERE organization_id=:org ORDER BY created_at DESC,id DESC LIMIT 200');
        $s->execute(['org' => $organizationId]);
        return $s->fetchAll() ?: [];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id, int $organizationId): ?array
    {
        $s = $this->database->prepare('SELECT * FROM organization_materials WHERE id=:id AND organization_id=:org LIMIT 1');
        $s->execute(['id' => $id, 'org' => $organizationId]);
        $row = $s->fetch();
        return is_array($row) ? $row : null;
    }

    /** @param array{file_name:string,mime_type:string,file_size:int,storage_path:string} $file */
    public function create(int $organizationId, string $title, array $file, ?int $userId): int
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 190) throw new RuntimeException('Informe um título entre 1 e 190 caracteres.');
        $s = $this->database->prepare('INSERT INTO organization_materials(organization_id,title,file_name,mime_type,file_size,storage_path,is_active,created_by) VALUES(:org,:title,:name,:mime,:size,:path,1,:creator)');
        $s->execute(['org' => $organizationId, 'title' => $title, 'name' => mb_substr((string) $file['file_name'], 0, 255), 'mime' => (string) $file['mime_type'], 'size' => (int) $file['file_size'], 'path' => (string) $file['storage_path'], 'creator' => $userId]);
        return (int) $this->database->lastInsertId();
    }

    public function toggle(int $id, int $organizationId, bool $active): void
    {
        $s = $this->database->prepare('UPDATE organization_materials SET is_active=:active WHERE id=:id AND organization_id=:org');
        $s->execute(['active' => (int) $active, 'id' => $id, 'org' => $organizationId]);
    }

    public function delete(int $id, int $organizationId): void
    {
        $s = $this->database->prepare('DELETE FROM organization_materials WHERE id=:id AND organization_id=:org');
        $s->execute(['id' => $id, 'org' => $organizationId]);
    }
}
