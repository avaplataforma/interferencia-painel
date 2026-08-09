<?php

declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use PDO;

final readonly class AvaBrandCatalog
{
    public function __construct(private PDO $database, private string $publicBaseUrl) {}

    /** @return array{schema:int,version:string,generated_at:string,profile_field:string,brands:list<array<string,mixed>>} */
    public function build(): array
    {
        $rows=$this->database->query("SELECT o.id,o.panel_slug,o.display_name,o.primary_color,o.secondary_color,o.logo_path,o.favicon_path,o.login_title,o.login_welcome_text,o.support_email,o.support_phone,u.name unit_name,m.field_value,mf.shortname profile_field FROM organizations o INNER JOIN organization_ava_settings s ON s.organization_id=o.id LEFT JOIN units u ON u.organization_id=o.id AND u.is_active=1 LEFT JOIN moodle_unit_mappings m ON m.unit_id=u.id LEFT JOIN moodle_profile_fields mf ON mf.id=m.field_id WHERE o.status='active' AND s.access_mode IN('shared','both') ORDER BY o.display_name,u.name,m.field_value")->fetchAll()?:[];
        $brands=[];$profileField='polo_presencial';
        foreach($rows as$row){
            $id=(int)$row['id'];
            if(!isset($brands[$id])){
                $brands[$id]=[
                    'slug'=>(string)$row['panel_slug'],
                    'name'=>(string)$row['display_name'],
                    'primary_color'=>$this->color((string)$row['primary_color'],'#ed1c24'),
                    'secondary_color'=>$this->color((string)($row['secondary_color']??''),'#082d72'),
                    'logo_url'=>$this->assetUrl((string)($row['logo_path']??''),'/assets/media/mundo-inter-logo.png'),
                    'favicon_url'=>$this->assetUrl((string)($row['favicon_path']??''),'/assets/media/mundo-inter-favicon.png'),
                    'login_title'=>(string)($row['login_title']?:$row['display_name']),
                    'welcome_text'=>(string)($row['login_welcome_text']?:'Use suas credenciais para continuar.'),
                    'support_email'=>(string)($row['support_email']??''),
                    'support_phone'=>(string)($row['support_phone']??''),
                    'poles'=>[],
                ];
            }
            foreach(['unit_name','field_value']as$key){$value=trim((string)($row[$key]??''));if($value!==''&&!in_array($value,$brands[$id]['poles'],true))$brands[$id]['poles'][]=$value;}
            $field=trim((string)($row['profile_field']??''));if($field!=='')$profileField=$field;
        }
        $brandList=array_values($brands);
        $canonical=json_encode(['profile_field'=>$profileField,'brands'=>$brandList],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        return['schema'=>1,'version'=>hash('sha256',$canonical),'generated_at'=>gmdate('c'),'profile_field'=>$profileField,'brands'=>$brandList];
    }

    /** @return list<array{slug:string,name:string,url:string,poles:list<string>}> */
    public function entryLinks(string $moodleBaseUrl): array
    {
        $base=rtrim($moodleBaseUrl,'/');$result=[];
        foreach($this->build()['brands']as$brand)$result[]=['slug'=>(string)$brand['slug'],'name'=>(string)$brand['name'],'url'=>$base.'/local/mundointer/entrar.php?franquia='.rawurlencode((string)$brand['slug']),'poles'=>$brand['poles']];
        return$result;
    }

    private function assetUrl(string $path,string $fallback): string
    {
        $path=trim($path)!==''?$path:$fallback;
        if(is_string($path)&&filter_var($path,FILTER_VALIDATE_URL)!==false)return$path;
        return rtrim($this->publicBaseUrl,'/').'/'.ltrim((string)$path,'/');
    }

    private function color(string $value,string $fallback): string{return preg_match('/^#[0-9a-fA-F]{6}$/',trim($value))===1?strtolower(trim($value)):$fallback;}
}
