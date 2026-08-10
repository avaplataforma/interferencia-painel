<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260810_999500_create_site_commercial_funnel';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE organization_site_events
            ADD COLUMN contact_id BIGINT UNSIGNED NULL AFTER entity_id,
            ADD COLUMN order_id BIGINT UNSIGNED NULL AFTER contact_id,
            ADD COLUMN unit_id BIGINT UNSIGNED NULL AFTER order_id,
            ADD COLUMN landing_page VARCHAR(500) NULL AFTER unit_id,
            ADD COLUMN utm_content VARCHAR(190) NULL AFTER utm_campaign,
            ADD COLUMN utm_term VARCHAR(190) NULL AFTER utm_content,
            ADD KEY organization_site_events_session_index(organization_id,session_hash,occurred_at),
            ADD KEY organization_site_events_contact_index(organization_id,contact_id,occurred_at),
            ADD CONSTRAINT organization_site_events_contact_fk FOREIGN KEY(contact_id) REFERENCES crm_contacts(id) ON DELETE SET NULL,
            ADD CONSTRAINT organization_site_events_order_fk FOREIGN KEY(order_id) REFERENCES organization_site_orders(id) ON DELETE SET NULL,
            ADD CONSTRAINT organization_site_events_unit_fk FOREIGN KEY(unit_id) REFERENCES units(id) ON DELETE SET NULL");

        $database->exec("ALTER TABLE organization_site_orders
            ADD COLUMN session_hash CHAR(64) NULL AFTER external_reference,
            ADD COLUMN landing_page VARCHAR(500) NULL AFTER session_hash,
            ADD COLUMN utm_source VARCHAR(190) NULL AFTER landing_page,
            ADD COLUMN utm_medium VARCHAR(190) NULL AFTER utm_source,
            ADD COLUMN utm_campaign VARCHAR(190) NULL AFTER utm_medium,
            ADD COLUMN utm_content VARCHAR(190) NULL AFTER utm_campaign,
            ADD COLUMN utm_term VARCHAR(190) NULL AFTER utm_content,
            ADD KEY organization_site_orders_attribution_index(organization_id,session_hash,created_at)");
    }

    public function down(PDO $database): void
    {
        $database->exec('ALTER TABLE organization_site_orders DROP KEY organization_site_orders_attribution_index,DROP COLUMN utm_term,DROP COLUMN utm_content,DROP COLUMN utm_campaign,DROP COLUMN utm_medium,DROP COLUMN utm_source,DROP COLUMN landing_page,DROP COLUMN session_hash');
        $database->exec('ALTER TABLE organization_site_events DROP FOREIGN KEY organization_site_events_unit_fk,DROP FOREIGN KEY organization_site_events_order_fk,DROP FOREIGN KEY organization_site_events_contact_fk,DROP KEY organization_site_events_contact_index,DROP KEY organization_site_events_session_index,DROP COLUMN utm_term,DROP COLUMN utm_content,DROP COLUMN landing_page,DROP COLUMN unit_id,DROP COLUMN order_id,DROP COLUMN contact_id');
    }
};
