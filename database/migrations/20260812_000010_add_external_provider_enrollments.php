<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string
    {
        return '20260812_000010_add_external_provider_enrollments';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE student_enrollments
            DROP FOREIGN KEY student_enrollments_course_fk,
            MODIFY moodle_course_id BIGINT UNSIGNED NULL,
            ADD provider_course_offer_id BIGINT UNSIGNED NULL AFTER moodle_course_id,
            ADD provider_content_offer_id BIGINT UNSIGNED NULL AFTER provider_course_offer_id,
            ADD academic_provider_code VARCHAR(100) NULL AFTER provider_content_offer_id,
            ADD provider_content_type VARCHAR(60) NULL AFTER academic_provider_code,
            ADD provider_batch VARCHAR(190) NULL AFTER provider_content_type,
            ADD provider_student_key VARCHAR(190) NULL AFTER provider_batch,
            ADD provider_access_url VARCHAR(1500) NULL AFTER provider_student_key,
            ADD provider_response LONGTEXT NULL AFTER provider_access_url,
            ADD KEY student_enrollments_provider_course_offer_idx(provider_course_offer_id),
            ADD KEY student_enrollments_provider_content_offer_idx(provider_content_offer_id),
            ADD KEY student_enrollments_provider_idx(academic_provider_code),
            ADD CONSTRAINT student_enrollments_course_fk FOREIGN KEY(moodle_course_id) REFERENCES moodle_courses(id),
            ADD CONSTRAINT student_enrollments_provider_course_offer_fk FOREIGN KEY(provider_course_offer_id) REFERENCES organization_provider_course_offers(id) ON DELETE SET NULL,
            ADD CONSTRAINT student_enrollments_provider_content_offer_fk FOREIGN KEY(provider_content_offer_id) REFERENCES organization_provider_content_offers(id) ON DELETE SET NULL");
    }

    public function down(PDO $database): void
    {
        $database->exec("ALTER TABLE student_enrollments
            DROP FOREIGN KEY student_enrollments_provider_content_offer_fk,
            DROP FOREIGN KEY student_enrollments_provider_course_offer_fk,
            DROP FOREIGN KEY student_enrollments_course_fk,
            DROP KEY student_enrollments_provider_idx,
            DROP KEY student_enrollments_provider_content_offer_idx,
            DROP KEY student_enrollments_provider_course_offer_idx,
            DROP COLUMN provider_response,
            DROP COLUMN provider_access_url,
            DROP COLUMN provider_student_key,
            DROP COLUMN provider_batch,
            DROP COLUMN provider_content_type,
            DROP COLUMN academic_provider_code,
            DROP COLUMN provider_content_offer_id,
            DROP COLUMN provider_course_offer_id,
            MODIFY moodle_course_id BIGINT UNSIGNED NOT NULL,
            ADD CONSTRAINT student_enrollments_course_fk FOREIGN KEY(moodle_course_id) REFERENCES moodle_courses(id)");
    }
};
