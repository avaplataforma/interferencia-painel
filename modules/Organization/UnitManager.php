<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

use RuntimeException;

final readonly class UnitManager
{
    public function __construct(private UnitRepository $units)
    {
    }

    public function create(string $name, string $city, bool $active): int
    {
        $code = $this->slug($name);
        if ($code === '') throw new RuntimeException('Não foi possível gerar o código interno da unidade.');
        if ($this->units->codeExists($code) || $this->units->nameExists($name)) throw new RuntimeException('Já existe uma unidade com este nome ou código interno.');

        return $this->units->create($code, trim($name), trim($city), $active);
    }

    public function update(int $id, string $name, string $city, bool $active): void
    {
        if ($this->units->find($id) === null) throw new RuntimeException('Unidade não encontrada.');
        if ($this->units->nameExists($name, $id)) throw new RuntimeException('Já existe outra unidade com este nome.');
        $this->units->update($id, trim($name), trim($city), $active);
    }

    private function slug(string $value): string
    {
        $value = trim($value);
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = is_string($transliterated) ? $transliterated : $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }
}
