<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

use PDO;
use RuntimeException;

final readonly class OrganizationAnnouncements
{
    public function __construct(private PDO $database) {}

    /** @return list<array<string,mixed>> */
    public function list(int $organizationId): array
    {
        $s = $this->database->prepare('SELECT id,title,body,is_active,created_by,created_at,updated_at FROM organization_announcements WHERE organization_id=:org ORDER BY created_at DESC,id DESC LIMIT 200');
        $s->execute(['org' => $organizationId]);
        return $s->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function active(int $organizationId, int $limit = 5): array
    {
        $s = $this->database->prepare('SELECT id,title,body,created_at FROM organization_announcements WHERE organization_id=:org AND is_active=1 ORDER BY created_at DESC,id DESC LIMIT ' . max(1, min(20, $limit)));
        $s->execute(['org' => $organizationId]);
        return $s->fetchAll() ?: [];
    }

    public function create(int $organizationId, string $title, string $body, ?int $userId): int
    {
        $title = trim($title);
        $body = trim($body);
        if ($title === '' || mb_strlen($title) > 190) throw new RuntimeException('Informe um título entre 1 e 190 caracteres.');
        if ($body === '' || mb_strlen($body) > 10000) throw new RuntimeException('Informe o texto do comunicado em até 10.000 caracteres.');
        $s = $this->database->prepare('INSERT INTO organization_announcements(organization_id,title,body,is_active,created_by) VALUES(:org,:title,:body,1,:creator)');
        $s->execute(['org' => $organizationId, 'title' => $title, 'body' => $body, 'creator' => $userId]);
        return (int) $this->database->lastInsertId();
    }

    public function toggle(int $id, int $organizationId, bool $active): void
    {
        $s = $this->database->prepare('UPDATE organization_announcements SET is_active=:active WHERE id=:id AND organization_id=:org');
        $s->execute(['active' => (int) $active, 'id' => $id, 'org' => $organizationId]);
    }

    public function delete(int $id, int $organizationId): void
    {
        $s = $this->database->prepare('DELETE FROM organization_announcements WHERE id=:id AND organization_id=:org');
        $s->execute(['id' => $id, 'org' => $organizationId]);
    }
}
