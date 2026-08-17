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
        'preparatorio aprova concursos' => 'Preparatórios',
        'cursos livres' => 'Cursos Livres',
        'graduacao' => 'Graduação',
        'extensao universitaria' => 'Extensão Universitária',
    ];

    /** Níveis acadêmicos que ainda podem ser encaixados em categorias afins pelo nome do curso. */
    private const ACADEMIC_LEVELS = ['Cursos Livres', 'Graduação', 'Extensão Universitária'];

    /** Palavras-chave (normalizadas) usadas para refinar a categoria pelo nome do curso. */
    private const NAME_RULES = [
        'Direito' => ['direito', 'jurid', 'eca', 'legislacao', 'estatuto da crianca', 'migratorio', 'etnico-raciais'],
        'TI' => ['algoritmo', 'programacao', 'informatic', 'inclusao digital', 'java', 'raciocinio logico', 'chat gpt'],
        'Saúde' => ['drogadicao', 'medicina legal', 'primeiros socorros', 'nr-1', 'psicossocial', 'assedio', 'discriminacao'],
        'Engenharia' => ['engenharia'],
        'Negócios' => ['financas', 'financ', 'economi', 'gestao', 'marketing', 'negoci', 'investimentos', 'credito', 'lideranca', 'oratoria', 'canvas', 'balanced scorecard', 'soft skills', 'produtividade', 'empreendedora', 'empresarial', 'macroeconomica', 'politica e economia', 'politica', 'projetos', 'metodologias ageis'],
        'Educação' => ['educac', 'bncc', 'escola', 'escolar', 'pedagog', 'ensino', 'aprendizagem', 'professor', 'crianca', 'infantil', 'libras', 'lingua brasileira de sinais', 'ludico', 'jogos', 'bullying', 'abaco', 'novo ensino medio', 'nem', 'metodologias ativas', 'curriculo', 'esporte', 'ambiental', 'meio ambiente', 'sustentabilidade', 'tics', 'tecnologias na educacao', 'transtornos', 'habilidades', 'anos iniciais', 'metodologia da ciencia'],
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

    /** Encaixa níveis acadêmicos (Cursos Livres, Graduação, Extensão) em categorias afins pelo nome do curso. */
    public static function refineCategory(?string $category, string $courseName): ?string
    {
        if ($category === null || $category === '') return $category;
        if (!in_array($category, self::ACADEMIC_LEVELS, true)) return $category;
        $name = self::normalize($courseName);
        foreach (self::NAME_RULES as $target => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($name, $keyword)) return $target;
            }
        }
        return $category;
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
