<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260809_992000_create_organization_finance_integrations';
    }

    public function up(PDO $database): void
    {
        $database->exec("CREATE TABLE organization_finance_integrations (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, organization_id BIGINT UNSIGNED NOT NULL, provider VARCHAR(40) NOT NULL DEFAULT 'asaas', account_mode VARCHAR(20) NOT NULL DEFAULT 'central', environment VARCHAR(20) NOT NULL DEFAULT 'production', api_key_encrypted TEXT NULL, api_key_last4 VARCHAR(4) NULL, webhook_token_encrypted TEXT NULL, is_active TINYINT(1) NOT NULL DEFAULT 0, last_test_status VARCHAR(20) NOT NULL DEFAULT 'not_tested', last_tested_at DATETIME NULL, last_test_error VARCHAR(500) NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY organization_finance_integrations_org_provider_unique (organization_id, provider), KEY organization_finance_integrations_active_idx (provider, is_active), CONSTRAINT organization_finance_integrations_org_fk FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE, CONSTRAINT organization_finance_integrations_user_fk FOREIGN KEY (updated_by) REFERENCES platform_users (id) ON DELETE SET NULL, CONSTRAINT organization_finance_integrations_mode_check CHECK (account_mode IN ('central','exclusive')), CONSTRAINT organization_finance_integrations_environment_check CHECK (environment IN ('production','sandbox')), CONSTRAINT organization_finance_integrations_test_check CHECK (last_test_status IN ('not_tested','pending','success','failed'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $organizationId = (int) $database->query('SELECT MIN(id) FROM organizations')->fetchColumn();
        if ($organizationId <= 0) {
            throw new RuntimeException('Nenhuma franquia disponível para migrar o escopo financeiro.');
        }

        $database->exec("ALTER TABLE finance_sync_cursors ADD organization_id BIGINT UNSIGNED NULL FIRST");
        $database->exec("UPDATE finance_sync_cursors SET organization_id={$organizationId}");
        $database->exec("ALTER TABLE finance_sync_cursors MODIFY organization_id BIGINT UNSIGNED NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (organization_id, resource), ADD CONSTRAINT finance_sync_cursors_org_fk FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE");

        $database->exec("ALTER TABLE finance_webhook_events ADD organization_id BIGINT UNSIGNED NULL AFTER id");
        $database->exec("UPDATE finance_webhook_events SET organization_id={$organizationId}");
        $database->exec("ALTER TABLE finance_webhook_events MODIFY organization_id BIGINT UNSIGNED NOT NULL, DROP INDEX finance_webhook_event_unique, ADD UNIQUE KEY finance_webhook_event_org_unique (organization_id, asaas_event_id), ADD KEY finance_webhook_events_org_received_idx (organization_id, last_received_at), ADD CONSTRAINT finance_webhook_events_org_fk FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE");
    }

    public function down(PDO $database): void
    {
        $organizationId = (int) $database->query('SELECT MIN(id) FROM organizations')->fetchColumn();
        $database->exec("DELETE FROM finance_webhook_events WHERE organization_id<>{$organizationId}");
        $database->exec('ALTER TABLE finance_webhook_events DROP FOREIGN KEY finance_webhook_events_org_fk, DROP INDEX finance_webhook_events_org_received_idx, DROP INDEX finance_webhook_event_org_unique, ADD UNIQUE KEY finance_webhook_event_unique (asaas_event_id), DROP COLUMN organization_id');
        $database->exec("DELETE FROM finance_sync_cursors WHERE organization_id<>{$organizationId}");
        $database->exec('ALTER TABLE finance_sync_cursors DROP FOREIGN KEY finance_sync_cursors_org_fk, DROP PRIMARY KEY, ADD PRIMARY KEY (resource), DROP COLUMN organization_id');
        $database->exec('DROP TABLE organization_finance_integrations');
    }
};
