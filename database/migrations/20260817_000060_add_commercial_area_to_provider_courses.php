<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;
use PDO;

return new class implements Migration {
    public function id(): string
    {
        return '20260817_000060_add_commercial_area_to_provider_courses';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE provider_courses ADD COLUMN commercial_area VARCHAR(255) NULL");
    }

    public function down(PDO $database): void
    {
        $database->exec("ALTER TABLE provider_courses DROP COLUMN commercial_area");
    }
};
