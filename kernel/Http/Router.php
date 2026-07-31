<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Http;

use Closure;

final class Router
{
    /** @var list<Route> */
    private array $routes = [];
    private readonly string $basePath;

    public function __construct(string $basePath = '/')
    {
        $normalized = '/' . trim($basePath, '/');
        $this->basePath = $normalized === '/' ? '' : $normalized;
    }

    /** @param Closure(Request, array<string, string>): Response $handler */
    public function get(string $path, Closure $handler): self
    {
        return $this->add(['GET'], $path, $handler);
    }

    /** @param Closure(Request, array<string, string>): Response $handler */
    public function post(string $path, Closure $handler): self
    {
        return $this->add(['POST'], $path, $handler);
    }

    /**
     * @param list<string> $methods
     * @param Closure(Request, array<string, string>): Response $handler
     */
    public function add(array $methods, string $path, Closure $handler): self
    {
        $this->routes[] = new Route($methods, $path, $handler);

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
