<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;
use PDO;

return new class implements Migration {
    public function id(): string
    {
        return '20260817_000050_create_testimonial_invites';
    }

    public function up(PDO $database): void
    {
        $database->exec("CREATE TABLE site_testimonial_invites(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            student_enrollment_id BIGINT UNSIGNED NOT NULL,
            organization_id BIGINT UNSIGNED NOT NULL,
            student_email VARCHAR(190) NOT NULL,
            student_name VARCHAR(190) NULL,
            course_name VARCHAR(190) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'sent',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY site_testimonial_invites_enrollment_uniq(student_enrollment_id),
            KEY site_testimonial_invites_org_idx(organization_id,created_at),
            CONSTRAINT site_testimonial_invites_enrollment_fk FOREIGN KEY(student_enrollment_id) REFERENCES student_enrollments(id) ON DELETE CASCADE
        )");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS site_testimonial_invites');
    }
};
