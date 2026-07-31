<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Environment;

use RuntimeException;

final class Environment
{
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            throw new RuntimeException(sprintf('Não foi possível ler o ambiente: %s', $path));
        }

        foreach ($lines as $number => $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            if (!str_contains($line, '=')) {
                throw new RuntimeException(sprintf('Linha inválida no .env: %d', $number + 1));
            }

            [$name, $value] = array_map('trim', explode('=', $line, 2));

            if (preg_match('/^[A-Z_][A-Z0-9_]*$/', $name) !== 1) {
                throw new RuntimeException(sprintf('Variável inválida no .env: %s', $name));
            }

            if (getenv($name) !== false) {
                continue;
            }

            $value = self::normalizeValue($value, $number + 1);
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    private static function normalizeValue(string $value, int $line): string
    {
        if ($value === '') {
            return '';
        }

        $first = $value[0];

        if ($first !== '"' && $first !== "'") {
            $comment = strpos($value, ' #');

            return trim($comment === false ? $value : substr($value, 0, $comment));
        }

        if (strlen($value) < 2 || !str_ends_with($value, $first)) {
            throw new RuntimeException(sprintf('Aspas não fechadas no .env, linha %d', $line));
        }

        $value = substr($value, 1, -1);

        return $first === '"' ? stripcslashes($value) : $value;
    }
}

