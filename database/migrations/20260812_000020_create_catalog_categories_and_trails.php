<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260812_000020_create_catalog_categories_and_trails';
    }

    public function up(PDO $database): void
    {
        $database->exec("CREATE TABLE catalog_categories(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            parent_id BIGINT UNSIGNED NULL,
            name VARCHAR(160) NOT NULL,
            code VARCHAR(120) NOT NULL UNIQUE,
            description VARCHAR(500) NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY catalog_categories_parent_order_idx(parent_id,sort_order,name),
            CONSTRAINT catalog_categories_parent_fk FOREIGN KEY(parent_id) REFERENCES catalog_categories(id) ON DELETE RESTRICT,
            CONSTRAINT catalog_categories_created_by_fk FOREIGN KEY(created_by) REFERENCES platform_users(id) ON DELETE SET NULL,
            CONSTRAINT catalog_categories_updated_by_fk FOREIGN KEY(updated_by) REFERENCES platform_users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("CREATE TABLE catalog_trails(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(190) NOT NULL,
            slug VARCHAR(160) NOT NULL UNIQUE,
            short_description VARCHAR(500) NULL,
            description LONGTEXT NULL,
            default_price DECIMAL(12,2) NULL,
            max_installments TINYINT UNSIGNED NOT NULL DEFAULT 1,
            cover_url VARCHAR(1000) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY catalog_trails_category_status_idx(category_id,is_active,name),
            CONSTRAINT catalog_trails_category_fk FOREIGN KEY(category_id) REFERENCES catalog_categories(id) ON DELETE RESTRICT,
            CONSTRAINT catalog_trails_created_by_fk FOREIGN KEY(created_by) REFERENCES platform_users(id) ON DELETE SET NULL,
            CONSTRAINT catalog_trails_updated_by_fk FOREIGN KEY(updated_by) REFERENCES platform_users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("CREATE TABLE catalog_trail_items(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            catalog_trail_id BIGINT UNSIGNED NOT NULL,
            item_type VARCHAR(40) NOT NULL,
            item_id BIGINT UNSIGNED NOT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY catalog_trail_item_unique(catalog_trail_id,item_type,item_id),
            KEY catalog_trail_items_type_idx(item_type,item_id),
            CONSTRAINT catalog_trail_items_trail_fk FOREIGN KEY(catalog_trail_id) REFERENCES catalog_trails(id) ON DELETE CASCADE,
            CONSTRAINT catalog_trail_items_type_check CHECK(item_type IN ('finance_product','provider_course','provider_content'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("CREATE TABLE organization_catalog_trail_access(
            organization_id BIGINT UNSIGNED NOT NULL,
            catalog_trail_id BIGINT UNSIGNED NOT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            is_visible TINYINT(1) NOT NULL DEFAULT 1,
            price_override DECIMAL(12,2) NULL,
            max_installments_override TINYINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(organization_id,catalog_trail_id),
            KEY organization_catalog_trail_visibility_idx(organization_id,is_enabled,is_visible),
            CONSTRAINT organization_catalog_trail_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            CONSTRAINT organization_catalog_trail_trail_fk FOREIGN KEY(catalog_trail_id) REFERENCES catalog_trails(id) ON DELETE CASCADE,
            CONSTRAINT organization_catalog_trail_user_fk FOREIGN KEY(updated_by) REFERENCES platform_users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("INSERT INTO catalog_categories(name,code,sort_order,is_active) VALUES
            ('Profissionalizantes','profissionalizantes',10,1),
            ('Técnico','tecnico',20,1),
            ('EJA','eja',30,1),
            ('Recursos','recursos',40,1)");
        $database->exec("INSERT INTO catalog_categories(parent_id,name,code,sort_order,is_active)
            SELECT id,'Administração','administracao',10,1 FROM catalog_categories WHERE code='profissionalizantes'");
        $database->exec("INSERT INTO catalog_categories(parent_id,name,code,sort_order,is_active)
            SELECT id,'Informática','informatica',20,1 FROM catalog_categories WHERE code='profissionalizantes'");
        $database->exec("INSERT INTO catalog_categories(parent_id,name,code,sort_order,is_active)
            SELECT id,'Reforço escolar','reforco-escolar',30,1 FROM catalog_categories WHERE code='profissionalizantes'");
        $database->exec("INSERT INTO catalog_categories(parent_id,name,code,sort_order,is_active)
            SELECT id,'Direito','direito',40,1 FROM catalog_categories WHERE code='profissionalizantes'");
        $database->exec("INSERT INTO catalog_categories(parent_id,name,code,sort_order,is_active)
            SELECT id,'Idiomas','idiomas',50,1 FROM catalog_categories WHERE code='profissionalizantes'");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS organization_catalog_trail_access');
        $database->exec('DROP TABLE IF EXISTS catalog_trail_items');
        $database->exec('DROP TABLE IF EXISTS catalog_trails');
        $database->exec('DROP TABLE IF EXISTS catalog_categories');
    }
};
