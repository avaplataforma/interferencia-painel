<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string { return '20260813_000030_create_provider_course_assessments'; }

    public function up(PDO $db): void
    {
        $db->exec("ALTER TABLE provider_courses ADD COLUMN commercial_summary TEXT NULL AFTER commercial_name");
        $db->exec("CREATE TABLE provider_course_assessments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider_course_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            source VARCHAR(30) NOT NULL DEFAULT 'ai_draft',
            questions_json LONGTEXT NOT NULL,
            signature CHAR(64) NOT NULL,
            review_status VARCHAR(30) NOT NULL DEFAULT 'draft',
            generated_by BIGINT UNSIGNED NULL,
            reviewed_by BIGINT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY provider_course_assessments_course_uq(provider_course_id),
            CONSTRAINT provider_course_assessments_course_fk FOREIGN KEY(provider_course_id) REFERENCES provider_courses(id) ON DELETE CASCADE,
            CONSTRAINT provider_course_assessments_generated_fk FOREIGN KEY(generated_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT provider_course_assessments_reviewed_fk FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS provider_course_assessments');
        $db->exec('ALTER TABLE provider_courses DROP COLUMN commercial_summary');
    }
};
