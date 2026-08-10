<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260810_999200_add_site_visual_identity';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE organization_sites
            ADD COLUMN site_primary_color CHAR(7) NULL AFTER publication_status,
            ADD COLUMN site_secondary_color CHAR(7) NULL AFTER site_primary_color");
    }

    public function down(PDO $database): void
    {
        $database->exec("ALTER TABLE organization_sites
            DROP COLUMN site_secondary_color,
            DROP COLUMN site_primary_color");
    }
};
