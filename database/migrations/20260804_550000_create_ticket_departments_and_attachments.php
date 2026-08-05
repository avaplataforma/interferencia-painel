<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260804_550000_create_ticket_departments_and_attachments'; }

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE ticket_departments(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,name VARCHAR(120) NOT NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY ticket_departments_name_unique(name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE ticket_department_users(department_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(department_id,user_id),CONSTRAINT ticket_department_users_department_fk FOREIGN KEY(department_id) REFERENCES ticket_departments(id) ON DELETE CASCADE,CONSTRAINT ticket_department_users_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("INSERT INTO ticket_departments(name,is_active) VALUES('Comercial',1),('Pedagógico',1),('Geral',1)");
        $db->exec('ALTER TABLE tickets ADD department_id BIGINT UNSIGNED NULL AFTER crm_contact_id, MODIFY assigned_user_id BIGINT UNSIGNED NULL');
        $db->exec("UPDATE tickets SET department_id=(SELECT id FROM ticket_departments WHERE name='Geral' LIMIT 1)");
        $db->exec('ALTER TABLE tickets MODIFY department_id BIGINT UNSIGNED NOT NULL, ADD KEY tickets_department_status_idx(department_id,status), ADD CONSTRAINT tickets_department_fk FOREIGN KEY(department_id) REFERENCES ticket_departments(id)');
        $db->exec("CREATE TABLE ticket_attachments(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,ticket_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,file_name VARCHAR(255) NOT NULL,mime_type VARCHAR(120) NOT NULL,file_size INT UNSIGNED NOT NULL,storage_path VARCHAR(500) NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY ticket_attachments_ticket_idx(ticket_id,created_at),CONSTRAINT ticket_attachments_ticket_fk FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,CONSTRAINT ticket_attachments_user_fk FOREIGN KEY(user_id) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("INSERT IGNORE INTO permissions(code,name) VALUES('tickets.departments.manage','Gerenciar setores de tickets')");
        $db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code IN('super_admin','headquarters') AND p.code='tickets.departments.manage'");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS ticket_attachments');
        $db->exec('ALTER TABLE tickets DROP FOREIGN KEY tickets_department_fk, DROP KEY tickets_department_status_idx, DROP COLUMN department_id, MODIFY assigned_user_id BIGINT UNSIGNED NOT NULL');
        $db->exec('DROP TABLE IF EXISTS ticket_department_users,ticket_departments');
        $db->exec("DELETE rp FROM role_permissions rp INNER JOIN permissions p ON p.id=rp.permission_id WHERE p.code='tickets.departments.manage'");
        $db->exec("DELETE FROM permissions WHERE code='tickets.departments.manage'");
    }
};
