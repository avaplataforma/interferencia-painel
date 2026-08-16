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
        self::apply_managed_course_format($course);
        $decoded=json_decode($parameters['sections'],true);
        if(!is_array($decoded))throw new \invalid_parameter_exception('A composição da Trilha não contém um JSON válido.');
        $decoded=array_values(array_filter($decoded,'is_array'));
        if(count($decoded)<2||count($decoded)>100)throw new \invalid_parameter_exception('A Trilha deve conter entre 2 e 100 Cursos individuais.');

        require_once($CFG->dirroot.'/course/lib.php');
        require_once($CFG->dirroot.'/course/modlib.php');
        require_once($CFG->dirroot.'/mod/url/lib.php');
        require_once($CFG->dirroot.'/mod/url/locallib.php');
        require_once($CFG->dirroot.'/mod/lti/lib.php');
        require_once($CFG->dirroot.'/mod/lti/locallib.php');
        require_once($CFG->dirroot.'/mod/label/lib.php');
        require_once($CFG->dirroot.'/mod/page/lib.php');
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
            $sourcecourseid=(int)($item['sourcecourseid']??0);
            if($sourcecourseid>0){
                $master=self::sync_master_course_module($course,$section,$number,$key,$name,$sourcecourseid);
                $activities+=$master['activities'];
                $hiddenactivities+=$master['hidden'];
                $quizzes+=$master['assessments'];
                // A Trilha MASTER usa a avaliação LTI oficial do fornecedor;
                // jamais converte esse banco em questões geradas por IA.
                $legacyurl=self::sync_url_activity($course,$section,$number,$key,$name,'');
                if($legacyurl<0)$hiddenactivities++;
                $legacyquiz=self::sync_quiz_activity($course,$section,$number,$key,$name,null);
                if($legacyquiz['quiz']<0)$hiddenquizzes++;
            }else{
                $activity=self::sync_url_activity($course,$section,$number,$key,$name,$accessurl);
                if($activity>0)$activities++;
                if($activity<0)$hiddenactivities++;
                $exam=is_array($item['exam']??null)?$item['exam']:null;
                $quizresult=self::sync_quiz_activity($course,$section,$number,$key,$name,$exam);
                if($quizresult['quiz']>0)$quizzes++;
                if($quizresult['quiz']<0)$hiddenquizzes++;
                $quizquestions+=$quizresult['questions'];
                $examconflicts+=$quizresult['conflict'];
            }
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
            $managedmaster=$DB->get_records_select('course_modules','course=:course AND section=:section AND idnumber LIKE :prefix',['course'=>$course->id,'section'=>$section->id,'prefix'=>'mi-trail-master-%']);
            foreach($managedmaster as$cm){set_coursemodule_visible((int)$cm->id,0);$hiddenactivities++;}
        }
        rebuild_course_cache($parameters['courseid'],true);
        $audit=self::audit_managed_course((int)$course->id);
        return['status'=>'ok','courseid'=>$parameters['courseid'],'sections'=>$updated,'hidden'=>$hidden,'activities'=>$activities,'hiddenactivities'=>$hiddenactivities,'quizzes'=>$quizzes,'quizquestions'=>$quizquestions,'hiddenquizzes'=>$hiddenquizzes,'examconflicts'=>$examconflicts]+$cover+$audit;
    }

    /**
     * Reuses the already homologated Curso Individual MASTER as the academic
     * source for one Trail module. Only the official LTI links are copied; the
     * protected provider content remains at the provider.
     *
     * @return array{activities:int,assessments:int,hidden:int}
     */
    private static function sync_master_course_module(object$course,object$section,int$sectionnumber,string$key,string$name,int$sourcecourseid):array
    {
        global$DB;
        if($sourcecourseid===(int)$course->id)throw new \invalid_parameter_exception('A Trilha não pode usar o próprio curso como fonte MASTER.');
        $sourcecourse=$DB->get_record('course',['id'=>$sourcecourseid],'*',MUST_EXIST);
        if(!str_starts_with((string)$sourcecourse->idnumber,'mi-master-'))throw new \invalid_parameter_exception('O Curso Individual de origem não é uma publicação MASTER gerenciada pelo Mundo Inter.');
        $ltimodule=$DB->get_record('modules',['name'=>'lti'],'*',MUST_EXIST);
        $buckets=['book'=>[],'lesson'=>[],'assessment'=>[]];
        $sections=$DB->get_records('course_sections',['course'=>$sourcecourseid],'section ASC','id,section,name,sequence');
        foreach($sections as$sourcesection){
            $sectionsubtitle=self::trail_subtitle((string)($sourcesection->name??''));
            $sectionname=self::fold((string)($sourcesection->name??''));
            $kind=preg_match('/avalia|prova|exame/u',$sectionname)===1?'assessment':(preg_match('/livro|material/u',$sectionname)===1?'book':'lesson');
            foreach(array_filter(array_map('intval',explode(',',(string)$sourcesection->sequence)))as$sourcecmid){
                $sourcecm=$DB->get_record('course_modules',['id'=>$sourcecmid,'course'=>$sourcecourseid,'module'=>$ltimodule->id]);
                if(!$sourcecm)continue;
                $source=$DB->get_record('lti',['id'=>$sourcecm->instance]);
                if(!$source)continue;
                // Some older MASTER publications placed the official book or
                // assessment inside an ordinary lesson section. The activity
                // name is the authoritative fallback so those links are not
                // exposed again as regular lessons in a Trail.
                $activityname=self::fold((string)$source->name);
                $activitykind=preg_match('/avalia|prova|exame/u',$activityname)===1?'assessment':(preg_match('/livro|apostila|material/u',$activityname)===1?'book':$kind);
                $buckets[$activitykind][]=['cm'=>$sourcecm,'lti'=>$source,'kind'=>$activitykind,'subtitle'=>$sectionsubtitle];
            }
        }
        if($buckets['book']===[])throw new \moodle_exception('O Curso Individual MASTER "'.$name.'" ainda não possui Livro e materiais sincronizados.');
        if($buckets['lesson']===[])throw new \moodle_exception('O Curso Individual MASTER "'.$name.'" ainda não possui aulas sincronizadas.');
        if($buckets['assessment']===[])throw new \moodle_exception('O Curso Individual MASTER "'.$name.'" ainda não possui a avaliação oficial sincronizada.');

        $ordered=array_merge($buckets['book'],$buckets['lesson'],$buckets['assessment']);
        $active=[];$bookcmids=[];$materialcmids=[];$lessonactivities=[];$assessmentcmids=[];$activities=0;$assessments=0;
        $seen=['book'=>[],'lesson'=>[],'assessment'=>[]];
        foreach($ordered as$item){
            $sourcecm=$item['cm'];$source=$item['lti'];
            $sourcekind=(string)$item['kind'];
            $idnumber=\core_text::substr('mi-trail-master-'.$key.'-'.$sourcekind.'-'.(int)$sourcecm->id,0,100);
            $displayname=$sourcekind==='assessment'?'Avaliação oficial':trim(clean_param((string)$source->name,PARAM_TEXT));
            if($sourcekind==='book'){
                $displayname=preg_match('/material/iu',$displayname)===1?'Materiais Interativos':('Livro - '.$name);
            }else if($displayname===''){
                $displayname='Aula - '.$name;
            }
            // Older homologations may contain the same official resource more
            // than once. A Trail must expose one book, one materials index,
            // one copy of each lesson and one official assessment per Curso
            // Individual, regardless of how many legacy LTI links remain in
            // the source course.
            $dedupekey=$sourcekind==='assessment'?'official':self::fold($displayname);
            if(isset($seen[$sourcekind][$dedupekey]))continue;
            $seen[$sourcekind][$dedupekey]=true;
            $existing=$DB->get_record('course_modules',['course'=>$course->id,'module'=>$ltimodule->id,'idnumber'=>$idnumber]);
            if($existing){
                $copy=clone$source;
                $copy->id=(int)$existing->instance;$copy->course=(int)$course->id;$copy->name=\core_text::substr($displayname,0,255);$copy->timemodified=time();
                $DB->update_record('lti',$copy);
                $DB->update_record('course_modules',(object)['id'=>$existing->id,'visible'=>1,'visibleold'=>1,'visibleoncoursepage'=>1,'completion'=>COMPLETION_TRACKING_AUTOMATIC,'completionview'=>1,'completionexpected'=>0,'showdescription'=>0]);
                $cm=get_coursemodule_from_id('lti',(int)$existing->id,(int)$course->id,false,MUST_EXIST);
                moveto_module($cm,$section);
                $cmid=(int)$existing->id;
            }else{
                $moduleinfo=clone$source;
                unset($moduleinfo->id,$moduleinfo->timecreated,$moduleinfo->timemodified);
                $moduleinfo->course=(int)$course->id;$moduleinfo->name=\core_text::substr($displayname,0,255);
                $moduleinfo->modulename='lti';$moduleinfo->module=(int)$ltimodule->id;$moduleinfo->section=$sectionnumber;
                $moduleinfo->visible=1;$moduleinfo->visibleoncoursepage=1;$moduleinfo->cmidnumber=$idnumber;
                $moduleinfo->groupmode=0;$moduleinfo->groupingid=0;$moduleinfo->completion=COMPLETION_TRACKING_AUTOMATIC;
                $moduleinfo->completionview=1;$moduleinfo->completionexpected=0;$moduleinfo->showdescription=0;
                $moduleinfo->coursemodule=0;$moduleinfo->instance=0;
                $created=add_moduleinfo($moduleinfo,$course);
                $cmid=(int)($created->coursemodule??0);
                if($cmid<1)throw new \moodle_exception('O Moodle não confirmou a cópia de uma atividade MASTER para a Trilha.');
            }
            $active[$idnumber]=true;$activities++;
            if($sourcekind==='assessment'){
                $assessmentcmids[]=$cmid;
                $assessments++;
            }else if($sourcekind==='book'){
                if($displayname==='Materiais Interativos')$materialcmids[]=$cmid;
                else$bookcmids[]=$cmid;
            }else{
                $lessonactivities[]=['cmid'=>$cmid,'name'=>$displayname,'subtitle'=>(string)($item['subtitle']??'')];
            }
        }

        // When the provider exposes the complete book but no standalone HTML
        // resource, create a lightweight index for the interactive lessons.
        // This keeps every MASTER module in the same academic order without
        // copying protected provider content into Moodle.
        if($materialcmids===[]){
            $materials=self::sync_trail_materials_index($course,$section,$sectionnumber,$key,$name,$lessonactivities);
            $active[$materials['idnumber']]=true;
            $materialcmids[]=$materials['cmid'];
            $activities++;
        }
        $booklabel=self::sync_trail_subtitle($course,$section,$sectionnumber,$key,'Livro e Materiais Interativos',0);
        $active[$booklabel['idnumber']]=true;
        $lessonsequence=[];$previoussubtitle='';$subtitleindex=0;
        foreach($lessonactivities as$lessonactivity){
            $subtitle=trim((string)($lessonactivity['subtitle']??''));
            if($subtitle==='')$subtitle=self::trail_subtitle((string)$lessonactivity['name']);
            if($subtitle!==''&&self::fold($subtitle)!==self::fold($previoussubtitle)){
                $subtitleindex++;
                $label=self::sync_trail_subtitle($course,$section,$sectionnumber,$key,$subtitle,$subtitleindex);
                $active[$label['idnumber']]=true;
                $lessonsequence[]=$label['cmid'];
                $previoussubtitle=$subtitle;
            }
            $lessonsequence[]=(int)$lessonactivity['cmid'];
        }
        $assessmentlabel=self::sync_trail_subtitle($course,$section,$sectionnumber,$key,'ATIVIDADES AVALIATIVAS',$subtitleindex+1);
        $active[$assessmentlabel['idnumber']]=true;
        $orderedcmids=array_merge([$booklabel['cmid']],$bookcmids,$materialcmids,$lessonsequence,[$assessmentlabel['cmid']],$assessmentcmids);

        $hidden=0;$hiddencmids=[];
        $managed=$DB->get_records_select('course_modules','course=:course AND section=:section AND idnumber LIKE :prefix',['course'=>$course->id,'section'=>$section->id,'prefix'=>\core_text::substr('mi-trail-master-'.$key.'-%',0,100)]);
        foreach($managed as$cm){
            if(isset($active[(string)$cm->idnumber]))continue;
            if((int)$cm->visible!==0){set_coursemodule_visible((int)$cm->id,0);$hidden++;}
            $DB->set_field('course_modules','visibleoncoursepage',0,['id'=>$cm->id]);
            $hiddencmids[(int)$cm->id]=true;
        }
        // Managed Trails are rebuilt from their approved Cursos Individuais.
        // Hide legacy LTI links left by older synchronizers so repeated runs
        // never show duplicate books, lessons or assessments to the learner.
        $activecmids=array_fill_keys($orderedcmids,true);
        $sectionlti=$DB->get_records('course_modules',['course'=>$course->id,'section'=>$section->id,'module'=>$ltimodule->id]);
        foreach($sectionlti as$cm){
            if(isset($activecmids[(int)$cm->id]))continue;
            if((int)$cm->visible!==0){set_coursemodule_visible((int)$cm->id,0);$hidden++;}
            $DB->set_field('course_modules','visibleoncoursepage',0,['id'=>$cm->id]);
            $hiddencmids[(int)$cm->id]=true;
        }
        // moveto_module() does not reorder an activity that is already in the
        // same section on every supported Moodle version. Persist the exact
        // academic order explicitly and keep any manual/unmanaged item after
        // the Mundo Inter sequence.
        $currentsequence=(string)$DB->get_field('course_sections','sequence',['id'=>$section->id]);
        $current=array_filter(array_map('intval',explode(',',$currentsequence)));
        $tail=array_values(array_filter($current,static fn(int$cmid):bool=>!in_array($cmid,$orderedcmids,true)&&!isset($hiddencmids[$cmid])));
        $DB->set_field('course_sections','sequence',implode(',',array_merge($orderedcmids,$tail)),['id'=>$section->id]);
        return['activities'=>$activities,'assessments'=>$assessments,'hidden'=>$hidden];
    }

    /**
     * Adds a visual-only Moodle "Text and media area" between lesson groups.
     * It never contributes to completion or the learner progress calculation.
     *
     * @return array{idnumber:string,cmid:int}
     */
    private static function sync_trail_subtitle(object$course,object$section,int$sectionnumber,string$key,string$title,int$position):array
    {
        global$DB;
        $labelmodule=$DB->get_record('modules',['name'=>'label'],'*',MUST_EXIST);
        $idnumber=\core_text::substr('mi-trail-master-'.$key.'-subtitle-'.$position.'-'.substr(sha1(self::fold($title)),0,8),0,100);
        $intro=\html_writer::tag('b',s($title));
        $existing=$DB->get_record('course_modules',['course'=>$course->id,'module'=>$labelmodule->id,'idnumber'=>$idnumber]);
        if($existing){
            $DB->update_record('label',(object)[
                'id'=>(int)$existing->instance,'name'=>\core_text::substr($title,0,255),
                'intro'=>$intro,'introformat'=>FORMAT_HTML,'timemodified'=>time(),
            ]);
            $DB->update_record('course_modules',(object)[
                'id'=>(int)$existing->id,'visible'=>1,'visibleold'=>1,'visibleoncoursepage'=>1,
                'completion'=>COMPLETION_TRACKING_NONE,'completionview'=>0,'completionexpected'=>0,
                'showdescription'=>0,
            ]);
            $cm=get_coursemodule_from_id('label',(int)$existing->id,(int)$course->id,false,MUST_EXIST);
            moveto_module($cm,$section);
            return['idnumber'=>$idnumber,'cmid'=>(int)$existing->id];
        }
        $moduleinfo=(object)[
            'course'=>(int)$course->id,'name'=>\core_text::substr($title,0,255),
            'modulename'=>'label','module'=>(int)$labelmodule->id,'section'=>$sectionnumber,
            'visible'=>1,'visibleoncoursepage'=>1,'cmidnumber'=>$idnumber,
            'intro'=>$intro,'introformat'=>FORMAT_HTML,'groupmode'=>0,'groupingid'=>0,
            'completion'=>COMPLETION_TRACKING_NONE,'completionview'=>0,'completionexpected'=>0,
            'showdescription'=>0,'coursemodule'=>0,'instance'=>0,
        ];
        $created=add_moduleinfo($moduleinfo,$course);
        $cmid=(int)($created->coursemodule??0);
        if($cmid<1)throw new \moodle_exception('O Moodle não confirmou o subtítulo da Trilha.');
        return['idnumber'=>$idnumber,'cmid'=>$cmid];
    }

    private static function trail_subtitle(string$value):string
    {
        $value=trim(clean_param($value,PARAM_TEXT));
        $value=trim((string)preg_replace('/^m[oó]dulo\s+\d+\s*[-:–—]\s*/iu','',$value));
        $value=trim((string)preg_replace('/\s*[-:–—]\s*parte\s+\d+\s*$/iu','',$value));
        return $value;
    }

    /**
     * Creates the reusable "Materiais Interativos" index for a MASTER Trail
     * module. The links always point to the cloned activities in this Trail,
     * never to the source course used during homologation.
     *
     * @param array<int,array{cmid:int,name:string}> $activities
     * @return array{idnumber:string,cmid:int}
     */
    private static function sync_trail_materials_index(object$course,object$section,int$sectionnumber,string$key,string$name,array$activities):array
    {
        global$DB;
        $pagemodule=$DB->get_record('modules',['name'=>'page'],'*',MUST_EXIST);
        $idnumber=\core_text::substr('mi-trail-master-'.$key.'-materials-index',0,100);
        $items=[];
        foreach($activities as$activity){
            $url=new \moodle_url('/mod/lti/view.php',['id'=>(int)$activity['cmid']]);
            $items[]=\html_writer::tag('li',\html_writer::link($url,format_string((string)$activity['name'])));
        }
        $content=\html_writer::div(
            \html_writer::tag('p','Acesse as aulas e os materiais interativos de '.format_string($name).'.')
            .\html_writer::tag('ol',implode('',$items)),
            'mundointer-materials-index'
        );
        $existing=$DB->get_record('course_modules',['course'=>$course->id,'module'=>$pagemodule->id,'idnumber'=>$idnumber]);
        if($existing){
            $DB->update_record('page',(object)[
                'id'=>(int)$existing->instance,'name'=>'Materiais Interativos','content'=>$content,
                'contentformat'=>FORMAT_HTML,'timemodified'=>time(),
            ]);
            $DB->update_record('course_modules',(object)[
                'id'=>(int)$existing->id,'visible'=>1,'visibleold'=>1,'visibleoncoursepage'=>1,
                'completion'=>COMPLETION_TRACKING_AUTOMATIC,'completionview'=>1,'completionexpected'=>0,
            ]);
            $cm=get_coursemodule_from_id('page',(int)$existing->id,(int)$course->id,false,MUST_EXIST);
            moveto_module($cm,$section);
            return['idnumber'=>$idnumber,'cmid'=>(int)$existing->id];
        }
        $moduleinfo=(object)[
            'course'=>(int)$course->id,'name'=>'Materiais Interativos','modulename'=>'page','module'=>(int)$pagemodule->id,
            'section'=>$sectionnumber,'visible'=>1,'visibleoncoursepage'=>1,'cmidnumber'=>$idnumber,
            'intro'=>'','introformat'=>FORMAT_HTML,'content'=>$content,'contentformat'=>FORMAT_HTML,
            'display'=>5,'displayoptions'=>serialize([]),'revision'=>1,'groupmode'=>0,'groupingid'=>0,
            'completion'=>COMPLETION_TRACKING_AUTOMATIC,'completionview'=>1,'completionexpected'=>0,
            'coursemodule'=>0,'instance'=>0,
        ];
        $created=add_moduleinfo($moduleinfo,$course);
        $cmid=(int)($created->coursemodule??0);
        if($cmid<1)throw new \moodle_exception('O Moodle não confirmou o índice de Materiais Interativos da Trilha.');
        return['idnumber'=>$idnumber,'cmid'=>$cmid];
    }

    private static function fold(string$value):string
    {
        $value=\core_text::strtolower(trim($value));
        return str_replace(['á','à','ã','â','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','õ','ô','ö','ú','ù','û','ü','ç'],['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c'],$value);
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
            // A imagem comercial permanece otimizada em WebP no Spaces, mas a
            // área oficial de capa dos cursos do Moodle aceita somente os
            // formatos tradicionais. Convertemos apenas na cópia interna.
            if(defined('IMAGETYPE_WEBP')&&$type===IMAGETYPE_WEBP){
                if(!function_exists('imagecreatefromstring')||!function_exists('imagejpeg'))throw new \RuntimeException('O servidor Moodle precisa da extensao GD para converter a capa WebP.');
                $source=@imagecreatefromstring($content);
                if($source===false)throw new \RuntimeException('A capa WebP nao pode ser convertida para JPG.');
                ob_start();
                $converted=imagejpeg($source,null,88);
                $jpeg=(string)ob_get_clean();
                imagedestroy($source);
                if(!$converted||$jpeg==='')throw new \RuntimeException('A conversao da capa WebP para JPG falhou.');
                $content=$jpeg;
                $extension='jpg';
                $mimetype='image/jpeg';
            }
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
        $managedmaster=$DB->get_records_select('course_modules','course=:course AND idnumber LIKE :prefix AND visible=1',['course'=>$courseid,'prefix'=>'mi-trail-master-%']);
        $validurls=0;
        foreach($managedurls as$cm){
            if((int)$cm->completion===COMPLETION_TRACKING_AUTOMATIC&&(int)$cm->completionview===1)$validurls++;
        }
        $masterlessons=0;$validmasterlessons=0;$masterassessments=0;$validmasterassessments=0;
        foreach($managedmaster as$cm){
            $isassessment=str_contains((string)$cm->idnumber,'-assessment-');
            $valid=(int)$cm->completion===COMPLETION_TRACKING_AUTOMATIC&&(int)$cm->completionview===1;
            if($isassessment){$masterassessments++;if($valid)$validmasterassessments++;}
            else{$masterlessons++;if($valid)$validmasterlessons++;}
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
        $ready=$validurls===count($managedurls)
            &&$validmasterlessons===$masterlessons
            &&$validquizzes===count($managedquizzes)
            &&$validmasterassessments===$masterassessments
            &&(count($managedquizzes)+$masterassessments)>0;
        return[
            'auditstatus'=>$ready?'ok':'warning',
            'auditurls'=>count($managedurls)+$masterlessons,
            'auditvalidurls'=>$validurls+$validmasterlessons,
            'auditquizzes'=>count($managedquizzes)+$masterassessments,
            'auditvalidquizzes'=>$validquizzes+$validmasterassessments,
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

    /** Reuses the same cover policy for MASTER courses. */
    public static function apply_managed_cover(object$course,string$coverurl,string$coveralt):array
    {
        return self::sync_course_cover($course,$coverurl,$coveralt);
    }

    /** Reuses the same 10-point, 6-pass, three-attempt assessment policy for MASTER courses. */
    public static function apply_managed_assessment(object$course,object$section,int$sectionnumber,string$key,string$name,array$exam):array
    {
        return self::sync_quiz_activity($course,$section,$sectionnumber,$key,$name,$exam);
    }

    /** Applies the approved Tiles layout to every course managed by Mundo Inter. */
    public static function apply_managed_course_format(object$course):void
    {
        global$DB;
        if(\core_component::get_plugin_directory('format','tiles')===null)return;

        if((string)($course->format??'')!=='tiles'){
            $DB->set_field('course','format','tiles',['id'=>(int)$course->id]);
            $course->format='tiles';
        }

        $options=[
            'defaulttileicon'=>'pie-chart',
            'basecolour'=>'#D13C3C',
            'courseusesubtiles'=>'0',
            'usesubtilesseczero'=>'0',
            'courseshowtileprogress'=>'2',
            'displayfilterbar'=>'0',
            'courseusebarforheadings'=>'1',
        ];
        foreach($options as$name=>$value){
            $criteria=['courseid'=>(int)$course->id,'format'=>'tiles','sectionid'=>0,'name'=>$name];
            $record=$DB->get_record('course_format_options',$criteria);
            if($record){
                if((string)$record->value!==$value){
                    $record->value=$value;
                    $DB->update_record('course_format_options',$record);
                }
                continue;
            }
            $DB->insert_record('course_format_options',(object)($criteria+['value'=>$value]));
        }
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
