<?php

namespace local_mundointer\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

final class diagnose_poles extends external_api
{
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([]);
    }

    public static function execute(): array
    {
        global $DB;
        self::validate_context(\context_system::instance());
        require_capability('local/mundointer:manage',\context_system::instance());
        $catalog=json_decode((string)get_config('local_mundointer','brandcatalog'),true);
        $shortname=clean_param((string)($catalog['profile_field']??get_config('local_mundointer','profilefield')?:'polo_presencial'),PARAM_ALPHANUMEXT)?:'polo_presencial';
        $field=$DB->get_record('user_info_field',['shortname'=>$shortname],'id',IGNORE_MISSING);
        $total=(int)$DB->count_records_select('user','deleted=0 AND username<>:guest',['guest'=>'guest']);
        if($field===false)return['profilefield'=>$shortname,'fieldexists'=>false,'totalusers'=>$total,'emptyusers'=>$total,'values'=>[]];
        $sql='SELECT d.data fieldvalue,COUNT(DISTINCT u.id) usercount FROM {user_info_data} d JOIN {user} u ON u.id=d.userid WHERE d.fieldid=:fieldid AND u.deleted=0 AND u.username<>:guest GROUP BY d.data ORDER BY usercount DESC';
        $records=$DB->get_records_sql($sql,['fieldid'=>(int)$field->id,'guest'=>'guest'],0,501);
        $values=[];$nonempty=0;
        foreach($records as$record){$value=trim((string)$record->fieldvalue);$users=(int)$record->usercount;if($value==='')continue;$nonempty+=$users;if(count($values)<500)$values[]=['value'=>$value,'users'=>$users];}
        return['profilefield'=>$shortname,'fieldexists'=>true,'totalusers'=>$total,'emptyusers'=>max(0,$total-$nonempty),'values'=>$values];
    }

    public static function execute_returns(): external_single_structure
    {
        return new external_single_structure([
            'profilefield'=>new external_value(PARAM_ALPHANUMEXT,'Nome breve do campo personalizado.'),
            'fieldexists'=>new external_value(PARAM_BOOL,'Indica se o campo existe.'),
            'totalusers'=>new external_value(PARAM_INT,'Usuários ativos considerados.'),
            'emptyusers'=>new external_value(PARAM_INT,'Usuários sem valor no campo.'),
            'values'=>new external_multiple_structure(new external_single_structure([
                'value'=>new external_value(PARAM_TEXT,'Valor encontrado no Polo Presencial.'),
                'users'=>new external_value(PARAM_INT,'Quantidade de usuários com o valor.'),
            ]),'Valores agregados do campo.'),
        ]);
    }
}
