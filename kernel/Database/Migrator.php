<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Database;

use PDO;
use RuntimeException;
use Throwable;

final readonly class Migrator
{
    private const TABLE = 'schema_migrations';

    public function __construct(
        private PDO $database,
        private MigrationRepository $repository,
    ) {
    }

    public function initialize(): void
    {
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` ('
            . '`migration` VARCHAR(190) NOT NULL PRIMARY KEY,'
            . '`batch` INT UNSIGNED NOT NULL,'
            . '`executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return list<MigrationStatus> */
    public function status(): array
    {
        $this->initialize();
        $applied = $this->applied();
        $available = $this->repository->all();
        $status = [];

        foreach ($available as $id => $migration) {
            $status[] = new MigrationStatus($id, isset($applied[$id]), $applied[$id] ?? null);
        }

        foreach (array_diff_key($applied, $available) as $id => $batch) {
            $status[] = new MigrationStatus($id, true, $batch);
        }

        return $status;
    }

    /** @return list<string> */
    public function migrate(): array
    {
        $this->initialize();
        $applied = $this->applied();
        $batch = $this->nextBatch();
        $executed = [];

        foreach ($this->repository->all() as $id => $migration) {
            if (isset($applied[$id])) {
                continue;
            }

            $migration->up($this->database);

            try {
                $statement = $this->database->prepare(
                    'INSERT INTO `' . self::TABLE . '` (`migration`, `batch`) VALUES (:migration, :batch)'
                );
                $statement->execute(['migration' => $id, 'batch' => $batch]);
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    sprintf('A migração %s foi executada, mas não pôde ser registrada.', $id),
                    0,
                    $exception,
                );
            }

            $executed[] = $id;
        }

        return $executed;
    }

    /** @return list<string> */
    public function rollback(): array
    {
        $this->initialize();
        $batch = (int) $this->database->query(
            'SELECT COALESCE(MAX(`batch`), 0) FROM `' . self::TABLE . '`'
        )->fetchColumn();

        if ($batch === 0) {
            return [];
        }

        $statement = $this->database->prepare(
            'SELECT `migration` FROM `' . self::TABLE . '` WHERE `batch` = :batch ORDER BY `migration` DESC'
        );
        $statement->execute(['batch' => $batch]);
        $ids = $statement->fetchAll(PDO::FETCH_COLUMN);
        $available = $this->repository->all();
        $reverted = [];

        foreach ($ids as $id) {
            if (!is_string($id) || !isset($available[$id])) {
                throw new RuntimeException(sprintf('Arquivo da migração aplicada não encontrado: %s', (string) $id));
            }

            $available[$id]->down($this->database);
            $delete = $this->database->prepare(
                'DELETE FROM `' . self::TABLE . '` WHERE `migration` = :migration'
            );
            $delete->execute(['migration' => $id]);
            $reverted[] = $id;
        }

        return $reverted;
    }

    /** @return array<string, int> */
    private function applied(): array
    {
        $rows = $this->database->query(
            'SELECT `migration`, `batch` FROM `' . self::TABLE . '` ORDER BY `migration`'
        )->fetchAll();
        $applied = [];

        foreach ($rows as $row) {
            if (is_array($row) && is_string($row['migration'] ?? null)) {
                $applied[$row['migration']] = (int) $row['batch'];
            }
        }

        return $applied;
    }

    private function nextBatch(): int
    {
        return 1 + (int) $this->database->query(
            'SELECT COALESCE(MAX(`batch`), 0) FROM `' . self::TABLE . '`'
        )->fetchColumn();
    }
}
