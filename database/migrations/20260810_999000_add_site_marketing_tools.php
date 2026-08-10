<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260810_999000_add_site_marketing_tools';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE organization_sites
            ADD COLUMN footer_text VARCHAR(500) NULL AFTER facebook_url,
            ADD COLUMN whatsapp_button_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER footer_text,
            ADD COLUMN whatsapp_button_label VARCHAR(80) NULL AFTER whatsapp_button_enabled,
            ADD COLUMN whatsapp_button_message VARCHAR(500) NULL AFTER whatsapp_button_label,
            ADD COLUMN scholarship_form_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER whatsapp_button_message,
            ADD COLUMN scholarship_display_mode VARCHAR(20) NOT NULL DEFAULT 'floating' AFTER scholarship_form_enabled,
            ADD COLUMN scholarship_popup_delay_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 15 AFTER scholarship_display_mode,
            ADD COLUMN scholarship_popup_repeat_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24 AFTER scholarship_popup_delay_seconds,
            ADD COLUMN scholarship_title VARCHAR(160) NOT NULL DEFAULT 'GANHE BOLSAS DE ESTUDOS' AFTER scholarship_popup_repeat_hours,
            ADD COLUMN scholarship_subtitle VARCHAR(250) NULL DEFAULT 'Preencha e participe!' AFTER scholarship_title,
            ADD COLUMN scholarship_button_label VARCHAR(80) NULL DEFAULT 'Ganhe uma bolsa' AFTER scholarship_subtitle,
            ADD CONSTRAINT organization_sites_scholarship_mode_check CHECK (scholarship_display_mode IN ('floating','popup','both'))");
    }

    public function down(PDO $database): void
    {
        $database->exec("ALTER TABLE organization_sites
            DROP CONSTRAINT organization_sites_scholarship_mode_check,
            DROP COLUMN scholarship_button_label,
            DROP COLUMN scholarship_subtitle,
            DROP COLUMN scholarship_title,
            DROP COLUMN scholarship_popup_repeat_hours,
            DROP COLUMN scholarship_popup_delay_seconds,
            DROP COLUMN scholarship_display_mode,
            DROP COLUMN scholarship_form_enabled,
            DROP COLUMN whatsapp_button_message,
            DROP COLUMN whatsapp_button_label,
            DROP COLUMN whatsapp_button_enabled,
            DROP COLUMN footer_text");
    }
};
