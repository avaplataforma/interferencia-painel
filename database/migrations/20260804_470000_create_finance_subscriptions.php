<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string{return '20260804_470000_create_finance_subscriptions';}
    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE finance_subscriptions(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,asaas_subscription_id VARCHAR(80) NOT NULL,finance_customer_id BIGINT UNSIGNED NULL,unit_id BIGINT UNSIGNED NULL,billing_type VARCHAR(30) NOT NULL,status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',value DECIMAL(12,2) NOT NULL,cycle VARCHAR(30) NOT NULL,next_due_date DATE NOT NULL,end_date DATE NULL,max_payments INT UNSIGNED NULL,description VARCHAR(500) NOT NULL,external_reference VARCHAR(255) NULL,is_deleted TINYINT(1) NOT NULL DEFAULT 0,synced_at DATETIME NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY finance_subscriptions_asaas_unique(asaas_subscription_id),KEY finance_subscriptions_unit_status_idx(unit_id,status),KEY finance_subscriptions_customer_idx(finance_customer_id),CONSTRAINT finance_subscriptions_customer_fk FOREIGN KEY(finance_customer_id) REFERENCES finance_customers(id) ON DELETE SET NULL,CONSTRAINT finance_subscriptions_unit_fk FOREIGN KEY(unit_id) REFERENCES units(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("INSERT IGNORE INTO finance_sync_cursors(resource) VALUES('subscriptions')");
    }
    public function down(PDO $db): void{$db->exec("DELETE FROM finance_sync_cursors WHERE resource='subscriptions'");$db->exec('DROP TABLE IF EXISTS finance_subscriptions');}
};
