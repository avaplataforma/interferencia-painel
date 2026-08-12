<?php

namespace local_mundointer\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

final class sync_trail_sections extends external_api
{
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'courseid'=>new external_value(PARAM_INT,'Curso de Trilha gerenciado pelo Mundo Inter.'),
            'sections'=>new external_value(PARAM_RAW,'Lista JSON dos Cursos individuais na ordem comercial.'),
        ]);
    }

    public static function execute(int$courseid,string$sections):array
    {
        global$CFG,$DB;
        $parameters=self::validate_parameters(self::execute_parameters(),compact('courseid','sections'));
        $context=\context_system::instance();
        self::validate_context($context);
        require_capability('local/mundointer:manage',$context);

        $course=$DB->get_record('course',['id'=>$parameters['courseid']],'id,idnumber',MUST_EXIST);
        if(!str_starts_with((string)$course->idnumber,'mi-trilha-'))throw new \invalid_parameter_exception('Somente Trilhas gerenciadas pelo Mundo Inter podem ter os blocos sincronizados.');
        $decoded=json_decode($parameters['sections'],true);
        if(!is_array($decoded))throw new \invalid_parameter_exception('A composição da Trilha não contém um JSON válido.');
        $decoded=array_values(array_filter($decoded,'is_array'));
        if(count($decoded)<2||count($decoded)>100)throw new \invalid_parameter_exception('A Trilha deve conter entre 2 e 100 Cursos individuais.');

        require_once($CFG->dirroot.'/course/lib.php');
        $updated=0;
        foreach($decoded as$offset=>$item){
            $number=$offset+1;
            $name=trim(clean_param((string)($item['name']??''),PARAM_TEXT));
            if($name==='')throw new \invalid_parameter_exception('Todos os blocos da Trilha precisam de nome.');
            $catalog=trim(clean_param((string)($item['catalog']??'Formação'),PARAM_TEXT));
            $execution=(string)($item['execution']??'provider_ava')==='shared_ava'?'shared_ava':'provider_ava';
            $key=trim(clean_param((string)($item['key']??('item-'.$number)),PARAM_ALPHANUMEXT));
            $accessurl=clean_param((string)($item['accessurl']??''),PARAM_URL);
            $section=$DB->get_record('course_sections',['course'=>$parameters['courseid'],'section'=>$number]);
            if(!$section)$section=course_create_section($parameters['courseid'],$number);
            $delivery=$execution==='shared_ava'
                ?'<span class="badge badge-success">Conteúdo no AVA Cursos</span>'
                :'<span class="badge badge-warning">Acesso no AVA do fornecedor</span>';
            $link=$execution!=='shared_ava'&&$accessurl!==''
                ?'<p><a class="btn btn-primary" href="'.s($accessurl).'" target="_blank" rel="noopener">Abrir ambiente do fornecedor</a></p>'
                :'';
            $summary='<!-- data-mundointer-trail-item="'.s($key).'" -->'
                .'<div class="mundointer-trail-block"><p><strong>Formação:</strong> '.s($catalog).'</p><p>'.$delivery.'</p>'.$link
                .'<p><small>Bloco sincronizado automaticamente pelo Mundo Inter.</small></p></div>';
            $DB->update_record('course_sections',(object)[
                'id'=>$section->id,
                'name'=>$name,
                'summary'=>$summary,
                'summaryformat'=>FORMAT_HTML,
                'visible'=>1,
            ]);
            $updated++;
        }

        $hidden=0;
        $existing=$DB->get_records_select('course_sections','course=:course AND section>:last',['course'=>$parameters['courseid'],'last'=>count($decoded)],'section ASC');
        foreach($existing as$section){
            if(!str_contains((string)$section->summary,'data-mundointer-trail-item='))continue;
            if((int)$section->visible!==0){$DB->set_field('course_sections','visible',0,['id'=>$section->id]);$hidden++;}
        }
        rebuild_course_cache($parameters['courseid'],true);
        return['status'=>'ok','courseid'=>$parameters['courseid'],'sections'=>$updated,'hidden'=>$hidden];
    }

    public static function execute_returns(): external_single_structure
    {
        return new external_single_structure([
            'status'=>new external_value(PARAM_ALPHA,'Estado da sincronização.'),
            'courseid'=>new external_value(PARAM_INT,'Curso atualizado.'),
            'sections'=>new external_value(PARAM_INT,'Quantidade de blocos sincronizados.'),
            'hidden'=>new external_value(PARAM_INT,'Blocos antigos ocultados.'),
        ]);
    }
}
