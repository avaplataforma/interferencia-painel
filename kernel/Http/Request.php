<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Http;

final readonly class Request
{
    /**
     * @param array<string, string|array<string>> $query
     * @param array<string, string> $headers
     * @param array<string, mixed> $input
     * @param array<string, UploadedFile> $files
     */
    public function __construct(
        private string $method,
        private string $path,
        private array $query = [],
        private array $headers = [],
        private string $body = '',
        private array $input = [],
        private array $files = [],
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
            $_POST,
            self::filesFromGlobals($_FILES),
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

    /** @return array<string, mixed> */
    public function inputData(): array
    {
        return $this->input;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->input[$key] ?? $default;
    }

    public function file(string $key): ?UploadedFile
    {
        return $this->files[$key] ?? null;
    }

    /** @param array<string, mixed> $files @return array<string, UploadedFile> */
    private static function filesFromGlobals(array $files): array
    {
        $normalized=[];
        foreach($files as$key=>$file){if(!is_array($file)||is_array($file['name']??null))continue;$normalized[$key]=new UploadedFile((string)($file['tmp_name']??''),(string)($file['name']??''),(string)($file['type']??''),(int)($file['size']??0),(int)($file['error']??UPLOAD_ERR_NO_FILE));}
        return$normalized;
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
