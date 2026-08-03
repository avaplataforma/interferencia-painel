<?php
declare(strict_types=1);
use Interferencia\Kernel\Database\Migration;
return new class implements Migration {
    public function id(): string { return '20260803_320000_create_whatsapp_lines'; }
    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE whatsapp_lines (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,unit_id BIGINT UNSIGNED NOT NULL,name VARCHAR(120) NOT NULL,phone_e164 VARCHAR(20) NOT NULL,connection_status VARCHAR(30) NOT NULL DEFAULT 'awaiting_official_api',waba_id VARCHAR(80) NULL,phone_number_id VARCHAR(80) NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY whatsapp_lines_unit_unique(unit_id),UNIQUE KEY whatsapp_lines_phone_unique(phone_e164),CONSTRAINT whatsapp_lines_unit_fk FOREIGN KEY(unit_id) REFERENCES units(id),CONSTRAINT whatsapp_lines_status_check CHECK(connection_status IN('awaiting_official_api','connected','paused'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE whatsapp_line_user_scopes (line_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(line_id,user_id),CONSTRAINT whatsapp_line_scopes_line_fk FOREIGN KEY(line_id) REFERENCES whatsapp_lines(id) ON DELETE CASCADE,CONSTRAINT whatsapp_line_scopes_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("INSERT IGNORE INTO permissions(code,name) VALUES('whatsapp.lines.manage','Gerenciar linhas do WhatsApp'),('whatsapp.inbox.view','Visualizar atendimento do WhatsApp')");
        $db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='super_admin' AND p.code IN('whatsapp.lines.manage','whatsapp.inbox.view')");
        $db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code IN('manager','agent') AND p.code='whatsapp.inbox.view'");
    }
    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS whatsapp_line_user_scopes');
        $db->exec('DROP TABLE IF EXISTS whatsapp_lines');
        $db->exec("DELETE FROM permissions WHERE code IN('whatsapp.lines.manage','whatsapp.inbox.view')");
    }
};
