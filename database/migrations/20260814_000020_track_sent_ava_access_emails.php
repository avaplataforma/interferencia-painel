<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string
    {
        return '20260814_000020_track_sent_ava_access_emails';
    }

    public function up(PDO $db): void
    {
        $db->exec("ALTER TABLE ava_access_communications MODIFY status ENUM('opened','sent','failed') NOT NULL DEFAULT 'opened'");
    }

    public function down(PDO $db): void
    {
        $db->exec("UPDATE ava_access_communications SET status='opened' WHERE status='sent'");
        $db->exec("ALTER TABLE ava_access_communications MODIFY status ENUM('opened','failed') NOT NULL DEFAULT 'opened'");
    }
};
