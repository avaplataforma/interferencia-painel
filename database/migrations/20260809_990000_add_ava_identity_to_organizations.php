<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string
    {
        return '20260809_990000_add_ava_identity_to_organizations';
    }

    public function up(PDO $database): void
    {
        $database->exec('ALTER TABLE organizations ADD ava_polo_name VARCHAR(255) NULL AFTER support_phone');
    }

    public function down(PDO $database): void
    {
        $database->exec('ALTER TABLE organizations DROP COLUMN ava_polo_name');
    }
};
