<?php

declare(strict_types=1);

namespace Interferencia\Modules\Identity;

use RuntimeException;

final readonly class RoleManager
{
    public function __construct(private RoleRepository $roles)
    {
    }

    /** @param list<int> $permissions */
    public function create(string $name, array $permissions): int
    {
        $this->assertPermissions($permissions);
        $code = $this->slug($name);
        if ($code === '') throw new RuntimeException('Não foi possível gerar o código interno do perfil.');
        if ($this->roles->codeExists($code) || $this->roles->nameExists($name)) throw new RuntimeException('Já existe um perfil com este nome ou código interno.');

        return $this->roles->create($code, trim($name), $permissions);
    }

    /** @param list<int> $permissions */
    public function update(int $id, string $name, array $permissions): void
    {
        $role = $this->roles->find($id);
        if ($role === null) throw new RuntimeException('Perfil não encontrado.');
        if ($this->roles->nameExists($name, $id)) throw new RuntimeException('Já existe outro perfil com este nome.');

        if ($role['code'] === 'super_admin') {
            $permissions = array_map(static fn (array $permission): int => (int) $permission['id'], $this->roles->permissions());
        } else {
            $this->assertPermissions($permissions);
        }
        $this->roles->update($id, trim($name), $permissions);
    }

    /** @param list<int> $permissions */
    private function assertPermissions(array $permissions): void
    {
        $valid = array_map(static fn (array $permission): int => (int) $permission['id'], $this->roles->permissions());
        if ($permissions === [] || array_diff($permissions, $valid) !== []) throw new RuntimeException('Selecione ao menos uma permissão válida.');
    }

    private function slug(string $value): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($value));
        $value = strtolower(is_string($transliterated) ? $transliterated : $value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';

        return trim($value, '_');
    }
}
