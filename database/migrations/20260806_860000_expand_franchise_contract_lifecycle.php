<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260806_860000_expand_franchise_contract_lifecycle'; }

    public function up(PDO $db): void
    {
        $db->exec("ALTER TABLE franchise_contracts DROP FOREIGN KEY franchise_contracts_user_fk");
        $db->exec("UPDATE franchise_contracts c LEFT JOIN platform_users u ON u.id=c.created_by SET c.created_by=NULL WHERE c.created_by IS NOT NULL AND u.id IS NULL");
        $db->exec("ALTER TABLE franchise_contracts ADD parent_contract_id BIGINT UNSIGNED NULL AFTER id,ADD contract_number INT UNSIGNED NOT NULL DEFAULT 1 AFTER parent_contract_id,ADD contract_type VARCHAR(20) NOT NULL DEFAULT 'new' AFTER contract_number,ADD valid_from DATE NULL AFTER contract_type,ADD valid_until DATE NULL AFTER valid_from,ADD commercial_model VARCHAR(30) NULL AFTER valid_until,ADD monthly_fixed_amount DECIMAL(12,2) NULL AFTER commercial_model,ADD sales_fee_percentage DECIMAL(7,4) NULL AFTER monthly_fixed_amount,ADD asaas_payment_link_id VARCHAR(80) NULL AFTER asaas_payment_id,ADD asaas_payment_link_url VARCHAR(500) NULL AFTER asaas_payment_link_id,ADD recurring_link_issued_at DATETIME NULL AFTER asaas_payment_link_url,ADD KEY franchise_contracts_history_idx(franchise_application_id,contract_number),ADD CONSTRAINT franchise_contracts_parent_fk FOREIGN KEY(parent_contract_id) REFERENCES franchise_contracts(id) ON DELETE SET NULL,ADD CONSTRAINT franchise_contracts_platform_user_fk FOREIGN KEY(created_by) REFERENCES platform_users(id) ON DELETE SET NULL,ADD CONSTRAINT franchise_contracts_type_check CHECK(contract_type IN('new','renewal')),ADD CONSTRAINT franchise_contracts_commercial_model_check CHECK(commercial_model IS NULL OR commercial_model IN('fixed_plus_percentage','split_only'))");
        $db->exec("UPDATE franchise_contracts SET commercial_model=CASE WHEN billing_required=1 THEN 'fixed_plus_percentage' ELSE 'split_only' END,monthly_fixed_amount=billing_amount,sales_fee_percentage=0");
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE franchise_contracts DROP FOREIGN KEY franchise_contracts_platform_user_fk,DROP FOREIGN KEY franchise_contracts_parent_fk,DROP CHECK franchise_contracts_type_check,DROP CHECK franchise_contracts_commercial_model_check,DROP INDEX franchise_contracts_history_idx,DROP COLUMN recurring_link_issued_at,DROP COLUMN asaas_payment_link_url,DROP COLUMN asaas_payment_link_id,DROP COLUMN sales_fee_percentage,DROP COLUMN monthly_fixed_amount,DROP COLUMN commercial_model,DROP COLUMN valid_until,DROP COLUMN valid_from,DROP COLUMN contract_type,DROP COLUMN contract_number,DROP COLUMN parent_contract_id,ADD CONSTRAINT franchise_contracts_user_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL");
    }
};
