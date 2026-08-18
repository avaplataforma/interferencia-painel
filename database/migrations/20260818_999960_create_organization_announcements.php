<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260818_999960_create_organization_announcements'; }

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE organization_announcements (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,organization_id BIGINT UNSIGNED NOT NULL,title VARCHAR(190) NOT NULL,body TEXT NOT NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,created_by BIGINT UNSIGNED NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY organization_announcements_org_idx(organization_id,is_active,created_at),CONSTRAINT organization_announcements_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,CONSTRAINT organization_announcements_user_fk FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS organization_announcements');
    }
};
