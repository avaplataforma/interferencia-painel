<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;
use PDO;

return new class implements Migration {
    public function up(PDO $db): void
    {
        $db->exec('ALTER TABLE finance_integrations DROP INDEX finance_integrations_provider_unique, ADD UNIQUE KEY finance_integrations_provider_environment_unique(provider, environment)');
        $db->exec("INSERT IGNORE INTO finance_integrations(provider, environment) VALUES('asaas', 'production'), ('asaas', 'sandbox')");
    }

    public function down(PDO $db): void
    {
        $db->exec("DELETE FROM finance_integrations WHERE provider='asaas' AND environment='sandbox'");
        $db->exec('ALTER TABLE finance_integrations DROP INDEX finance_integrations_provider_environment_unique, ADD UNIQUE KEY finance_integrations_provider_unique(provider)');
    }
};
