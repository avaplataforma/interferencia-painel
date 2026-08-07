<?php
declare(strict_types=1);
use Interferencia\Kernel\Database\Migration;
return new class implements Migration{
 public function id():string{return'20260807_870000_add_franchise_finance_and_role_matrix';}
 public function up(PDO$db):void{
  $db->exec("ALTER TABLE organizations ADD asaas_wallet_id VARCHAR(80) NULL AFTER support_phone,ADD asaas_wallet_status VARCHAR(24) NOT NULL DEFAULT 'not_configured' AFTER asaas_wallet_id,ADD asaas_wallet_validated_at DATETIME NULL AFTER asaas_wallet_status,ADD split_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER asaas_wallet_validated_at,ADD asaas_finance_notes VARCHAR(500) NULL AFTER split_enabled");
  $db->exec("INSERT IGNORE INTO platform_permissions(code,name) VALUES('franchises.view','Consultar franquias'),('franchises.manage','Cadastrar e editar franquias'),('contracts.manage','Gerenciar contratos das franquias'),('billing.manage','Gerenciar cobranças e split da rede'),('branding.manage','Personalizar o ADM Central')");
  $db->exec("INSERT IGNORE INTO platform_roles(code,name) VALUES('platform_general_manager','Gerente')");
  $db->exec("UPDATE platform_roles SET name=CASE code WHEN 'super_admin' THEN 'Admin' WHEN 'platform_manager' THEN 'Gestor' WHEN 'platform_general_manager' THEN 'Gerente' WHEN 'platform_agent' THEN 'Atendente' ELSE name END");
  $db->exec("DELETE rp FROM platform_role_permissions rp INNER JOIN platform_roles r ON r.id=rp.role_id WHERE r.code IN('super_admin','platform_manager','platform_general_manager','platform_agent')");
  $db->exec("INSERT IGNORE INTO platform_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM platform_roles r CROSS JOIN platform_permissions p WHERE r.code='super_admin'");
  $db->exec("INSERT IGNORE INTO platform_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM platform_roles r CROSS JOIN platform_permissions p WHERE r.code='platform_manager' AND p.code IN('dashboard.view','users.manage','franchises.view','franchises.manage','contracts.manage','billing.manage','tickets.manage','branding.manage')");
  $db->exec("INSERT IGNORE INTO platform_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM platform_roles r CROSS JOIN platform_permissions p WHERE r.code='platform_general_manager' AND p.code IN('dashboard.view','franchises.view','franchises.manage','contracts.manage','billing.manage','tickets.manage')");
  $db->exec("INSERT IGNORE INTO platform_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM platform_roles r CROSS JOIN platform_permissions p WHERE r.code='platform_agent' AND p.code IN('dashboard.view','franchises.view','tickets.manage')");
  $db->exec("UPDATE roles SET name=CASE code WHEN 'super_admin' THEN 'Admin' WHEN 'headquarters' THEN 'Gestor' WHEN 'manager' THEN 'Gerente' WHEN 'agent' THEN 'Atendente' ELSE name END WHERE code IN('super_admin','headquarters','manager','agent')");
  $db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='headquarters' AND p.code NOT IN('users.manage','roles.manage','finance.settings.manage','moodle.settings.manage')");
 }
 public function down(PDO$db):void{
  $db->exec("DELETE rp FROM platform_role_permissions rp INNER JOIN platform_roles r ON r.id=rp.role_id WHERE r.code='platform_general_manager'");$db->exec("DELETE FROM platform_roles WHERE code='platform_general_manager'");
  $db->exec("DELETE FROM platform_permissions WHERE code IN('franchises.view','franchises.manage','contracts.manage','billing.manage','branding.manage')");
  $db->exec("UPDATE platform_roles SET name=CASE code WHEN 'super_admin' THEN 'Admin Central' WHEN 'platform_manager' THEN 'Gestor Central' WHEN 'platform_agent' THEN 'Colaborador Central' ELSE name END");
  $db->exec("UPDATE roles SET name=CASE code WHEN 'super_admin' THEN 'Admin System' WHEN 'headquarters' THEN 'Sede' WHEN 'manager' THEN 'Gestor' WHEN 'agent' THEN 'Atendente' ELSE name END WHERE code IN('super_admin','headquarters','manager','agent')");
  $db->exec('ALTER TABLE organizations DROP COLUMN asaas_finance_notes,DROP COLUMN split_enabled,DROP COLUMN asaas_wallet_validated_at,DROP COLUMN asaas_wallet_status,DROP COLUMN asaas_wallet_id');
 }
};
