<?php
declare(strict_types=1);
use Interferencia\Kernel\Database\Migration;
return new class implements Migration{
 public function id():string{return'20260807_880000_create_franchise_billing_control';}
 public function up(PDO$db):void{
  $db->exec("ALTER TABLE franchise_contracts ADD commercial_flow_status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER sales_fee_percentage,ADD commercial_flow_activated_at DATETIME NULL AFTER commercial_flow_status,ADD split_wallet_snapshot VARCHAR(80) NULL AFTER commercial_flow_activated_at,ADD CONSTRAINT franchise_contracts_flow_check CHECK(commercial_flow_status IN('pending','active','blocked','inactive'))");
  $db->exec("CREATE TABLE franchise_billing_events(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,contract_id BIGINT UNSIGNED NOT NULL,event_type VARCHAR(60) NOT NULL,description VARCHAR(500) NOT NULL,platform_user_id BIGINT UNSIGNED NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY franchise_billing_events_contract_idx(contract_id,created_at),CONSTRAINT franchise_billing_events_contract_fk FOREIGN KEY(contract_id) REFERENCES franchise_contracts(id) ON DELETE CASCADE,CONSTRAINT franchise_billing_events_user_fk FOREIGN KEY(platform_user_id) REFERENCES platform_users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $db->exec("INSERT INTO franchise_billing_events(contract_id,event_type,description) SELECT id,'contract_imported','Contrato incorporado ao controle financeiro central.' FROM franchise_contracts");
 }
 public function down(PDO$db):void{$db->exec('DROP TABLE franchise_billing_events');$db->exec('ALTER TABLE franchise_contracts DROP CHECK franchise_contracts_flow_check,DROP COLUMN split_wallet_snapshot,DROP COLUMN commercial_flow_activated_at,DROP COLUMN commercial_flow_status');}
};
