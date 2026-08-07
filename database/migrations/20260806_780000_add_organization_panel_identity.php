<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string { return '20260806_780000_add_organization_panel_identity'; }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE organizations ADD panel_slug VARCHAR(100) NULL AFTER code, ADD login_title VARCHAR(160) NULL AFTER favicon_path, ADD login_welcome_text VARCHAR(500) NULL AFTER login_title, ADD support_email VARCHAR(190) NULL AFTER login_welcome_text, ADD support_phone VARCHAR(30) NULL AFTER support_email, ADD UNIQUE KEY organizations_panel_slug_unique(panel_slug)");
        $database->exec("UPDATE organizations SET panel_slug='interferencia' WHERE code='interferencia'");
        $database->exec("INSERT IGNORE INTO organization_domains(organization_id,host,purpose,is_primary,status,verified_at) SELECT id,'mundointer.com.br','panel',0,'active',NOW() FROM organizations WHERE code='interferencia'");
    }

    public function down(PDO $database): void
    {
        $database->exec('ALTER TABLE organizations DROP INDEX organizations_panel_slug_unique, DROP COLUMN support_phone, DROP COLUMN support_email, DROP COLUMN login_welcome_text, DROP COLUMN login_title, DROP COLUMN panel_slug');
    }
};
