<?php

declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

final readonly class PluginReleaseManager
{
    public function __construct(private string $pluginDirectory) {}

    /** @return array{version:string,release:string,filename:string} */
    public function metadata(): array
    {
        $versionFile=$this->pluginDirectory.'/version.php';
        $source=is_file($versionFile)?(string)file_get_contents($versionFile):'';
        if(!preg_match('/\$plugin->version\s*=\s*(\d+)\s*;/', $source,$versionMatch)||!preg_match('/\$plugin->release\s*=\s*[\'\"]([^\'\"]+)[\'\"]\s*;/', $source,$releaseMatch)){
            throw new RuntimeException('Não foi possível identificar a versão oficial do plugin Mundo Inter.');
        }
        $release=(string)$releaseMatch[1];
        return ['version'=>(string)$versionMatch[1],'release'=>$release,'filename'=>'local_mundointer-'.$release.'.zip'];
    }

    /** @return array{body:string,filename:string,version:string,release:string,sha256:string,size:int} */
    public function package(): array
    {
        if(!is_dir($this->pluginDirectory)||!class_exists(ZipArchive::class))throw new RuntimeException('A geração do pacote ZIP não está disponível neste servidor.');
        $metadata=$this->metadata();
        $temporary=tempnam(sys_get_temp_dir(),'mundointer-plugin-');
        if($temporary===false)throw new RuntimeException('Não foi possível preparar o pacote do plugin.');
        $zip=new ZipArchive();
        if($zip->open($temporary,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true){@unlink($temporary);throw new RuntimeException('Não foi possível criar o pacote do plugin.');}
        $zip->addEmptyDir('mundointer');
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->pluginDirectory,RecursiveDirectoryIterator::SKIP_DOTS));
        foreach($iterator as$file){
            if(!$file->isFile())continue;
            $absolute=$file->getPathname();
            $relative=str_replace('\\','/',substr($absolute,strlen($this->pluginDirectory)+1));
            $zip->addFile($absolute,'mundointer/'.$relative);
        }
        $zip->close();
        $body=(string)file_get_contents($temporary);@unlink($temporary);
        if($body==='')throw new RuntimeException('O pacote do plugin foi gerado vazio.');
        return $metadata+['body'=>$body,'sha256'=>hash('sha256',$body),'size'=>strlen($body)];
    }

    /** @param array<string,mixed> $connection @return array{code:string,label:string,class:string,detail:string} */
    public function deploymentStatus(array $connection): array
    {
        $official=$this->metadata();
        if(empty($connection['configured']))return$this->status('pending','Configuração pendente','warning','Informe o endereço e o token deste Moodle.');
        if(empty($connection['is_active']))return$this->status('inactive','Desativada','neutral','A conexão está cadastrada, mas desativada.');
        if(!empty($connection['last_error']))return$this->status('connection_error','Erro de conexão','danger',(string)$connection['last_error']);
        if(empty($connection['last_tested_at']))return$this->status('never_tested','Aguardando teste','warning','A conexão ainda não foi validada.');
        if(!empty($connection['plugin_last_error']))return$this->status('plugin_missing','Plugin não detectado','danger',(string)$connection['plugin_last_error']);
        if(($connection['plugin_status']??null)==='disabled')return$this->status('disabled','Plugin desativado','danger','Ative o conector nas configurações do Moodle.');
        $installed=(string)($connection['plugin_version']??'');
        if($installed==='')return$this->status('plugin_missing','Plugin não detectado','danger','Instale o pacote oficial e teste novamente.');
        if(version_compare($installed,$official['version'],'<'))return$this->status('outdated','Atualização disponível','warning','Instalada '.(string)($connection['plugin_release']?:$installed).' · oficial '.$official['release'].'.');
        $testedAt=strtotime((string)$connection['last_tested_at']);
        if($testedAt!==false&&$testedAt<time()-172800)return$this->status('stale','Verificação vencida','warning','Execute uma nova verificação para confirmar a disponibilidade.');
        return$this->status('current','Atualizado','success','Plugin '.$official['release'].' conectado e disponível.');
    }

    /** @return array{code:string,label:string,class:string,detail:string} */
    private function status(string$code,string$label,string$class,string$detail):array{return compact('code','label','class','detail');}
}
