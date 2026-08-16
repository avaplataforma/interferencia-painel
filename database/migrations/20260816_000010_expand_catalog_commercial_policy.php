<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string
    {
        return '20260816_000010_expand_catalog_commercial_policy';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE course_catalogs
            ADD COLUMN central_trail_default_price DECIMAL(12,2) NULL AFTER central_default_price,
            ADD COLUMN central_default_module_workload DECIMAL(8,2) NULL AFTER central_trail_default_price,
            ADD COLUMN central_default_trail_workload DECIMAL(8,2) NULL AFTER central_default_module_workload,
            ADD COLUMN allow_franchise_price_override TINYINT(1) NOT NULL DEFAULT 1 AFTER allow_franchise_commercial_override,
            ADD COLUMN allow_franchise_installment_override TINYINT(1) NOT NULL DEFAULT 1 AFTER allow_franchise_price_override,
            ADD COLUMN allow_franchise_visibility_override TINYINT(1) NOT NULL DEFAULT 1 AFTER allow_franchise_installment_override");
    }

    public function down(PDO $database): void
    {
        $database->exec("ALTER TABLE course_catalogs
            DROP COLUMN allow_franchise_visibility_override,
            DROP COLUMN allow_franchise_installment_override,
            DROP COLUMN allow_franchise_price_override,
            DROP COLUMN central_default_trail_workload,
            DROP COLUMN central_default_module_workload,
            DROP COLUMN central_trail_default_price");
    }
};
