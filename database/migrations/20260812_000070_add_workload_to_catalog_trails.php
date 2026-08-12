<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260812_000070_add_workload_to_catalog_trails';
    }

    public function up(PDO $database): void
    {
        $database->exec('ALTER TABLE catalog_trails ADD workload_hours DECIMAL(8,2) NULL AFTER description');
    }

    public function down(PDO $database): void
    {
        $database->exec('ALTER TABLE catalog_trails DROP COLUMN workload_hours');
    }
};
