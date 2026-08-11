<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260811_000060_create_catalog_media_assets';
    }

    public function up(\PDO $database): void
    {
        $database->exec("CREATE TABLE catalog_media_assets(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(30) NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL,
            purpose VARCHAR(30) NOT NULL DEFAULT 'cover',
            storage_path VARCHAR(1000) NULL,
            mime_type VARCHAR(100) NULL,
            width INT UNSIGNED NULL,
            height INT UNSIGNED NULL,
            file_size BIGINT UNSIGNED NULL,
            source VARCHAR(30) NOT NULL DEFAULT 'upload',
            generation_provider VARCHAR(100) NULL,
            generation_prompt TEXT NULL,
            generation_status VARCHAR(30) NOT NULL DEFAULT 'ready',
            generation_error TEXT NULL,
            generated_at DATETIME NULL,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY catalog_media_entity_unique(entity_type,entity_id,purpose),
            KEY catalog_media_status_index(generation_status,updated_at),
            KEY catalog_media_user_index(updated_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(\PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS catalog_media_assets');
    }
};
