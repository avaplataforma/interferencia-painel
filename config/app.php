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
];
