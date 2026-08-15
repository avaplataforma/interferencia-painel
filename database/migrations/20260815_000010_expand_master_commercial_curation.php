<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string
    {
        return '20260815_000010_expand_master_commercial_curation';
    }

    public function up(PDO $db): void
    {
        $db->exec("ALTER TABLE provider_commercial_catalog_items
            ADD COLUMN duplicate_key VARCHAR(190) NULL AFTER title,
            ADD COLUMN source_published_at DATETIME NULL AFTER detail_url,
            ADD COLUMN source_updated_at DATETIME NULL AFTER source_published_at,
            ADD COLUMN commercial_name VARCHAR(500) NULL AFTER complementary_count,
            ADD COLUMN commercial_summary TEXT NULL AFTER commercial_name,
            ADD COLUMN commercial_description MEDIUMTEXT NULL AFTER commercial_summary,
            ADD COLUMN commercial_category VARCHAR(255) NULL AFTER commercial_description,
            ADD COLUMN default_price DECIMAL(12,2) NULL AFTER commercial_category,
            ADD COLUMN max_installments TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER default_price,
            ADD COLUMN review_status VARCHAR(30) NOT NULL DEFAULT 'imported' AFTER max_installments,
            ADD COLUMN is_globally_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER review_status,
            ADD COLUMN reviewed_by BIGINT UNSIGNED NULL AFTER is_globally_enabled,
            ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by,
            ADD INDEX provider_commercial_duplicate_idx(provider_id,duplicate_key,is_available),
            ADD INDEX provider_commercial_review_idx(provider_id,review_status,is_globally_enabled),
            ADD CONSTRAINT provider_commercial_reviewed_by_fk FOREIGN KEY(reviewed_by) REFERENCES platform_users(id) ON DELETE SET NULL");
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE provider_commercial_catalog_items
            DROP FOREIGN KEY provider_commercial_reviewed_by_fk,
            DROP INDEX provider_commercial_review_idx,
            DROP INDEX provider_commercial_duplicate_idx,
            DROP COLUMN reviewed_at,
            DROP COLUMN reviewed_by,
            DROP COLUMN is_globally_enabled,
            DROP COLUMN review_status,
            DROP COLUMN max_installments,
            DROP COLUMN default_price,
            DROP COLUMN commercial_category,
            DROP COLUMN commercial_description,
            DROP COLUMN commercial_summary,
            DROP COLUMN commercial_name,
            DROP COLUMN source_updated_at,
            DROP COLUMN source_published_at,
            DROP COLUMN duplicate_key");
    }
};
