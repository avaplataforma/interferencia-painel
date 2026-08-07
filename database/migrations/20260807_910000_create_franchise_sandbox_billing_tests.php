<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string { return '20260807_910000_create_franchise_sandbox_billing_tests'; }

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE franchise_sandbox_billing_tests(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,organization_id BIGINT UNSIGNED NOT NULL,contract_id BIGINT UNSIGNED NOT NULL,asaas_customer_id VARCHAR(80) NULL,asaas_payment_id VARCHAR(80) NULL,external_reference VARCHAR(150) NOT NULL,billing_type VARCHAR(20) NOT NULL DEFAULT 'PIX',gross_value DECIMAL(12,2) NOT NULL,central_percentage DECIMAL(7,4) NOT NULL,central_value DECIMAL(12,2) NOT NULL,franchise_percentage DECIMAL(7,4) NOT NULL,franchise_value DECIMAL(12,2) NOT NULL,split_mode VARCHAR(20) NOT NULL DEFAULT 'simulated',status VARCHAR(40) NOT NULL DEFAULT 'issuing',invoice_url VARCHAR(500) NULL,error_message VARCHAR(500) NULL,created_by BIGINT UNSIGNED NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,last_synced_at TIMESTAMP NULL,PRIMARY KEY(id),UNIQUE KEY franchise_sandbox_tests_reference_unique(external_reference),UNIQUE KEY franchise_sandbox_tests_payment_unique(asaas_payment_id),KEY franchise_sandbox_tests_contract_idx(contract_id,created_at),CONSTRAINT franchise_sandbox_tests_organization_fk FOREIGN KEY(organization_id) REFERENCES organizations(id),CONSTRAINT franchise_sandbox_tests_contract_fk FOREIGN KEY(contract_id) REFERENCES franchise_contracts(id),CONSTRAINT franchise_sandbox_tests_user_fk FOREIGN KEY(created_by) REFERENCES platform_users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $db): void { $db->exec('DROP TABLE franchise_sandbox_billing_tests'); }
};
