<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

use PDO;

final readonly class OrganizationPortalSettings
{
    private const TABS = ['journey', 'enrollments', 'finance', 'tickets', 'documents'];

    public function __construct(private PDO $database) {}

    /** @return array{journey:bool,enrollments:bool,finance:bool,tickets:bool,documents:bool} */
    public function get(int $organizationId): array
    {
        $s = $this->database->prepare('SELECT show_journey,show_enrollments,show_finance,show_tickets,show_documents FROM organization_portal_settings WHERE organization_id=:org LIMIT 1');
        $s->execute(['org' => $organizationId]);
        $row = $s->fetch() ?: [];
        $tabs = [];
        foreach (self::TABS as $tab) {
            $column = 'show_' . $tab;
            $tabs[$tab] = isset($row[$column]) ? (int) $row[$column] === 1 : true;
        }
        return $tabs;
    }

    /** @param array<string,bool> $tabs */
    public function save(int $organizationId, array $tabs): void
    {
        $values = [];
        foreach (self::TABS as $tab) {
            $values['show_' . $tab] = !empty($tabs[$tab]) ? 1 : 0;
        }
        $sql = 'INSERT INTO organization_portal_settings(organization_id,show_journey,show_enrollments,show_finance,show_tickets,show_documents) VALUES(:org,:show_journey,:show_enrollments,:show_finance,:show_tickets,:show_documents) ON DUPLICATE KEY UPDATE show_journey=VALUES(show_journey),show_enrollments=VALUES(show_enrollments),show_finance=VALUES(show_finance),show_tickets=VALUES(show_tickets),show_documents=VALUES(show_documents)';
        $this->database->prepare($sql)->execute(['org' => $organizationId] + $values);
    }
}
