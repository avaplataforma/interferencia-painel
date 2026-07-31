<?php
declare(strict_types=1);
use Interferencia\Kernel\Database\Migration;
return new class implements Migration{
 public function id():string{return '20260731_290000_create_crm_follow_ups';}
 public function up(PDO $db):void{$db->exec("CREATE TABLE crm_follow_ups (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,contact_id BIGINT UNSIGNED NOT NULL,responsible_user_id BIGINT UNSIGNED NOT NULL,action VARCHAR(160) NOT NULL,scheduled_at DATETIME NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'pending',notes TEXT NOT NULL,completed_at DATETIME NULL,created_by BIGINT UNSIGNED NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY crm_follow_ups_contact_index(contact_id),KEY crm_follow_ups_schedule_index(status,scheduled_at),CONSTRAINT crm_follow_ups_contact_fk FOREIGN KEY(contact_id) REFERENCES crm_contacts(id) ON DELETE CASCADE,CONSTRAINT crm_follow_ups_responsible_fk FOREIGN KEY(responsible_user_id) REFERENCES users(id),CONSTRAINT crm_follow_ups_creator_fk FOREIGN KEY(created_by) REFERENCES users(id),CONSTRAINT crm_follow_ups_status_check CHECK(status IN('pending','completed','cancelled'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}
 public function down(PDO $db):void{$db->exec('DROP TABLE IF EXISTS crm_follow_ups');}
};
