<?php

declare(strict_types=1);

return [
    'name' => 'PAINEL INTER',
    'browser_title' => 'PAINEL INTER',
    'environment' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),
    'url' => getenv('APP_URL') ?: 'https://interferencia.com.br/painel',
    'base_path' => getenv('APP_BASE_PATH') ?: '/painel',
    'timezone' => getenv('APP_TIMEZONE') ?: 'America/Sao_Paulo',
    'log_level' => getenv('LOG_LEVEL') ?: 'warning',
    'log_channel' => getenv('LOG_CHANNEL') ?: 'application',
    'external_form_key' => getenv('EXTERNAL_FORM_KEY') ?: '',
    'whatsapp_verify_token' => getenv('WHATSAPP_VERIFY_TOKEN') ?: '',
    'whatsapp_app_secret' => getenv('WHATSAPP_APP_SECRET') ?: '',
    'whatsapp_access_token' => getenv('WHATSAPP_ACCESS_TOKEN') ?: '',
    'whatsapp_graph_version' => getenv('WHATSAPP_GRAPH_VERSION') ?: '',
    'whatsapp_send_enabled' => filter_var(getenv('WHATSAPP_SEND_ENABLED') ?: false, FILTER_VALIDATE_BOOL),
    'asaas_environment' => getenv('ASAAS_ENVIRONMENT') ?: 'sandbox',
    'asaas_api_key' => getenv('ASAAS_API_KEY') ?: '',
    'asaas_webhook_token' => getenv('ASAAS_WEBHOOK_TOKEN') ?: '',
    'asaas_payments_write_enabled' => filter_var(getenv('ASAAS_PAYMENTS_WRITE_ENABLED') ?: false, FILTER_VALIDATE_BOOL),
    'encryption_key' => getenv('APP_ENCRYPTION_KEY') ?: '',
];
