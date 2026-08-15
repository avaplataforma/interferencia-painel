<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration {
    public function id(): string { return '20260814_000010_create_central_email_service'; }

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE central_email_integrations (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            provider VARCHAR(30) NOT NULL DEFAULT 'smtp',
            smtp_host VARCHAR(255) NULL,
            smtp_port SMALLINT UNSIGNED NOT NULL DEFAULT 587,
            encryption VARCHAR(10) NOT NULL DEFAULT 'tls',
            username_encrypted TEXT NULL,
            username_last4 VARCHAR(4) NULL,
            password_encrypted TEXT NULL,
            password_last4 VARCHAR(4) NULL,
            from_name VARCHAR(160) NOT NULL DEFAULT 'Mundo Inter',
            from_email VARCHAR(190) NOT NULL DEFAULT 'no-reply@mundointer.com.br',
            reply_to_email VARCHAR(190) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            last_tested_at DATETIME NULL,
            last_error VARCHAR(500) NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT central_email_updated_by_fk FOREIGN KEY(updated_by) REFERENCES platform_users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE organization_email_senders (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organization_id BIGINT UNSIGNED NOT NULL,
            from_name VARCHAR(160) NOT NULL,
            from_email VARCHAR(190) NOT NULL,
            reply_to_email VARCHAR(190) NULL,
            domain_status VARCHAR(20) NOT NULL DEFAULT 'pending',
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            verified_at DATETIME NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY organization_email_sender_uq(organization_id),
            CONSTRAINT organization_email_sender_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            CONSTRAINT organization_email_sender_user_fk FOREIGN KEY(updated_by) REFERENCES platform_users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE email_delivery_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organization_id BIGINT UNSIGNED NULL,
            message_type VARCHAR(60) NOT NULL,
            recipient_email VARCHAR(190) NOT NULL,
            sender_email VARCHAR(190) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL,
            provider_message_id VARCHAR(190) NULL,
            error_message VARCHAR(500) NULL,
            related_type VARCHAR(60) NULL,
            related_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX email_delivery_org_created_idx(organization_id,created_at),
            INDEX email_delivery_status_created_idx(status,created_at),
            CONSTRAINT email_delivery_org_fk FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS email_delivery_logs');
        $db->exec('DROP TABLE IF EXISTS organization_email_senders');
        $db->exec('DROP TABLE IF EXISTS central_email_integrations');
    }
};
