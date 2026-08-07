<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260806_850000_separate_platform_identity'; }

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE platform_users(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,name VARCHAR(120) NOT NULL,email VARCHAR(190) NOT NULL,password_hash VARCHAR(255) NOT NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,failed_login_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,locked_until DATETIME NULL,last_login_at DATETIME NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY platform_users_email_unique(email),KEY platform_users_active_idx(is_active)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE platform_roles(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,code VARCHAR(80) NOT NULL,name VARCHAR(120) NOT NULL,PRIMARY KEY(id),UNIQUE KEY platform_roles_code_unique(code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE platform_permissions(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,code VARCHAR(120) NOT NULL,name VARCHAR(160) NOT NULL,PRIMARY KEY(id),UNIQUE KEY platform_permissions_code_unique(code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE platform_user_roles(user_id BIGINT UNSIGNED NOT NULL,role_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(user_id,role_id),CONSTRAINT platform_user_roles_user_fk FOREIGN KEY(user_id) REFERENCES platform_users(id) ON DELETE CASCADE,CONSTRAINT platform_user_roles_role_fk FOREIGN KEY(role_id) REFERENCES platform_roles(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE platform_role_permissions(role_id BIGINT UNSIGNED NOT NULL,permission_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(role_id,permission_id),CONSTRAINT platform_role_permissions_role_fk FOREIGN KEY(role_id) REFERENCES platform_roles(id) ON DELETE CASCADE,CONSTRAINT platform_role_permissions_permission_fk FOREIGN KEY(permission_id) REFERENCES platform_permissions(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("INSERT INTO platform_roles(code,name) VALUES('super_admin','Admin Central'),('platform_manager','Gestor Central'),('platform_agent','Colaborador Central')");
        $db->exec("INSERT INTO platform_permissions(code,name) VALUES('dashboard.view','Visualizar painel central'),('users.manage','Gerenciar colaboradores centrais'),('roles.manage','Gerenciar perfis centrais'),('tickets.manage','Gerenciar tickets da rede')");
        $db->exec("INSERT INTO platform_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM platform_roles r CROSS JOIN platform_permissions p WHERE r.code='super_admin'");
        $db->exec("INSERT INTO platform_users(name,email,password_hash,is_active,failed_login_attempts,locked_until,last_login_at) SELECT DISTINCT u.name,u.email,u.password_hash,u.is_active,u.failed_login_attempts,u.locked_until,u.last_login_at FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id WHERE r.code='super_admin' AND u.email='contato@interferencia.com.br'");
        $db->exec("INSERT INTO platform_users(name,email,password_hash,is_active,failed_login_attempts,locked_until,last_login_at) SELECT u.name,u.email,u.password_hash,u.is_active,u.failed_login_attempts,u.locked_until,u.last_login_at FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id WHERE r.code='super_admin' AND NOT EXISTS(SELECT 1 FROM platform_users) ORDER BY u.id LIMIT 1");
        $db->exec("INSERT INTO platform_user_roles(user_id,role_id) SELECT u.id,r.id FROM platform_users u CROSS JOIN platform_roles r WHERE r.code='super_admin'");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS platform_role_permissions,platform_user_roles,platform_permissions,platform_roles,platform_users');
    }
};
