<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260810_999920_add_portalava_provider_credentials';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE course_provider_integrations
            ADD COLUMN username_encrypted TEXT NULL AFTER token_last4,
            ADD COLUMN username_last4 VARCHAR(4) NULL AFTER username_encrypted,
            ADD COLUMN password_encrypted TEXT NULL AFTER username_last4,
            ADD COLUMN password_last4 VARCHAR(4) NULL AFTER password_encrypted");

        $database->exec("UPDATE course_provider_integrations
            SET base_url='https://ead.portalava.com.br'
            WHERE provider_code='iesde' AND (base_url IS NULL OR base_url='')");
    }

    public function down(PDO $database): void
    {
        $database->exec("ALTER TABLE course_provider_integrations
            DROP COLUMN password_last4,
            DROP COLUMN password_encrypted,
            DROP COLUMN username_last4,
            DROP COLUMN username_encrypted");
    }
};
