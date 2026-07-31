<?php

declare(strict_types=1);

namespace Interferencia\Modules\Identity;

use DateTimeImmutable;

final readonly class User
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $passwordHash,
        public bool $active,
        public int $failedLoginAttempts,
        public ?DateTimeImmutable $lockedUntil,
    ) {
    }

    public function isLocked(DateTimeImmutable $now = new DateTimeImmutable()): bool
    {
        return $this->lockedUntil !== null && $this->lockedUntil > $now;
    }
}

