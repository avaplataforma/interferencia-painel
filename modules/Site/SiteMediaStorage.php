<?php

declare(strict_types=1);

namespace Interferencia\Modules\Site;

use Interferencia\Kernel\Http\UploadedFile;
use Interferencia\Modules\Storage\SpacesStorageManager;
use RuntimeException;

final readonly class SiteMediaStorage
{
    public function __construct(private SpacesStorageManager $spaces) {}

    /** @return array{storage_path:string,mime_type:string,width:int,height:int,file_size:int,title:string,alt_text:string} */
    public function store(int $organizationId, UploadedFile $file, string $title, string $altText, ?int $userId): array
    {
        if ($file->error !== UPLOAD_ERR_OK || $file->temporaryPath === '' || !is_uploaded_file($file->temporaryPath)) throw new RuntimeException('Não foi possível receber a imagem.');
        if ($file->size < 1 || $file->size > 8 * 1024 * 1024) throw new RuntimeException('A imagem deve possuir no máximo 8 MB.');
        $contents=file_get_contents($file->temporaryPath);if(!is_string($contents)||$contents==='')throw new RuntimeException('A imagem enviada está vazia.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);if(!is_string($mime)||!in_array($mime,['image/jpeg','image/png','image/webp'],true))throw new RuntimeException('Envie uma imagem JPG, PNG ou WebP.');
        $dimensions=@getimagesizefromstring($contents);if(!is_array($dimensions))throw new RuntimeException('O arquivo enviado não é uma imagem válida.');
        [$contents,$mime,$width,$height]=$this->optimize($contents,$mime,(int)$dimensions[0],(int)$dimensions[1]);
        $extension=$mime==='image/webp'?'webp':($mime==='image/png'?'png':'jpg');$name=(preg_replace('/[^A-Za-z0-9._-]+/','-',pathinfo($file->originalName,PATHINFO_FILENAME))?:'imagem').'.'.$extension;
        $path=$this->spaces->storeFranchise($organizationId,'Site/Midias',$contents,$name,$mime,$userId);if($path===null)throw new RuntimeException('Ative a integração DigitalOcean Spaces antes de enviar imagens.');
        return['storage_path'=>$path,'mime_type'=>$mime,'width'=>$width,'height'=>$height,'file_size'=>strlen($contents),'title'=>trim($title)!==''?$title:pathinfo($file->originalName,PATHINFO_FILENAME),'alt_text'=>$altText];
    }

    /** @return array{string,string,int,int} */
    private function optimize(string$contents,string$mime,int$width,int$height):array
    {
        if(!function_exists('imagecreatefromstring'))return[$contents,$mime,$width,$height];$source=@imagecreatefromstring($contents);if($source===false)return[$contents,$mime,$width,$height];
        $scale=min(1,1920/max($width,$height));$targetWidth=max(1,(int)round($width*$scale));$targetHeight=max(1,(int)round($height*$scale));$target=imagecreatetruecolor($targetWidth,$targetHeight);if($target===false){imagedestroy($source);return[$contents,$mime,$width,$height];}
        if($mime==='image/png'||$mime==='image/webp'){imagealphablending($target,false);imagesavealpha($target,true);$transparent=imagecolorallocatealpha($target,0,0,0,127);imagefilledrectangle($target,0,0,$targetWidth,$targetHeight,$transparent);}imagecopyresampled($target,$source,0,0,0,0,$targetWidth,$targetHeight,$width,$height);ob_start();
        if(function_exists('imagewebp')){$ok=imagewebp($target,null,84);$mime='image/webp';}elseif($mime==='image/png')$ok=imagepng($target,null,7);else$ok=imagejpeg($target,null,86);$optimized=ob_get_clean();imagedestroy($target);imagedestroy($source);
        return$ok&&is_string($optimized)&&$optimized!==''?[$optimized,$mime,$targetWidth,$targetHeight]:[$contents,$mime,$width,$height];
    }
}
