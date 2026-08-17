<?php

declare(strict_types=1);

namespace Interferencia\Modules\Identity;

use RuntimeException;

final readonly class UserManager
{
    public function __construct(private UserRepository $users, private PasswordHasher $hasher)
    {
    }

    /** @param list<int> $roles @param list<int> $units */
    public function create(string $name, string $email, string $password, bool $active, array $roles, array $units): int
    {
        $this->assertSelections($roles, $units);
        return $this->users->createManaged($name, $email, $this->hasher->hash($password), $active, $roles, $units);
    }

    /** @param list<int> $roles @param list<int> $units */
    public function update(int $id, string $name, string $email, ?string $password, bool $active, array $roles, array $units): void
    {
        $user = $this->users->findById($id);
        if ($user === null) throw new RuntimeException('Usuário não encontrado.');
        $this->assertSelections($roles, $units);
        $availableRoles = $this->users->availableRoles();
        $superAdminId = null;
        foreach ($availableRoles as $role) if ($role['code'] === 'super_admin') $superAdminId = (int) $role['id'];
        $removesLastAdmin = $this->users->isSuperAdmin($id)
            && (!$active || $superAdminId === null || !in_array($superAdminId, $roles, true))
            && $this->users->activeSuperAdminCount() <= 1;
        if ($removesLastAdmin) throw new RuntimeException('O último administrador global não pode ser desativado ou perder seu papel.');
        $hash = $password !== null && $password !== '' ? $this->hasher->hash($password) : null;
        $this->users->updateManaged($id, $name, $email, $active, $roles, $units, $hash);
    }

    /** @param list<int> $roles @param list<int> $units */
    private function assertSelections(array $roles, array $units): void
    {
        $validRoles = array_map(static fn (array $r): int => (int) $r['id'], $this->users->availableRoles());
        $validUnits = array_map(static fn (array $u): int => (int) $u['id'], $this->users->availableUnits());
        if ($roles === [] || array_diff($roles, $validRoles) !== []) throw new RuntimeException('Selecione ao menos um papel válido.');
        $fullAccess = $this->users->roleGrantsAllUnits($roles);
        $needsUnits = $this->users->requiresUnits() && !$fullAccess;
        if ($needsUnits && ($units === [] || array_diff($units, $validUnits) !== [])) throw new RuntimeException('Selecione ao menos uma unidade válida.');
    }
}
