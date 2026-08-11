<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260811_000070_create_catalog_access_overrides';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE course_catalogs ADD COLUMN is_globally_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active");
        $database->exec("ALTER TABLE provider_courses ADD COLUMN is_globally_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER is_available");
        $database->exec("ALTER TABLE provider_catalog_contents ADD COLUMN is_globally_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER is_available");

        $database->exec("CREATE TABLE organization_catalog_item_access(
            organization_id BIGINT UNSIGNED NOT NULL,
            item_type VARCHAR(20) NOT NULL,
            item_id BIGINT UNSIGNED NOT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            updated_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(organization_id,item_type,item_id),
            KEY organization_catalog_item_enabled_index(organization_id,item_type,is_enabled),
            CONSTRAINT organization_catalog_item_access_organization_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS organization_catalog_item_access');
        $database->exec('ALTER TABLE provider_catalog_contents DROP COLUMN is_globally_enabled');
        $database->exec('ALTER TABLE provider_courses DROP COLUMN is_globally_enabled');
        $database->exec('ALTER TABLE course_catalogs DROP COLUMN is_globally_enabled');
    }
};
