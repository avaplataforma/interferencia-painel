<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260806_840000_add_franchise_contract_billing'; }

    public function up(PDO $db): void
    {
        $db->exec("ALTER TABLE franchise_contracts ADD asaas_customer_id VARCHAR(80) NULL AFTER billing_description,ADD asaas_payment_id VARCHAR(80) NULL AFTER asaas_customer_id,ADD asaas_payment_status VARCHAR(40) NULL AFTER asaas_payment_id,ADD asaas_invoice_url VARCHAR(500) NULL AFTER asaas_payment_status,ADD billing_type VARCHAR(20) NULL AFTER asaas_invoice_url,ADD billing_due_date DATE NULL AFTER billing_type,ADD billing_issue_state VARCHAR(20) NOT NULL DEFAULT 'not_issued' AFTER billing_due_date,ADD billing_issued_at DATETIME NULL AFTER billing_issue_state,ADD billing_paid_at DATETIME NULL AFTER billing_issued_at,ADD billing_last_synced_at DATETIME NULL AFTER billing_paid_at,ADD billing_error VARCHAR(500) NULL AFTER billing_last_synced_at,ADD UNIQUE KEY franchise_contracts_asaas_payment_unique(asaas_payment_id),ADD KEY franchise_contracts_billing_state_idx(billing_issue_state,asaas_payment_status)");
    }

    public function down(PDO $db): void
    {
        $db->exec('ALTER TABLE franchise_contracts DROP INDEX franchise_contracts_asaas_payment_unique,DROP INDEX franchise_contracts_billing_state_idx,DROP COLUMN billing_error,DROP COLUMN billing_last_synced_at,DROP COLUMN billing_paid_at,DROP COLUMN billing_issued_at,DROP COLUMN billing_issue_state,DROP COLUMN billing_due_date,DROP COLUMN billing_type,DROP COLUMN asaas_invoice_url,DROP COLUMN asaas_payment_status,DROP COLUMN asaas_payment_id,DROP COLUMN asaas_customer_id');
    }
};
