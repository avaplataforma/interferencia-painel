<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Security;

use Interferencia\Kernel\Http\Request;
use Interferencia\Kernel\Session\Session;

final readonly class Csrf
{
    private const SESSION_KEY = '_csrf_token';
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);

        if (!is_string($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
            $token = bin2hex(random_bytes(32));
            $this->session->put(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function field(): string
    {
        return sprintf('<input type="hidden" name="_token" value="%s">', $this->token());
    }

    public function validate(?string $token): bool
    {
        $expected = $this->session->get(self::SESSION_KEY);

        return is_string($expected)
            && is_string($token)
            && $token !== ''
            && hash_equals($expected, $token);
    }

    public function validateRequest(Request $request): bool
    {
        if (in_array($request->method(), self::SAFE_METHODS, true)) {
            return true;
        }

        $token = $request->input('_token');

        if (!is_string($token)) {
            $token = $request->header('x-csrf-token');
        }

        return $this->validate($token);
    }

    public function rotate(): string
    {
        $this->session->forget(self::SESSION_KEY);

        return $this->token();
    }
}
