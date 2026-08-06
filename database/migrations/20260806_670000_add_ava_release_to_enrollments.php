<?php declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string{return '20260806_670000_add_ava_release_to_enrollments';}
    public function up(PDO $db): void{$db->exec('ALTER TABLE student_enrollments ADD ava_user_id BIGINT UNSIGNED NULL AFTER moodle_enrolment_status, ADD ava_released_at DATETIME NULL AFTER ava_user_id, ADD ava_released_by BIGINT UNSIGNED NULL AFTER ava_released_at, ADD ava_last_error VARCHAR(500) NULL AFTER ava_released_by, ADD KEY student_enrollments_ava_user_idx(ava_user_id), ADD CONSTRAINT student_enrollments_ava_releaser_fk FOREIGN KEY(ava_released_by) REFERENCES users(id) ON DELETE SET NULL');}
    public function down(PDO $db): void{$db->exec('ALTER TABLE student_enrollments DROP FOREIGN KEY student_enrollments_ava_releaser_fk, DROP KEY student_enrollments_ava_user_idx, DROP COLUMN ava_last_error, DROP COLUMN ava_released_by, DROP COLUMN ava_released_at, DROP COLUMN ava_user_id');}
};
