<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

use PDO;
use RuntimeException;
use Throwable;

final readonly class PlatformSettingsRepository
{
    public function __construct(private PDO $database) {}

    public function settings(): array
    {
        try{$row=$this->database->query('SELECT * FROM platform_settings WHERE id=1')->fetch();return is_array($row)?$row:$this->defaults();}catch(Throwable){return$this->defaults();}
    }

    public function save(array$data):void
    {
        $name=trim((string)($data['display_name']??''));$primary=self::color((string)($data['primary_color']??''));$secondary=self::color((string)($data['secondary_color']??''));$title=trim((string)($data['login_title']??''));$welcome=trim((string)($data['login_welcome_text']??''));$email=strtolower(trim((string)($data['support_email']??'')));$phone=trim((string)($data['support_phone']??''));
        if(mb_strlen($name)<2||mb_strlen($name)>120)throw new RuntimeException('Informe um nome de exibição válido.');
        if($primary===null||$secondary===null)throw new RuntimeException('Informe cores válidas no formato hexadecimal.');
        if(mb_strlen($title)>160||mb_strlen($welcome)>500||mb_strlen($phone)>30)throw new RuntimeException('Um dos textos excede o tamanho permitido.');
        if($email!==''&&filter_var($email,FILTER_VALIDATE_EMAIL)===false)throw new RuntimeException('Informe um e-mail de suporte válido.');
        $s=$this->database->prepare('UPDATE platform_settings SET display_name=:name,primary_color=:primary,secondary_color=:secondary,login_title=:title,login_welcome_text=:welcome,support_email=:email,support_phone=:phone WHERE id=1');$s->execute(['name'=>$name,'primary'=>$primary,'secondary'=>$secondary,'title'=>$title!==''?$title:null,'welcome'=>$welcome!==''?$welcome:null,'email'=>$email!==''?$email:null,'phone'=>$phone!==''?$phone:null]);
    }

    public function updateBrandingPaths(?string$logo,?string$favicon):void{$s=$this->database->prepare('UPDATE platform_settings SET logo_path=:logo,favicon_path=:favicon WHERE id=1');$s->execute(['logo'=>$logo??'/assets/media/mundo-inter-logo.png','favicon'=>$favicon??'/assets/media/mundo-inter-favicon.png']);}
    private static function color(string$value):?string{$value=strtolower(trim($value));return preg_match('/^#[0-9a-f]{6}$/',$value)===1?$value:null;}
    private function defaults():array{return['id'=>1,'display_name'=>'MUNDO INTER','primary_color'=>'#ed1c24','secondary_color'=>'#082d72','logo_path'=>'/assets/media/mundo-inter-logo.png','favicon_path'=>'/assets/media/mundo-inter-favicon.png','login_title'=>'MUNDO INTER','login_welcome_text'=>'Use suas credenciais para continuar.','support_email'=>null,'support_phone'=>null];}
}
