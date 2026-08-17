<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;
use PDO;

return new class implements Migration {
    public function id(): string
    {
        return '20260817_000020_create_site_testimonials';
    }

    public function up(PDO $database): void
    {
        $database->exec("CREATE TABLE site_testimonials(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organization_id BIGINT UNSIGNED NOT NULL,
            author_name VARCHAR(160) NOT NULL,
            author_city VARCHAR(190) NULL,
            course_name VARCHAR(190) NOT NULL,
            rating TINYINT UNSIGNED NOT NULL,
            testimonial_text TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY site_testimonials_org_status_idx(organization_id,status,created_at),
            CONSTRAINT site_testimonials_organization_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            CONSTRAINT site_testimonials_rating_check CHECK(rating BETWEEN 1 AND 5)
        )");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS site_testimonials');
    }
};
