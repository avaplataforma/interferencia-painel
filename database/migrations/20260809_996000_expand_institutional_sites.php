<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260809_996000_expand_institutional_sites';
    }

    public function up(PDO $database): void
    {
        $database->exec("CREATE TABLE organization_site_banners (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, organization_id BIGINT UNSIGNED NOT NULL, title VARCHAR(190) NOT NULL, subtitle VARCHAR(500) NULL, cta_label VARCHAR(80) NULL, cta_url VARCHAR(500) NULL, image_path VARCHAR(1000) NOT NULL, image_mime VARCHAR(80) NOT NULL, image_name VARCHAR(255) NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY organization_site_banners_order_idx (organization_id,is_active,sort_order,id), CONSTRAINT organization_site_banners_org_fk FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $database->exec("CREATE TABLE organization_site_pages (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, organization_id BIGINT UNSIGNED NOT NULL, title VARCHAR(190) NOT NULL, slug VARCHAR(120) NOT NULL, summary VARCHAR(500) NULL, content MEDIUMTEXT NOT NULL, publication_status VARCHAR(20) NOT NULL DEFAULT 'draft', show_in_menu TINYINT(1) NOT NULL DEFAULT 1, sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY organization_site_pages_slug_unique (organization_id,slug), KEY organization_site_pages_status_idx (organization_id,publication_status,sort_order), CONSTRAINT organization_site_pages_org_fk FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE, CONSTRAINT organization_site_pages_status_check CHECK (publication_status IN ('draft','published'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $database->exec("CREATE TABLE organization_site_orders (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, organization_id BIGINT UNSIGNED NOT NULL, unit_id BIGINT UNSIGNED NOT NULL, crm_contact_id BIGINT UNSIGNED NULL, finance_product_id BIGINT UNSIGNED NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'CREATING', asaas_checkout_id VARCHAR(80) NULL, external_reference VARCHAR(200) NOT NULL, link VARCHAR(1000) NULL, error_message VARCHAR(500) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY organization_site_orders_asaas_unique (asaas_checkout_id), UNIQUE KEY organization_site_orders_external_unique (external_reference), KEY organization_site_orders_org_status_idx (organization_id,status,created_at), KEY organization_site_orders_contact_idx (crm_contact_id), CONSTRAINT organization_site_orders_org_fk FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE, CONSTRAINT organization_site_orders_unit_fk FOREIGN KEY (unit_id) REFERENCES units (id), CONSTRAINT organization_site_orders_contact_fk FOREIGN KEY (crm_contact_id) REFERENCES crm_contacts (id) ON DELETE SET NULL, CONSTRAINT organization_site_orders_product_fk FOREIGN KEY (finance_product_id) REFERENCES finance_products (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS organization_site_orders');
        $database->exec('DROP TABLE IF EXISTS organization_site_pages');
        $database->exec('DROP TABLE IF EXISTS organization_site_banners');
    }
};
