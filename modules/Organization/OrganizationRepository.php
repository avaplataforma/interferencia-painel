<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

use PDO;

final readonly class OrganizationRepository
{
    public function __construct(private PDO $database)
    {
    }

    public function findActiveByHost(string $host): ?Organization
    {
        $normalized = self::normalizeHost($host);
        if ($normalized === null) return null;

        $statement = $this->database->prepare("SELECT o.* FROM organizations o INNER JOIN organization_domains d ON d.organization_id=o.id WHERE d.host=:host AND d.status='active' AND o.status='active' LIMIT 1");
        $statement->execute(['host' => $normalized]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function userBelongsTo(int $userId, int $organizationId): bool
    {
        $statement = $this->database->prepare("SELECT 1 FROM organization_users WHERE user_id=? AND organization_id=? AND status='active' LIMIT 1");
        $statement->execute([$userId, $organizationId]);
        return $statement->fetchColumn() !== false;
    }

    /** @return list<Organization> */
    public function forUser(int $userId): array
    {
        $statement = $this->database->prepare("SELECT o.* FROM organizations o INNER JOIN organization_users membership ON membership.organization_id=o.id WHERE membership.user_id=? AND membership.status='active' AND o.status='active' ORDER BY o.display_name");
        $statement->execute([$userId]);
        return array_map(fn (array $row): Organization => $this->hydrate($row), $statement->fetchAll());
    }

    public static function normalizeHost(string $host): ?string
    {
        $value = strtolower(rtrim(trim($host), '.'));
        if (str_starts_with($value, '[')) {
            $end = strpos($value, ']');
            $value = $end === false ? '' : substr($value, 1, $end - 1);
        } elseif (substr_count($value, ':') === 1) {
            $value = explode(':', $value, 2)[0];
        }

        if ($value === '' || strlen($value) > 253 || filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) return null;
        return $value;
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array $row): Organization
    {
        return new Organization(
            (int) $row['id'],
            (string) $row['public_id'],
            (string) $row['code'],
            (string) $row['legal_name'],
            (string) $row['display_name'],
            (string) $row['timezone'],
            (string) $row['locale'],
            (string) $row['primary_color'],
            $row['secondary_color'] === null ? null : (string) $row['secondary_color'],
            $row['logo_path'] === null ? null : (string) $row['logo_path'],
            $row['favicon_path'] === null ? null : (string) $row['favicon_path'],
        );
    }
}
