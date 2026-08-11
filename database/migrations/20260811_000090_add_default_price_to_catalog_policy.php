<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260811_000090_add_default_price_to_catalog_policy';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE organization_course_catalog_access
            ADD COLUMN default_price DECIMAL(12,2) NULL AFTER markup_percent");
    }

    public function down(PDO $database): void
    {
        $database->exec('ALTER TABLE organization_course_catalog_access DROP COLUMN default_price');
    }
};
