<?php declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id():string{return '20260806_730000_track_moodle_progress_changes';}
    public function up(PDO$db):void{$db->exec('ALTER TABLE moodle_enrolments ADD progress_changed_at DATETIME NULL AFTER progress_synced_at');}
    public function down(PDO$db):void{$db->exec('ALTER TABLE moodle_enrolments DROP COLUMN progress_changed_at');}
};
