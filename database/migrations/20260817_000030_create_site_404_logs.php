<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;
use PDO;

return new class implements Migration {
    public function id(): string
    {
        return '20260817_000030_create_site_404_logs';
    }

    public function up(PDO $database): void
    {
        $database->exec("CREATE TABLE site_404_logs(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            host VARCHAR(190) NOT NULL,
            path VARCHAR(500) NOT NULL,
            referer VARCHAR(500) NULL,
            ip VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY site_404_logs_created_idx(created_at)
        )");
    }

    public function down(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS site_404_logs');
    }
};
