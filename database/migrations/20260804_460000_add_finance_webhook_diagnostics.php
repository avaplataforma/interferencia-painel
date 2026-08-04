<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id():string{return '20260804_460000_add_finance_webhook_diagnostics';}
    public function up(PDO$db):void
    {
        $db->exec("ALTER TABLE finance_webhook_events ADD COLUMN delivery_count INT UNSIGNED NOT NULL DEFAULT 1 AFTER resource_id,ADD COLUMN last_received_at DATETIME NULL AFTER received_at,ADD COLUMN processing_status VARCHAR(20) NOT NULL DEFAULT 'received' AFTER last_received_at");
        $db->exec("UPDATE finance_webhook_events SET last_received_at=received_at,processing_status=CASE WHEN error_message IS NOT NULL THEN 'failed' WHEN processed_at IS NOT NULL THEN 'processed' ELSE 'received' END");
    }
    public function down(PDO$db):void{$db->exec('ALTER TABLE finance_webhook_events DROP COLUMN processing_status,DROP COLUMN last_received_at,DROP COLUMN delivery_count');}
};
