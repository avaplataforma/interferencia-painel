<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Http;

use Closure;
use InvalidArgumentException;

final readonly class Route
{
    /** @var list<string> */
    private array $methods;
    private string $pattern;

    /**
     * @param list<string> $methods
     * @param Closure(Request, array<string, string>): Response $handler
     * @param list<Middleware> $middleware
     */
    public function __construct(array $methods, string $path, private Closure $handler, private array $middleware = [])
    {
        $this->methods = array_values(array_unique(array_map('strtoupper', $methods)));

        if ($this->methods === []) {
            throw new InvalidArgumentException('A rota deve aceitar ao menos um método HTTP.');
        }

        $this->pattern = self::compilePattern($path);
    }

    public function allows(string $method): bool
    {
        $method = strtoupper($method);

        return in_array($method, $this->methods, true)
            || ($method === 'HEAD' && in_array('GET', $this->methods, true));
    }

    /** @return list<string> */
    public function methods(): array
    {
        return $this->methods;
    }

    /** @return array<string, string>|null */
    public function match(string $path): ?array
    {
        if (preg_match($this->pattern, $path, $matches) !== 1) {
            return null;
        }

        return array_map(
            static fn (string $value): string => rawurldecode($value),
            array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY),
        );
    }

    /** @param array<string, string> $parameters */
    public function run(Request $request, array $parameters): Response
    {
        $next = fn (Request $request): Response => ($this->handler)($request, $parameters);

        foreach (array_reverse($this->middleware) as $middleware) {
            $next = fn (Request $request): Response => $middleware->handle($request, $next);
        }

        return $next($request);
    }

    private static function compilePattern(string $path): string
    {
        $path = self::normalizePath($path);
        $offset = 0;
        $pattern = '';

        while (preg_match('/\{([A-Za-z_][A-Za-z0-9_]*)(?::([^{}]+))?\}/', $path, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $token = $match[0][0];
            $position = $match[0][1];
            $name = $match[1][0];
            $constraint = isset($match[2][0]) && $match[2][0] !== '' ? $match[2][0] : '[^/]+';

            $pattern .= preg_quote(substr($path, $offset, $position - $offset), '#');
            $pattern .= sprintf('(?P<%s>%s)', $name, $constraint);
            $offset = $position + strlen($token);
        }

        $pattern .= preg_quote(substr($path, $offset), '#');

        if (str_contains($pattern, '{') || str_contains($pattern, '}')) {
            throw new InvalidArgumentException(sprintf('Parâmetro de rota inválido: %s', $path));
        }

        $compiled = '#^' . $pattern . '$#uD';

        if (@preg_match($compiled, '/') === false) {
            throw new InvalidArgumentException(sprintf('Restrição de rota inválida: %s', $path));
        }

        return $compiled;
    }

    private static function normalizePath(string $path): string
    {
        $normalized = '/' . trim($path, '/');

        return $normalized === '/' ? '/' : rtrim($normalized, '/');
    }
}
