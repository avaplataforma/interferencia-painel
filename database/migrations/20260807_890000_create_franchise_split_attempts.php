<?php
declare(strict_types=1);
use Interferencia\Kernel\Database\Migration;
return new class implements Migration{
 public function id():string{return'20260807_890000_create_franchise_split_attempts';}
 public function up(PDO$db):void{
  $db->exec("CREATE TABLE franchise_split_attempts(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,organization_id BIGINT UNSIGNED NOT NULL,contract_id BIGINT UNSIGNED NOT NULL,external_reference VARCHAR(120) NOT NULL,gross_value DECIMAL(12,2) NOT NULL,central_percentage DECIMAL(7,4) NOT NULL,franchise_percentage DECIMAL(7,4) NOT NULL,wallet_id VARCHAR(80) NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'prepared',asaas_payment_id VARCHAR(80) NULL,error_message VARCHAR(500) NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY franchise_split_attempts_org_idx(organization_id,created_at),KEY franchise_split_attempts_contract_idx(contract_id,status),CONSTRAINT franchise_split_attempts_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE RESTRICT,CONSTRAINT franchise_split_attempts_contract_fk FOREIGN KEY(contract_id) REFERENCES franchise_contracts(id) ON DELETE RESTRICT,CONSTRAINT franchise_split_attempts_status_check CHECK(status IN('prepared','submitted','failed'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 }
 public function down(PDO$db):void{$db->exec('DROP TABLE franchise_split_attempts');}
};
