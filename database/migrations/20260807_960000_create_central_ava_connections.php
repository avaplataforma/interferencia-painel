<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string { return '20260807_960000_create_central_ava_connections'; }

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE ava_connections(
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            connection_key VARCHAR(120) NOT NULL,
            organization_id BIGINT UNSIGNED NULL,
            connection_type VARCHAR(20) NOT NULL,
            name VARCHAR(160) NOT NULL,
            base_url VARCHAR(255) NULL,
            token_encrypted TEXT NULL,
            token_last4 VARCHAR(4) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            plugin_version VARCHAR(40) NULL,
            plugin_release VARCHAR(40) NULL,
            plugin_status VARCHAR(30) NULL,
            plugin_last_error TEXT NULL,
            last_seen_at DATETIME NULL,
            last_tested_at DATETIME NULL,
            last_error TEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            UNIQUE KEY ava_connections_key_unique(connection_key),
            KEY ava_connections_org_type_idx(organization_id,connection_type),
            CONSTRAINT ava_connections_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            CONSTRAINT ava_connections_created_by_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT ava_connections_updated_by_fk FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT ava_connections_type_check CHECK(connection_type IN('shared','franchise'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE organization_ava_settings(
            organization_id BIGINT UNSIGNED NOT NULL,
            access_mode VARCHAR(20) NOT NULL DEFAULT 'shared',
            primary_ava VARCHAR(20) NOT NULL DEFAULT 'shared',
            shared_connection_id BIGINT UNSIGNED NULL,
            own_connection_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(organization_id),
            CONSTRAINT organization_ava_settings_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            CONSTRAINT organization_ava_settings_shared_fk FOREIGN KEY(shared_connection_id) REFERENCES ava_connections(id) ON DELETE SET NULL,
            CONSTRAINT organization_ava_settings_own_fk FOREIGN KEY(own_connection_id) REFERENCES ava_connections(id) ON DELETE SET NULL,
            CONSTRAINT organization_ava_settings_mode_check CHECK(access_mode IN('shared','own','both')),
            CONSTRAINT organization_ava_settings_primary_check CHECK(primary_ava IN('shared','own'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("INSERT INTO ava_connections(connection_key,connection_type,name,base_url,token_encrypted,token_last4,is_active,last_tested_at,last_error,created_by,updated_by)
            SELECT 'shared:ava-cursos','shared','AVA Cursos',base_url,token_encrypted,token_last4,is_active,last_tested_at,last_error,updated_by,updated_by
            FROM moodle_integrations WHERE id=1 LIMIT 1");
        $db->exec("INSERT INTO ava_connections(connection_key,connection_type,name,is_active)
            SELECT 'shared:ava-cursos','shared','AVA Cursos',0
            WHERE NOT EXISTS(SELECT 1 FROM ava_connections WHERE connection_key='shared:ava-cursos')");
        $db->exec("INSERT INTO organization_ava_settings(organization_id,access_mode,primary_ava,shared_connection_id)
            SELECT o.id,'shared','shared',c.id FROM organizations o JOIN ava_connections c ON c.connection_key='shared:ava-cursos'");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS organization_ava_settings');
        $db->exec('DROP TABLE IF EXISTS ava_connections');
    }
};
