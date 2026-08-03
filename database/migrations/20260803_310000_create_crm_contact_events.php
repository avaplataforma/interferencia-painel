<?php
declare(strict_types=1);
use Interferencia\Kernel\Database\Migration;
return new class implements Migration {
    public function id(): string { return '20260803_310000_create_crm_contact_events'; }
    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE crm_contact_events (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,contact_id BIGINT UNSIGNED NOT NULL,actor_user_id BIGINT UNSIGNED NULL,event_type VARCHAR(40) NOT NULL,description VARCHAR(500) NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY crm_contact_events_contact_index(contact_id,created_at),CONSTRAINT crm_contact_events_contact_fk FOREIGN KEY(contact_id) REFERENCES crm_contacts(id) ON DELETE CASCADE,CONSTRAINT crm_contact_events_actor_fk FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("INSERT INTO crm_contact_events(contact_id,actor_user_id,event_type,description,created_at) SELECT id,created_by,'created','Contato incorporado ao histórico do CRM.',created_at FROM crm_contacts");
    }
    public function down(PDO $db): void { $db->exec('DROP TABLE IF EXISTS crm_contact_events'); }
};
