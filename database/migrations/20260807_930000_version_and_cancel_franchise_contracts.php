<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260807_930000_version_and_cancel_franchise_contracts'; }

    public function up(PDO $db): void
    {
        $db->exec("ALTER TABLE franchise_contract_templates ADD parent_template_id BIGINT UNSIGNED NULL AFTER id,ADD KEY franchise_contract_templates_family_idx(parent_template_id,version),ADD CONSTRAINT franchise_contract_templates_parent_fk FOREIGN KEY(parent_template_id) REFERENCES franchise_contract_templates(id) ON DELETE SET NULL");
        $db->exec("ALTER TABLE franchise_contracts ADD cancelled_reason VARCHAR(500) NULL AFTER status,ADD cancelled_at DATETIME NULL AFTER cancelled_reason,ADD cancelled_by BIGINT UNSIGNED NULL AFTER cancelled_at,ADD KEY franchise_contracts_cancelled_idx(cancelled_at),ADD CONSTRAINT franchise_contracts_cancelled_by_fk FOREIGN KEY(cancelled_by) REFERENCES platform_users(id) ON DELETE SET NULL");
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE franchise_contracts DROP FOREIGN KEY franchise_contracts_cancelled_by_fk,DROP INDEX franchise_contracts_cancelled_idx,DROP COLUMN cancelled_by,DROP COLUMN cancelled_at,DROP COLUMN cancelled_reason");
        $db->exec("ALTER TABLE franchise_contract_templates DROP FOREIGN KEY franchise_contract_templates_parent_fk,DROP INDEX franchise_contract_templates_family_idx,DROP COLUMN parent_template_id");
    }
};
