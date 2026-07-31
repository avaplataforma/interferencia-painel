<?php

declare(strict_types=1);

return [
    'name' => getenv('SESSION_NAME') ?: 'painel_inter_session',
    'lifetime' => (int) (getenv('SESSION_LIFETIME') ?: 7200),
    'secure' => filter_var(getenv('SESSION_SECURE_COOKIE') ?: true, FILTER_VALIDATE_BOOL),
    'http_only' => true,
    'same_site' => getenv('SESSION_SAME_SITE') ?: 'Lax',
];

