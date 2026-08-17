<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;
use PDO;

return new class implements Migration {
    public function id(): string
    {
        return '20260817_000110_add_ava_colors_to_organizations';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE organizations ADD COLUMN ava_primary_color VARCHAR(7) NULL, ADD COLUMN ava_secondary_color VARCHAR(7) NULL");
    }

    public function down(PDO $database): void
    {
        $database->exec("ALTER TABLE organizations DROP COLUMN ava_primary_color, DROP COLUMN ava_secondary_color");
    }
};
