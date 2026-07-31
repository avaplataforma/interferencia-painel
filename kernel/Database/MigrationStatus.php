<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Database;

final readonly class MigrationStatus
{
    public function __construct(
        public string $id,
        public bool $applied,
        public ?int $batch = null,
    ) {
    }
}

