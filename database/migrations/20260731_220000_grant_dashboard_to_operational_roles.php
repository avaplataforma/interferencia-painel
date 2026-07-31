<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string { return '20260731_220000_grant_dashboard_to_operational_roles'; }
    public function up(PDO $database): void
    {
        $database->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code IN ('manager', 'agent') AND p.code = 'dashboard.view'");
    }
    public function down(PDO $database): void
    {
        $database->exec("DELETE rp FROM role_permissions rp INNER JOIN roles r ON r.id = rp.role_id INNER JOIN permissions p ON p.id = rp.permission_id WHERE r.code IN ('manager', 'agent') AND p.code = 'dashboard.view'");
    }
};
