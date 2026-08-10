<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260810_999400_expand_site_catalog_experience';
    }

    public function up(PDO $database): void
    {
        $database->exec("CREATE TABLE organization_site_product_details (
            organization_id BIGINT UNSIGNED NOT NULL,
            finance_product_id BIGINT UNSIGNED NOT NULL,
            category VARCHAR(120) NULL,
            modality VARCHAR(80) NULL,
            workload_hours INT UNSIGNED NULL,
            target_audience TEXT NULL,
            curriculum LONGTEXT NULL,
            requirements TEXT NULL,
            certificate_text TEXT NULL,
            faq_text LONGTEXT NULL,
            rating_average DECIMAL(3,2) NULL,
            rating_count INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(organization_id,finance_product_id),
            KEY organization_site_product_details_category_index(organization_id,category),
            CONSTRAINT organization_site_product_details_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            CONSTRAINT organization_site_product_details_product_fk FOREIGN KEY(finance_product_id) REFERENCES finance_products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("CREATE TABLE organization_site_blocks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NOT NULL,
            block_type VARCHAR(30) NOT NULL,
            title VARCHAR(190) NOT NULL,
            subtitle VARCHAR(500) NULL,
            body LONGTEXT NULL,
            media_url VARCHAR(1000) NULL,
            button_label VARCHAR(80) NULL,
            button_url VARCHAR(1000) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            KEY organization_site_blocks_order_index(organization_id,is_active,sort_order),
            CONSTRAINT organization_site_blocks_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            CONSTRAINT organization_site_blocks_type_check CHECK(block_type IN ('text','video','testimonial','faq','partners','stats','cta','poles'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS organization_site_blocks');
        $database->exec('DROP TABLE IF EXISTS organization_site_product_details');
    }
};
