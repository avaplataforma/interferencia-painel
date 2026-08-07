<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string { return '20260807_930000_create_spaces_storage'; }

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE object_storage_integrations(id TINYINT UNSIGNED NOT NULL,provider VARCHAR(40) NOT NULL DEFAULT 'digitalocean_spaces',endpoint VARCHAR(255) NOT NULL,bucket VARCHAR(120) NOT NULL,region VARCHAR(32) NOT NULL,access_key_encrypted TEXT NOT NULL,access_key_last4 CHAR(4) NOT NULL,secret_key_encrypted TEXT NOT NULL,secret_key_last4 CHAR(4) NOT NULL,central_prefix VARCHAR(120) NOT NULL DEFAULT 'Mundo Inter',franchises_prefix VARCHAR(120) NOT NULL DEFAULT 'Franquias',is_active TINYINT(1) NOT NULL DEFAULT 0,last_tested_at DATETIME NULL,last_error VARCHAR(1000) NULL,updated_by BIGINT UNSIGNED NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),CONSTRAINT object_storage_provider_check CHECK(provider='digitalocean_spaces')) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE object_storage_objects(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,organization_id BIGINT UNSIGNED NULL,scope VARCHAR(20) NOT NULL,category VARCHAR(60) NOT NULL,object_key VARCHAR(1024) NOT NULL,original_name VARCHAR(255) NULL,mime_type VARCHAR(120) NOT NULL,bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,checksum_sha256 CHAR(64) NOT NULL,created_by BIGINT UNSIGNED NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY object_storage_objects_key_unique(object_key(255)),KEY object_storage_objects_org_idx(organization_id,category,created_at),CONSTRAINT object_storage_objects_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE SET NULL,CONSTRAINT object_storage_objects_scope_check CHECK(scope IN('central','franchise'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS object_storage_objects,object_storage_integrations');
    }
};
