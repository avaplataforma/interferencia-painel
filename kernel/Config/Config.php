<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Config;

use RuntimeException;

final readonly class Config
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values)
    {
    }

    public static function fromDirectory(string $directory): self
    {
        if (!is_dir($directory)) {
            throw new RuntimeException(sprintf('Diretório de configuração inexistente: %s', $directory));
        }

        $values = [];
        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php');

        if ($files === false) {
            throw new RuntimeException('Não foi possível localizar as configurações.');
        }

        sort($files);

        foreach ($files as $file) {
            $group = pathinfo($file, PATHINFO_FILENAME);
            $configuration = require $file;

            if (!is_array($configuration)) {
                throw new RuntimeException(sprintf('Configuração %s deve retornar um array.', $file));
            }

            $values[$group] = $configuration;
        }

        return new self($values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function string(string $key): string
    {
        $value = $this->get($key);

        if (!is_string($value) || $value === '') {
            throw new RuntimeException(sprintf('Configuração obrigatória inválida: %s', $key));
        }

        return $value;
    }

    public function path(string $key): string
    {
        $value = $this->get($key);

        if (!is_string($value)) {
            throw new RuntimeException(sprintf('Configuração de caminho inválida: %s', $key));
        }

        $normalized = '/' . trim($value, '/');

        return $normalized === '/' ? '' : $normalized;
    }

    public function bool(string $key): bool
    {
        $value = $this->get($key);

        if (!is_bool($value)) {
            throw new RuntimeException(sprintf('Configuração booleana inválida: %s', $key));
        }

        return $value;
    }
}
