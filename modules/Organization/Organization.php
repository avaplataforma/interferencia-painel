<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

final readonly class Organization
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $code,
        public string $legalName,
        public string $displayName,
        public string $timezone,
        public string $locale,
        public string $primaryColor,
        public ?string $secondaryColor,
        public ?string $logoPath,
        public ?string $faviconPath,
    ) {
    }
}
