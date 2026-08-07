<?php

declare(strict_types=1);

namespace Interferencia\Modules\Storage;

use RuntimeException;
use Throwable;

final readonly class SpacesStorageManager
{
    private const CENTRAL_FOLDERS=['Personalizacao','Contratos','Solicitacoes','Tickets','Documentos','Backups'];
    private const FRANCHISE_FOLDERS=['Personalizacao','Alunos','Tickets','Documentos','Contratos','Importacoes','Backups'];
    public function __construct(private SpacesIntegrationRepository$repository){}
    public function status():array{return$this->repository->settings(false)+$this->repository->summary()+['encryption_ready'=>$this->repository->encryptionReady()];}
    public function active():bool{$s=$this->repository->settings();return(bool)$s['is_active']&&(bool)$s['configured'];}
    public function save(array$data,?int$userId):void{$this->repository->save($data,$userId);}

    /** @param list<array<string,mixed>> $organizations @return array{folders:int,franchises:int} */
    public function testAndProvision(array$organizations):array
    {
        try{$settings=$this->repository->settings();$client=$this->client($settings);$test=rtrim((string)$settings['central_prefix'],'/').'/.connection-'.bin2hex(random_bytes(8));$client->put($test,'Mundo Inter '.gmdate(DATE_ATOM),'text/plain');$client->get($test);$client->delete($test);$folders=$this->provision($client,(string)$settings['central_prefix'],self::CENTRAL_FOLDERS);$count=0;foreach($organizations as$organization){$folders+=$this->provision($client,$this->franchiseRoot((int)$organization['id'],(string)$organization['code'],$settings),self::FRANCHISE_FOLDERS);$count++;}$this->repository->markTest(null);return['folders'=>$folders,'franchises'=>$count];}
        catch(Throwable$e){$this->repository->markTest(mb_substr($e->getMessage(),0,1000));throw$e;}
    }
    public function provisionOrganization(int$id):void
    {
        if(!$this->active())return;$settings=$this->repository->settings();$this->provision($this->client($settings),$this->franchiseRoot($id,$this->repository->organizationCode($id),$settings),self::FRANCHISE_FOLDERS);
    }
    public function storeCentral(string$category,string$content,string$name,string$mime='application/octet-stream',?int$userId=null):?string{return$this->store('central',null,$category,$content,$name,$mime,$userId);}
    public function storeFranchise(int$organizationId,string$category,string$content,string$name,string$mime='application/octet-stream',?int$userId=null):?string{return$this->store('franchise',$organizationId,$category,$content,$name,$mime,$userId);}
    public function read(string$storagePath):string{$key=$this->keyFromStoragePath($storagePath);return$this->client($this->repository->settings())->get($key);}

    private function store(string$scope,?int$organizationId,string$category,string$content,string$name,string$mime,?int$userId):?string
    {
        if(!$this->active())return null;$settings=$this->repository->settings();$root=$scope==='central'?(string)$settings['central_prefix']:$this->franchiseRoot((int)$organizationId,$this->repository->organizationCode((int)$organizationId),$settings);$folder=$this->segment($category);$safeName=$this->fileName($name);$key=$root.'/'.$folder.'/'.date('Y/m').'/'.bin2hex(random_bytes(16)).'-'.$safeName;$this->client($settings)->put($key,$content,$mime);$this->repository->register($scope,$organizationId,$folder,$key,['name'=>$name,'mime'=>$mime,'bytes'=>strlen($content),'checksum'=>hash('sha256',$content),'user'=>$userId]);return'spaces:'.$key;
    }
    private function client(array$settings):SpacesClient
    {
        if(!(bool)$settings['configured'])throw new RuntimeException('Configure as credenciais da DigitalOcean Spaces.');return new SpacesClient((string)$settings['endpoint'],(string)$settings['region'],(string)$settings['access_key'],(string)$settings['secret_key']);
    }
    private function provision(SpacesClient$client,string$root,array$folders):int{$count=0;foreach($folders as$folder){$client->put(rtrim($root,'/').'/'.$folder.'/.keep','Mundo Inter','text/plain');$count++;}return$count;}
    private function franchiseRoot(int$id,string$code,array$settings):string{return rtrim((string)$settings['franchises_prefix'],'/').'/'.str_pad((string)$id,6,'0',STR_PAD_LEFT).'-'.$this->segment($code);}
    private function segment(string$value):string{$ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',trim($value));$value=strtolower(is_string($ascii)?$ascii:$value);$value=preg_replace('/[^a-z0-9]+/','-',$value)??'';return trim($value,'-')?:'arquivos';}
    private function fileName(string$value):string{$value=preg_replace('/[^\pL\pN._ -]+/u','_',basename($value))??'';return mb_substr($value!==''?$value:'arquivo',0,180);}
    private function keyFromStoragePath(string$path):string{if(!str_starts_with($path,'spaces:'))throw new RuntimeException('Referência externa inválida.');$key=substr($path,7);if($key===''||str_contains($key,'..'))throw new RuntimeException('Caminho externo inválido.');return$key;}
}
