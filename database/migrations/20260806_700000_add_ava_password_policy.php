<?php declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id():string{return '20260806_700000_add_ava_password_policy';}
    public function up(PDO$db):void{$db->exec("ALTER TABLE moodle_integrations ADD COLUMN initial_password_mode ENUM('automatic','cpf5') NOT NULL DEFAULT 'automatic' AFTER is_active");}
    public function down(PDO$db):void{$db->exec('ALTER TABLE moodle_integrations DROP COLUMN initial_password_mode');}
};
