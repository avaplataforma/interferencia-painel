<?php
declare(strict_types=1);
use Interferencia\Kernel\Database\Migration;
return new class implements Migration {
    public function id(): string { return '20260803_360000_add_whatsapp_assignment_permission'; }
    public function up(PDO $db): void
    {
        $db->exec("INSERT IGNORE INTO permissions(code,name) VALUES('whatsapp.conversations.assign','Transferir conversas do WhatsApp')");
        $db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code IN('super_admin','manager') AND p.code='whatsapp.conversations.assign'");
    }
    public function down(PDO $db): void { $db->exec("DELETE FROM permissions WHERE code='whatsapp.conversations.assign'"); }
};
