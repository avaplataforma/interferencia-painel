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

        $course=$DB->get_record('course',['id'=>$parameters['courseid']],'*',MUST_EXIST);
        if(!str_starts_with((string)$course->idnumber,'mi-trilha-'))throw new \invalid_parameter_exception('Somente Trilhas gerenciadas pelo Mundo Inter podem ter os blocos sincronizados.');
        $decoded=json_decode($parameters['sections'],true);
        if(!is_array($decoded))throw new \invalid_parameter_exception('A composição da Trilha não contém um JSON válido.');
        $decoded=array_values(array_filter($decoded,'is_array'));
        if(count($decoded)<2||count($decoded)>100)throw new \invalid_parameter_exception('A Trilha deve conter entre 2 e 100 Cursos individuais.');

        require_once($CFG->dirroot.'/course/lib.php');
        require_once($CFG->dirroot.'/course/modlib.php');
        require_once($CFG->dirroot.'/mod/url/lib.php');
        require_once($CFG->dirroot.'/mod/url/locallib.php');
        if((int)$course->enablecompletion!==1){
            $DB->set_field('course','enablecompletion',1,['id'=>$course->id]);
            $course->enablecompletion=1;
        }
        $updated=0;
        $activities=0;
        $hiddenactivities=0;
        foreach($decoded as$offset=>$item){
            $number=$offset+1;
            $name=trim(clean_param((string)($item['name']??''),PARAM_TEXT));
            if($name==='')throw new \invalid_parameter_exception('Todos os blocos da Trilha precisam de nome.');
            $key=trim(clean_param((string)($item['key']??('item-'.$number)),PARAM_ALPHANUMEXT));
            $accessurl=clean_param((string)($item['accessurl']??''),PARAM_URL);
            $section=$DB->get_record('course_sections',['course'=>$parameters['courseid'],'section'=>$number]);
            if(!$section)$section=course_create_section($parameters['courseid'],$number);
            // Keep the management marker invisible. The learner only needs the
            // sequential module title and the activity that opens the lesson.
            $summary='<!-- data-mundointer-trail-item="'.s($key).'" -->';
            $DB->update_record('course_sections',(object)[
                'id'=>$section->id,
                'name'=>'Módulo '.$number.' - '.$name,
                'summary'=>$summary,
                'summaryformat'=>FORMAT_HTML,
                'visible'=>1,
            ]);
            $activity=self::sync_url_activity($course,$section,$number,$key,$name,$accessurl);
            if($activity>0)$activities++;
            if($activity<0)$hiddenactivities++;
            $updated++;
        }

        $hidden=0;
        $existing=$DB->get_records_select('course_sections','course=:course AND section>:last',['course'=>$parameters['courseid'],'last'=>count($decoded)],'section ASC');
        foreach($existing as$section){
            if(!str_contains((string)$section->summary,'data-mundointer-trail-item='))continue;
            if((int)$section->visible!==0){$DB->set_field('course_sections','visible',0,['id'=>$section->id]);$hidden++;}
            $managed=$DB->get_records_select('course_modules','course=:course AND section=:section AND idnumber LIKE :prefix',['course'=>$course->id,'section'=>$section->id,'prefix'=>'mi-trail-url-%']);
            foreach($managed as$cm){
                set_coursemodule_visible((int)$cm->id,0);
                $hiddenactivities++;
            }
        }
        rebuild_course_cache($parameters['courseid'],true);
        return['status'=>'ok','courseid'=>$parameters['courseid'],'sections'=>$updated,'hidden'=>$hidden,'activities'=>$activities,'hiddenactivities'=>$hiddenactivities];
    }

    private static function sync_url_activity(object$course,object$section,int$sectionnumber,string$key,string$name,string$accessurl):int
    {
        global$DB;
        $module=$DB->get_record('modules',['name'=>'url'],'id',MUST_EXIST);
        $idnumber=\core_text::substr('mi-trail-url-'.$key,0,100);
        $existing=$DB->get_record('course_modules',['course'=>$course->id,'module'=>$module->id,'idnumber'=>$idnumber]);
        if($accessurl===''){
            if($existing){set_coursemodule_visible((int)$existing->id,0);return-1;}
            return 0;
        }

        $activityname=\core_text::substr('Aula - '.$name,0,255);
        if($existing){
            $url=$DB->get_record('url',['id'=>$existing->instance],'*',MUST_EXIST);
            $url->name=$activityname;
            $url->externalurl=url_fix_submitted_url($accessurl);
            $url->display=RESOURCELIB_DISPLAY_EMBED;
            $url->displayoptions=serialize(['printintro'=>0]);
            // The form labels this variable as "id", but Moodle stores and
            // resolves it internally by the canonical key "userid".
            $url->parameters=serialize(['ext_user_username'=>'userid']);
            $url->timemodified=time();
            $DB->update_record('url',$url);
            $DB->update_record('course_modules',(object)[
                'id'=>$existing->id,
                'visible'=>1,
                'visibleold'=>1,
                'visibleoncoursepage'=>1,
                'completion'=>COMPLETION_TRACKING_AUTOMATIC,
                'completionview'=>1,
                'completionexpected'=>0,
                'showdescription'=>0,
            ]);
            if((int)$existing->section!==(int)$section->id){
                $cm=get_fast_modinfo($course,0,true)->get_cm((int)$existing->id);
                moveto_module($cm,$section);
            }
            return(int)$existing->id;
        }

        $created=add_moduleinfo((object)[
            'modulename'=>'url',
            'module'=>(int)$module->id,
            'section'=>$sectionnumber,
            'name'=>$activityname,
            'intro'=>'',
            'introformat'=>FORMAT_HTML,
            'externalurl'=>$accessurl,
            'display'=>RESOURCELIB_DISPLAY_EMBED,
            'printintro'=>0,
            'parameter_0'=>'ext_user_username',
            'variable_0'=>'userid',
            'cmidnumber'=>$idnumber,
            'visible'=>1,
            'completion'=>COMPLETION_TRACKING_AUTOMATIC,
            'completionview'=>1,
            'completionexpected'=>0,
            'showdescription'=>0,
        ],$course);
        return(int)$created->coursemodule;
    }

    public static function execute_returns(): external_single_structure
    {
        return new external_single_structure([
            'status'=>new external_value(PARAM_ALPHA,'Estado da sincronização.'),
            'courseid'=>new external_value(PARAM_INT,'Curso atualizado.'),
            'sections'=>new external_value(PARAM_INT,'Quantidade de blocos sincronizados.'),
            'hidden'=>new external_value(PARAM_INT,'Blocos antigos ocultados.'),
            'activities'=>new external_value(PARAM_INT,'Atividades URL criadas ou atualizadas.'),
            'hiddenactivities'=>new external_value(PARAM_INT,'Atividades URL antigas ocultadas.'),
        ]);
    }
}
