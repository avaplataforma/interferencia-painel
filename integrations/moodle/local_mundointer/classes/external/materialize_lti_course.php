<?php

namespace local_mundointer\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Copies one approved IESDE Deep Linking selection into a reusable course.
 *
 * Only the Moodle LTI activity is copied. Protected material, handouts and
 * assessments remain on the provider and are rendered through LTI 1.3.
 */
final class materialize_lti_course extends external_api
{
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'sourcecmid'=>new external_value(PARAM_INT,'Atividade LTI MASTER homologada.'),
            'targetcourseid'=>new external_value(PARAM_INT,'Curso Individual definitivo.'),
            'activityname'=>new external_value(PARAM_TEXT,'Nome comercial da atividade.'),
            'idnumber'=>new external_value(PARAM_ALPHANUMEXT,'Código idempotente da atividade.'),
        ]);
    }

    public static function execute(int$sourcecmid,int$targetcourseid,string$activityname,string$idnumber):array
    {
        global$CFG,$DB;
        $parameters=self::validate_parameters(self::execute_parameters(),compact('sourcecmid','targetcourseid','activityname','idnumber'));
        $system=\context_system::instance();
        self::validate_context($system);
        require_capability('local/mundointer:manage',$system);

        $sourcecm=$DB->get_record('course_modules',['id'=>$parameters['sourcecmid']],'*',MUST_EXIST);
        $ltimodule=$DB->get_record('modules',['name'=>'lti'],'*',MUST_EXIST);
        if((int)$sourcecm->module!==(int)$ltimodule->id)throw new \invalid_parameter_exception('A origem selecionada não é uma atividade LTI.');
        $source=$DB->get_record('lti',['id'=>$sourcecm->instance],'*',MUST_EXIST);
        $course=$DB->get_record('course',['id'=>$parameters['targetcourseid']],'*',MUST_EXIST);
        if(!str_starts_with((string)$course->idnumber,'mi-master-content-'))throw new \invalid_parameter_exception('Somente Cursos Individuais MASTER gerenciados pelo Mundo Inter podem receber esta atividade.');
        self::assert_iesde_source($source);

        require_once($CFG->dirroot.'/course/lib.php');
        require_once($CFG->dirroot.'/course/modlib.php');
        require_once($CFG->dirroot.'/mod/lti/lib.php');
        require_once($CFG->dirroot.'/mod/lti/locallib.php');
        if((int)$course->enablecompletion!==1){$DB->set_field('course','enablecompletion',1,['id'=>$course->id]);$course->enablecompletion=1;}
        $section=$DB->get_record('course_sections',['course'=>$course->id,'section'=>1]);
        if(!$section)$section=course_create_section((int)$course->id,1);
        $DB->update_record('course_sections',(object)['id'=>$section->id,'name'=>'Conteúdo e atividades','visible'=>1]);

        $existing=$DB->get_record('course_modules',['course'=>$course->id,'module'=>$ltimodule->id,'idnumber'=>$parameters['idnumber']]);
        if($existing){
            $DB->set_field('lti','name',$parameters['activityname'],['id'=>$existing->instance]);
            $DB->update_record('course_modules',(object)['id'=>$existing->id,'visible'=>1,'visibleoncoursepage'=>1,'completion'=>COMPLETION_TRACKING_AUTOMATIC,'completionview'=>1]);
            rebuild_course_cache((int)$course->id,true);
            return self::result((int)$course->id,(int)$existing->instance,(int)$existing->id,true);
        }

        $moduleinfo=clone$source;
        unset($moduleinfo->id,$moduleinfo->timecreated,$moduleinfo->timemodified);
        $moduleinfo->course=(int)$course->id;
        $moduleinfo->name=$parameters['activityname'];
        $moduleinfo->modulename='lti';
        $moduleinfo->module=(int)$ltimodule->id;
        $moduleinfo->section=1;
        $moduleinfo->visible=1;
        $moduleinfo->visibleoncoursepage=1;
        $moduleinfo->cmidnumber=$parameters['idnumber'];
        $moduleinfo->groupmode=0;
        $moduleinfo->groupingid=0;
        $moduleinfo->completion=COMPLETION_TRACKING_AUTOMATIC;
        $moduleinfo->completionview=1;
        $moduleinfo->completionexpected=0;
        $moduleinfo->coursemodule=0;
        $moduleinfo->instance=0;
        $created=add_moduleinfo($moduleinfo,$course);
        $cmid=(int)($created->coursemodule??0);
        $activityid=(int)($created->instance??0);
        if($cmid<1||$activityid<1)throw new \moodle_exception('O Moodle não confirmou a criação da atividade LTI MASTER.');
        rebuild_course_cache((int)$course->id,true);
        return self::result((int)$course->id,$activityid,$cmid,false);
    }

    private static function assert_iesde_source(object$source):void
    {
        global$DB;
        $type=(int)($source->typeid??0)>0?$DB->get_record('lti_types',['id'=>(int)$source->typeid]):false;
        $typename=is_object($type)?(string)($type->name??''):'';
        $baseurl=is_object($type)?(string)($type->baseurl??''):'';
        $tooldomain=is_object($type)?(string)($type->tooldomain??''):'';
        $fingerprint=mb_strtolower((string)($source->toolurl??'').' '.$typename.' '.$baseurl.' '.$tooldomain);
        if(!str_contains($fingerprint,'iesde')&&!str_contains($fingerprint,'api-fornecimento'))throw new \invalid_parameter_exception('A atividade não pertence ao conector LTI homologado do IESDE.');
    }

    private static function result(int$courseid,int$activityid,int$cmid,bool$reused):array
    {
        return['status'=>'ok','courseid'=>$courseid,'activityid'=>$activityid,'cmid'=>$cmid,'reused'=>$reused];
    }

    public static function execute_returns():external_single_structure
    {
        return new external_single_structure([
            'status'=>new external_value(PARAM_ALPHA,'Estado da operação.'),
            'courseid'=>new external_value(PARAM_INT,'Curso Individual reutilizável.'),
            'activityid'=>new external_value(PARAM_INT,'Atividade LTI criada ou atualizada.'),
            'cmid'=>new external_value(PARAM_INT,'Módulo do curso.'),
            'reused'=>new external_value(PARAM_BOOL,'Indica se a atividade existente foi reutilizada.'),
        ]);
    }
}
