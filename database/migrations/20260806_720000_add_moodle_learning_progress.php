<?php declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id():string{return '20260806_720000_add_moodle_learning_progress';}
    public function up(PDO$db):void
    {
        $db->exec("ALTER TABLE moodle_enrolments ADD completion_percent DECIMAL(5,2) NULL AFTER is_active,ADD completion_status VARCHAR(30) NOT NULL DEFAULT 'unknown' AFTER completion_percent,ADD progress_synced_at DATETIME NULL AFTER completion_status,ADD progress_error VARCHAR(500) NULL AFTER progress_synced_at");
    }
    public function down(PDO$db):void{$db->exec('ALTER TABLE moodle_enrolments DROP COLUMN progress_error,DROP COLUMN progress_synced_at,DROP COLUMN completion_status,DROP COLUMN completion_percent');}
};
