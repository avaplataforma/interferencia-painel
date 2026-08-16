<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string
    {
        return '20260816_000030_create_catalog_commercial_policy_history';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE organization_course_catalog_access
            ADD COLUMN price_is_overridden TINYINT(1) NOT NULL DEFAULT 0 AFTER default_price,
            ADD COLUMN installments_is_overridden TINYINT(1) NOT NULL DEFAULT 0 AFTER default_max_installments,
            ADD COLUMN dates_are_overridden TINYINT(1) NOT NULL DEFAULT 0 AFTER valid_until");
        $database->exec("UPDATE organization_course_catalog_access access
            INNER JOIN course_catalogs catalog ON catalog.id=access.course_catalog_id
            SET access.price_is_overridden=CASE WHEN access.default_price IS NOT NULL AND (catalog.central_default_price IS NULL OR ABS(access.default_price-catalog.central_default_price)>0.009) THEN 1 ELSE 0 END,
                access.installments_is_overridden=CASE WHEN access.default_max_installments IS NOT NULL AND access.default_max_installments<>catalog.central_default_max_installments THEN 1 ELSE 0 END,
                access.dates_are_overridden=CASE WHEN NOT(access.valid_from<=>catalog.central_valid_from) OR NOT(access.valid_until<=>catalog.central_valid_until) THEN 1 ELSE 0 END");
        $database->exec("CREATE TABLE catalog_commercial_policy_events(
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_catalog_id BIGINT UNSIGNED NOT NULL,
            organization_id BIGINT UNSIGNED NULL,
            action VARCHAR(30) NOT NULL,
            apply_scope VARCHAR(20) NOT NULL,
            franchises_count INT UNSIGNED NOT NULL DEFAULT 0,
            module_offers_count INT UNSIGNED NOT NULL DEFAULT 0,
            trails_count INT UNSIGNED NOT NULL DEFAULT 0,
            exceptions_count INT UNSIGNED NOT NULL DEFAULT 0,
            snapshot_json LONGTEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            KEY catalog_policy_events_catalog_idx(course_catalog_id,created_at),
            KEY catalog_policy_events_organization_idx(organization_id,created_at),
            CONSTRAINT catalog_policy_events_catalog_fk FOREIGN KEY(course_catalog_id) REFERENCES course_catalogs(id) ON DELETE CASCADE,
            CONSTRAINT catalog_policy_events_organization_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
            CONSTRAINT catalog_policy_events_user_fk FOREIGN KEY(created_by) REFERENCES platform_users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS catalog_commercial_policy_events');
        $database->exec("ALTER TABLE organization_course_catalog_access
            DROP COLUMN dates_are_overridden,
            DROP COLUMN installments_is_overridden,
            DROP COLUMN price_is_overridden");
    }
};
