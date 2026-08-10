<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260809_997000_automate_site_order_fulfillment';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE organization_sites ADD checkout_fulfillment_mode VARCHAR(20) NOT NULL DEFAULT 'manual_review' AFTER allow_store, ADD CONSTRAINT organization_sites_fulfillment_check CHECK (checkout_fulfillment_mode IN ('manual_review','automatic'))");
        $database->exec("ALTER TABLE organization_site_orders ADD finance_customer_id BIGINT UNSIGNED NULL AFTER crm_contact_id, ADD student_enrollment_id BIGINT UNSIGNED NULL AFTER finance_customer_id, ADD fulfillment_status VARCHAR(30) NOT NULL DEFAULT 'awaiting_payment' AFTER status, ADD fulfillment_error VARCHAR(500) NULL AFTER error_message, ADD paid_at DATETIME NULL AFTER fulfillment_error, ADD UNIQUE KEY organization_site_orders_enrollment_unique (student_enrollment_id), ADD KEY organization_site_orders_customer_idx (finance_customer_id), ADD KEY organization_site_orders_fulfillment_idx (organization_id,fulfillment_status,created_at), ADD CONSTRAINT organization_site_orders_customer_fk FOREIGN KEY (finance_customer_id) REFERENCES finance_customers (id) ON DELETE SET NULL, ADD CONSTRAINT organization_site_orders_enrollment_fk FOREIGN KEY (student_enrollment_id) REFERENCES student_enrollments (id) ON DELETE SET NULL, ADD CONSTRAINT organization_site_orders_fulfillment_check CHECK (fulfillment_status IN ('awaiting_payment','payment_confirmed','manual_review','releasing','released','failed'))");
    }

    public function down(PDO $database): void
    {
        $database->exec('ALTER TABLE organization_site_orders DROP FOREIGN KEY organization_site_orders_enrollment_fk, DROP FOREIGN KEY organization_site_orders_customer_fk, DROP CONSTRAINT organization_site_orders_fulfillment_check, DROP INDEX organization_site_orders_fulfillment_idx, DROP INDEX organization_site_orders_customer_idx, DROP INDEX organization_site_orders_enrollment_unique, DROP COLUMN paid_at, DROP COLUMN fulfillment_error, DROP COLUMN fulfillment_status, DROP COLUMN student_enrollment_id, DROP COLUMN finance_customer_id');
        $database->exec('ALTER TABLE organization_sites DROP CONSTRAINT organization_sites_fulfillment_check, DROP COLUMN checkout_fulfillment_mode');
    }
};
