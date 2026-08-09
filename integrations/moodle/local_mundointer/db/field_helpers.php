<?php

defined('MOODLE_INTERNAL') || die();

function local_mundointer_ensure_identity_fields(): void
{
    global $DB;
    $category=$DB->get_record('user_info_category',['name'=>'Mundo Inter']);
    if(!$category){$category=(object)['name'=>'Mundo Inter','sortorder'=>(int)$DB->get_field_sql('SELECT COALESCE(MAX(sortorder),0)+1 FROM {user_info_category}')];$category->id=$DB->insert_record('user_info_category',$category);}
    $definitions=[
        'mundointer_franchise'=>'Franquia Mundo Inter',
        'mundointer_pole'=>'Polo Mundo Inter',
    ];
    foreach($definitions as$shortname=>$name){
        if($DB->record_exists('user_info_field',['shortname'=>$shortname]))continue;
        $field=(object)[
            'shortname'=>$shortname,'name'=>$name,'datatype'=>'text','description'=>'Identificador técnico sincronizado pelo Mundo Inter.','descriptionformat'=>FORMAT_HTML,'categoryid'=>$category->id,
            'sortorder'=>(int)$DB->get_field_sql('SELECT COALESCE(MAX(sortorder),0)+1 FROM {user_info_field} WHERE categoryid=?',[$category->id]),'required'=>0,'locked'=>0,'visible'=>0,'forceunique'=>0,'signup'=>0,
            'defaultdata'=>'','defaultdataformat'=>FORMAT_PLAIN,'param1'=>'30','param2'=>'255','param3'=>'0','param4'=>'','param5'=>'',
        ];
        $DB->insert_record('user_info_field',$field);
    }
}

/** @param array<string,mixed> $catalog */
function local_mundointer_migrate_profile_identities(array $catalog): int
{
    global $DB;
    local_mundointer_ensure_identity_fields();
    $legacyshort=(string)($catalog['profile_field']??'polo_presencial');
    $franchiseshort=(string)($catalog['franchise_field']??'mundointer_franchise');
    $poleshort=(string)($catalog['pole_field']??'mundointer_pole');
    $legacy=$DB->get_record('user_info_field',['shortname'=>$legacyshort]);
    $franchise=$DB->get_record('user_info_field',['shortname'=>$franchiseshort]);
    $pole=$DB->get_record('user_info_field',['shortname'=>$poleshort]);
    if(!$legacy||!$franchise||!$pole)return 0;
    $map=[];
    foreach((array)($catalog['brands']??[])as$brand){
        if(!is_array($brand))continue;$franchisecode=(string)($brand['code']??'');if($franchisecode==='')continue;
        $records=(array)($brand['pole_records']??[]);
        foreach($records as$record){
            if(!is_array($record)||empty($record['code']))continue;
            foreach(['legacy_value','name']as$key){$alias=local_mundointer_normalize_identity((string)($record[$key]??''));if($alias!=='')$map[$alias]=[$franchisecode,(string)$record['code']];}
        }
        if(count($records)===1&&is_array($records[0]??null)&&!empty($records[0]['code']))foreach((array)($brand['poles']??[])as$alias){$alias=local_mundointer_normalize_identity((string)$alias);if($alias!=='')$map[$alias]=[$franchisecode,(string)$records[0]['code']];}
    }
    if($map===[])return 0;
    $rows=$DB->get_records('user_info_data',['fieldid'=>$legacy->id]);$migrated=0;
    foreach($rows as$row){$identity=$map[local_mundointer_normalize_identity((string)$row->data)]??null;if($identity===null)continue;local_mundointer_upsert_profile_value((int)$row->userid,(int)$franchise->id,$identity[0]);local_mundointer_upsert_profile_value((int)$row->userid,(int)$pole->id,$identity[1]);$migrated++;}
    return$migrated;
}

function local_mundointer_upsert_profile_value(int $userId,int $fieldId,string $value): void
{
    global $DB;
    $existing=$DB->get_record('user_info_data',['userid'=>$userId,'fieldid'=>$fieldId]);
    if($existing){$existing->data=$value;$existing->dataformat=0;$DB->update_record('user_info_data',$existing);return;}
    $DB->insert_record('user_info_data',(object)['userid'=>$userId,'fieldid'=>$fieldId,'data'=>$value,'dataformat'=>0]);
}

function local_mundointer_normalize_identity(string $value): string
{
    $value=core_text::strtolower(trim($value));return preg_replace('/\s+/u',' ',$value)??$value;
}
