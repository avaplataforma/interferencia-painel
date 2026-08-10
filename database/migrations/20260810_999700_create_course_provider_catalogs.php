<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260810_999700_create_course_provider_catalogs';
    }

    public function up(PDO $database): void
    {
        $database->exec("CREATE TABLE course_catalogs(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(100) NOT NULL UNIQUE,
            name VARCHAR(190) NOT NULL,
            description TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("CREATE TABLE course_provider_integrations(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider_code VARCHAR(100) NOT NULL UNIQUE,
            name VARCHAR(190) NOT NULL,
            base_url VARCHAR(500) NULL,
            token_encrypted TEXT NULL,
            token_last4 VARCHAR(4) NULL,
            catalog_id BIGINT UNSIGNED NULL,
            delivery_mode VARCHAR(30) NOT NULL DEFAULT 'external_link',
            launch_url_template VARCHAR(1000) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            last_test_status VARCHAR(30) NOT NULL DEFAULT 'not_tested',
            last_tested_at DATETIME NULL,
            last_sync_status VARCHAR(30) NOT NULL DEFAULT 'never',
            last_synced_at DATETIME NULL,
            last_error TEXT NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY course_provider_catalog_index(catalog_id),
            CONSTRAINT course_provider_catalog_fk FOREIGN KEY(catalog_id) REFERENCES course_catalogs(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("CREATE TABLE provider_courses(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider_id BIGINT UNSIGNED NOT NULL,
            catalog_id BIGINT UNSIGNED NOT NULL,
            external_key CHAR(64) NOT NULL,
            remote_id VARCHAR(190) NULL,
            name VARCHAR(500) NOT NULL,
            description LONGTEXT NULL,
            category VARCHAR(255) NULL,
            workload VARCHAR(100) NULL,
            lesson_count INT UNSIGNED NOT NULL DEFAULT 0,
            cover_url VARCHAR(1000) NULL,
            remote_reference_price DECIMAL(12,2) NULL,
            remote_promotional_price DECIMAL(12,2) NULL,
            remote_installments INT UNSIGNED NULL,
            remote_status VARCHAR(100) NULL,
            is_available TINYINT(1) NOT NULL DEFAULT 1,
            raw_payload LONGTEXT NULL,
            first_seen_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY provider_courses_external_unique(provider_id,external_key),
            KEY provider_courses_catalog_index(catalog_id,is_available),
            KEY provider_courses_category_index(category),
            CONSTRAINT provider_courses_provider_fk FOREIGN KEY(provider_id) REFERENCES course_provider_integrations(id) ON DELETE CASCADE,
            CONSTRAINT provider_courses_catalog_fk FOREIGN KEY(catalog_id) REFERENCES course_catalogs(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("INSERT INTO course_catalogs(code,name,description,is_active) VALUES('catalogo-pro','Catálogo PRO','Cursos de fornecedores externos, com comercialização controlada pelo Mundo Inter.',1)");
        $database->exec("INSERT INTO course_provider_integrations(provider_code,name,catalog_id,delivery_mode,is_active) SELECT 'escola_avancada','Escola Avançada',id,'external_link',0 FROM course_catalogs WHERE code='catalogo-pro'");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS provider_courses');
        $database->exec('DROP TABLE IF EXISTS course_provider_integrations');
        $database->exec('DROP TABLE IF EXISTS course_catalogs');
    }
};
