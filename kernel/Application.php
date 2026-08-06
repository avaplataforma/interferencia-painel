<?php

declare(strict_types=1);

namespace Interferencia\Kernel;

use Interferencia\Kernel\Http\Request;
use Interferencia\Kernel\Http\Router;
use Interferencia\Kernel\Log\RequestContext;

final readonly class Application
{
    public function __construct(private Router $router, private ?Request $request = null)
    {
    }

    public function run(): void
    {
        $response = $this->router->dispatch($this->request ?? Request::fromGlobals());
        $securityHeaders = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'",
            'Cache-Control' => 'no-store',
            'X-Request-ID' => RequestContext::id(),
        ];
        if ($response->header('Content-Security-Policy') !== null) unset($securityHeaders['X-Frame-Options']);
        $response->withHeaders(array_merge($securityHeaders, $response->headers()))->send();
    }
}
