<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Database;

final readonly class DatabaseInfo
{
    public function __construct(
        public string $database,
        public string $serverVersion,
    ) {
    }
}

