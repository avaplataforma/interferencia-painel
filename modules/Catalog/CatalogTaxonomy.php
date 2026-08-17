<?php

declare(strict_types=1);

namespace Interferencia\Modules\Catalog;

final class CatalogTaxonomy
{
    /** Normaliza nomes compostos e variantes conhecidas do fornecedor. */
    private const AREA_ALIASES = [
        'administracao geral' => 'Administração',
        'artes' => 'Arte',
        'eduacao infantil' => 'Educação Infantil',
        'educacao financeria' => 'Educação Financeira',
        'inteligencia artifical' => 'Inteligência Artificial',
        'metodologia do ensino' => 'Metodologia de Ensino',
        'eja - educacao de jovens e adultos' => 'EJA',
    ];

    public static function clean(?string $value): ?string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', (string) $value));
        if ($value === '') return null;
        $first = trim(explode('|', $value)[0]);
        return $first === '' ? null : $first;
    }

    public static function category(?string $value): ?string
    {
        return self::alias(self::clean($value));
    }

    public static function area(?string $value): ?string
    {
        return self::alias(self::clean($value));
    }

    private static function alias(?string $value): ?string
    {
        if ($value === null) return null;
        $key = self::normalize($value);
        return self::AREA_ALIASES[$key] ?? $value;
    }

    private static function normalize(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($value));
        return is_string($ascii) ? trim($ascii) : $value;
    }
}
