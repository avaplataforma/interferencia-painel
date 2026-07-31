<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Session;

use RuntimeException;

final class Session
{
    private const FLASH_NEW = '_flash.new';
    private const FLASH_OLD = '_flash.old';

    public function __construct(
        private readonly string $name,
        private readonly string $path,
        private readonly int $lifetime,
        private readonly bool $secure,
        private readonly bool $httpOnly,
        private readonly string $sameSite,
        private readonly string $savePath,
    ) {
        if (preg_match('/^[A-Za-z0-9_-]+$/', $name) !== 1) {
            throw new RuntimeException('Nome de sessão inválido.');
        }

        if ($lifetime < 300) {
            throw new RuntimeException('Tempo de sessão deve ser de pelo menos 300 segundos.');
        }

        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            throw new RuntimeException('Política SameSite inválida.');
        }

        if ($sameSite === 'None' && !$secure) {
            throw new RuntimeException('SameSite=None exige cookie seguro.');
        }
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (headers_sent($file, $line)) {
            throw new RuntimeException(sprintf('Não foi possível iniciar a sessão; saída em %s:%d.', $file, $line));
        }

        if (!is_dir($this->savePath) && !mkdir($this->savePath, 0770, true) && !is_dir($this->savePath)) {
            throw new RuntimeException('Não foi possível criar o diretório de sessões.');
        }

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string) $this->lifetime);
        session_save_path($this->savePath);
        session_name($this->name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $this->path,
            'domain' => '',
            'secure' => $this->secure,
            'httponly' => $this->httpOnly,
            'samesite' => $this->sameSite,
        ]);

        if (!session_start()) {
            throw new RuntimeException('Não foi possível iniciar a sessão.');
        }

        $this->ageFlash();
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION ?? []);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function flash(string $key, mixed $value): void
    {
        $this->put($key, $value);
        $new = $this->flashKeys(self::FLASH_NEW);

        if (!in_array($key, $new, true)) {
            $new[] = $key;
        }

        $this->put(self::FLASH_NEW, $new);
    }

    public function ageFlash(): void
    {
        foreach ($this->flashKeys(self::FLASH_OLD) as $key) {
            $this->forget($key);
        }

        $this->put(self::FLASH_OLD, $this->flashKeys(self::FLASH_NEW));
        $this->put(self::FLASH_NEW, []);
    }

    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !session_regenerate_id(true)) {
            throw new RuntimeException('Não foi possível renovar a sessão.');
        }
    }

    public function invalidate(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->regenerate();
        }
    }

    /** @return list<string> */
    private function flashKeys(string $key): array
    {
        $keys = $this->get($key, []);

        if (!is_array($keys)) {
            return [];
        }

        return array_values(array_filter($keys, 'is_string'));
    }
}

