<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string
    {
        return '20260815_000030_create_lti_selection_snapshots';
    }

    public function up(PDO $database): void
    {
        $database->exec("CREATE TABLE lti_selection_snapshots (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provisioning_job_id BIGINT UNSIGNED NULL,
            provider_course_id BIGINT UNSIGNED NOT NULL,
            provider_code VARCHAR(100) NOT NULL,
            staging_course_id BIGINT UNSIGNED NOT NULL,
            source_name VARCHAR(500) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'requested',
            selection_payload LONGTEXT NULL,
            payload_sha256 CHAR(64) NULL,
            resource_count INT UNSIGNED NOT NULL DEFAULT 0,
            final_remote_course_id BIGINT UNSIGNED NULL,
            last_error TEXT NULL,
            selected_at DATETIME NULL,
            materialized_at DATETIME NULL,
            purged_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY lti_selection_snapshot_job_idx(provisioning_job_id),
            KEY lti_selection_snapshot_course_idx(provider_course_id,created_at),
            KEY lti_selection_snapshot_status_idx(status,updated_at),
            CONSTRAINT lti_selection_snapshot_job_fk FOREIGN KEY(provisioning_job_id) REFERENCES ava_course_provisioning_jobs(id) ON DELETE SET NULL,
            CONSTRAINT lti_selection_snapshot_course_fk FOREIGN KEY(provider_course_id) REFERENCES provider_courses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS lti_selection_snapshots');
    }
};
