<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string{return '20260804_490000_add_finance_customer_address';}
    public function up(PDO $db): void{$db->exec('ALTER TABLE finance_customers ADD address VARCHAR(255) NULL AFTER mobile_phone,ADD address_number VARCHAR(40) NULL AFTER address,ADD complement VARCHAR(120) NULL AFTER address_number,ADD province VARCHAR(120) NULL AFTER complement,ADD postal_code VARCHAR(12) NULL AFTER province');}
    public function down(PDO $db): void{$db->exec('ALTER TABLE finance_customers DROP COLUMN postal_code,DROP COLUMN province,DROP COLUMN complement,DROP COLUMN address_number,DROP COLUMN address');}
};
