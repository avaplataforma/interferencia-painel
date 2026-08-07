<?php

declare(strict_types=1);

namespace Interferencia\Modules\WhatsApp;

use Interferencia\Kernel\Http\UploadedFile;
use Interferencia\Modules\Storage\SpacesStorageManager;
use RuntimeException;

final readonly class MediaStorage
{
    private const MAX_SIZE=16777216;
    private const TYPES=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf','application/msword'=>'doc','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx','audio/mpeg'=>'mp3','audio/ogg'=>'ogg','audio/mp4'=>'m4a'];

    public function __construct(private string $directory,private ?SpacesStorageManager$spaces=null,private string$scope='local',private int$organizationId=0,private string$category='Anexos'){}

    /** @return array{mime_type:string,file_name:string,file_size:int,storage_path:string,message_type:string}|null */
    public function storeUploaded(?UploadedFile $file):?array
    {
        if($file===null||$file->isEmpty())return null;
        if($file->error!==UPLOAD_ERR_OK)throw new RuntimeException('O anexo não foi recebido corretamente.');
        if($file->size<1||$file->size>self::MAX_SIZE)throw new RuntimeException('O anexo deve possuir no máximo 16 MB.');
        if(!is_uploaded_file($file->temporaryPath))throw new RuntimeException('O arquivo recebido não é um upload válido.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file->temporaryPath);if(!is_string($mime)||!isset(self::TYPES[$mime]))throw new RuntimeException('Tipo de anexo não permitido. Use imagem, PDF, Word ou áudio.');
        $name=preg_replace('/[^\pL\pN._ -]+/u','_',basename($file->originalName))?:'anexo.'.self::TYPES[$mime];$contents=file_get_contents($file->temporaryPath);if(!is_string($contents))throw new RuntimeException('Não foi possível ler o anexo.');$remote=$this->remoteStore($contents,$name,$mime);if($remote!==null)return['mime_type'=>$mime,'file_name'=>mb_substr($name,0,255),'file_size'=>$file->size,'storage_path'=>$remote,'message_type'=>str_starts_with($mime,'image/')?'image':(str_starts_with($mime,'audio/')?'audio':'document')];
        $relative=date('Y/m').'/'.bin2hex(random_bytes(24)).'.'.self::TYPES[$mime];$destination=$this->absolute($relative);$folder=dirname($destination);if(!is_dir($folder)&&!mkdir($folder,0700,true)&&!is_dir($folder))throw new RuntimeException('Não foi possível preparar o armazenamento do anexo.');
        if(!move_uploaded_file($file->temporaryPath,$destination))throw new RuntimeException('Não foi possível guardar o anexo.');chmod($destination,0600);
        return['mime_type'=>$mime,'file_name'=>mb_substr($name,0,255),'file_size'=>$file->size,'storage_path'=>$relative,'message_type'=>str_starts_with($mime,'image/')?'image':(str_starts_with($mime,'audio/')?'audio':'document')];
    }

    /** @return array{mime_type:string,file_name:string,file_size:int,storage_path:string,message_type:string} */
    public function storeContent(string $content, string $fileName = ''): array
    {
        $size = strlen($content);
        if ($size < 1 || $size > self::MAX_SIZE) {
            throw new RuntimeException('A mídia deve possuir no máximo 16 MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
        if (!is_string($mime) || !isset(self::TYPES[$mime])) {
            throw new RuntimeException('O tipo da mídia recebida não é permitido.');
        }
        $name = preg_replace('/[^\pL\pN._ -]+/u', '_', basename($fileName));if (!is_string($name) || $name === '')$name = 'midia.' . self::TYPES[$mime];$remote=$this->remoteStore($content,$name,$mime);if($remote!==null)return ['mime_type'=>$mime,'file_name'=>mb_substr($name,0,255),'file_size'=>$size,'storage_path'=>$remote,'message_type'=>str_starts_with($mime,'image/')?'image':(str_starts_with($mime,'audio/')?'audio':'document')];
        $relative = date('Y/m') . '/' . bin2hex(random_bytes(24)) . '.' . self::TYPES[$mime];
        $destination = $this->absolute($relative);
        $folder = dirname($destination);
        if (!is_dir($folder) && !mkdir($folder, 0700, true) && !is_dir($folder)) {
            throw new RuntimeException('Não foi possível preparar o armazenamento da mídia.');
        }
        if (file_put_contents($destination, $content, LOCK_EX) !== $size) {
            throw new RuntimeException('Não foi possível guardar a mídia recebida.');
        }
        chmod($destination, 0600);
        return ['mime_type'=>$mime,'file_name'=>mb_substr($name,0,255),'file_size'=>$size,'storage_path'=>$relative,'message_type'=>str_starts_with($mime,'image/')?'image':(str_starts_with($mime,'audio/')?'audio':'document')];
    }

    public function read(string $relative):string
    {
        if(str_starts_with($relative,'spaces:')){if($this->spaces===null)throw new RuntimeException('Armazenamento externo indisponível.');return$this->spaces->read($relative);}$path=$this->absolute($relative);if(!is_file($path))throw new RuntimeException('Anexo não encontrado.');$contents=file_get_contents($path);if($contents===false)throw new RuntimeException('Não foi possível ler o anexo.');return$contents;
    }

    public function forFranchise(int $organizationId):self
    {
        return new self($this->directory,$this->spaces,'franchise',$organizationId,$this->category);
    }

    /** @param list<string> $referenced @return array{candidates:int,deleted:int} */
    public function cleanupOrphans(array $referenced,bool $delete=false,int $minimumAge=86400):array
    {
        if(!is_dir($this->directory))return['candidates'=>0,'deleted'=>0];
        $known=array_fill_keys(array_map(static fn(string$path):string=>str_replace('\\','/',$path),$referenced),true);$candidates=0;$deleted=0;$cutoff=time()-max(3600,$minimumAge);
        $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory,\FilesystemIterator::SKIP_DOTS));
        foreach($iterator as$file){if(!$file->isFile()||$file->getFilename()==='.gitkeep'||$file->getMTime()>$cutoff)continue;$relative=str_replace('\\','/',substr($file->getPathname(),strlen(rtrim($this->directory,'/\\'))+1));if(isset($known[$relative]))continue;$candidates++;if($delete&&unlink($file->getPathname()))$deleted++;}
        return['candidates'=>$candidates,'deleted'=>$deleted];
    }

    private function absolute(string $relative):string
    {
        if($relative===''||str_contains($relative,'..')||str_starts_with($relative,'/')||str_starts_with($relative,'\\'))throw new RuntimeException('Caminho de anexo inválido.');return rtrim($this->directory,'/\\').DIRECTORY_SEPARATOR.str_replace(['/', '\\'],DIRECTORY_SEPARATOR,$relative);
    }

    private function remoteStore(string$content,string$name,string$mime):?string
    {
        if($this->spaces===null)return null;if($this->scope==='central')return$this->spaces->storeCentral($this->category,$content,$name,$mime);if($this->scope==='franchise'&&$this->organizationId>0)return$this->spaces->storeFranchise($this->organizationId,$this->category,$content,$name,$mime);return null;
    }
}
