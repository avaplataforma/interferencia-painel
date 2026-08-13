<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string
    {
        return '20260813_000010_add_standard_academic_tracking';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE student_enrollments
            ADD academic_source VARCHAR(100) NULL AFTER provider_response,
            ADD academic_progress_percent DECIMAL(5,2) NULL AFTER academic_source,
            ADD academic_progress_status VARCHAR(30) NOT NULL DEFAULT 'not_available' AFTER academic_progress_percent,
            ADD academic_grade_percent DECIMAL(5,2) NULL AFTER academic_progress_status,
            ADD academic_grade_status VARCHAR(30) NOT NULL DEFAULT 'not_available' AFTER academic_grade_percent,
            ADD academic_last_access_at DATETIME NULL AFTER academic_grade_status,
            ADD academic_certificate_status VARCHAR(30) NOT NULL DEFAULT 'not_available' AFTER academic_last_access_at,
            ADD academic_certificate_url VARCHAR(1500) NULL AFTER academic_certificate_status,
            ADD academic_synced_at DATETIME NULL AFTER academic_certificate_url,
            ADD academic_sync_error VARCHAR(500) NULL AFTER academic_synced_at,
            ADD KEY student_enrollments_academic_sync_idx(organization_id,academic_synced_at),
            ADD KEY student_enrollments_academic_risk_idx(organization_id,academic_progress_status,academic_grade_status)");

        $database->exec("UPDATE student_enrollments enrollment
            INNER JOIN moodle_courses course ON course.id=enrollment.moodle_course_id
            LEFT JOIN moodle_enrolments progress ON progress.moodle_user_id=enrollment.ava_user_id AND progress.moodle_course_id=course.moodle_course_id
            SET enrollment.academic_source='ava_cursos',
                enrollment.academic_progress_percent=progress.completion_percent,
                enrollment.academic_progress_status=COALESCE(progress.completion_status,'not_available'),
                enrollment.academic_synced_at=progress.progress_synced_at,
                enrollment.academic_sync_error=progress.progress_error
            WHERE COALESCE(enrollment.academic_provider_code,'')='' AND progress.id IS NOT NULL");
    }

    public function down(PDO $database): void
    {
        $database->exec("ALTER TABLE student_enrollments
            DROP KEY student_enrollments_academic_risk_idx,
            DROP KEY student_enrollments_academic_sync_idx,
            DROP COLUMN academic_sync_error,
            DROP COLUMN academic_synced_at,
            DROP COLUMN academic_certificate_url,
            DROP COLUMN academic_certificate_status,
            DROP COLUMN academic_last_access_at,
            DROP COLUMN academic_grade_status,
            DROP COLUMN academic_grade_percent,
            DROP COLUMN academic_progress_status,
            DROP COLUMN academic_progress_percent,
            DROP COLUMN academic_source");
    }
};
