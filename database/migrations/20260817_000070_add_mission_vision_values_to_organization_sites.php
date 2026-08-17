<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;
use PDO;

return new class implements Migration {
    public function id(): string
    {
        return '20260817_000070_add_mission_vision_values_to_organization_sites';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE organization_sites ADD COLUMN about_mission TEXT NULL, ADD COLUMN about_vision TEXT NULL, ADD COLUMN about_values TEXT NULL");
    }

    public function down(PDO $database): void
    {
        $database->exec("ALTER TABLE organization_sites DROP COLUMN about_mission, DROP COLUMN about_vision, DROP COLUMN about_values");
    }
};
