<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Http;

final readonly class Request
{
    /**
     * @param array<string, string|array<string>> $query
     * @param array<string, string> $headers
     */
    public function __construct(
        private string $method,
        private string $path,
        private array $query = [],
        private array $headers = [],
        private string $body = '',
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $body = file_get_contents('php://input');

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            is_string($path) && $path !== '' ? $path : '/',
            $_GET,
            self::headersFromServer($_SERVER),
            $body === false ? '' : $body,
        );
    }

    public function method(): string
    {
        return strtoupper($this->method);
    }

    public function path(): string
    {
        return $this->normalizePath($this->path);
    }

    /** @return array<string, string|array<string>> */
    public function query(): array
    {
        return $this->query;
    }

    public function queryValue(string $key, string|array|null $default = null): string|array|null
    {
        return $this->query[$key] ?? $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private static function headersFromServer(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        foreach (['CONTENT_TYPE' => 'content-type', 'CONTENT_LENGTH' => 'content-length'] as $key => $name) {
            if (isset($server[$key]) && is_string($server[$key])) {
                $headers[$name] = $server[$key];
            }
        }

        return $headers;
    }

    private function normalizePath(string $path): string
    {
        $normalized = '/' . ltrim(preg_replace('#/+#', '/', $path) ?? '/', '/');

        return $normalized === '/' ? '/' : rtrim($normalized, '/');
    }
}

