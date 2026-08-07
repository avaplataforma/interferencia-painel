<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

use Interferencia\Kernel\Http\UploadedFile;
use RuntimeException;

final readonly class PlatformBrandingStorage
{
    public function __construct(private string $publicDirectory) {}
    public function store(UploadedFile$file,string$kind):string
    {
        if(!in_array($kind,['logo','favicon'],true))throw new RuntimeException('Tipo de imagem inválido.');
        if($file->error!==UPLOAD_ERR_OK||!is_uploaded_file($file->temporaryPath))throw new RuntimeException('Não foi possível receber a imagem enviada.');
        if($file->size<1||$file->size>3*1024*1024)throw new RuntimeException('A imagem deve ter no máximo 3 MB.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file->temporaryPath);$extensions=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];if(!is_string($mime)||!isset($extensions[$mime]))throw new RuntimeException('Envie uma imagem PNG, JPG ou WebP.');
        $directory=rtrim($this->publicDirectory,'/\\');if(!is_dir($directory)&&!mkdir($directory,0755,true)&&!is_dir($directory))throw new RuntimeException('Não foi possível preparar o diretório da marca.');
        foreach(glob($directory.DIRECTORY_SEPARATOR.$kind.'.*')?:[]as$old)if(is_file($old))@unlink($old);$filename=$kind.'.'.$extensions[$mime];$destination=$directory.DIRECTORY_SEPARATOR.$filename;if(!move_uploaded_file($file->temporaryPath,$destination))throw new RuntimeException('Não foi possível guardar a imagem.');chmod($destination,0644);return'/assets/platform/'.$filename.'?v='.time();
    }
}
