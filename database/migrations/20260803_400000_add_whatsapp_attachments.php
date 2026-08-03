<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string{return '20260803_400000_add_whatsapp_attachments';}
    public function up(PDO $db): void{$db->exec('ALTER TABLE whatsapp_messages ADD media_id VARCHAR(255) NULL AFTER body, ADD mime_type VARCHAR(120) NULL AFTER media_id, ADD file_name VARCHAR(255) NULL AFTER mime_type, ADD file_size BIGINT UNSIGNED NULL AFTER file_name, ADD storage_path VARCHAR(500) NULL AFTER file_size');}
    public function down(PDO $db): void{$db->exec('ALTER TABLE whatsapp_messages DROP COLUMN storage_path, DROP COLUMN file_size, DROP COLUMN file_name, DROP COLUMN mime_type, DROP COLUMN media_id');}
};
