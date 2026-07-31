<?php

declare(strict_types=1);

namespace Interferencia\Modules\Identity;

use Closure;
use Interferencia\Kernel\Http\Middleware;
use Interferencia\Kernel\Http\Request;
use Interferencia\Kernel\Http\Response;

final readonly class RequirePermission implements Middleware
{
    public function __construct(private Auth $auth, private string $permission)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        return $this->auth->can($this->permission)
            ? $next($request)
            : Response::text("Acesso negado.\n", 403);
    }
}

