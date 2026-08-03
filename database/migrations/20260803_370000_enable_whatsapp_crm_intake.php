<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string { return '20260803_370000_enable_whatsapp_crm_intake'; }

    public function up(PDO $db): void
    {
        $db->exec("ALTER TABLE crm_contacts DROP CONSTRAINT crm_contacts_source_check, ADD CONSTRAINT crm_contacts_source_check CHECK (registration_source IN ('internal','external_form','whatsapp'))");
    }

    public function down(PDO $db): void
    {
        $count = (int) $db->query("SELECT COUNT(*) FROM crm_contacts WHERE registration_source='whatsapp'")->fetchColumn();
        if ($count > 0) throw new RuntimeException('Existem contatos originados do WhatsApp; converta-os antes de reverter.');
        $db->exec("ALTER TABLE crm_contacts DROP CONSTRAINT crm_contacts_source_check, ADD CONSTRAINT crm_contacts_source_check CHECK (registration_source IN ('internal','external_form'))");
    }
};
