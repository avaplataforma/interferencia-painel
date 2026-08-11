<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260811_000010_add_lti13_deep_link_url';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE course_provider_integrations
            ADD COLUMN lti_deep_link_url VARCHAR(1000) NULL AFTER lti_tool_url");
    }

    public function down(PDO $database): void
    {
        $database->exec("ALTER TABLE course_provider_integrations
            DROP COLUMN lti_deep_link_url");
    }
};
