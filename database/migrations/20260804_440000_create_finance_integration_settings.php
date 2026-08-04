<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id():string{return '20260804_440000_create_finance_integration_settings';}
    public function up(PDO$db):void
    {
        $db->exec("INSERT IGNORE INTO permissions(code,name) VALUES('finance.settings.manage','Gerenciar integração financeira')");
        $db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='super_admin' AND p.code='finance.settings.manage'");
        $db->exec("CREATE TABLE finance_integrations(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,provider VARCHAR(40) NOT NULL,environment VARCHAR(20) NOT NULL DEFAULT 'sandbox',api_key_encrypted TEXT NULL,api_key_last4 VARCHAR(4) NULL,webhook_token_encrypted TEXT NULL,is_active TINYINT(1) NOT NULL DEFAULT 0,updated_by BIGINT UNSIGNED NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY finance_integrations_provider_unique(provider),CONSTRAINT finance_integrations_user_fk FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("INSERT INTO finance_integrations(provider) VALUES('asaas')");
    }
    public function down(PDO$db):void{$db->exec('DROP TABLE IF EXISTS finance_integrations');$db->exec("DELETE rp FROM role_permissions rp INNER JOIN permissions p ON p.id=rp.permission_id WHERE p.code='finance.settings.manage'");$db->exec("DELETE FROM permissions WHERE code='finance.settings.manage'");}
};
