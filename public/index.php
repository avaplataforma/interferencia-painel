<?php

declare(strict_types=1);

// Ponto de entrada provisório da Sprint 0. O roteador será criado com o kernel.
http_response_code(503);
header('Content-Type: text/plain; charset=UTF-8');
header('Retry-After: 3600');

echo "Interferência Painel — fundação em preparação.\n";

