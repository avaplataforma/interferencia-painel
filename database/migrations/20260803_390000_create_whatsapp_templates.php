<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260803_390000_create_whatsapp_templates';
    }

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE whatsapp_templates (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,name VARCHAR(120) NOT NULL,meta_name VARCHAR(512) NOT NULL,category VARCHAR(30) NOT NULL,language VARCHAR(20) NOT NULL DEFAULT 'pt_BR',body TEXT NOT NULL,approval_status VARCHAR(30) NOT NULL DEFAULT 'draft',is_active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY whatsapp_templates_meta_name_unique(meta_name),CONSTRAINT whatsapp_templates_category_check CHECK(category IN('marketing','utility','authentication')),CONSTRAINT whatsapp_templates_status_check CHECK(approval_status IN('draft','pending','approved','rejected'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS whatsapp_templates');
    }
};
