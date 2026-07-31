<?php

declare(strict_types=1);

namespace Interferencia\Modules\Identity;

final readonly class AuthResult
{
    private function __construct(
        public bool $successful,
        public ?User $user = null,
    ) {
    }

    public static function success(User $user): self
    {
        return new self(true, $user);
    }

    public static function failure(): self
    {
        return new self(false);
    }
}

