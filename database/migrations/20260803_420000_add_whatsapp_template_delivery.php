<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string{return '20260803_420000_add_whatsapp_template_delivery';}
    public function up(PDO $db): void{$db->exec('ALTER TABLE whatsapp_messages ADD template_id BIGINT UNSIGNED NULL AFTER body, ADD template_name VARCHAR(512) NULL AFTER template_id, ADD template_language VARCHAR(20) NULL AFTER template_name, ADD template_variables JSON NULL AFTER template_language, ADD INDEX idx_whatsapp_messages_template(template_id)');}
    public function down(PDO $db): void{$db->exec('ALTER TABLE whatsapp_messages DROP INDEX idx_whatsapp_messages_template, DROP COLUMN template_variables, DROP COLUMN template_language, DROP COLUMN template_name, DROP COLUMN template_id');}
};
