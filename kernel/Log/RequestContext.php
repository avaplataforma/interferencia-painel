<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Log;

final class RequestContext
{
    private static ?string $id = null;

    public static function id(): string
    {
        if (self::$id === null) {
            self::$id = bin2hex(random_bytes(16));
        }

        return self::$id;
    }
}

