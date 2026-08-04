<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id():string{return '20260804_450000_create_finance_sync_cursors';}
    public function up(PDO$db):void{$db->exec("CREATE TABLE finance_sync_cursors(resource VARCHAR(40) NOT NULL,next_offset INT UNSIGNED NOT NULL DEFAULT 0,is_complete TINYINT(1) NOT NULL DEFAULT 0,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(resource)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");$db->exec("INSERT INTO finance_sync_cursors(resource) VALUES('customers'),('payments')");}
    public function down(PDO$db):void{$db->exec('DROP TABLE IF EXISTS finance_sync_cursors');}
};
