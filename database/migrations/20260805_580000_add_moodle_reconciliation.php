<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string { return '20260805_580000_add_moodle_reconciliation'; }

    public function up(PDO $db): void
    {
        $db->exec("ALTER TABLE moodle_users ADD reconciliation_status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER finance_customer_id, ADD match_method VARCHAR(20) NULL AFTER reconciliation_status, ADD matched_at DATETIME NULL AFTER match_method, ADD reviewed_by BIGINT UNSIGNED NULL AFTER matched_at, ADD KEY moodle_users_reconciliation_idx(reconciliation_status), ADD CONSTRAINT moodle_users_reviewer_fk FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL");
    }

    public function down(PDO $db): void
    {
        $db->exec('ALTER TABLE moodle_users DROP FOREIGN KEY moodle_users_reviewer_fk, DROP KEY moodle_users_reconciliation_idx, DROP COLUMN reviewed_by, DROP COLUMN matched_at, DROP COLUMN match_method, DROP COLUMN reconciliation_status');
    }
};
