<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260804_500000_add_headquarters_role_and_customer_delete'; }

    public function up(PDO $db): void
    {
        $db->exec("INSERT IGNORE INTO permissions(code,name) VALUES('finance.customers.delete','Excluir clientes financeiros')");
        $db->exec("INSERT IGNORE INTO roles(code,name) VALUES('headquarters','Sede')");
        $db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='super_admin' AND p.code='finance.customers.delete'");
        $db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='headquarters' AND p.code IN('dashboard.view','units.access_all','crm.contacts.view','crm.contacts.manage','whatsapp.inbox.view','whatsapp.conversations.assign','finance.view','finance.manage','finance.legacy_view')");
    }

    public function down(PDO $db): void
    {
        $db->exec("DELETE rp FROM role_permissions rp INNER JOIN roles r ON r.id=rp.role_id WHERE r.code='headquarters'");
        $db->exec("DELETE FROM roles WHERE code='headquarters'");
        $db->exec("DELETE rp FROM role_permissions rp INNER JOIN permissions p ON p.id=rp.permission_id WHERE p.code='finance.customers.delete'");
        $db->exec("DELETE FROM permissions WHERE code='finance.customers.delete'");
    }
};
