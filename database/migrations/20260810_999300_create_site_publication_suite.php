<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260810_999300_create_site_publication_suite';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE organization_sites
            ADD COLUMN live_snapshot_json LONGTEXT NULL AFTER site_secondary_color,
            ADD COLUMN live_version INT UNSIGNED NULL AFTER live_snapshot_json,
            ADD COLUMN scheduled_snapshot_json LONGTEXT NULL AFTER live_version,
            ADD COLUMN scheduled_publish_at DATETIME NULL AFTER scheduled_snapshot_json,
            ADD COLUMN privacy_policy LONGTEXT NULL AFTER seo_description,
            ADD COLUMN cookie_notice TEXT NULL AFTER privacy_policy,
            ADD COLUMN terms_text LONGTEXT NULL AFTER cookie_notice,
            ADD COLUMN cookie_banner_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER terms_text");

        $database->exec("ALTER TABLE organization_site_pages
            ADD COLUMN seo_title VARCHAR(190) NULL AFTER summary,
            ADD COLUMN seo_description VARCHAR(320) NULL AFTER seo_title");

        $database->exec("ALTER TABLE crm_contacts
            ADD COLUMN landing_page VARCHAR(500) NULL AFTER privacy_notice_version,
            ADD COLUMN utm_source VARCHAR(190) NULL AFTER landing_page,
            ADD COLUMN utm_medium VARCHAR(190) NULL AFTER utm_source,
            ADD COLUMN utm_campaign VARCHAR(190) NULL AFTER utm_medium,
            ADD COLUMN utm_content VARCHAR(190) NULL AFTER utm_campaign,
            ADD COLUMN utm_term VARCHAR(190) NULL AFTER utm_content");

        $database->exec("CREATE TABLE organization_site_versions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NOT NULL,
            version_number INT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            label VARCHAR(190) NULL,
            snapshot_json LONGTEXT NOT NULL,
            scheduled_at DATETIME NULL,
            published_at DATETIME NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            UNIQUE KEY organization_site_versions_number_unique(organization_id,version_number),
            KEY organization_site_versions_status_index(organization_id,status,created_at),
            CONSTRAINT organization_site_versions_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            CONSTRAINT organization_site_versions_user_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT organization_site_versions_status_check CHECK(status IN ('draft','scheduled','published','archived'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("CREATE TABLE organization_site_media (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(190) NOT NULL,
            alt_text VARCHAR(255) NULL,
            storage_path VARCHAR(1000) NOT NULL,
            public_path VARCHAR(1000) NULL,
            mime_type VARCHAR(100) NOT NULL,
            width INT UNSIGNED NULL,
            height INT UNSIGNED NULL,
            file_size BIGINT UNSIGNED NOT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            KEY organization_site_media_org_index(organization_id,created_at),
            CONSTRAINT organization_site_media_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            CONSTRAINT organization_site_media_user_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("CREATE TABLE organization_site_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NOT NULL,
            session_hash CHAR(64) NULL,
            event_type VARCHAR(40) NOT NULL,
            page_path VARCHAR(500) NULL,
            entity_type VARCHAR(40) NULL,
            entity_id BIGINT UNSIGNED NULL,
            utm_source VARCHAR(190) NULL,
            utm_medium VARCHAR(190) NULL,
            utm_campaign VARCHAR(190) NULL,
            metadata_json TEXT NULL,
            occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            KEY organization_site_events_summary_index(organization_id,event_type,occurred_at),
            CONSTRAINT organization_site_events_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("CREATE TABLE organization_site_domain_checks (
            organization_id BIGINT UNSIGNED NOT NULL,
            host VARCHAR(255) NULL,
            dns_ok TINYINT(1) NOT NULL DEFAULT 0,
            ssl_ok TINYINT(1) NOT NULL DEFAULT 0,
            resolved_ip VARCHAR(64) NULL,
            error_message VARCHAR(1000) NULL,
            checked_at DATETIME NULL,
            PRIMARY KEY(organization_id),
            CONSTRAINT organization_site_domain_checks_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("CREATE TABLE organization_site_product_seo (
            organization_id BIGINT UNSIGNED NOT NULL,
            finance_product_id BIGINT UNSIGNED NOT NULL,
            seo_title VARCHAR(190) NULL,
            seo_description VARCHAR(320) NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(organization_id,finance_product_id),
            CONSTRAINT organization_site_product_seo_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            CONSTRAINT organization_site_product_seo_product_fk FOREIGN KEY(finance_product_id) REFERENCES finance_products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS organization_site_product_seo');
        $database->exec('DROP TABLE IF EXISTS organization_site_domain_checks');
        $database->exec('DROP TABLE IF EXISTS organization_site_events');
        $database->exec('DROP TABLE IF EXISTS organization_site_media');
        $database->exec('DROP TABLE IF EXISTS organization_site_versions');
        $database->exec('ALTER TABLE crm_contacts DROP COLUMN utm_term,DROP COLUMN utm_content,DROP COLUMN utm_campaign,DROP COLUMN utm_medium,DROP COLUMN utm_source,DROP COLUMN landing_page');
        $database->exec('ALTER TABLE organization_site_pages DROP COLUMN seo_description,DROP COLUMN seo_title');
        $database->exec('ALTER TABLE organization_sites DROP COLUMN cookie_banner_enabled,DROP COLUMN terms_text,DROP COLUMN cookie_notice,DROP COLUMN privacy_policy,DROP COLUMN scheduled_publish_at,DROP COLUMN scheduled_snapshot_json,DROP COLUMN live_version,DROP COLUMN live_snapshot_json');
    }
};
