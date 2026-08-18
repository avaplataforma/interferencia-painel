<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260818_999998_ava_navbar_logo'; }

    public function up(PDO $db): void
    {
        $db->exec("ALTER TABLE organizations ADD ava_navbar_logo_path VARCHAR(255) NULL AFTER favicon_path");
    }

    public function down(PDO $db): void
    {
        $db->exec('ALTER TABLE organizations DROP COLUMN ava_navbar_logo_path');
    }
};
