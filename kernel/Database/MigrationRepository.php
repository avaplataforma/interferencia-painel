<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Database;

use RuntimeException;

final readonly class MigrationRepository
{
    public function __construct(private string $directory)
    {
    }

    /** @return array<string, Migration> */
    public function all(): array
    {
        $files = glob(rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php');

        if ($files === false) {
            throw new RuntimeException('Não foi possível listar as migrações.');
        }

        sort($files, SORT_STRING);
        $migrations = [];

        foreach ($files as $file) {
            $migration = require $file;

            if (!$migration instanceof Migration) {
                throw new RuntimeException(sprintf('Migração inválida: %s', basename($file)));
            }

            $id = $migration->id();

            if (preg_match('/^[0-9]{8}_[0-9]{6}_[a-z0-9_]+$/', $id) !== 1) {
                throw new RuntimeException(sprintf('Identificador de migração inválido: %s', $id));
            }

            if (array_key_exists($id, $migrations)) {
                throw new RuntimeException(sprintf('Migração duplicada: %s', $id));
            }

            $migrations[$id] = $migration;
        }

        ksort($migrations, SORT_STRING);

        return $migrations;
    }
}

