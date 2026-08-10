<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260809_994000_separate_franchise_commercial_processing';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE franchise_contracts
            ADD commercial_rule VARCHAR(40) NULL AFTER commercial_model,
            ADD financial_processing VARCHAR(40) NULL AFTER commercial_rule,
            ADD franchise_fee_percentage DECIMAL(7,4) NULL AFTER sales_fee_percentage,
            ADD fixed_fee_per_enrollment DECIMAL(12,2) NULL AFTER franchise_fee_percentage,
            ADD closing_day TINYINT UNSIGNED NULL AFTER fixed_fee_per_enrollment,
            ADD settlement_day TINYINT UNSIGNED NULL AFTER closing_day,
            ADD KEY franchise_contracts_commercial_rule_idx(commercial_rule,financial_processing),
            ADD CONSTRAINT franchise_contracts_commercial_rule_check CHECK(commercial_rule IS NULL OR commercial_rule IN('percentage_commission','fixed_monthly','hybrid','per_enrollment')),
            ADD CONSTRAINT franchise_contracts_financial_processing_check CHECK(financial_processing IS NULL OR financial_processing IN('central_monthly_settlement','central_automatic_split','franchise_asaas','external_gateway')),
            ADD CONSTRAINT franchise_contracts_closing_day_check CHECK(closing_day IS NULL OR closing_day BETWEEN 1 AND 31),
            ADD CONSTRAINT franchise_contracts_settlement_day_check CHECK(settlement_day IS NULL OR settlement_day BETWEEN 1 AND 31)");

        $database->exec("UPDATE franchise_contracts SET
            commercial_rule=CASE
                WHEN commercial_model='fixed_plus_percentage' AND COALESCE(sales_fee_percentage,0)>0 THEN 'hybrid'
                WHEN commercial_model='fixed_plus_percentage' THEN 'fixed_monthly'
                ELSE 'percentage_commission'
            END,
            financial_processing=CASE
                WHEN COALESCE(sales_fee_percentage,0)>0 THEN 'central_automatic_split'
                ELSE 'central_monthly_settlement'
            END,
            franchise_fee_percentage=CASE WHEN COALESCE(sales_fee_percentage,0)>0 THEN 100-sales_fee_percentage ELSE 0 END,
            closing_day=CASE WHEN COALESCE(sales_fee_percentage,0)<=0 THEN 30 ELSE NULL END,
            settlement_day=CASE WHEN COALESCE(sales_fee_percentage,0)<=0 THEN 10 ELSE NULL END");
    }

    public function down(PDO $database): void
    {
        $database->exec('ALTER TABLE franchise_contracts
            DROP CHECK franchise_contracts_settlement_day_check,
            DROP CHECK franchise_contracts_closing_day_check,
            DROP CHECK franchise_contracts_financial_processing_check,
            DROP CHECK franchise_contracts_commercial_rule_check,
            DROP INDEX franchise_contracts_commercial_rule_idx,
            DROP COLUMN settlement_day,
            DROP COLUMN closing_day,
            DROP COLUMN fixed_fee_per_enrollment,
            DROP COLUMN franchise_fee_percentage,
            DROP COLUMN financial_processing,
            DROP COLUMN commercial_rule');
    }
};
