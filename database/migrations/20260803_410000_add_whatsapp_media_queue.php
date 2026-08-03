<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string{return '20260803_410000_add_whatsapp_media_queue';}
    public function up(PDO $db): void{$db->exec("ALTER TABLE whatsapp_messages ADD media_sync_status VARCHAR(20) NULL AFTER storage_path, ADD media_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER media_sync_status, ADD media_next_attempt_at DATETIME NULL AFTER media_attempts, ADD media_last_attempt_at DATETIME NULL AFTER media_next_attempt_at, ADD media_error VARCHAR(500) NULL AFTER media_last_attempt_at, ADD INDEX idx_whatsapp_media_queue(media_sync_status,media_next_attempt_at)");$db->exec("UPDATE whatsapp_messages SET media_sync_status=CASE WHEN storage_path IS NOT NULL THEN 'synced' WHEN media_id IS NOT NULL THEN 'pending' ELSE NULL END,media_next_attempt_at=CASE WHEN media_id IS NOT NULL AND storage_path IS NULL THEN CURRENT_TIMESTAMP ELSE NULL END");}
    public function down(PDO $db): void{$db->exec('ALTER TABLE whatsapp_messages DROP INDEX idx_whatsapp_media_queue, DROP COLUMN media_error, DROP COLUMN media_last_attempt_at, DROP COLUMN media_next_attempt_at, DROP COLUMN media_attempts, DROP COLUMN media_sync_status');}
};
