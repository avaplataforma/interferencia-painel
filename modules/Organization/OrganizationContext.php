<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

use Interferencia\Kernel\Http\Request;
use Interferencia\Modules\Identity\User;

final readonly class OrganizationContext
{
    public function __construct(private OrganizationRepository $organizations)
    {
    }

    public function resolve(Request $request, ?User $user = null): ?Organization
    {
        $organization = $this->organizations->findActiveByHost((string) $request->header('host', ''));
        if ($organization === null) return null;
        if ($user !== null && !$this->organizations->userBelongsTo($user->id, $organization->id)) return null;
        return $organization;
    }
}
