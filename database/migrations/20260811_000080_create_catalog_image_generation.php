<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260811_000080_create_catalog_image_generation';
    }

    public function up(PDO $database): void
    {
        $database->exec("CREATE TABLE catalog_image_generation_settings(
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            provider VARCHAR(40) NOT NULL DEFAULT 'openai',
            api_key_encrypted TEXT NULL,
            api_key_last4 VARCHAR(4) NULL,
            model VARCHAR(100) NOT NULL DEFAULT 'gpt-image-2',
            quality VARCHAR(20) NOT NULL DEFAULT 'low',
            size VARCHAR(30) NOT NULL DEFAULT '1536x1024',
            style_prompt TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            auto_generate_missing TINYINT(1) NOT NULL DEFAULT 0,
            last_tested_at DATETIME NULL,
            last_error TEXT NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $database->exec("INSERT INTO catalog_image_generation_settings(id,style_prompt) VALUES(1,'Fotografia editorial contemporânea, iluminação profissional, composição limpa, visual educacional premium, sem textos, sem logotipos e sem marcas.')");

        $database->exec("CREATE TABLE catalog_image_generation_jobs(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(30) NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL,
            prompt TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            error_message TEXT NULL,
            requested_by BIGINT UNSIGNED NULL,
            started_at DATETIME NULL,
            finished_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY catalog_image_job_status_index(status,created_at),
            KEY catalog_image_job_entity_index(entity_type,entity_id,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS catalog_image_generation_jobs');
        $database->exec('DROP TABLE IF EXISTS catalog_image_generation_settings');
    }
};
