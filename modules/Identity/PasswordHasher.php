<?php

declare(strict_types=1);

namespace Interferencia\Modules\Identity;

use RuntimeException;

final class PasswordHasher
{
    /** @param array<string, int> $options */
    public function __construct(private readonly array $options = ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2])
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            throw new RuntimeException('Argon2id não está disponível nesta instalação do PHP.');
        }
    }

    public function hash(string $password): string
    {
        $hash = password_hash($password, PASSWORD_ARGON2ID, $this->options);

        if (!is_string($hash)) {
            throw new RuntimeException('Não foi possível gerar o hash da senha.');
        }

        return $hash;
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, $this->options);
    }
}

