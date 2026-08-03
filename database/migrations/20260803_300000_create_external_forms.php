<?php
declare(strict_types=1);
use Interferencia\Kernel\Database\Migration;
return new class implements Migration{
 public function id():string{return '20260803_300000_create_external_forms';}
 public function up(PDO $db):void{$db->exec("CREATE TABLE external_forms (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,name VARCHAR(120) NOT NULL,slug VARCHAR(100) NOT NULL,allowed_domain VARCHAR(190) NOT NULL,tag_id BIGINT UNSIGNED NOT NULL,initial_status_id BIGINT UNSIGNED NOT NULL,title VARCHAR(160) NOT NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,submission_count BIGINT UNSIGNED NOT NULL DEFAULT 0,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY external_forms_slug_unique(slug),CONSTRAINT external_forms_tag_fk FOREIGN KEY(tag_id) REFERENCES crm_tags(id),CONSTRAINT external_forms_status_fk FOREIGN KEY(initial_status_id) REFERENCES crm_statuses(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");$db->exec("INSERT IGNORE INTO permissions(code,name) VALUES('external_forms.manage','Gerenciar formulários externos')");$db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code IN('super_admin','manager') AND p.code='external_forms.manage'");}
 public function down(PDO $db):void{$db->exec('DROP TABLE IF EXISTS external_forms');$db->exec("DELETE FROM permissions WHERE code='external_forms.manage'");}
};
