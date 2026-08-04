<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string{return '20260804_480000_create_finance_products_and_checkouts';}
    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE finance_products(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,unit_id BIGINT UNSIGNED NULL,name VARCHAR(160) NOT NULL,description VARCHAR(500) NULL,value DECIMAL(12,2) NOT NULL,billing_types VARCHAR(80) NOT NULL DEFAULT 'PIX',minutes_to_expire SMALLINT UNSIGNED NOT NULL DEFAULT 1440,is_active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY finance_products_unit_active_idx(unit_id,is_active),CONSTRAINT finance_products_unit_fk FOREIGN KEY(unit_id) REFERENCES units(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE finance_checkouts(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,asaas_checkout_id VARCHAR(80) NULL,finance_customer_id BIGINT UNSIGNED NOT NULL,finance_product_id BIGINT UNSIGNED NOT NULL,unit_id BIGINT UNSIGNED NOT NULL,status VARCHAR(30) NOT NULL DEFAULT 'CREATING',link VARCHAR(1000) NULL,external_reference VARCHAR(200) NOT NULL,expires_at DATETIME NULL,error_message VARCHAR(500) NULL,created_by BIGINT UNSIGNED NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY finance_checkouts_asaas_unique(asaas_checkout_id),UNIQUE KEY finance_checkouts_external_unique(external_reference),KEY finance_checkouts_customer_idx(finance_customer_id),KEY finance_checkouts_unit_status_idx(unit_id,status),CONSTRAINT finance_checkouts_customer_fk FOREIGN KEY(finance_customer_id) REFERENCES finance_customers(id),CONSTRAINT finance_checkouts_product_fk FOREIGN KEY(finance_product_id) REFERENCES finance_products(id),CONSTRAINT finance_checkouts_unit_fk FOREIGN KEY(unit_id) REFERENCES units(id),CONSTRAINT finance_checkouts_user_fk FOREIGN KEY(created_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(PDO $db): void{$db->exec('DROP TABLE IF EXISTS finance_checkouts');$db->exec('DROP TABLE IF EXISTS finance_products');}
};
