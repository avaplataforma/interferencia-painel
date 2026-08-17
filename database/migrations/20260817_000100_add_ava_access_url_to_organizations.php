<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;
use PDO;

return new class implements Migration {
    public function id(): string
    {
        return '20260817_000100_add_ava_access_url_to_organizations';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE organizations ADD COLUMN ava_access_url VARCHAR(500) NULL");
    }

    public function down(PDO $database): void
    {
        $database->exec("ALTER TABLE organizations DROP COLUMN ava_access_url");
    }
};
