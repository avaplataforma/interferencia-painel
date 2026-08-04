<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260804_530000_create_internal_tickets'; }

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE tickets (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,unit_id BIGINT UNSIGNED NOT NULL,requester_user_id BIGINT UNSIGNED NOT NULL,assigned_user_id BIGINT UNSIGNED NOT NULL,subject VARCHAR(180) NOT NULL,description TEXT NOT NULL,priority VARCHAR(20) NOT NULL DEFAULT 'normal',status VARCHAR(20) NOT NULL DEFAULT 'open',due_at DATETIME NULL,resolved_at DATETIME NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY tickets_unit_status_idx(unit_id,status),KEY tickets_assigned_status_idx(assigned_user_id,status),CONSTRAINT tickets_unit_fk FOREIGN KEY(unit_id) REFERENCES units(id),CONSTRAINT tickets_requester_fk FOREIGN KEY(requester_user_id) REFERENCES users(id),CONSTRAINT tickets_assigned_fk FOREIGN KEY(assigned_user_id) REFERENCES users(id),CONSTRAINT tickets_priority_check CHECK(priority IN('low','normal','high','urgent')),CONSTRAINT tickets_status_check CHECK(status IN('open','in_progress','waiting','resolved','closed'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE ticket_comments (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,ticket_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,body TEXT NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY ticket_comments_ticket_idx(ticket_id,created_at),CONSTRAINT ticket_comments_ticket_fk FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,CONSTRAINT ticket_comments_user_fk FOREIGN KEY(user_id) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE ticket_events (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,ticket_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,event_type VARCHAR(40) NOT NULL,old_value VARCHAR(190) NULL,new_value VARCHAR(190) NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY ticket_events_ticket_idx(ticket_id,created_at),CONSTRAINT ticket_events_ticket_fk FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,CONSTRAINT ticket_events_user_fk FOREIGN KEY(user_id) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE ticket_reads (ticket_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,last_read_at DATETIME NOT NULL,PRIMARY KEY(ticket_id,user_id),CONSTRAINT ticket_reads_ticket_fk FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,CONSTRAINT ticket_reads_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("INSERT IGNORE INTO permissions(code,name) VALUES('tickets.view','Visualizar tickets internos'),('tickets.create','Abrir tickets internos'),('tickets.manage','Gerenciar tickets internos')");
        $db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code IN('super_admin','headquarters','manager','agent') AND p.code IN('tickets.view','tickets.create')");
        $db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code IN('super_admin','headquarters','manager') AND p.code='tickets.manage'");
    }

    public function down(PDO $db): void
    {
        $db->exec("DELETE rp FROM role_permissions rp INNER JOIN permissions p ON p.id=rp.permission_id WHERE p.code IN('tickets.view','tickets.create','tickets.manage')");
        $db->exec("DELETE FROM permissions WHERE code IN('tickets.view','tickets.create','tickets.manage')");
        $db->exec('DROP TABLE IF EXISTS ticket_reads,ticket_events,ticket_comments,tickets');
    }
};
