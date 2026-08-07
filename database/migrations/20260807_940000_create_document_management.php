<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string { return '20260807_940000_create_document_management'; }

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE managed_documents(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,document_group CHAR(36) NOT NULL,version_number INT UNSIGNED NOT NULL DEFAULT 1,organization_id BIGINT UNSIGNED NULL,scope VARCHAR(20) NOT NULL,category VARCHAR(60) NOT NULL,title VARCHAR(180) NOT NULL,description VARCHAR(1000) NULL,storage_path VARCHAR(1200) NOT NULL,original_name VARCHAR(255) NOT NULL,mime_type VARCHAR(120) NOT NULL,bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,checksum_sha256 CHAR(64) NOT NULL,created_by BIGINT UNSIGNED NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,deleted_by BIGINT UNSIGNED NULL,deleted_at DATETIME NULL,PRIMARY KEY(id),UNIQUE KEY managed_documents_group_version_unique(document_group,version_number),KEY managed_documents_scope_idx(scope,organization_id,deleted_at,created_at),KEY managed_documents_category_idx(category,created_at),CONSTRAINT managed_documents_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE SET NULL,CONSTRAINT managed_documents_scope_check CHECK(scope IN('central','franchise'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS managed_documents');
    }
};
