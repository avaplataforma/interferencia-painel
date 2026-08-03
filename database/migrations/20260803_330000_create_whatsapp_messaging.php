<?php
declare(strict_types=1);
use Interferencia\Kernel\Database\Migration;
return new class implements Migration {
    public function id(): string { return '20260803_330000_create_whatsapp_messaging'; }
    public function up(PDO $db): void
    {
        $db->exec('ALTER TABLE whatsapp_lines ADD UNIQUE KEY whatsapp_lines_phone_number_id_unique(phone_number_id)');
        $db->exec("CREATE TABLE whatsapp_conversations (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,line_id BIGINT UNSIGNED NOT NULL,wa_contact_id VARCHAR(30) NOT NULL,contact_name VARCHAR(190) NULL,last_message_at DATETIME NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY whatsapp_conversation_unique(line_id,wa_contact_id),KEY whatsapp_conversations_last_idx(line_id,last_message_at),CONSTRAINT whatsapp_conversations_line_fk FOREIGN KEY(line_id) REFERENCES whatsapp_lines(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE whatsapp_messages (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,conversation_id BIGINT UNSIGNED NOT NULL,line_id BIGINT UNSIGNED NOT NULL,wamid VARCHAR(255) NOT NULL,direction VARCHAR(12) NOT NULL,message_type VARCHAR(30) NOT NULL,body TEXT NULL,status VARCHAR(20) NOT NULL DEFAULT 'received',message_at DATETIME NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY whatsapp_messages_wamid_unique(wamid),KEY whatsapp_messages_conversation_idx(conversation_id,message_at),CONSTRAINT whatsapp_messages_conversation_fk FOREIGN KEY(conversation_id) REFERENCES whatsapp_conversations(id) ON DELETE CASCADE,CONSTRAINT whatsapp_messages_line_fk FOREIGN KEY(line_id) REFERENCES whatsapp_lines(id) ON DELETE CASCADE,CONSTRAINT whatsapp_messages_direction_check CHECK(direction IN('inbound','outbound'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE whatsapp_webhook_events (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,event_key CHAR(64) NOT NULL,line_id BIGINT UNSIGNED NULL,event_type VARCHAR(40) NOT NULL,received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,processed_at TIMESTAMP NULL,error_message VARCHAR(500) NULL,PRIMARY KEY(id),UNIQUE KEY whatsapp_webhook_event_unique(event_key),CONSTRAINT whatsapp_webhook_events_line_fk FOREIGN KEY(line_id) REFERENCES whatsapp_lines(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS whatsapp_webhook_events');
        $db->exec('DROP TABLE IF EXISTS whatsapp_messages');
        $db->exec('DROP TABLE IF EXISTS whatsapp_conversations');
        $db->exec('ALTER TABLE whatsapp_lines DROP INDEX whatsapp_lines_phone_number_id_unique');
    }
};
