<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260810_999600_create_site_recovery_automation';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE crm_follow_ups
            ADD COLUMN source_type VARCHAR(40) NULL AFTER created_by,
            ADD COLUMN source_id BIGINT UNSIGNED NULL AFTER source_type,
            ADD UNIQUE KEY crm_follow_ups_source_unique(source_type,source_id)");

        $database->exec("CREATE TABLE organization_site_recoveries (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NOT NULL,
            site_order_id BIGINT UNSIGNED NOT NULL,
            unit_id BIGINT UNSIGNED NOT NULL,
            crm_contact_id BIGINT UNSIGNED NULL,
            responsible_user_id BIGINT UNSIGNED NULL,
            follow_up_id BIGINT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            alert_stage TINYINT UNSIGNED NOT NULL DEFAULT 0,
            first_alert_at DATETIME NULL,
            last_alert_at DATETIME NULL,
            next_alert_at DATETIME NULL,
            recovered_at DATETIME NULL,
            recovered_amount DECIMAL(12,2) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            UNIQUE KEY organization_site_recoveries_order_unique(site_order_id),
            KEY organization_site_recoveries_alert_index(organization_id,status,next_alert_at),
            KEY organization_site_recoveries_user_index(responsible_user_id,status,alert_stage),
            CONSTRAINT organization_site_recoveries_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            CONSTRAINT organization_site_recoveries_order_fk FOREIGN KEY(site_order_id) REFERENCES organization_site_orders(id) ON DELETE CASCADE,
            CONSTRAINT organization_site_recoveries_unit_fk FOREIGN KEY(unit_id) REFERENCES units(id),
            CONSTRAINT organization_site_recoveries_contact_fk FOREIGN KEY(crm_contact_id) REFERENCES crm_contacts(id) ON DELETE SET NULL,
            CONSTRAINT organization_site_recoveries_user_fk FOREIGN KEY(responsible_user_id) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT organization_site_recoveries_follow_up_fk FOREIGN KEY(follow_up_id) REFERENCES crm_follow_ups(id) ON DELETE SET NULL,
            CONSTRAINT organization_site_recoveries_status_check CHECK(status IN('pending','recovered','cancelled')),
            CONSTRAINT organization_site_recoveries_stage_check CHECK(alert_stage BETWEEN 0 AND 3)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS organization_site_recoveries');
        $database->exec('ALTER TABLE crm_follow_ups DROP KEY crm_follow_ups_source_unique,DROP COLUMN source_id,DROP COLUMN source_type');
    }
};
