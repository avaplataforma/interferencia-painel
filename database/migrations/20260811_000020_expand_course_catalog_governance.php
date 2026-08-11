<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260811_000020_expand_course_catalog_governance';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE provider_courses
            ADD COLUMN slug VARCHAR(500) NULL AFTER name,
            ADD COLUMN short_description TEXT NULL AFTER slug,
            ADD COLUMN certificate VARCHAR(190) NULL AFTER workload,
            ADD COLUMN access_type VARCHAR(60) NULL AFTER certificate,
            ADD COLUMN supplier_updated_at DATETIME NULL AFTER remote_status,
            ADD COLUMN content_hash CHAR(64) NULL AFTER raw_payload,
            ADD COLUMN sync_state VARCHAR(30) NOT NULL DEFAULT 'new' AFTER content_hash,
            ADD COLUMN last_changed_at DATETIME NULL AFTER sync_state,
            ADD COLUMN commercial_cover_url VARCHAR(1000) NULL AFTER commercial_description,
            ADD COLUMN commercial_category VARCHAR(255) NULL AFTER commercial_cover_url,
            ADD COLUMN commercial_workload VARCHAR(100) NULL AFTER commercial_category,
            ADD COLUMN commercial_certificate VARCHAR(190) NULL AFTER commercial_workload,
            ADD COLUMN release_status VARCHAR(30) NOT NULL DEFAULT 'private' AFTER review_status,
            ADD COLUMN equivalent_course_id BIGINT UNSIGNED NULL AFTER release_status,
            ADD KEY provider_courses_release_index(review_status,release_status,is_available),
            ADD KEY provider_courses_sync_index(provider_id,sync_state),
            ADD CONSTRAINT provider_courses_equivalent_fk FOREIGN KEY(equivalent_course_id) REFERENCES provider_courses(id) ON DELETE SET NULL");

        $database->exec("UPDATE provider_courses SET
            release_status=CASE WHEN review_status='approved' THEN 'released' ELSE 'private' END,
            sync_state='unchanged',
            last_changed_at=COALESCE(updated_at,created_at)");
        $database->exec("UPDATE provider_courses SET review_status='imported' WHERE review_status='pending'");
        $database->exec("ALTER TABLE provider_courses MODIFY COLUMN review_status VARCHAR(30) NOT NULL DEFAULT 'imported'");

        $database->exec("ALTER TABLE course_provider_integrations
            ADD COLUMN consecutive_failures INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_error,
            ADD COLUMN last_created_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER consecutive_failures,
            ADD COLUMN last_updated_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_created_count,
            ADD COLUMN last_unavailable_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_updated_count,
            ADD COLUMN next_retry_at DATETIME NULL AFTER last_unavailable_count");

        $database->exec("ALTER TABLE organization_course_catalog_access
            ADD COLUMN markup_percent DECIMAL(8,4) NOT NULL DEFAULT 0 AFTER is_enabled,
            ADD COLUMN default_max_installments TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER markup_percent,
            ADD COLUMN valid_from DATE NULL AFTER default_max_installments,
            ADD COLUMN valid_until DATE NULL AFTER valid_from");

        $database->exec("CREATE TABLE course_provider_capabilities(
            provider_id BIGINT UNSIGNED PRIMARY KEY,
            catalog_sync TINYINT(1) NOT NULL DEFAULT 0,
            automatic_enrollment TINYINT(1) NOT NULL DEFAULT 0,
            single_sign_on TINYINT(1) NOT NULL DEFAULT 0,
            progress_tracking TINYINT(1) NOT NULL DEFAULT 0,
            grade_tracking TINYINT(1) NOT NULL DEFAULT 0,
            certificate_access TINYINT(1) NOT NULL DEFAULT 0,
            suspend_access TINYINT(1) NOT NULL DEFAULT 0,
            send_access TINYINT(1) NOT NULL DEFAULT 0,
            updated_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT course_provider_capabilities_provider_fk FOREIGN KEY(provider_id) REFERENCES course_provider_integrations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("INSERT INTO course_provider_capabilities(provider_id,catalog_sync,automatic_enrollment,single_sign_on,progress_tracking,grade_tracking,certificate_access,suspend_access,send_access)
            SELECT id,
                IF(provider_code IN ('escola_avancada','iesde'),1,0),
                IF(provider_code='escola_avancada',1,0),
                IF(provider_code='iesde',1,0),
                IF(provider_code IN ('escola_avancada','iesde'),1,0),
                IF(provider_code='iesde',1,0),
                IF(provider_code IN ('escola_avancada','iesde'),1,0),
                0,
                IF(provider_code='escola_avancada',1,0)
            FROM course_provider_integrations");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS course_provider_capabilities');
        $database->exec("ALTER TABLE provider_courses MODIFY COLUMN review_status VARCHAR(30) NOT NULL DEFAULT 'pending'");
        $database->exec("ALTER TABLE organization_course_catalog_access
            DROP COLUMN valid_until,
            DROP COLUMN valid_from,
            DROP COLUMN default_max_installments,
            DROP COLUMN markup_percent");
        $database->exec("ALTER TABLE course_provider_integrations
            DROP COLUMN next_retry_at,
            DROP COLUMN last_unavailable_count,
            DROP COLUMN last_updated_count,
            DROP COLUMN last_created_count,
            DROP COLUMN consecutive_failures");
        $database->exec("ALTER TABLE provider_courses
            DROP FOREIGN KEY provider_courses_equivalent_fk,
            DROP KEY provider_courses_sync_index,
            DROP KEY provider_courses_release_index,
            DROP COLUMN equivalent_course_id,
            DROP COLUMN release_status,
            DROP COLUMN commercial_certificate,
            DROP COLUMN commercial_workload,
            DROP COLUMN commercial_category,
            DROP COLUMN commercial_cover_url,
            DROP COLUMN last_changed_at,
            DROP COLUMN sync_state,
            DROP COLUMN content_hash,
            DROP COLUMN supplier_updated_at,
            DROP COLUMN access_type,
            DROP COLUMN certificate,
            DROP COLUMN short_description,
            DROP COLUMN slug");
    }
};
