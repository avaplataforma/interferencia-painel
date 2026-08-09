<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string
    {
        return '20260808_980000_create_ava_polo_mappings';
    }

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE ava_polo_mappings(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,organization_id BIGINT UNSIGNED NOT NULL,field_value VARCHAR(255) NOT NULL,created_by BIGINT UNSIGNED NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY ava_polo_mappings_value_unique(field_value),KEY ava_polo_mappings_org_idx(organization_id),CONSTRAINT ava_polo_mappings_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE ava_polo_diagnostics(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,connection_id BIGINT UNSIGNED NOT NULL,profile_field VARCHAR(190) NOT NULL,total_users INT UNSIGNED NOT NULL DEFAULT 0,empty_users INT UNSIGNED NOT NULL DEFAULT 0,values_json LONGTEXT NOT NULL,last_error TEXT NULL,checked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY ava_polo_diagnostics_connection_unique(connection_id),CONSTRAINT ava_polo_diagnostics_connection_fk FOREIGN KEY(connection_id) REFERENCES ava_connections(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("INSERT IGNORE INTO ava_polo_mappings(organization_id,field_value) SELECT DISTINCT u.organization_id,TRIM(m.field_value) FROM moodle_unit_mappings m INNER JOIN units u ON u.id=m.unit_id WHERE NULLIF(TRIM(m.field_value),'') IS NOT NULL");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS ava_polo_diagnostics');
        $db->exec('DROP TABLE IF EXISTS ava_polo_mappings');
    }
};
