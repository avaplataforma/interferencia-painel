<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string
    {
        return '20260731_240000_add_units_management_permission';
    }

    public function up(PDO $database): void
    {
        $database->exec("INSERT IGNORE INTO permissions (code, name) VALUES ('units.manage', 'Gerenciar unidades')");
        $database->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'super_admin' AND p.code = 'units.manage'");
    }

    public function down(PDO $database): void
    {
        $database->exec("DELETE FROM permissions WHERE code = 'units.manage'");
    }
};
