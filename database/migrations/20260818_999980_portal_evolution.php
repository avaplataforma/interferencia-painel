<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260818_999980_portal_evolution'; }

    public function up(PDO $db): void
    {
        $db->exec("ALTER TABLE organization_portal_settings ADD show_certificates TINYINT(1) NOT NULL DEFAULT 1 AFTER show_documents,ADD show_materials TINYINT(1) NOT NULL DEFAULT 1 AFTER show_certificates,ADD show_satisfaction TINYINT(1) NOT NULL DEFAULT 1 AFTER show_materials");
        $db->exec("CREATE TABLE organization_materials (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,organization_id BIGINT UNSIGNED NOT NULL,title VARCHAR(190) NOT NULL,file_name VARCHAR(255) NOT NULL,mime_type VARCHAR(120) NOT NULL,file_size INT UNSIGNED NOT NULL DEFAULT 0,storage_path VARCHAR(500) NOT NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,created_by BIGINT UNSIGNED NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY organization_materials_org_idx(organization_id,is_active,created_at),CONSTRAINT organization_materials_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,CONSTRAINT organization_materials_user_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE portal_satisfaction_responses (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,organization_id BIGINT UNSIGNED NOT NULL,finance_customer_id BIGINT UNSIGNED NOT NULL,rating TINYINT UNSIGNED NOT NULL,comment TEXT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY portal_satisfaction_org_idx(organization_id,created_at),KEY portal_satisfaction_customer_idx(finance_customer_id,created_at),CONSTRAINT portal_satisfaction_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,CONSTRAINT portal_satisfaction_customer_fk FOREIGN KEY(finance_customer_id) REFERENCES finance_customers(id) ON DELETE CASCADE,CONSTRAINT portal_satisfaction_rating_check CHECK(rating BETWEEN 1 AND 5)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS portal_satisfaction_responses,organization_materials');
        $db->exec("ALTER TABLE organization_portal_settings DROP COLUMN show_satisfaction,DROP COLUMN show_materials,DROP COLUMN show_certificates");
    }
};
