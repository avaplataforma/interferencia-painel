<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260818_999970_portal_announcement_expiry_and_tabs'; }

    public function up(PDO $db): void
    {
        $db->exec("ALTER TABLE organization_announcements ADD expires_at DATE NULL AFTER body");
        $db->exec("CREATE TABLE organization_portal_settings (organization_id BIGINT UNSIGNED NOT NULL,show_journey TINYINT(1) NOT NULL DEFAULT 1,show_enrollments TINYINT(1) NOT NULL DEFAULT 1,show_finance TINYINT(1) NOT NULL DEFAULT 1,show_tickets TINYINT(1) NOT NULL DEFAULT 1,show_documents TINYINT(1) NOT NULL DEFAULT 1,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(organization_id),CONSTRAINT organization_portal_settings_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS organization_portal_settings');
        $db->exec('ALTER TABLE organization_announcements DROP COLUMN expires_at');
    }
};
