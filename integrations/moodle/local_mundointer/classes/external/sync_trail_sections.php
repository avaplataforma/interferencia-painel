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
        require_once($CFG->dirroot.'/mod/quiz/lib.php');
        require_once($CFG->dirroot.'/mod/quiz/locallib.php');
        require_once($CFG->dirroot.'/question/editlib.php');
        if((int)$course->enablecompletion!==1){
            $DB->set_field('course','enablecompletion',1,['id'=>$course->id]);
            $course->enablecompletion=1;
        }
        $updated=0;
        $activities=0;
        $hiddenactivities=0;
        $quizzes=0;
        $quizquestions=0;
        $hiddenquizzes=0;
        $examconflicts=0;
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
            $exam=is_array($item['exam']??null)?$item['exam']:null;
            $quizresult=self::sync_quiz_activity($course,$section,$number,$key,$name,$exam);
            if($quizresult['quiz']>0)$quizzes++;
            if($quizresult['quiz']<0)$hiddenquizzes++;
            $quizquestions+=$quizresult['questions'];
            $examconflicts+=$quizresult['conflict'];
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
            $managedquizzes=$DB->get_records_select('course_modules','course=:course AND section=:section AND idnumber LIKE :prefix',['course'=>$course->id,'section'=>$section->id,'prefix'=>'mi-trail-exam-%']);
            foreach($managedquizzes as$cm){set_coursemodule_visible((int)$cm->id,0);$hiddenquizzes++;}
        }
        rebuild_course_cache($parameters['courseid'],true);
        return['status'=>'ok','courseid'=>$parameters['courseid'],'sections'=>$updated,'hidden'=>$hidden,'activities'=>$activities,'hiddenactivities'=>$hiddenactivities,'quizzes'=>$quizzes,'quizquestions'=>$quizquestions,'hiddenquizzes'=>$hiddenquizzes,'examconflicts'=>$examconflicts];
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

    /** @return array{quiz:int,questions:int,conflict:int} */
    private static function sync_quiz_activity(object$course,object$section,int$sectionnumber,string$key,string$name,?array$exam):array
    {
        global$DB;
        $module=$DB->get_record('modules',['name'=>'quiz'],'id',MUST_EXIST);
        $idnumber=\core_text::substr('mi-trail-exam-'.$key,0,100);
        $existing=$DB->get_record('course_modules',['course'=>$course->id,'module'=>$module->id,'idnumber'=>$idnumber]);
        if($exam===null||!is_array($exam['questions']??null)||$exam['questions']===[]){
            if($existing){set_coursemodule_visible((int)$existing->id,0);return['quiz'=>-1,'questions'=>0,'conflict'=>0];}
            return['quiz'=>0,'questions'=>0,'conflict'=>0];
        }

        $signature=clean_param((string)($exam['signature']??''),PARAM_ALPHANUMEXT);
        if($signature==='')throw new \invalid_parameter_exception('A avaliação recebida não possui assinatura de integridade.');
        $questions=array_values(array_filter(array_slice($exam['questions'],0,100),'is_array'));
        if($questions===[])return['quiz'=>0,'questions'=>0,'conflict'=>0];
        $marker='<!-- data-mundointer-exam-signature="'.s($signature).'" -->';

        if($existing){
            $quiz=$DB->get_record('quiz',['id'=>$existing->instance],'*',MUST_EXIST);
            $same=str_contains((string)$quiz->intro,'data-mundointer-exam-signature="'.s($signature).'"');
            $slotcount=(int)$DB->count_records('quiz_slots',['quizid'=>$quiz->id]);
            if($same&&$slotcount===count($questions)){
                $quiz->name=\core_text::substr('Avaliação - '.$name,0,255);
                $quiz->timemodified=time();
                $DB->update_record('quiz',$quiz);
                set_coursemodule_visible((int)$existing->id,1);
                if((int)$existing->section!==(int)$section->id){
                    $cm=get_fast_modinfo($course,0,true)->get_cm((int)$existing->id);
                    moveto_module($cm,$section);
                }
                return['quiz'=>(int)$existing->id,'questions'=>$slotcount,'conflict'=>0];
            }
            if($DB->record_exists('quiz_attempts',['quiz'=>$quiz->id])){
                set_coursemodule_visible((int)$existing->id,1);
                return['quiz'=>(int)$existing->id,'questions'=>$slotcount,'conflict'=>1];
            }
            course_delete_module((int)$existing->id,true);
        }

        $activityname=\core_text::substr('Avaliação - '.$name,0,255);
        $created=add_moduleinfo((object)[
            'modulename'=>'quiz','module'=>(int)$module->id,'section'=>$sectionnumber,
            'name'=>$activityname,'intro'=>$marker,'introformat'=>FORMAT_HTML,
            'timeopen'=>0,'timeclose'=>0,'timelimit'=>0,'overduehandling'=>'autosubmit','graceperiod'=>0,
            'preferredbehaviour'=>'deferredfeedback','attempts'=>0,'attemptonlast'=>0,
            'grademethod'=>QUIZ_GRADEHIGHEST,'decimalpoints'=>2,'questiondecimalpoints'=>-1,
            'questionsperpage'=>1,'navmethod'=>QUIZ_NAVMETHOD_FREE,'shuffleanswers'=>1,
            'sumgrades'=>0,'grade'=>10,'quizpassword'=>'','subnet'=>'','browsersecurity'=>'',
            'delay1'=>0,'delay2'=>0,'showuserpicture'=>0,'showblocks'=>0,
            'attemptduring'=>1,'correctnessduring'=>0,'maxmarksduring'=>1,'marksduring'=>1,
            'specificfeedbackduring'=>0,'generalfeedbackduring'=>0,'rightanswerduring'=>0,'overallfeedbackduring'=>0,
            'attemptimmediately'=>1,'correctnessimmediately'=>1,'maxmarksimmediately'=>1,'marksimmediately'=>1,
            'specificfeedbackimmediately'=>1,'generalfeedbackimmediately'=>1,'rightanswerimmediately'=>1,'overallfeedbackimmediately'=>1,
            'attemptopen'=>1,'correctnessopen'=>1,'maxmarksopen'=>1,'marksopen'=>1,'specificfeedbackopen'=>1,
            'generalfeedbackopen'=>1,'rightansweropen'=>1,'overallfeedbackopen'=>1,
            'attemptclosed'=>1,'correctnessclosed'=>1,'maxmarksclosed'=>1,'marksclosed'=>1,'specificfeedbackclosed'=>1,
            'generalfeedbackclosed'=>1,'rightanswerclosed'=>1,'overallfeedbackclosed'=>1,
            'cmidnumber'=>$idnumber,'visible'=>1,'completion'=>COMPLETION_TRACKING_AUTOMATIC,
            'completionview'=>1,'completionexpected'=>0,'showdescription'=>0,
        ],$course);
        $quiz=$DB->get_record('quiz',['id'=>$created->instance],'*',MUST_EXIST);
        $category=self::question_category($course,$key,$signature);
        $createdquestions=0;
        foreach($questions as$index=>$question){
            $questionid=self::create_multichoice_question($category,$key,$signature,$index+1,$question);
            quiz_add_quiz_question($questionid,$quiz,0,1.0);
            $createdquestions++;
        }
        $quiz=$DB->get_record('quiz',['id'=>$quiz->id],'*',MUST_EXIST);
        quiz_set_grade(10.0,$quiz);
        return['quiz'=>(int)$created->coursemodule,'questions'=>$createdquestions,'conflict'=>0];
    }

    private static function question_category(object$course,string$key,string$signature):object
    {
        global$DB;
        $context=\context_course::instance((int)$course->id);
        $idnumber=\core_text::substr('mi-exam-'.$key.'-'.substr($signature,0,12),0,100);
        $category=$DB->get_record('question_categories',['contextid'=>$context->id,'idnumber'=>$idnumber]);
        if($category)return$category;
        $category=(object)[
            'name'=>\core_text::substr('Mundo Inter - '.$key,0,255),'contextid'=>$context->id,
            'info'=>'Questões sincronizadas automaticamente pelo Mundo Inter.','infoformat'=>FORMAT_HTML,
            'stamp'=>make_unique_id_code(),'parent'=>0,'sortorder'=>999,'idnumber'=>$idnumber,
        ];
        $category->id=$DB->insert_record('question_categories',$category);
        return$category;
    }

    private static function create_multichoice_question(object$category,string$key,string$signature,int$position,array$data):int
    {
        $text=clean_param((string)($data['text']??''),PARAM_CLEANHTML);
        $correct=trim((string)($data['correct_key']??''));
        $answers=[];$feedback=[];$fractions=[];
        foreach(array_slice((array)($data['options']??[]),0,10)as$option){
            if(!is_array($option))continue;
            $optiontext=clean_param((string)($option['text']??''),PARAM_CLEANHTML);
            $optionkey=trim((string)($option['key']??''));
            if(trim(strip_tags($optiontext))==='')continue;
            $answers[]=['text'=>$optiontext,'format'=>FORMAT_HTML];
            $feedback[]=['text'=>'','format'=>FORMAT_HTML];
            $fractions[]=$optionkey===$correct?1.0:0.0;
        }
        if(count($answers)<2||!in_array(1.0,$fractions,true))throw new \invalid_parameter_exception('Uma questão da CONTED chegou sem alternativas ou resposta correta válida.');
        $form=(object)[
            'category'=>(string)$category->id.','.(string)$category->contextid,'name'=>\core_text::substr('MI '.$key.' Q'.$position,0,255),
            'questiontext'=>['text'=>$text,'format'=>FORMAT_HTML],'generalfeedback'=>['text'=>'','format'=>FORMAT_HTML],
            'defaultmark'=>1.0,'penalty'=>0.3333333,'status'=>'ready','idnumber'=>\core_text::substr('mi-'.$key.'-'.substr($signature,0,12).'-q'.$position,0,100),
            'shuffleanswers'=>1,'answernumbering'=>'abc','showstandardinstruction'=>1,'single'=>'1',
            'correctfeedback'=>['text'=>'Resposta correta.','format'=>FORMAT_HTML],
            'partiallycorrectfeedback'=>['text'=>'Resposta parcialmente correta.','format'=>FORMAT_HTML],
            'incorrectfeedback'=>['text'=>'Resposta incorreta.','format'=>FORMAT_HTML],'shownumcorrect'=>0,
            'fraction'=>$fractions,'answer'=>$answers,'feedback'=>$feedback,
            'hint'=>[],'hintclearwrong'=>[],'hintshownumcorrect'=>[],
        ];
        $question=(object)['qtype'=>'multichoice'];
        $saved=\question_bank::get_qtype('multichoice')->save_question($question,$form);
        return(int)$saved->id;
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
            'quizzes'=>new external_value(PARAM_INT,'Avaliações criadas ou preservadas.'),
            'quizquestions'=>new external_value(PARAM_INT,'Questões vinculadas às avaliações.'),
            'hiddenquizzes'=>new external_value(PARAM_INT,'Avaliações antigas ocultadas.'),
            'examconflicts'=>new external_value(PARAM_INT,'Avaliações preservadas por possuírem tentativas.'),
        ]);
    }
}
