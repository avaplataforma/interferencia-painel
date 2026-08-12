<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260812_000050_create_ava_academic_backfill';
    }

    public function up(PDO $database): void
    {
        $database->exec("CREATE TABLE ava_academic_backfill_runs(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            status VARCHAR(30) NOT NULL DEFAULT 'running',
            batch_size SMALLINT UNSIGNED NOT NULL DEFAULT 10,
            discovered_count INT UNSIGNED NOT NULL DEFAULT 0,
            processed_count INT UNSIGNED NOT NULL DEFAULT 0,
            synced_count INT UNSIGNED NOT NULL DEFAULT 0,
            skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
            failed_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_by BIGINT UNSIGNED NULL,
            started_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY ava_academic_backfill_run_status_idx(status,updated_at),
            CONSTRAINT ava_academic_backfill_run_user_fk FOREIGN KEY(created_by) REFERENCES platform_users(id) ON DELETE SET NULL,
            CONSTRAINT ava_academic_backfill_run_status_check CHECK(status IN ('running','completed','completed_with_errors'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("CREATE TABLE ava_academic_backfill_items(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            run_id BIGINT UNSIGNED NOT NULL,
            source_type VARCHAR(30) NOT NULL,
            source_id BIGINT UNSIGNED NOT NULL,
            student_enrollment_id BIGINT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            message VARCHAR(500) NULL,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ava_academic_backfill_item_run_unique(run_id,source_type,source_id),
            KEY ava_academic_backfill_item_status_idx(run_id,status,id),
            KEY ava_academic_backfill_item_source_idx(source_type,source_id,status),
            CONSTRAINT ava_academic_backfill_item_run_fk FOREIGN KEY(run_id) REFERENCES ava_academic_backfill_runs(id) ON DELETE CASCADE,
            CONSTRAINT ava_academic_backfill_item_enrollment_fk FOREIGN KEY(student_enrollment_id) REFERENCES student_enrollments(id) ON DELETE SET NULL,
            CONSTRAINT ava_academic_backfill_item_source_check CHECK(source_type IN ('panel_enrollment','moodle_mirror')),
            CONSTRAINT ava_academic_backfill_item_status_check CHECK(status IN ('pending','processing','synced','skipped','failed'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS ava_academic_backfill_items');
        $database->exec('DROP TABLE IF EXISTS ava_academic_backfill_runs');
    }
};
