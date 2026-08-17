<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;
use PDO;

return new class implements Migration {
    public function id(): string
    {
        return '20260817_000040_add_ga4_to_organization_sites';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE organization_sites ADD COLUMN analytics_ga4_id VARCHAR(64) NULL");
    }

    public function down(PDO $database): void
    {
        $database->exec("ALTER TABLE organization_sites DROP COLUMN analytics_ga4_id");
    }
};
