<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260804_510000_add_finance_payment_issue_permission'; }

    public function up(PDO $db): void
    {
        $db->exec("INSERT IGNORE INTO permissions(code,name) VALUES('finance.payments.issue','Emitir cobranças financeiras'),('finance.payments.modify','Alterar e cancelar cobranças financeiras')");
        $db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code IN('super_admin','headquarters','manager','agent') AND p.code IN('finance.view','finance.payments.issue')");
        $db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code IN('super_admin','headquarters') AND p.code='finance.payments.modify'");
    }

    public function down(PDO $db): void
    {
        $db->exec("DELETE rp FROM role_permissions rp INNER JOIN permissions p ON p.id=rp.permission_id WHERE p.code IN('finance.payments.issue','finance.payments.modify')");
        $db->exec("DELETE FROM permissions WHERE code IN('finance.payments.issue','finance.payments.modify')");
    }
};
