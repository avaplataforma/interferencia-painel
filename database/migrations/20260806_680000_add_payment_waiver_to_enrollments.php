<?php declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string{return '20260806_680000_add_payment_waiver_to_enrollments';}
    public function up(PDO $db): void{$db->exec('ALTER TABLE student_enrollments ADD payment_waiver_reason VARCHAR(500) NULL AFTER final_value, ADD payment_waived_at DATETIME NULL AFTER payment_waiver_reason, ADD payment_waived_by BIGINT UNSIGNED NULL AFTER payment_waived_at, ADD KEY student_enrollments_waiver_user_idx(payment_waived_by), ADD CONSTRAINT student_enrollments_waiver_user_fk FOREIGN KEY(payment_waived_by) REFERENCES users(id) ON DELETE SET NULL');}
    public function down(PDO $db): void{$db->exec('ALTER TABLE student_enrollments DROP FOREIGN KEY student_enrollments_waiver_user_fk, DROP KEY student_enrollments_waiver_user_idx, DROP COLUMN payment_waived_by, DROP COLUMN payment_waived_at, DROP COLUMN payment_waiver_reason');}
};
