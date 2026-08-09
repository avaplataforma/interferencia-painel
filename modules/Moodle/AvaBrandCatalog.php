<?php

declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use PDO;

final readonly class AvaBrandCatalog
{
    public function __construct(private PDO $database, private string $publicBaseUrl) {}

    /** @return array{schema:int,version:string,generated_at:string,profile_field:string,franchise_field:string,pole_field:string,brands:list<array<string,mixed>>} */
    public function build(): array
    {
        $rows=$this->database->query("SELECT o.id,o.code,o.panel_slug,o.display_name,o.primary_color,o.secondary_color,o.logo_path,o.favicon_path,o.login_title,o.login_welcome_text,o.support_email,o.support_phone,o.ava_polo_name,p.code pole_code,p.name pole_name,p.legacy_value,u.code unit_code,u.name unit_name FROM organizations o INNER JOIN organization_ava_settings s ON s.organization_id=o.id LEFT JOIN organization_poles p ON p.organization_id=o.id AND p.is_active=1 LEFT JOIN units u ON u.id=p.unit_id WHERE o.status='active' AND s.access_mode IN('shared','both') ORDER BY o.display_name,p.is_primary DESC,p.name")->fetchAll()?:[];
        $brands=[];$profileField='polo_presencial';
        foreach($rows as$row){
            $id=(int)$row['id'];
            if(!isset($brands[$id])){
                $brands[$id]=[
                    'code'=>(string)$row['code'],
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
                    'pole_records'=>[],
                ];
            }
            $poleCode=trim((string)($row['pole_code']??''));
            if($poleCode!==''){
                $record=['code'=>$poleCode,'name'=>(string)$row['pole_name'],'unit_code'=>(string)($row['unit_code']??''),'legacy_value'=>(string)($row['legacy_value']??'')];
                $brands[$id]['pole_records'][]=$record;
                foreach(['pole_name','legacy_value','unit_name','ava_polo_name']as$key){$value=trim((string)($row[$key]??''));if($value!==''&&!in_array($value,$brands[$id]['poles'],true))$brands[$id]['poles'][]=$value;}
            }
        }
        $legacy=$this->database->query("SELECT o.id organization_id,pm.field_value,mf.shortname profile_field FROM organizations o LEFT JOIN ava_polo_mappings pm ON pm.organization_id=o.id LEFT JOIN moodle_profile_fields mf ON mf.id=(SELECT field_id FROM moodle_unit_mappings mum INNER JOIN units uu ON uu.id=mum.unit_id WHERE uu.organization_id=o.id LIMIT 1) WHERE o.status='active'")->fetchAll()?:[];
        foreach($legacy as$row){
            $id=(int)$row['organization_id'];if(!isset($brands[$id]))continue;
            $value=trim((string)($row['field_value']??''));
            if($value!==''){
                if(!in_array($value,$brands[$id]['poles'],true))$brands[$id]['poles'][]=$value;
                $legacyKey=$this->poleKey($value);
                foreach($brands[$id]['pole_records']as&$record){
                    if($legacyKey===''||!in_array($legacyKey,[$this->poleKey((string)$record['name']),$this->poleKey((string)$record['legacy_value'])],true))continue;
                    $record['legacy_value']=$value;
                    break;
                }
                unset($record);
            }
            $field=trim((string)($row['profile_field']??''));if($field!=='')$profileField=$field;
        }
        $brandList=array_values($brands);
        $canonical=json_encode(['profile_field'=>$profileField,'franchise_field'=>'mundointer_franchise','pole_field'=>'mundointer_pole','brands'=>$brandList],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        return['schema'=>1,'version'=>hash('sha256',$canonical),'generated_at'=>gmdate('c'),'profile_field'=>$profileField,'franchise_field'=>'mundointer_franchise','pole_field'=>'mundointer_pole','brands'=>$brandList];
    }

    /** @return list<array{slug:string,name:string,url:string,poles:list<string>}> */
    public function entryLinks(string $moodleBaseUrl): array
    {
        $base=rtrim($moodleBaseUrl,'/');$result=[];
        foreach($this->build()['brands']as$brand)$result[]=['slug'=>(string)$brand['slug'],'name'=>(string)$brand['name'],'url'=>$base.'/franquia.php?slug='.rawurlencode((string)$brand['slug']),'poles'=>$brand['poles']];
        return$result;
    }

    private function assetUrl(string $path,string $fallback): string
    {
        $path=trim($path)!==''?$path:$fallback;
        if(is_string($path)&&filter_var($path,FILTER_VALIDATE_URL)!==false)return$path;
        return rtrim($this->publicBaseUrl,'/').'/'.ltrim((string)$path,'/');
    }

    private function color(string $value,string $fallback): string{return preg_match('/^#[0-9a-fA-F]{6}$/',trim($value))===1?strtolower(trim($value)):$fallback;}

    private function poleKey(string $value): string
    {
        $value=mb_strtolower(trim(preg_replace('/\s+/u',' ',$value)??''));
        $value=(string)(iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value)?:$value);
        $value=preg_replace('/\s*(?:,|\/|-)\s*[a-z]{2}$/i','',$value)??$value;
        return trim(preg_replace('/[^a-z0-9]+/i',' ',$value)??$value);
    }
}
