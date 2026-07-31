<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

use Interferencia\Kernel\Session\Session;
use Interferencia\Modules\Identity\Auth;
use RuntimeException;

final readonly class UnitContext
{
    private const SESSION_KEY = 'context.unit_code';
    public const ALL = '__all__';

    public function __construct(
        private Auth $auth,
        private UnitRepository $units,
        private Session $session,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function available(): array
    {
        return $this->units->activeByCodes($this->auth->unitScopes());
    }

    /** @return array<string, mixed>|null */
    public function current(): ?array
    {
        $available = $this->available();
        if ($available === []) {
            $this->session->forget(self::SESSION_KEY);
            return null;
        }

        $selected = $this->session->get(self::SESSION_KEY);
        if ($selected === self::ALL && count($available) > 1) return ['id' => null, 'code' => self::ALL, 'name' => 'Todas as unidades', 'city' => null];
        foreach ($available as $unit) {
            if (is_string($selected) && $unit['code'] === $selected) return $unit;
        }

        $this->session->put(self::SESSION_KEY, (string) $available[0]['code']);
        return $available[0];
    }

    public function select(string $code): void
    {
        if ($code === self::ALL && count($this->available()) > 1) { $this->session->put(self::SESSION_KEY, self::ALL); return; }
        foreach ($this->available() as $unit) {
            if ($unit['code'] === $code) {
                $this->session->put(self::SESSION_KEY, $code);
                return;
            }
        }

        throw new RuntimeException('Unidade indisponível para este usuário.');
    }
}
