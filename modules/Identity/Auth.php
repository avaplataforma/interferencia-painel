<?php

declare(strict_types=1);

namespace Interferencia\Modules\Identity;

use Interferencia\Kernel\Security\Csrf;
use Interferencia\Kernel\Session\Session;

final class Auth
{
    private ?User $resolvedUser = null;
    private bool $resolved = false;

    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly Session $session,
        private readonly Csrf $csrf,
    ) {
    }

    public function attempt(string $email, string $password): AuthResult
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || !$user->active || $user->isLocked() || !$this->hasher->verify($password, $user->passwordHash)) {
            if ($user !== null && $user->active && !$user->isLocked()) {
                $this->users->recordFailedLogin($user->id);
            } else {
                password_verify($password, '$argon2id$v=19$m=65536,t=4,p=2$bm90YXJlYWxzYWx0MTIzNA$12dlRjGbikcKq8oK0W8R5CUuAlTEfDPHwWDSgAYlfgw');
            }

            return AuthResult::failure();
        }

        $newHash = $this->hasher->needsRehash($user->passwordHash)
            ? $this->hasher->hash($password)
            : null;
        $this->users->recordSuccessfulLogin($user->id, $newHash);
        $this->session->regenerate();
        $this->session->put('auth.user_id', $user->id);
        $this->csrf->rotate();
        $this->resolvedUser = $user;
        $this->resolved = true;

        return AuthResult::success($user);
    }

    public function user(): ?User
    {
        if ($this->resolved) {
            return $this->resolvedUser;
        }

        $this->resolved = true;
        $id = $this->session->get('auth.user_id');

        if (!is_int($id)) {
            return null;
        }

        $user = $this->users->findById($id);

        if ($user === null || !$user->active) {
            $this->session->forget('auth.user_id');
            return null;
        }

        return $this->resolvedUser = $user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function can(string $permission): bool
    {
        $user = $this->user();

        return $user !== null && in_array($permission, $this->users->permissions($user->id), true);
    }

    /** @return list<string> */
    public function unitScopes(): array
    {
        $user = $this->user();

        if ($user === null) return [];

        return $this->can('units.access_all')
            ? $this->users->activeUnitCodes()
            : $this->users->unitScopes($user->id);
    }

    public function logout(): void
    {
        $this->session->invalidate();
        $this->csrf->rotate();
        $this->resolved = true;
        $this->resolvedUser = null;
    }
}
