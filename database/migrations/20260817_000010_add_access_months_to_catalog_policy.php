<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string
    {
        return '20260817_000010_add_access_months_to_catalog_policy';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE course_catalogs
            ADD COLUMN central_default_module_access_months INT UNSIGNED NULL AFTER central_default_module_workload,
            ADD COLUMN central_default_trail_access_months INT UNSIGNED NULL AFTER central_default_trail_workload");
        $database->exec("UPDATE course_catalogs
            SET central_default_module_access_months=3,
                central_default_trail_access_months=12
            WHERE code='catalogo-master'");
    }

    public function down(PDO $database): void
    {
        $database->exec("ALTER TABLE course_catalogs
            DROP COLUMN central_default_trail_access_months,
            DROP COLUMN central_default_module_access_months");
    }
};
