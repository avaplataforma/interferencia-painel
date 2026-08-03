<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260803_380000_add_whatsapp_delivery_diagnostics';
    }

    public function up(PDO $db): void
    {
        $db->exec('ALTER TABLE whatsapp_messages ADD error_message VARCHAR(500) NULL AFTER status, ADD attempted_at DATETIME NULL AFTER message_at');
    }

    public function down(PDO $db): void
    {
        $db->exec('ALTER TABLE whatsapp_messages DROP COLUMN attempted_at, DROP COLUMN error_message');
    }
};
