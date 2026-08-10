<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260810_999800_create_provider_course_offers';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE provider_courses
            ADD COLUMN review_status VARCHAR(30) NOT NULL DEFAULT 'pending' AFTER is_available,
            ADD COLUMN commercial_name VARCHAR(500) NULL AFTER review_status,
            ADD COLUMN commercial_description LONGTEXT NULL AFTER commercial_name,
            ADD COLUMN review_notes TEXT NULL AFTER commercial_description,
            ADD COLUMN reviewed_by BIGINT UNSIGNED NULL AFTER review_notes,
            ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by,
            ADD KEY provider_courses_review_index(review_status,is_available)");

        $database->exec("CREATE TABLE organization_provider_course_offers(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organization_id BIGINT UNSIGNED NOT NULL,
            provider_course_id BIGINT UNSIGNED NOT NULL,
            commercial_name VARCHAR(500) NULL,
            commercial_description LONGTEXT NULL,
            price DECIMAL(12,2) NOT NULL DEFAULT 0,
            max_installments TINYINT UNSIGNED NOT NULL DEFAULT 1,
            sale_mode VARCHAR(30) NOT NULL DEFAULT 'assisted',
            is_visible TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY organization_provider_course_unique(organization_id,provider_course_id),
            KEY organization_provider_offer_public_index(organization_id,is_active,is_visible),
            KEY organization_provider_offer_course_index(provider_course_id),
            CONSTRAINT organization_provider_offer_organization_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            CONSTRAINT organization_provider_offer_course_fk FOREIGN KEY(provider_course_id) REFERENCES provider_courses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS organization_provider_course_offers');
        $database->exec("ALTER TABLE provider_courses
            DROP KEY provider_courses_review_index,
            DROP COLUMN reviewed_at,
            DROP COLUMN reviewed_by,
            DROP COLUMN review_notes,
            DROP COLUMN commercial_description,
            DROP COLUMN commercial_name,
            DROP COLUMN review_status");
    }
};
