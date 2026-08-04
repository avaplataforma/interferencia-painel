<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260804_520000_rename_system_roles';
    }

    public function up(PDO $db): void
    {
        $db->exec("UPDATE roles SET name='Admin System' WHERE code='super_admin'");
        $db->exec("UPDATE roles SET name='Sede' WHERE code='headquarters'");
    }

    public function down(PDO $db): void
    {
        $db->exec("UPDATE roles SET name='Administrador global' WHERE code='super_admin'");
        $db->exec("UPDATE roles SET name='SEDE' WHERE code='headquarters'");
    }
};
