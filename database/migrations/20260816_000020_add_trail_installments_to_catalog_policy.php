<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string
    {
        return '20260816_000020_add_trail_installments_to_catalog_policy';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE course_catalogs
            ADD COLUMN central_trail_default_max_installments SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER central_default_max_installments");
        $database->exec("UPDATE course_catalogs
            SET central_trail_default_max_installments=central_default_max_installments");
    }

    public function down(PDO $database): void
    {
        $database->exec("ALTER TABLE course_catalogs
            DROP COLUMN central_trail_default_max_installments");
    }
};
