<?php

namespace local_mundointer\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

final class sync_trail_sections extends external_api
{
    private const QUIZ_GRADE = 10.0;
    private const QUIZ_PASS_GRADE = 6.0;
    private const QUIZ_ATTEMPTS = 3;

    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'courseid'=>new external_value(PARAM_INT,'Curso de Trilha gerenciado pelo Mundo Inter.'),
            'sections'=>new external_value(PARAM_RAW,'Lista JSON dos Cursos individuais na ordem comercial.'),
            'coverurl'=>new external_value(PARAM_URL,'Capa comercial gerada e armazenada pelo Mundo Inter.',VALUE_DEFAULT,''),
            'coveralt'=>new external_value(PARAM_TEXT,'Texto alternativo da capa.',VALUE_DEFAULT,''),
        ]);
    }

    public static function execute(int$courseid,string$sections,string$coverurl='',string$coveralt=''):array
    {
        global$CFG,$DB;
        $parameters=self::validate_parameters(self::execute_parameters(),compact('courseid','sections','coverurl','coveralt'));
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
        require_once($CFG->libdir.'/filelib.php');
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
        $cover=self::sync_course_cover($course,(string)$parameters['coverurl'],(string)$parameters['coveralt']);
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
        $audit=self::audit_managed_course((int)$course->id);
        return['status'=>'ok','courseid'=>$parameters['courseid'],'sections'=>$updated,'hidden'=>$hidden,'activities'=>$activities,'hiddenactivities'=>$hiddenactivities,'quizzes'=>$quizzes,'quizquestions'=>$quizquestions,'hiddenquizzes'=>$hiddenquizzes,'examconflicts'=>$examconflicts]+$cover+$audit;
    }

    /** @return array{coverstatus:string,coverfilename:string,courseimage:int,coursebanner:int} */
    private static function sync_course_cover(object$course,string$coverurl,string$coveralt):array
    {
        global$DB;
        if($coverurl==='')return['coverstatus'=>'missing','coverfilename'=>'','courseimage'=>0,'coursebanner'=>0];
        try{
            $parts=parse_url($coverurl);
            if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'||trim((string)($parts['host']??''))==='')throw new \RuntimeException('A capa do curso precisa usar um endereco HTTPS valido.');
            $content=download_file_content($coverurl,null,null,false,30,10);
            if(!is_string($content)||$content===''||strlen($content)>8*1024*1024)throw new \RuntimeException('A capa comercial nao pode ser baixada ou excede 8 MB.');
            $image=@getimagesizefromstring($content);
            $type=(int)($image[2]??0);
            $allowed=[IMAGETYPE_JPEG=>['jpg','image/jpeg'],IMAGETYPE_PNG=>['png','image/png']];
            if(defined('IMAGETYPE_WEBP'))$allowed[IMAGETYPE_WEBP]=['webp','image/webp'];
            if(!isset($allowed[$type]))throw new \RuntimeException('A capa comercial precisa ser JPG, PNG ou WebP.');
            [$extension,$mimetype]=$allowed[$type];
            $filename='mundointer-course-cover.'.$extension;
            $context=\context_course::instance((int)$course->id);
            $fs=get_file_storage();
            $fs->delete_area_files($context->id,'course','overviewfiles',0);
            $fs->create_file_from_string([
                'contextid'=>$context->id,'component'=>'course','filearea'=>'overviewfiles','itemid'=>0,
                'filepath'=>'/','filename'=>$filename,'mimetype'=>$mimetype,'source'=>'Mundo Inter',
            ],$content);
            // A área course/overviewfiles não expõe o itemid na URL pública.
            // Manter o zero no registro do arquivo é correto, mas incluí-lo no
            // endereço faz o Moodle procurar por um arquivo chamado "0".
            $fileurl=\moodle_url::make_pluginfile_url($context->id,'course','overviewfiles',null,'/',$filename)->out(false);
            $section=$DB->get_record('course_sections',['course'=>$course->id,'section'=>0]);
            if(!$section)$section=course_create_section((int)$course->id,0);
            $summary=(string)($section->summary??'');
            $summary=(string)preg_replace('~<!-- mundointer-course-banner:start -->.*?<!-- mundointer-course-banner:end -->~s','',$summary);
            $alt=trim($coveralt)!==''?trim($coveralt):(string)$course->fullname;
            $banner='<!-- mundointer-course-banner:start --><div class="mundointer-course-banner" style="margin:0 0 1.5rem"><img src="'.s($fileurl).'" alt="'.s($alt).'" style="display:block;width:100%;max-height:360px;object-fit:cover;border-radius:16px"></div><!-- mundointer-course-banner:end -->';
            $DB->update_record('course_sections',(object)['id'=>$section->id,'summary'=>$banner.trim($summary),'summaryformat'=>FORMAT_HTML]);
            return['coverstatus'=>'applied','coverfilename'=>$filename,'courseimage'=>1,'coursebanner'=>1];
        }catch(\Throwable){
            return['coverstatus'=>'failed','coverfilename'=>'','courseimage'=>0,'coursebanner'=>0];
        }
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
                $quiz=self::repair_quiz_grades($quiz);
                $quiz=self::apply_quiz_policy($course,$quiz,(int)$existing->id);
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
                $quiz=self::repair_quiz_grades($quiz);
                self::apply_quiz_policy($course,$quiz,(int)$existing->id);
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
            'preferredbehaviour'=>'deferredfeedback','attempts'=>self::QUIZ_ATTEMPTS,'attemptonlast'=>0,
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
        $quiz=self::repair_quiz_grades($quiz);
        self::apply_quiz_policy($course,$quiz,(int)$created->coursemodule);
        return['quiz'=>(int)$created->coursemodule,'questions'=>$createdquestions,'conflict'=>0];
    }

    /**
     * Keep question weights and the quiz total in sync across supported Moodle versions.
     *
     * Moodle 4.3+ no longer recalculates quiz.sumgrades when a question is added by
     * quiz_add_quiz_question(). Re-publication also reaches this method, so quizzes
     * created by older connector builds are repaired without being recreated.
     */
    private static function repair_quiz_grades(object$quiz):object
    {
        global$DB;
        $slots=$DB->get_records('quiz_slots',['quizid'=>$quiz->id],'slot ASC','id,maxmark');
        foreach($slots as$slot){
            if((float)$slot->maxmark>0)continue;
            $DB->set_field('quiz_slots','maxmark',1.0,['id'=>$slot->id]);
        }

        $recomputed=false;
        if(class_exists('\\mod_quiz\\quiz_settings')&&method_exists('\\mod_quiz\\quiz_settings','create')){
            $settings=\mod_quiz\quiz_settings::create((int)$quiz->id);
            $calculator=$settings->get_grade_calculator();
            if(method_exists($calculator,'recompute_quiz_sumgrades')){
                $calculator->recompute_quiz_sumgrades();
                $recomputed=true;
            }
        }
        if(!$recomputed){
            $sumgrades=(float)$DB->get_field_sql(
                'SELECT COALESCE(SUM(maxmark),0) FROM {quiz_slots} WHERE quizid = ?',
                [$quiz->id]
            );
            $DB->set_field('quiz','sumgrades',$sumgrades,['id'=>$quiz->id]);
        }

        $quiz=$DB->get_record('quiz',['id'=>$quiz->id],'*',MUST_EXIST);
        quiz_set_grade(self::QUIZ_GRADE,$quiz);
        return$DB->get_record('quiz',['id'=>$quiz->id],'*',MUST_EXIST);
    }

    /**
     * Applies the network pedagogical policy without recreating attempts.
     *
     * Every managed assessment is worth 10 points, requires 6 points to pass,
     * allows three attempts and completes only after a passing grade.
     */
    private static function apply_quiz_policy(object$course,object$quiz,int$cmid):object
    {
        global$DB;
        $quiz->grade=self::QUIZ_GRADE;
        $quiz->attempts=self::QUIZ_ATTEMPTS;
        $quiz->grademethod=QUIZ_GRADEHIGHEST;
        if(property_exists($quiz,'completionattemptsexhausted'))$quiz->completionattemptsexhausted=0;
        if(property_exists($quiz,'completionminattempts'))$quiz->completionminattempts=0;
        $quiz->timemodified=time();
        $DB->update_record('quiz',$quiz);
        quiz_set_grade(self::QUIZ_GRADE,$quiz);

        $gradeitem=$DB->get_record('grade_items',[
            'courseid'=>$course->id,
            'itemmodule'=>'quiz',
            'iteminstance'=>$quiz->id,
            'itemnumber'=>0,
        ]);
        if($gradeitem!==false&&abs((float)$gradeitem->gradepass-self::QUIZ_PASS_GRADE)>0.001){
            $DB->set_field('grade_items','gradepass',self::QUIZ_PASS_GRADE,['id'=>$gradeitem->id]);
        }

        $columns=$DB->get_columns('course_modules');
        $completion=(object)[
            'id'=>$cmid,
            'completion'=>COMPLETION_TRACKING_AUTOMATIC,
            'completionview'=>0,
            'completiongradeitemnumber'=>0,
            'completionexpected'=>0,
        ];
        if(isset($columns['completionpassgrade']))$completion->completionpassgrade=1;
        $DB->update_record('course_modules',$completion);
        return$DB->get_record('quiz',['id'=>$quiz->id],'*',MUST_EXIST);
    }

    /** @return array<string,int|string|float> */
    private static function audit_managed_course(int$courseid):array
    {
        global$DB;
        $columns=$DB->get_columns('course_modules');
        $managedurls=$DB->get_records_select('course_modules','course=:course AND idnumber LIKE :prefix AND visible=1',['course'=>$courseid,'prefix'=>'mi-trail-url-%']);
        $managedquizzes=$DB->get_records_select('course_modules','course=:course AND idnumber LIKE :prefix AND visible=1',['course'=>$courseid,'prefix'=>'mi-trail-exam-%']);
        $validurls=0;
        foreach($managedurls as$cm){
            if((int)$cm->completion===COMPLETION_TRACKING_AUTOMATIC&&(int)$cm->completionview===1)$validurls++;
        }
        $validquizzes=0;
        $questioncount=0;
        foreach($managedquizzes as$cm){
            $quiz=$DB->get_record('quiz',['id'=>$cm->instance]);
            if($quiz===false)continue;
            $questioncount+=(int)$DB->count_records('quiz_slots',['quizid'=>$quiz->id]);
            $gradeitem=$DB->get_record('grade_items',['courseid'=>$courseid,'itemmodule'=>'quiz','iteminstance'=>$quiz->id,'itemnumber'=>0]);
            $completionvalid=(int)$cm->completion===COMPLETION_TRACKING_AUTOMATIC&&(int)$cm->completiongradeitemnumber===0;
            if(isset($columns['completionpassgrade']))$completionvalid=$completionvalid&&(int)$cm->completionpassgrade===1;
            $gradevalid=(float)$quiz->sumgrades>0.0
                &&abs((float)$quiz->grade-self::QUIZ_GRADE)<0.001
                &&(int)$quiz->attempts===self::QUIZ_ATTEMPTS
                &&$gradeitem!==false
                &&abs((float)$gradeitem->gradepass-self::QUIZ_PASS_GRADE)<0.001;
            if($completionvalid&&$gradevalid)$validquizzes++;
        }
        $ready=$validurls===count($managedurls)&&$validquizzes===count($managedquizzes)&&count($managedquizzes)>0;
        return[
            'auditstatus'=>$ready?'ok':'warning',
            'auditurls'=>count($managedurls),
            'auditvalidurls'=>$validurls,
            'auditquizzes'=>count($managedquizzes),
            'auditvalidquizzes'=>$validquizzes,
            'auditquestions'=>$questioncount,
            'passinggrade'=>self::QUIZ_PASS_GRADE,
            'maxattempts'=>self::QUIZ_ATTEMPTS,
        ];
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
            'coverstatus'=>new external_value(PARAM_ALPHANUMEXT,'Situação da capa comercial.'),
            'coverfilename'=>new external_value(PARAM_FILE,'Arquivo aplicado como imagem oficial.',VALUE_DEFAULT,''),
            'courseimage'=>new external_value(PARAM_INT,'Confirma a imagem oficial do curso.'),
            'coursebanner'=>new external_value(PARAM_INT,'Confirma a testeira no bloco inicial.'),
            'auditstatus'=>new external_value(PARAM_ALPHA,'Resultado da auditoria pedagógica.'),
            'auditurls'=>new external_value(PARAM_INT,'Atividades de aula auditadas.'),
            'auditvalidurls'=>new external_value(PARAM_INT,'Atividades de aula com conclusão válida.'),
            'auditquizzes'=>new external_value(PARAM_INT,'Avaliações auditadas.'),
            'auditvalidquizzes'=>new external_value(PARAM_INT,'Avaliações em conformidade.'),
            'auditquestions'=>new external_value(PARAM_INT,'Questões auditadas.'),
            'passinggrade'=>new external_value(PARAM_FLOAT,'Nota mínima para aprovação.'),
            'maxattempts'=>new external_value(PARAM_INT,'Tentativas permitidas por avaliação.'),
        ]);
    }
}
