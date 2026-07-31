<?php

declare(strict_types=1);

namespace Interferencia\Kernel;

use Interferencia\Kernel\Http\Request;
use Interferencia\Kernel\Http\Router;
use Interferencia\Kernel\Log\RequestContext;

final readonly class Application
{
    public function __construct(private Router $router)
    {
    }

    public function run(): void
    {
        $response = $this->router->dispatch(Request::fromGlobals());
        $response->withHeaders([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'",
            'Cache-Control' => 'no-store',
            'X-Request-ID' => RequestContext::id(),
        ])->send();
    }
}
