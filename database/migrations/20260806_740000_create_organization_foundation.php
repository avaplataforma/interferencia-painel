<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string
    {
        return '20260806_740000_create_organization_foundation';
    }

    public function up(PDO $database): void
    {
        $database->exec("CREATE TABLE organizations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id CHAR(36) NOT NULL,
            code VARCHAR(80) NOT NULL,
            legal_name VARCHAR(190) NOT NULL,
            display_name VARCHAR(160) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            timezone VARCHAR(60) NOT NULL DEFAULT 'America/Sao_Paulo',
            locale VARCHAR(12) NOT NULL DEFAULT 'pt_BR',
            primary_color VARCHAR(7) NOT NULL DEFAULT '#ed1c24',
            secondary_color VARCHAR(7) NULL,
            logo_path VARCHAR(500) NULL,
            favicon_path VARCHAR(500) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY organizations_public_unique (public_id),
            UNIQUE KEY organizations_code_unique (code),
            CONSTRAINT organizations_status_check CHECK(status IN('active','suspended','archived'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("CREATE TABLE organization_domains (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NOT NULL,
            host VARCHAR(253) NOT NULL,
            purpose VARCHAR(20) NOT NULL DEFAULT 'panel',
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            verified_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY organization_domains_host_unique (host),
            KEY organization_domains_org_idx (organization_id,status),
            CONSTRAINT organization_domains_org_fk FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            CONSTRAINT organization_domains_purpose_check CHECK(purpose IN('panel','site','api')),
            CONSTRAINT organization_domains_status_check CHECK(status IN('pending','active','disabled'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("CREATE TABLE organization_users (
            organization_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            is_owner TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (organization_id,user_id),
            KEY organization_users_user_idx (user_id,status),
            CONSTRAINT organization_users_org_fk FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            CONSTRAINT organization_users_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT organization_users_status_check CHECK(status IN('invited','active','suspended'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $insert = $database->prepare('INSERT INTO organizations(public_id,code,legal_name,display_name) VALUES(UUID(),?,?,?)');
        $insert->execute(['interferencia', 'Interferência Treinamento LTDA', 'Interferência']);
        $organizationId = (int) $database->lastInsertId();

        $domain = $database->prepare("INSERT INTO organization_domains(organization_id,host,purpose,is_primary,status,verified_at) VALUES(?,?,'panel',?,'active',NOW())");
        $domain->execute([$organizationId, 'painel.mundointer.com.br', 1]);
        $domain->execute([$organizationId, 'interferencia.com.br', 0]);

        $database->exec('ALTER TABLE units ADD organization_id BIGINT UNSIGNED NULL AFTER id');
        $updateUnits = $database->prepare('UPDATE units SET organization_id=? WHERE organization_id IS NULL');
        $updateUnits->execute([$organizationId]);
        $database->exec('ALTER TABLE units MODIFY organization_id BIGINT UNSIGNED NOT NULL, DROP INDEX units_code_unique, ADD UNIQUE KEY units_org_code_unique(organization_id,code), ADD KEY units_org_active_idx(organization_id,is_active), ADD CONSTRAINT units_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id)');

        $membership = $database->prepare("INSERT INTO organization_users(organization_id,user_id,status,is_owner) SELECT ?,u.id,'active',EXISTS(SELECT 1 FROM user_roles ur INNER JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=u.id AND r.code='super_admin') FROM users u");
        $membership->execute([$organizationId]);
    }

    public function down(PDO $database): void
    {
        $database->exec('ALTER TABLE units DROP FOREIGN KEY units_org_fk, DROP INDEX units_org_code_unique, DROP INDEX units_org_active_idx, DROP COLUMN organization_id, ADD UNIQUE KEY units_code_unique(code)');
        $database->exec('DROP TABLE IF EXISTS organization_users');
        $database->exec('DROP TABLE IF EXISTS organization_domains');
        $database->exec('DROP TABLE IF EXISTS organizations');
    }
};
