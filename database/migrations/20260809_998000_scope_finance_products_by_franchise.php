<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260809_998000_scope_finance_products_by_franchise';
    }

    public function up(PDO $database): void
    {
        $database->exec("CREATE TABLE organization_finance_products (
            organization_id BIGINT UNSIGNED NOT NULL,
            finance_product_id BIGINT UNSIGNED NOT NULL,
            source VARCHAR(20) NOT NULL,
            is_owner TINYINT(1) NOT NULL DEFAULT 1,
            is_visible TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (organization_id, finance_product_id),
            KEY organization_finance_products_visibility_idx (organization_id, is_visible),
            CONSTRAINT organization_finance_products_org_fk FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
            CONSTRAINT organization_finance_products_product_fk FOREIGN KEY (finance_product_id) REFERENCES finance_products (id) ON DELETE CASCADE,
            CONSTRAINT organization_finance_products_source_check CHECK (source IN ('ava','manual'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $database->exec("INSERT IGNORE INTO organization_finance_products (organization_id, finance_product_id, source, is_owner, is_visible)
            SELECT m.organization_id, p.id, 'ava', 1, 1
            FROM finance_products p
            INNER JOIN moodle_courses m ON m.id=p.moodle_course_id
            WHERE m.organization_id IS NOT NULL");

        $database->exec("INSERT IGNORE INTO organization_finance_products (organization_id, finance_product_id, source, is_owner, is_visible)
            SELECT u.organization_id, p.id, IF(p.moodle_course_id IS NULL,'manual','ava'), 1, 1
            FROM finance_products p
            INNER JOIN units u ON u.id=p.unit_id
            WHERE u.organization_id IS NOT NULL");

        $database->exec("INSERT IGNORE INTO organization_finance_products (organization_id, finance_product_id, source, is_owner, is_visible)
            SELECT o.id, p.id, IF(p.moodle_course_id IS NULL,'manual','ava'), 1, 1
            FROM finance_products p
            INNER JOIN organizations o ON o.code='interferencia'
            LEFT JOIN organization_finance_products scoped ON scoped.finance_product_id=p.id
            WHERE scoped.finance_product_id IS NULL");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE organization_finance_products');
    }
};
