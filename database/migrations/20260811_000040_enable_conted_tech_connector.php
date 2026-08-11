<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260811_000040_enable_conted_tech_connector';
    }

    public function up(\PDO $database): void
    {
        $database->exec("UPDATE course_provider_integrations SET name='CONTED TECH',integration_mode='api',delivery_mode='sso' WHERE provider_code='conted_tech'");
        $database->exec("UPDATE course_provider_capabilities capability INNER JOIN course_provider_integrations provider ON provider.id=capability.provider_id SET capability.catalog_sync=1,capability.single_sign_on=1,capability.suspend_access=1,capability.send_access=1 WHERE provider.provider_code='conted_tech'");
    }

    public function down(\PDO $database): void
    {
        $database->exec("UPDATE course_provider_capabilities capability INNER JOIN course_provider_integrations provider ON provider.id=capability.provider_id SET capability.catalog_sync=0,capability.single_sign_on=0,capability.suspend_access=0,capability.send_access=0 WHERE provider.provider_code='conted_tech'");
    }
};
