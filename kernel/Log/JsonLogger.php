<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Log;

use DateTimeImmutable;
use JsonException;
use RuntimeException;
use Throwable;

final class JsonLogger
{
    private const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'notice' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5,
        'alert' => 6,
        'emergency' => 7,
    ];

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password', 'passwd', 'secret', 'token', 'authorization', 'cookie', 'api_key',
    ];

    public function __construct(
        private readonly string $path,
        private readonly string $minimumLevel = 'warning',
    ) {
        if (!array_key_exists($minimumLevel, self::LEVELS)) {
            throw new RuntimeException(sprintf('Nível de log inválido: %s', $minimumLevel));
        }

        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Não foi possível criar o diretório de logs: %s', $directory));
        }
    }

    /** @param array<string, mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!array_key_exists($level, self::LEVELS)) {
            throw new RuntimeException(sprintf('Nível de log inválido: %s', $level));
        }

        if (self::LEVELS[$level] < self::LEVELS[$this->minimumLevel]) {
            return;
        }

        $record = [
            'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
            'level' => $level,
            'message' => $message,
            'request_id' => RequestContext::id(),
            'context' => $this->sanitize($context),
        ];

        try {
            $line = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new RuntimeException('Não foi possível serializar o registro de log.', 0, $exception);
        }

        if (file_put_contents($this->path, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException(sprintf('Não foi possível gravar o log: %s', $this->path));
        }
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /** @param array<string, mixed> $values
     *  @return array<string, mixed>
     */
    private function sanitize(array $values): array
    {
        foreach ($values as $key => $value) {
            $normalized = strtolower((string) $key);

            if ($this->isSensitive($normalized)) {
                $values[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->sanitize($value);
            } elseif ($value instanceof Throwable) {
                $values[$key] = [
                    'type' => $value::class,
                    'message' => $value->getMessage(),
                    'file' => $value->getFile(),
                    'line' => $value->getLine(),
                ];
            } elseif (is_object($value)) {
                $values[$key] = $value::class;
            } elseif (is_resource($value)) {
                $values[$key] = get_resource_type($value);
            }
        }

        return $values;
    }

    private function isSensitive(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if (str_contains($key, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }
}
