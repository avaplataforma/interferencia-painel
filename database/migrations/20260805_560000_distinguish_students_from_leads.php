<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260805_560000_distinguish_students_from_leads'; }

    public function up(PDO $db): void
    {
        $db->exec("ALTER TABLE finance_customers ADD student_status VARCHAR(20) NOT NULL DEFAULT 'inactive' AFTER is_legacy, ADD KEY finance_customers_student_idx(unit_id,student_status,is_deleted)");
        $db->exec("UPDATE finance_customers SET student_status='active' WHERE unit_id IS NOT NULL AND is_deleted=0");
        $db->exec('ALTER TABLE tickets ADD finance_customer_id BIGINT UNSIGNED NULL AFTER crm_contact_id, ADD KEY tickets_finance_customer_idx(finance_customer_id), ADD CONSTRAINT tickets_finance_customer_fk FOREIGN KEY(finance_customer_id) REFERENCES finance_customers(id) ON DELETE SET NULL');
        $db->exec('UPDATE tickets t INNER JOIN finance_customers f ON f.crm_contact_id=t.crm_contact_id AND f.unit_id=t.unit_id SET t.finance_customer_id=f.id WHERE t.finance_customer_id IS NULL');
    }

    public function down(PDO $db): void
    {
        $db->exec('ALTER TABLE tickets DROP FOREIGN KEY tickets_finance_customer_fk, DROP KEY tickets_finance_customer_idx, DROP COLUMN finance_customer_id');
        $db->exec('ALTER TABLE finance_customers DROP KEY finance_customers_student_idx, DROP COLUMN student_status');
    }
};
