<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string
    {
        return '20260815_000040_track_ava_course_reuse';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE ava_course_provisioning_jobs
            ADD COLUMN reuse_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER attempts,
            ADD COLUMN last_reused_at DATETIME NULL AFTER completed_at");
    }

    public function down(PDO $database): void
    {
        $database->exec("ALTER TABLE ava_course_provisioning_jobs
            DROP COLUMN last_reused_at,
            DROP COLUMN reuse_count");
    }
};
