<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260810_999100_add_site_navigation_footer';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE organization_sites
            ADD COLUMN youtube_url VARCHAR(500) NULL AFTER facebook_url,
            ADD COLUMN linkedin_url VARCHAR(500) NULL AFTER youtube_url,
            ADD COLUMN tiktok_url VARCHAR(500) NULL AFTER linkedin_url,
            ADD COLUMN classroom_url VARCHAR(500) NULL AFTER tiktok_url,
            ADD COLUMN classroom_label VARCHAR(80) NOT NULL DEFAULT 'Sala de Aula' AFTER classroom_url,
            ADD COLUMN webmail_url VARCHAR(500) NULL AFTER classroom_label,
            ADD COLUMN social_bar_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER webmail_url,
            ADD COLUMN site_search_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER social_bar_enabled,
            ADD COLUMN footer_show_legal_data TINYINT(1) NOT NULL DEFAULT 1 AFTER footer_text");
    }

    public function down(PDO $database): void
    {
        $database->exec("ALTER TABLE organization_sites
            DROP COLUMN footer_show_legal_data,
            DROP COLUMN site_search_enabled,
            DROP COLUMN social_bar_enabled,
            DROP COLUMN webmail_url,
            DROP COLUMN classroom_label,
            DROP COLUMN classroom_url,
            DROP COLUMN tiktok_url,
            DROP COLUMN linkedin_url,
            DROP COLUMN youtube_url");
    }
};
