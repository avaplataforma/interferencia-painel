<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260818_999997_enrollment_cancellation'; }

    public function up(PDO $db): void
    {
        $db->exec("ALTER TABLE student_enrollments ADD cancelled_at DATETIME NULL AFTER status,ADD cancelled_reason VARCHAR(500) NULL AFTER cancelled_at,ADD cancelled_by BIGINT UNSIGNED NULL AFTER cancelled_reason,ADD KEY student_enrollments_cancelled_idx(cancelled_at),ADD CONSTRAINT student_enrollments_cancelled_fk FOREIGN KEY(cancelled_by) REFERENCES users(id) ON DELETE SET NULL");
        $db->exec("INSERT IGNORE INTO permissions(code,name) VALUES('enrollments.cancel','Cancelar matrículas')");
        $db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code IN('super_admin','headquarters','manager') AND p.code='enrollments.cancel'");
    }

    public function down(PDO $db): void
    {
        $db->exec("DELETE rp FROM role_permissions rp INNER JOIN permissions p ON p.id=rp.permission_id WHERE p.code='enrollments.cancel'");
        $db->exec("DELETE FROM permissions WHERE code='enrollments.cancel'");
        $db->exec('ALTER TABLE student_enrollments DROP FOREIGN KEY student_enrollments_cancelled_fk,DROP INDEX student_enrollments_cancelled_idx,DROP COLUMN cancelled_by,DROP COLUMN cancelled_reason,DROP COLUMN cancelled_at');
    }
};
