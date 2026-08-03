<?php
declare(strict_types=1);
use Interferencia\Kernel\Database\Migration;
return new class implements Migration {
    public function id(): string { return '20260803_350000_add_whatsapp_simulation_flag'; }
    public function up(PDO $db): void { $db->exec('ALTER TABLE whatsapp_conversations ADD is_test TINYINT(1) NOT NULL DEFAULT 0 AFTER unread_count'); }
    public function down(PDO $db): void { $db->exec('ALTER TABLE whatsapp_conversations DROP COLUMN is_test'); }
};
