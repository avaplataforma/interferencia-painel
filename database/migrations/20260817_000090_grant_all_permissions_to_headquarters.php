<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string { return '20260817_000090_grant_all_permissions_to_headquarters'; }
    public function up(PDO $database): void
    {
        $database->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'headquarters'");
    }
    public function down(PDO $database): void
    {
        $database->exec("DELETE rp FROM role_permissions rp INNER JOIN roles r ON r.id = rp.role_id WHERE r.code = 'headquarters'");
    }
};
