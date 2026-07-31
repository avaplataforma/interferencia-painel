<?php

declare(strict_types=1);

namespace Interferencia\Kernel\View;

use Interferencia\Kernel\Http\Response;
use RuntimeException;

final readonly class View
{
    public function __construct(private string $directory)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], int $status = 200): Response
    {
        $content = $this->capture($template, $data);
        $html = $this->capture('layouts/app', array_merge($data, ['content' => $content]));

        return Response::html($html, $status);
    }

    /** @param array<string, mixed> $data */
    private function capture(string $template, array $data): string
    {
        if (preg_match('#^[A-Za-z0-9/_-]+$#', $template) !== 1 || str_contains($template, '..')) {
            throw new RuntimeException(sprintf('Nome de template inválido: %s', $template));
        }

        $path = rtrim($this->directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $template)
            . '.php';

        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Template não encontrado: %s', $template));
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

