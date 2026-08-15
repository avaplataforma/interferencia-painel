<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string
    {
        return '20260815_000020_create_ava_course_provisioning_jobs';
    }

    public function up(PDO $database): void
    {
        $database->exec("CREATE TABLE ava_course_provisioning_jobs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            request_key VARCHAR(190) NOT NULL,
            provider_course_id BIGINT UNSIGNED NOT NULL,
            organization_id BIGINT UNSIGNED NOT NULL,
            provider_code VARCHAR(100) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'queued',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            requested_by BIGINT UNSIGNED NULL,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            last_error TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ava_course_provisioning_request_uq(request_key),
            KEY ava_course_provisioning_status_idx(status,updated_at),
            KEY ava_course_provisioning_course_idx(provider_course_id),
            CONSTRAINT ava_course_provisioning_course_fk FOREIGN KEY(provider_course_id) REFERENCES provider_courses(id) ON DELETE CASCADE,
            CONSTRAINT ava_course_provisioning_organization_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            CONSTRAINT ava_course_provisioning_user_fk FOREIGN KEY(requested_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS ava_course_provisioning_jobs');
    }
};
