<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260810_999930_add_lti13_course_provider_connection';
    }

    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE course_provider_integrations
            ADD COLUMN integration_mode VARCHAR(30) NOT NULL DEFAULT 'api' AFTER name,
            ADD COLUMN lti_integration_name VARCHAR(190) NULL AFTER integration_mode,
            ADD COLUMN lti_platform_url VARCHAR(500) NULL AFTER lti_integration_name,
            ADD COLUMN lti_registration_url VARCHAR(1000) NULL AFTER lti_platform_url,
            ADD COLUMN lti_tool_url VARCHAR(1000) NULL AFTER lti_registration_url,
            ADD COLUMN lti_login_url VARCHAR(1000) NULL AFTER lti_tool_url,
            ADD COLUMN lti_jwks_url VARCHAR(1000) NULL AFTER lti_login_url,
            ADD COLUMN lti_redirect_uris TEXT NULL AFTER lti_jwks_url,
            ADD COLUMN lti_client_id VARCHAR(190) NULL AFTER lti_redirect_uris,
            ADD COLUMN lti_deployment_id VARCHAR(190) NULL AFTER lti_client_id,
            ADD COLUMN lti_status VARCHAR(30) NOT NULL DEFAULT 'draft' AFTER lti_deployment_id");

        $database->exec("UPDATE course_provider_integrations SET
            integration_mode='lti13',
            delivery_mode='lti',
            lti_integration_name='Mundo Inter — Catálogo MASTER',
            lti_platform_url='https://avacursos.com.br',
            lti_status='provider_started',
            is_active=0,
            last_test_status='not_tested',
            last_error=NULL
            WHERE provider_code='iesde'");
    }

    public function down(PDO $database): void
    {
        $database->exec("ALTER TABLE course_provider_integrations
            DROP COLUMN lti_status,
            DROP COLUMN lti_deployment_id,
            DROP COLUMN lti_client_id,
            DROP COLUMN lti_redirect_uris,
            DROP COLUMN lti_jwks_url,
            DROP COLUMN lti_login_url,
            DROP COLUMN lti_tool_url,
            DROP COLUMN lti_registration_url,
            DROP COLUMN lti_platform_url,
            DROP COLUMN lti_integration_name,
            DROP COLUMN integration_mode");
    }
};
