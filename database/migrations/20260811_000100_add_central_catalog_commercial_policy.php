<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260811_000100_add_central_catalog_commercial_policy';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE course_catalogs
            ADD COLUMN central_default_price DECIMAL(12,2) NULL AFTER is_globally_enabled,
            ADD COLUMN central_markup_percent DECIMAL(8,4) NOT NULL DEFAULT 0 AFTER central_default_price,
            ADD COLUMN central_default_max_installments SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER central_markup_percent,
            ADD COLUMN central_valid_from DATE NULL AFTER central_default_max_installments,
            ADD COLUMN central_valid_until DATE NULL AFTER central_valid_from,
            ADD COLUMN allow_franchise_commercial_override TINYINT(1) NOT NULL DEFAULT 1 AFTER central_valid_until,
            ADD COLUMN commercial_policy_updated_by BIGINT UNSIGNED NULL AFTER allow_franchise_commercial_override,
            ADD COLUMN commercial_policy_updated_at DATETIME NULL AFTER commercial_policy_updated_by,
            ADD CONSTRAINT course_catalogs_commercial_policy_user_fk FOREIGN KEY(commercial_policy_updated_by) REFERENCES platform_users(id) ON DELETE SET NULL");
    }

    public function down(PDO $database): void
    {
        $database->exec("ALTER TABLE course_catalogs
            DROP FOREIGN KEY course_catalogs_commercial_policy_user_fk,
            DROP COLUMN commercial_policy_updated_at,
            DROP COLUMN commercial_policy_updated_by,
            DROP COLUMN allow_franchise_commercial_override,
            DROP COLUMN central_valid_until,
            DROP COLUMN central_valid_from,
            DROP COLUMN central_default_max_installments,
            DROP COLUMN central_markup_percent,
            DROP COLUMN central_default_price");
    }
};
