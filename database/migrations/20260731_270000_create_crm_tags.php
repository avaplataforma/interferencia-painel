<?php
declare(strict_types=1);
use Interferencia\Kernel\Database\Migration;
return new class implements Migration{
 public function id():string{return '20260731_270000_create_crm_tags';}
 public function up(PDO $db):void{
  $db->exec('CREATE TABLE crm_tags (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,name VARCHAR(80) NOT NULL,color VARCHAR(7) NOT NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY crm_tags_name_unique(name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
  $db->exec('CREATE TABLE crm_contact_tags (contact_id BIGINT UNSIGNED NOT NULL,tag_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(contact_id,tag_id),CONSTRAINT crm_contact_tags_contact_fk FOREIGN KEY(contact_id) REFERENCES crm_contacts(id) ON DELETE CASCADE,CONSTRAINT crm_contact_tags_tag_fk FOREIGN KEY(tag_id) REFERENCES crm_tags(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
  $db->exec("INSERT IGNORE INTO permissions(code,name) VALUES('crm.tags.manage','Gerenciar etiquetas do CRM')");
  $db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code IN('super_admin','manager') AND p.code='crm.tags.manage'");
 }
 public function down(PDO $db):void{$db->exec('DROP TABLE IF EXISTS crm_contact_tags');$db->exec('DROP TABLE IF EXISTS crm_tags');$db->exec("DELETE FROM permissions WHERE code='crm.tags.manage'");}
};
