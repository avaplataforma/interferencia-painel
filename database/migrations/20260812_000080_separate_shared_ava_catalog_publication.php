<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260812_000080_separate_shared_ava_catalog_publication';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE course_catalogs
            ADD COLUMN is_shared_ava_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER is_globally_enabled,
            ADD COLUMN shared_ava_updated_by BIGINT UNSIGNED NULL AFTER is_shared_ava_enabled,
            ADD COLUMN shared_ava_updated_at DATETIME NULL AFTER shared_ava_updated_by,
            ADD CONSTRAINT course_catalogs_shared_ava_user_fk FOREIGN KEY(shared_ava_updated_by) REFERENCES platform_users(id) ON DELETE SET NULL");

        $database->exec("UPDATE course_catalogs
            SET is_shared_ava_enabled=1
            WHERE execution_environment='shared_ava' AND is_active=1");
    }

    public function down(PDO $database): void
    {
        $database->exec("ALTER TABLE course_catalogs
            DROP FOREIGN KEY course_catalogs_shared_ava_user_fk,
            DROP COLUMN shared_ava_updated_at,
            DROP COLUMN shared_ava_updated_by,
            DROP COLUMN is_shared_ava_enabled");
    }
};
