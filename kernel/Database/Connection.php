<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Database;

use Interferencia\Kernel\Config\Config;
use PDO;
use PDOException;
use RuntimeException;

final class Connection
{
    private ?PDO $pdo = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $driver = $this->config->string('database.driver');

        if ($driver !== 'mysql') {
            throw new RuntimeException(sprintf('Driver de banco não suportado: %s', $driver));
        }

        $database = $this->config->string('database.database');
        $username = $this->config->string('database.username');
        $host = $this->config->string('database.host');
        $port = $this->config->get('database.port');
        $charset = $this->config->string('database.charset');

        if (!is_int($port) || $port < 1 || $port > 65535) {
            throw new RuntimeException('Porta do banco de dados inválida.');
        }

        self::assertIdentifier($database, 'nome do banco');

        $dsn = self::dsn($host, $port, $database, $charset);
        $password = $this->config->get('database.password');
        $options = $this->config->get('database.options', []);

        if (!is_string($password) || !is_array($options)) {
            throw new RuntimeException('Configuração de banco de dados inválida.');
        }

        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $exception) {
            throw new RuntimeException('Não foi possível conectar ao banco de dados.', 0, $exception);
        }

        return $this->pdo;
    }

    public function check(): DatabaseInfo
    {
        $statement = $this->pdo()->query('SELECT DATABASE() AS database_name, VERSION() AS server_version');
        $result = $statement->fetch();

        if (!is_array($result) || !is_string($result['database_name'] ?? null) || !is_string($result['server_version'] ?? null)) {
            throw new RuntimeException('O banco respondeu em formato inesperado.');
        }

        return new DatabaseInfo($result['database_name'], $result['server_version']);
    }

    public static function dsn(string $host, int $port, string $database, string $charset): string
    {
        return sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);
    }

    private static function assertIdentifier(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $value) !== 1) {
            throw new RuntimeException(sprintf('%s inválido.', ucfirst($label)));
        }
    }
}

