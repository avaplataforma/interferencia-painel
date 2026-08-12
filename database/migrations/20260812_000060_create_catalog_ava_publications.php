<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260812_000060_create_catalog_ava_publications';
    }

    public function up(PDO $database): void
    {
        $database->exec("CREATE TABLE catalog_ava_publications(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(30) NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL,
            ava_connection_id BIGINT UNSIGNED NOT NULL,
            publication_status VARCHAR(20) NOT NULL DEFAULT 'draft',
            moodle_course_id BIGINT UNSIGNED NULL,
            remote_category_id BIGINT NULL,
            remote_course_id BIGINT NULL,
            source_signature CHAR(64) NULL,
            last_error VARCHAR(1000) NULL,
            prepared_at DATETIME NULL,
            published_at DATETIME NULL,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY catalog_ava_publication_entity_unique(entity_type,entity_id,ava_connection_id),
            KEY catalog_ava_publication_status_idx(publication_status,updated_at),
            CONSTRAINT catalog_ava_publication_connection_fk FOREIGN KEY(ava_connection_id) REFERENCES ava_connections(id) ON DELETE CASCADE,
            CONSTRAINT catalog_ava_publication_course_fk FOREIGN KEY(moodle_course_id) REFERENCES moodle_courses(id) ON DELETE SET NULL,
            CONSTRAINT catalog_ava_publication_created_by_fk FOREIGN KEY(created_by) REFERENCES platform_users(id) ON DELETE SET NULL,
            CONSTRAINT catalog_ava_publication_updated_by_fk FOREIGN KEY(updated_by) REFERENCES platform_users(id) ON DELETE SET NULL,
            CONSTRAINT catalog_ava_publication_type_check CHECK(entity_type IN ('trail','finance_product','provider_course','provider_content')),
            CONSTRAINT catalog_ava_publication_status_check CHECK(publication_status IN ('draft','ready','published','failed'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("CREATE TABLE catalog_ava_publication_events(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            publication_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(30) NOT NULL,
            event_status VARCHAR(20) NOT NULL,
            remote_category_id BIGINT NULL,
            remote_course_id BIGINT NULL,
            message VARCHAR(1000) NULL,
            details_json LONGTEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY catalog_ava_publication_events_history_idx(publication_id,created_at,id),
            CONSTRAINT catalog_ava_publication_events_publication_fk FOREIGN KEY(publication_id) REFERENCES catalog_ava_publications(id) ON DELETE CASCADE,
            CONSTRAINT catalog_ava_publication_events_user_fk FOREIGN KEY(created_by) REFERENCES platform_users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS catalog_ava_publication_events');
        $database->exec('DROP TABLE IF EXISTS catalog_ava_publications');
    }
};
