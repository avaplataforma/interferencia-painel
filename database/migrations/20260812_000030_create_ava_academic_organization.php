<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260812_000030_create_ava_academic_organization';
    }

    public function up(PDO $database): void
    {
        $database->exec("CREATE TABLE ava_academic_cohorts(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ava_connection_id BIGINT UNSIGNED NOT NULL,
            organization_id BIGINT UNSIGNED NOT NULL,
            catalog_trail_id BIGINT UNSIGNED NULL,
            moodle_course_id BIGINT UNSIGNED NULL,
            scope_type VARCHAR(20) NOT NULL,
            scope_reference VARCHAR(190) NOT NULL,
            code VARCHAR(190) NOT NULL,
            name VARCHAR(255) NOT NULL,
            remote_cohort_id BIGINT NULL,
            sync_status VARCHAR(20) NOT NULL DEFAULT 'pending',
            last_synced_at DATETIME NULL,
            last_error VARCHAR(500) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ava_academic_cohort_scope_unique(ava_connection_id,organization_id,scope_type,scope_reference),
            UNIQUE KEY ava_academic_cohort_code_unique(ava_connection_id,code),
            KEY ava_academic_cohort_status_idx(sync_status,updated_at),
            CONSTRAINT ava_academic_cohort_connection_fk FOREIGN KEY(ava_connection_id) REFERENCES ava_connections(id) ON DELETE CASCADE,
            CONSTRAINT ava_academic_cohort_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            CONSTRAINT ava_academic_cohort_trail_fk FOREIGN KEY(catalog_trail_id) REFERENCES catalog_trails(id) ON DELETE SET NULL,
            CONSTRAINT ava_academic_cohort_course_fk FOREIGN KEY(moodle_course_id) REFERENCES moodle_courses(id) ON DELETE SET NULL,
            CONSTRAINT ava_academic_cohort_scope_check CHECK(scope_type IN ('course','trail')),
            CONSTRAINT ava_academic_cohort_status_check CHECK(sync_status IN ('pending','synced','failed'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("CREATE TABLE ava_academic_groups(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ava_academic_cohort_id BIGINT UNSIGNED NOT NULL,
            ava_connection_id BIGINT UNSIGNED NOT NULL,
            organization_id BIGINT UNSIGNED NOT NULL,
            organization_pole_id BIGINT UNSIGNED NOT NULL,
            moodle_course_id BIGINT UNSIGNED NULL,
            remote_course_id BIGINT NOT NULL,
            period_code VARCHAR(30) NOT NULL,
            code VARCHAR(190) NOT NULL,
            name VARCHAR(255) NOT NULL,
            remote_group_id BIGINT NULL,
            sync_status VARCHAR(20) NOT NULL DEFAULT 'pending',
            last_synced_at DATETIME NULL,
            last_error VARCHAR(500) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ava_academic_group_scope_unique(ava_connection_id,organization_id,organization_pole_id,remote_course_id,period_code),
            UNIQUE KEY ava_academic_group_code_unique(ava_connection_id,remote_course_id,code),
            KEY ava_academic_group_status_idx(sync_status,updated_at),
            CONSTRAINT ava_academic_group_cohort_fk FOREIGN KEY(ava_academic_cohort_id) REFERENCES ava_academic_cohorts(id) ON DELETE CASCADE,
            CONSTRAINT ava_academic_group_connection_fk FOREIGN KEY(ava_connection_id) REFERENCES ava_connections(id) ON DELETE CASCADE,
            CONSTRAINT ava_academic_group_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            CONSTRAINT ava_academic_group_pole_fk FOREIGN KEY(organization_pole_id) REFERENCES organization_poles(id) ON DELETE RESTRICT,
            CONSTRAINT ava_academic_group_course_fk FOREIGN KEY(moodle_course_id) REFERENCES moodle_courses(id) ON DELETE SET NULL,
            CONSTRAINT ava_academic_group_status_check CHECK(sync_status IN ('pending','synced','failed'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("ALTER TABLE student_enrollments
            ADD catalog_trail_id BIGINT UNSIGNED NULL AFTER moodle_course_id,
            ADD ava_academic_cohort_id BIGINT UNSIGNED NULL AFTER ava_course_id,
            ADD ava_academic_group_id BIGINT UNSIGNED NULL AFTER ava_academic_cohort_id,
            ADD academic_period_code VARCHAR(30) NULL AFTER ava_academic_group_id,
            ADD KEY student_enrollments_catalog_trail_idx(catalog_trail_id),
            ADD KEY student_enrollments_academic_cohort_idx(ava_academic_cohort_id),
            ADD KEY student_enrollments_academic_group_idx(ava_academic_group_id),
            ADD CONSTRAINT student_enrollments_catalog_trail_fk FOREIGN KEY(catalog_trail_id) REFERENCES catalog_trails(id) ON DELETE SET NULL,
            ADD CONSTRAINT student_enrollments_academic_cohort_fk FOREIGN KEY(ava_academic_cohort_id) REFERENCES ava_academic_cohorts(id) ON DELETE SET NULL,
            ADD CONSTRAINT student_enrollments_academic_group_fk FOREIGN KEY(ava_academic_group_id) REFERENCES ava_academic_groups(id) ON DELETE SET NULL");
    }

    public function down(PDO $database): void
    {
        $database->exec('ALTER TABLE student_enrollments DROP FOREIGN KEY student_enrollments_academic_group_fk, DROP FOREIGN KEY student_enrollments_academic_cohort_fk, DROP FOREIGN KEY student_enrollments_catalog_trail_fk, DROP INDEX student_enrollments_academic_group_idx, DROP INDEX student_enrollments_academic_cohort_idx, DROP INDEX student_enrollments_catalog_trail_idx, DROP COLUMN academic_period_code, DROP COLUMN ava_academic_group_id, DROP COLUMN ava_academic_cohort_id, DROP COLUMN catalog_trail_id');
        $database->exec('DROP TABLE IF EXISTS ava_academic_groups');
        $database->exec('DROP TABLE IF EXISTS ava_academic_cohorts');
    }
};
