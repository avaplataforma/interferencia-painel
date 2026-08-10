<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260809_995000_create_institutional_sites';
    }

    public function up(PDO $database): void
    {
        $database->exec("CREATE TABLE organization_sites (organization_id BIGINT UNSIGNED NOT NULL, is_enabled TINYINT(1) NOT NULL DEFAULT 0, template_key VARCHAR(40) NOT NULL DEFAULT 'modern', allow_catalog TINYINT(1) NOT NULL DEFAULT 1, allow_store TINYINT(1) NOT NULL DEFAULT 0, allow_custom_pages TINYINT(1) NOT NULL DEFAULT 0, max_banners TINYINT UNSIGNED NOT NULL DEFAULT 3, max_pages TINYINT UNSIGNED NOT NULL DEFAULT 5, max_featured_courses TINYINT UNSIGNED NOT NULL DEFAULT 6, selected_mode VARCHAR(20) NOT NULL DEFAULT 'catalog', publication_status VARCHAR(20) NOT NULL DEFAULT 'draft', site_title VARCHAR(160) NULL, hero_title VARCHAR(190) NULL, hero_text VARCHAR(700) NULL, about_title VARCHAR(160) NULL, about_text TEXT NULL, contact_email VARCHAR(190) NULL, contact_phone VARCHAR(30) NULL, whatsapp VARCHAR(30) NULL, instagram_url VARCHAR(500) NULL, facebook_url VARCHAR(500) NULL, seo_title VARCHAR(190) NULL, seo_description VARCHAR(320) NULL, published_at DATETIME NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (organization_id), CONSTRAINT organization_sites_org_fk FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE, CONSTRAINT organization_sites_template_check CHECK (template_key IN ('modern','classic','minimal')), CONSTRAINT organization_sites_mode_check CHECK (selected_mode IN ('catalog','store')), CONSTRAINT organization_sites_status_check CHECK (publication_status IN ('draft','published','maintenance'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $database->exec("CREATE TABLE organization_site_products (organization_id BIGINT UNSIGNED NOT NULL, finance_product_id BIGINT UNSIGNED NOT NULL, sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (organization_id, finance_product_id), KEY organization_site_products_order_idx (organization_id, sort_order), CONSTRAINT organization_site_products_org_fk FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE, CONSTRAINT organization_site_products_product_fk FOREIGN KEY (finance_product_id) REFERENCES finance_products (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS organization_site_products');
        $database->exec('DROP TABLE IF EXISTS organization_sites');
    }
};
