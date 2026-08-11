<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260811_000050_create_provider_catalog_contents';
    }

    public function up(\PDO $database): void
    {
        $database->exec("CREATE TABLE provider_catalog_contents(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider_id BIGINT UNSIGNED NOT NULL,
            catalog_id BIGINT UNSIGNED NOT NULL,
            external_key VARCHAR(190) NOT NULL,
            content_type VARCHAR(60) NOT NULL,
            name VARCHAR(500) NOT NULL,
            commercial_name VARCHAR(500) NULL,
            commercial_description LONGTEXT NULL,
            commercial_category VARCHAR(255) NULL,
            commercial_workload VARCHAR(100) NULL,
            commercial_cover_url VARCHAR(1000) NULL,
            review_status VARCHAR(30) NOT NULL DEFAULT 'imported',
            release_status VARCHAR(30) NOT NULL DEFAULT 'private',
            is_available TINYINT(1) NOT NULL DEFAULT 1,
            raw_payload LONGTEXT NULL,
            content_hash CHAR(64) NULL,
            sync_state VARCHAR(30) NOT NULL DEFAULT 'new',
            review_notes TEXT NULL,
            reviewed_by BIGINT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            first_seen_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            last_changed_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY provider_content_external_unique(provider_id,content_type,external_key),
            KEY provider_content_catalog_index(catalog_id,is_available),
            KEY provider_content_review_index(review_status,release_status,is_available),
            KEY provider_content_name_index(name(190)),
            CONSTRAINT provider_content_provider_fk FOREIGN KEY(provider_id) REFERENCES course_provider_integrations(id) ON DELETE CASCADE,
            CONSTRAINT provider_content_catalog_fk FOREIGN KEY(catalog_id) REFERENCES course_catalogs(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("CREATE TABLE provider_course_content_links(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider_course_id BIGINT UNSIGNED NOT NULL,
            provider_content_id BIGINT UNSIGNED NOT NULL,
            semester_number SMALLINT UNSIGNED NULL,
            discipline_name VARCHAR(500) NULL,
            position INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY provider_course_content_unique(provider_course_id,provider_content_id),
            KEY provider_content_link_index(provider_content_id),
            CONSTRAINT provider_course_content_course_fk FOREIGN KEY(provider_course_id) REFERENCES provider_courses(id) ON DELETE CASCADE,
            CONSTRAINT provider_course_content_content_fk FOREIGN KEY(provider_content_id) REFERENCES provider_catalog_contents(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("CREATE TABLE organization_provider_content_offers(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organization_id BIGINT UNSIGNED NOT NULL,
            provider_content_id BIGINT UNSIGNED NOT NULL,
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
            UNIQUE KEY organization_provider_content_unique(organization_id,provider_content_id),
            KEY organization_content_offer_public_index(organization_id,is_active,is_visible),
            KEY organization_content_offer_content_index(provider_content_id),
            CONSTRAINT organization_content_offer_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            CONSTRAINT organization_content_offer_content_fk FOREIGN KEY(provider_content_id) REFERENCES provider_catalog_contents(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(\PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS organization_provider_content_offers');
        $database->exec('DROP TABLE IF EXISTS provider_course_content_links');
        $database->exec('DROP TABLE IF EXISTS provider_catalog_contents');
    }
};
