<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Http;

use Closure;
use Interferencia\Kernel\Security\Csrf;

final class Router
{
    /** @var list<Route> */
    private array $routes = [];
    private readonly string $basePath;

    public function __construct(string $basePath = '/', private readonly ?Csrf $csrf = null)
    {
        $normalized = '/' . trim($basePath, '/');
        $this->basePath = $normalized === '/' ? '' : $normalized;
    }

    /** @param Closure(Request, array<string, string>): Response $handler
     *  @param list<Middleware> $middleware
     */
    public function get(string $path, Closure $handler, array $middleware = []): self
    {
        return $this->add(['GET'], $path, $handler, $middleware);
    }

    /** @param Closure(Request, array<string, string>): Response $handler */
    public function post(string $path, Closure $handler, array $middleware = []): self
    {
        return $this->add(['POST'], $path, $handler, $middleware);
    }

    /**
     * @param list<string> $methods
     * @param Closure(Request, array<string, string>): Response $handler
     * @param list<Middleware> $middleware
     */
    public function add(array $methods, string $path, Closure $handler, array $middleware = []): self
    {
        $this->routes[] = new Route($methods, $path, $handler, $middleware);

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $path = $this->relativePath($request->path());

        if ($path === null) {
            return Response::text("Página não encontrada.\n", 404);
        }

        $allowed = [];

        foreach ($this->routes as $route) {
            $parameters = $route->match($path);

            if ($parameters === null) {
                continue;
            }

            if ($route->allows($request->method())) {
                if ($this->csrf !== null && !$this->csrf->validateRequest($request)) {
                    return Response::text("Token de segurança inválido ou expirado.\n", 419);
                }

                return $route->run($request, $parameters);
            }

            $allowed = array_merge($allowed, $route->methods());
        }

        if ($allowed !== []) {
            $allowed = array_values(array_unique($allowed));

            if (in_array('GET', $allowed, true) && !in_array('HEAD', $allowed, true)) {
                $allowed[] = 'HEAD';
            }

            sort($allowed);

            return Response::text("Método não permitido.\n", 405)
                ->withHeaders(['Allow' => implode(', ', $allowed)]);
        }

        return Response::text("Página não encontrada.\n", 404);
    }

    private function relativePath(string $path): ?string
    {
        if ($this->basePath === '') {
            return $path;
        }

        if ($path === $this->basePath) {
            return '/';
        }

        if (!str_starts_with($path, $this->basePath . '/')) {
            return null;
        }

        return substr($path, strlen($this->basePath));
    }
}
