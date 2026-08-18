<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260818_999990_satisfaction_per_enrollment'; }

    public function up(PDO $db): void
    {
        $db->exec("ALTER TABLE portal_satisfaction_responses ADD enrollment_id BIGINT UNSIGNED NULL AFTER finance_customer_id,ADD KEY portal_satisfaction_enrollment_idx(enrollment_id,created_at),ADD CONSTRAINT portal_satisfaction_enrollment_fk FOREIGN KEY(enrollment_id) REFERENCES student_enrollments(id) ON DELETE SET NULL");
    }

    public function down(PDO $db): void
    {
        $db->exec('ALTER TABLE portal_satisfaction_responses DROP FOREIGN KEY portal_satisfaction_enrollment_fk,DROP INDEX portal_satisfaction_enrollment_idx,DROP COLUMN enrollment_id');
    }
};
