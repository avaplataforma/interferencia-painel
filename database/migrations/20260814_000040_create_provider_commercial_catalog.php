<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string { return '20260814_000040_create_provider_commercial_catalog'; }

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE provider_commercial_catalog_connections (
            provider_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            base_url VARCHAR(500) NOT NULL,
            username_encrypted TEXT NULL,
            username_last4 VARCHAR(4) NULL,
            password_encrypted TEXT NULL,
            password_last4 VARCHAR(4) NULL,
            last_sync_status VARCHAR(30) NOT NULL DEFAULT 'never',
            last_synced_at DATETIME NULL,
            last_error TEXT NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT provider_commercial_connection_fk FOREIGN KEY(provider_id) REFERENCES course_provider_integrations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE provider_commercial_catalog_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider_id BIGINT UNSIGNED NOT NULL,
            provider_course_id BIGINT UNSIGNED NULL,
            external_id VARCHAR(190) NOT NULL,
            title VARCHAR(500) NOT NULL,
            slug VARCHAR(500) NULL,
            author VARCHAR(500) NULL,
            summary TEXT NULL,
            description MEDIUMTEXT NULL,
            category VARCHAR(255) NULL,
            subcategory VARCHAR(255) NULL,
            material_type VARCHAR(160) NULL,
            cover_url TEXT NULL,
            detail_url TEXT NULL,
            topics_count INT UNSIGNED NOT NULL DEFAULT 0,
            resources_count INT UNSIGNED NOT NULL DEFAULT 0,
            questions_count INT UNSIGNED NOT NULL DEFAULT 0,
            complementary_count INT UNSIGNED NOT NULL DEFAULT 0,
            sync_status VARCHAR(30) NOT NULL DEFAULT 'pending_lti',
            is_available TINYINT(1) NOT NULL DEFAULT 1,
            content_hash CHAR(64) NOT NULL,
            raw_payload LONGTEXT NULL,
            first_seen_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            last_changed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY provider_commercial_external_uq(provider_id,external_id),
            INDEX provider_commercial_status_idx(provider_id,sync_status,is_available),
            INDEX provider_commercial_course_idx(provider_course_id),
            INDEX provider_commercial_title_idx(title(190)),
            CONSTRAINT provider_commercial_provider_fk FOREIGN KEY(provider_id) REFERENCES course_provider_integrations(id) ON DELETE CASCADE,
            CONSTRAINT provider_commercial_course_fk FOREIGN KEY(provider_course_id) REFERENCES provider_courses(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS provider_commercial_catalog_items');
        $db->exec('DROP TABLE IF EXISTS provider_commercial_catalog_connections');
    }
};
