<?php
declare(strict_types=1);
use Interferencia\Kernel\Database\Migration;
return new class implements Migration{
 public function id():string{return '20260731_280000_add_crm_status_management';}
 public function up(PDO $db):void{$db->exec("INSERT IGNORE INTO permissions(code,name) VALUES('crm.statuses.manage','Gerenciar status do CRM')");$db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code IN('super_admin','manager') AND p.code='crm.statuses.manage'");}
 public function down(PDO $db):void{$db->exec("DELETE FROM permissions WHERE code='crm.statuses.manage'");}
};
