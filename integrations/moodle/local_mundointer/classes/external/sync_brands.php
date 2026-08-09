<?php

namespace local_mundointer\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;

final class sync_brands extends external_api
{
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters(['catalog'=>new external_value(PARAM_RAW,'Catálogo JSON assinado pela conexão autenticada do ADM Central.')]);
    }

    public static function execute(string $catalog): array
    {
        self::validate_context(\context_system::instance());
        require_capability('local/mundointer:manage',\context_system::instance());
        $data=json_decode($catalog,true);
        if(!is_array($data)||(int)($data['schema']??0)!==1||!is_array($data['brands']??null))throw new invalid_parameter_exception('Catálogo de identidades inválido.');
        if(count($data['brands'])>500)throw new invalid_parameter_exception('O catálogo excede o limite de franquias.');
        $brands=[];
        foreach($data['brands']as$brand){
            if(!is_array($brand))continue;
            $slug=clean_param((string)($brand['slug']??''),PARAM_ALPHANUMEXT);
            $name=clean_param((string)($brand['name']??''),PARAM_TEXT);
            if($slug===''||$name==='')continue;
            $primary=self::color((string)($brand['primary_color']??''),'#ed1c24');
            $secondary=self::color((string)($brand['secondary_color']??''),'#082d72');
            $poles=[];
            foreach((array)($brand['poles']??[])as$polo){$polo=clean_param((string)$polo,PARAM_TEXT);if($polo!==''&&!in_array($polo,$poles,true))$poles[]=$polo;}
            $polerecords=[];
            foreach((array)($brand['pole_records']??[])as$record){
                if(!is_array($record))continue;$code=clean_param((string)($record['code']??''),PARAM_ALPHANUMEXT);$polename=clean_param((string)($record['name']??''),PARAM_TEXT);if($code===''||$polename==='')continue;
                $polerecords[]=['code'=>$code,'name'=>$polename,'unit_code'=>clean_param((string)($record['unit_code']??''),PARAM_ALPHANUMEXT),'legacy_value'=>clean_param((string)($record['legacy_value']??''),PARAM_TEXT)];
            }
            $brands[]=[
                'code'=>clean_param((string)($brand['code']??$slug),PARAM_ALPHANUMEXT),
                'slug'=>$slug,'name'=>$name,'primary_color'=>$primary,'secondary_color'=>$secondary,
                'logo_url'=>clean_param((string)($brand['logo_url']??''),PARAM_URL),
                'favicon_url'=>clean_param((string)($brand['favicon_url']??''),PARAM_URL),
                'login_title'=>clean_param((string)($brand['login_title']??$name),PARAM_TEXT),
                'welcome_text'=>clean_param((string)($brand['welcome_text']??''),PARAM_TEXT),
                'support_email'=>clean_param((string)($brand['support_email']??''),PARAM_EMAIL),
                'support_phone'=>clean_param((string)($brand['support_phone']??''),PARAM_TEXT),
                'poles'=>$poles,'pole_records'=>$polerecords,
            ];
        }
        $profilefield=clean_param((string)($data['profile_field']??'polo_presencial'),PARAM_ALPHANUMEXT)?:'polo_presencial';
        $franchisefield=clean_param((string)($data['franchise_field']??'mundointer_franchise'),PARAM_ALPHANUMEXT)?:'mundointer_franchise';
        $polefield=clean_param((string)($data['pole_field']??'mundointer_pole'),PARAM_ALPHANUMEXT)?:'mundointer_pole';
        $stored=['schema'=>1,'version'=>clean_param((string)($data['version']??''),PARAM_ALPHANUM),'generated_at'=>clean_param((string)($data['generated_at']??''),PARAM_TEXT),'profile_field'=>$profilefield,'franchise_field'=>$franchisefield,'pole_field'=>$polefield,'brands'=>$brands];
        set_config('brandcatalog',json_encode($stored,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'local_mundointer');
        set_config('brand_catalog_version',(string)$stored['version'],'local_mundointer');
        set_config('brand_synced_at',time(),'local_mundointer');
        require_once(__DIR__.'/../../db/field_helpers.php');
        $migrated=local_mundointer_migrate_profile_identities($stored);
        return['status'=>'ok','count'=>count($brands),'migrated'=>$migrated,'version'=>(string)$stored['version'],'syncedat'=>time()];
    }

    public static function execute_returns(): external_single_structure
    {
        return new external_single_structure([
            'status'=>new external_value(PARAM_ALPHA,'Estado da sincronização.'),
            'count'=>new external_value(PARAM_INT,'Quantidade de identidades recebidas.'),
            'migrated'=>new external_value(PARAM_INT,'Usuários migrados do campo antigo.'),
            'version'=>new external_value(PARAM_ALPHANUM,'Versão do catálogo.'),
            'syncedat'=>new external_value(PARAM_INT,'Horário da sincronização.'),
        ]);
    }

    private static function color(string$value,string$fallback):string{return preg_match('/^#[0-9a-fA-F]{6}$/',trim($value))===1?strtolower(trim($value)):$fallback;}
}
