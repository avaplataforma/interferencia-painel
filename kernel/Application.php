<?php

declare(strict_types=1);

namespace Interferencia\Kernel;

use Interferencia\Kernel\Config\Config;
use Interferencia\Kernel\Log\JsonLogger;
use Interferencia\Kernel\Log\RequestContext;

final readonly class Application
{
    public function __construct(
        private Config $config,
        private JsonLogger $logger,
    ) {
    }

    public function run(): void
    {
        $basePath = '/' . trim($this->config->string('app.base_path'), '/');
        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        if (!in_array(rtrim($requestPath, '/'), [$basePath, $basePath . '/status'], true)) {
            $this->respondNotFound();
            return;
        }

        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
        header('Cache-Control: no-store');
        header('X-Request-ID: ' . RequestContext::id());

        $name = htmlspecialchars($this->config->string('app.name'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $environment = htmlspecialchars($this->config->string('app.environment'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $base = htmlspecialchars($basePath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        echo <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>{$name}</title>
  <style>
    :root { color-scheme: light; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f3f5f7; color: #17212b; }
    main { width: min(38rem, calc(100% - 2rem)); background: #fff; border: 1px solid #dfe4e8; border-radius: 1rem; padding: 2rem; box-shadow: 0 1rem 3rem rgb(23 33 43 / 8%); }
    .status { display: inline-flex; align-items: center; gap: .5rem; color: #176b3a; font-weight: 700; }
    .status::before { content: ""; width: .7rem; height: .7rem; border-radius: 50%; background: #27a45d; }
    h1 { margin: .75rem 0; font-size: clamp(1.7rem, 5vw, 2.4rem); }
    p { color: #50606f; line-height: 1.6; }
    dl { display: grid; grid-template-columns: auto 1fr; gap: .65rem 1rem; margin: 1.5rem 0 0; }
    dt { color: #6a7783; } dd { margin: 0; font-weight: 600; }
  </style>
</head>
<body>
  <main>
    <span class="status">Fundação operacional</span>
    <h1>{$name}</h1>
    <p>O núcleo técnico inicial está ativo. Os módulos de negócio ainda não foram habilitados.</p>
    <dl>
      <dt>Ambiente</dt><dd>{$environment}</dd>
      <dt>Base</dt><dd>{$base}</dd>
      <dt>PHP</dt><dd>8.3+</dd>
    </dl>
  </main>
</body>
</html>
HTML;
    }

    private function respondNotFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
        header('X-Request-ID: ' . RequestContext::id());
        echo "Página não encontrada.\n";
    }
}
