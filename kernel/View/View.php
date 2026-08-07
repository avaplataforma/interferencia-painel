<?php

declare(strict_types=1);

namespace Interferencia\Kernel\View;

use Interferencia\Kernel\Http\Response;
use RuntimeException;

final class View
{
    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(private string $directory)
    {
    }

    /** @param array<string, mixed> $data */
    public function share(array $data): void
    {
        $this->shared = array_merge($this->shared, $data);
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], int $status = 200): Response
    {
        $data = array_merge($this->shared, $data);
        $content = $this->capture($template, $data);
        $html = $this->capture('layouts/app', array_merge($data, ['content' => $content]));

        return Response::html($html, $status);
    }

    /** @param array<string, mixed> $data */
    public function renderStandalone(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html($this->capture($template, array_merge($this->shared, $data)), $status);
    }

    /** @param array<string, mixed> $data */
    private function capture(string $templateName, array $data): string
    {
        if (preg_match('#^[A-Za-z0-9/_-]+$#', $templateName) !== 1 || str_contains($templateName, '..')) {
            throw new RuntimeException(sprintf('Nome de template inválido: %s', $templateName));
        }

        $path = rtrim($this->directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $templateName)
            . '.php';

        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Template não encontrado: %s', $templateName));
        }

        $escape = static fn (mixed $value): string => htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );

        extract($data, EXTR_SKIP);
        ob_start();

        try {
            require $path;
            return (string) ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }
}
