<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

use Interferencia\Kernel\Http\UploadedFile;
use Interferencia\Modules\Storage\SpacesStorageManager;
use RuntimeException;

final readonly class OrganizationBrandingStorage
{
    public function __construct(private string $publicDirectory,private ?SpacesStorageManager $spaces=null) {}

    public function store(int $organizationId, UploadedFile $file, string $kind): string
    {
        if (!in_array($kind, ['logo', 'favicon', 'navbar'], true)) throw new RuntimeException('Tipo de imagem inválido.');
        if ($file->error !== UPLOAD_ERR_OK || !is_uploaded_file($file->temporaryPath)) throw new RuntimeException('Não foi possível receber a imagem enviada.');
        if ($file->size < 1 || $file->size > 3 * 1024 * 1024) throw new RuntimeException('A imagem deve ter no máximo 3 MB.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->temporaryPath);
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];
        if (!is_string($mime) || !isset($extensions[$mime])) throw new RuntimeException('Envie uma imagem PNG, JPG, WebP ou SVG.');
        $contents=file_get_contents($file->temporaryPath);if(!is_string($contents))throw new RuntimeException('Não foi possível ler a imagem.');$this->spaces?->storeFranchise($organizationId,'Personalizacao',$contents,$kind.'.'.$extensions[$mime],$mime);
        $directory = rtrim($this->publicDirectory, '/\\') . DIRECTORY_SEPARATOR . $organizationId;
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new RuntimeException('Não foi possível preparar o diretório da marca.');
        foreach (glob($directory . DIRECTORY_SEPARATOR . $kind . '.*') ?: [] as $oldFile) if (is_file($oldFile)) @unlink($oldFile);
        $filename = $kind . '.' . $extensions[$mime];
        $destination = $directory . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($file->temporaryPath, $destination)) throw new RuntimeException('Não foi possível guardar a imagem.');
        chmod($destination, 0644);
        return '/assets/organizations/' . $organizationId . '/' . $filename . '?v=' . time();
    }
}
