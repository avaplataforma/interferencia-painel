<?php
declare(strict_types=1);
use Interferencia\Kernel\Database\Migration;
return new class implements Migration {
    public function id(): string { return '20260803_340000_prepare_whatsapp_inbox'; }
    public function up(PDO $db): void { $db->exec("ALTER TABLE whatsapp_conversations ADD crm_contact_id BIGINT UNSIGNED NULL AFTER line_id, ADD assigned_user_id BIGINT UNSIGNED NULL AFTER crm_contact_id, ADD status VARCHAR(20) NOT NULL DEFAULT 'open' AFTER contact_name, ADD unread_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER status, ADD CONSTRAINT whatsapp_conversations_contact_fk FOREIGN KEY(crm_contact_id) REFERENCES crm_contacts(id) ON DELETE SET NULL, ADD CONSTRAINT whatsapp_conversations_assignee_fk FOREIGN KEY(assigned_user_id) REFERENCES users(id) ON DELETE SET NULL, ADD CONSTRAINT whatsapp_conversations_status_check CHECK(status IN('open','closed'))"); }
    public function down(PDO $db): void { $db->exec('ALTER TABLE whatsapp_conversations DROP FOREIGN KEY whatsapp_conversations_contact_fk, DROP FOREIGN KEY whatsapp_conversations_assignee_fk, DROP CHECK whatsapp_conversations_status_check, DROP COLUMN crm_contact_id, DROP COLUMN assigned_user_id, DROP COLUMN status, DROP COLUMN unread_count'); }
};
