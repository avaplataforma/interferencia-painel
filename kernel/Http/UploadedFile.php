<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Http;

final readonly class UploadedFile
{
    public function __construct(
        public string $temporaryPath,
        public string $originalName,
        public string $clientMimeType,
        public int $size,
        public int $error,
    ) {}

    public function isEmpty(): bool
    {
        return $this->error === UPLOAD_ERR_NO_FILE;
    }
}
