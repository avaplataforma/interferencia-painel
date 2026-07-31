<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Error;

use ErrorException;
use Interferencia\Kernel\Log\JsonLogger;
use Interferencia\Kernel\Log\RequestContext;
use Throwable;

final readonly class ErrorHandler
{
    public function __construct(
        private JsonLogger $logger,
        private bool $debug,
    ) {
    }

    public function register(): void
    {
        set_error_handler($this->handleError(...));
        set_exception_handler($this->handleException(...));
        register_shutdown_function($this->handleShutdown(...));
    }

    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    public function handleException(Throwable $exception): void
    {
        try {
            $this->logger->error('Exceção não tratada.', ['exception' => $exception]);
        } catch (Throwable) {
            error_log((string) $exception);
        }

        $this->renderFailure($exception);
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }

        $this->handleException(new ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line'],
        ));
    }

    private function renderFailure(Throwable $exception): void
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            header('X-Request-ID: ' . RequestContext::id());
            header('Cache-Control: no-store');
        }

        $message = "Ocorreu um erro interno. Referência: " . RequestContext::id();

        if ($this->debug) {
            $message .= sprintf("\n\n%s: %s\n%s:%d", $exception::class, $exception->getMessage(), $exception->getFile(), $exception->getLine());
        }

        echo $message . PHP_EOL;
    }
}

