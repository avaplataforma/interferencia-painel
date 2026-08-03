<?php

declare(strict_types=1);

namespace Interferencia\Modules\WhatsApp;

use Interferencia\Kernel\Http\UploadedFile;
use RuntimeException;

final readonly class MediaStorage
{
    private const MAX_SIZE=16777216;
    private const TYPES=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf','application/msword'=>'doc','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx','audio/mpeg'=>'mp3','audio/ogg'=>'ogg','audio/mp4'=>'m4a'];

    public function __construct(private string $directory){}

    /** @return array{mime_type:string,file_name:string,file_size:int,storage_path:string,message_type:string}|null */
    public function storeUploaded(?UploadedFile $file):?array
    {
        if($file===null||$file->isEmpty())return null;
        if($file->error!==UPLOAD_ERR_OK)throw new RuntimeException('O anexo não foi recebido corretamente.');
        if($file->size<1||$file->size>self::MAX_SIZE)throw new RuntimeException('O anexo deve possuir no máximo 16 MB.');
        if(!is_uploaded_file($file->temporaryPath))throw new RuntimeException('O arquivo recebido não é um upload válido.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file->temporaryPath);if(!is_string($mime)||!isset(self::TYPES[$mime]))throw new RuntimeException('Tipo de anexo não permitido. Use imagem, PDF, Word ou áudio.');
        $relative=date('Y/m').'/'.bin2hex(random_bytes(24)).'.'.self::TYPES[$mime];$destination=$this->absolute($relative);$folder=dirname($destination);if(!is_dir($folder)&&!mkdir($folder,0700,true)&&!is_dir($folder))throw new RuntimeException('Não foi possível preparar o armazenamento do anexo.');
        if(!move_uploaded_file($file->temporaryPath,$destination))throw new RuntimeException('Não foi possível guardar o anexo.');chmod($destination,0600);
        $name=preg_replace('/[^\pL\pN._ -]+/u','_',basename($file->originalName))?:'anexo.'.self::TYPES[$mime];
        return['mime_type'=>$mime,'file_name'=>mb_substr($name,0,255),'file_size'=>$file->size,'storage_path'=>$relative,'message_type'=>str_starts_with($mime,'image/')?'image':(str_starts_with($mime,'audio/')?'audio':'document')];
    }

    public function read(string $relative):string
    {
        $path=$this->absolute($relative);if(!is_file($path))throw new RuntimeException('Anexo não encontrado.');$contents=file_get_contents($path);if($contents===false)throw new RuntimeException('Não foi possível ler o anexo.');return$contents;
    }

    private function absolute(string $relative):string
    {
        if($relative===''||str_contains($relative,'..')||str_starts_with($relative,'/')||str_starts_with($relative,'\\'))throw new RuntimeException('Caminho de anexo inválido.');return rtrim($this->directory,'/\\').DIRECTORY_SEPARATOR.str_replace(['/', '\\'],DIRECTORY_SEPARATOR,$relative);
    }
}
