<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260804_540000_link_tickets_to_crm_contacts'; }

    public function up(PDO $db): void
    {
        $db->exec('ALTER TABLE tickets ADD crm_contact_id BIGINT UNSIGNED NULL AFTER unit_id, ADD KEY tickets_contact_idx(crm_contact_id), ADD CONSTRAINT tickets_contact_fk FOREIGN KEY(crm_contact_id) REFERENCES crm_contacts(id) ON DELETE SET NULL');
    }

    public function down(PDO $db): void
    {
        $db->exec('ALTER TABLE tickets DROP FOREIGN KEY tickets_contact_fk, DROP KEY tickets_contact_idx, DROP COLUMN crm_contact_id');
    }
};
