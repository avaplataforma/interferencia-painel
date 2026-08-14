<?php

declare(strict_types=1);

namespace Interferencia\Modules\Catalog;

use Interferencia\Modules\Moodle\AvaConnectionRepository;
use Interferencia\Modules\Moodle\MoodleAcademicCategoryManager;
use Interferencia\Modules\Moodle\MoodleClient;
use Interferencia\Modules\Moodle\MoodleRepository;
use RuntimeException;
use Throwable;

final readonly class AvaCatalogPublisher
{
    public function __construct(
        private LearningCatalogRepository $catalog,
        private AvaConnectionRepository $connections,
        private MoodleRepository $moodle,
        private CourseProviderRepository $providers,
        private string $publicBaseUrl,
    ) {}

    /** @return array{remote_course_id:int,remote_category_id:int,created_or_updated:string} */
    public function publishTrail(int$trailId,?int$userId):array
    {
        $trail=$this->catalog->trailPublicationContext($trailId);
        if($trail===null)throw new RuntimeException('Trilha não encontrada.');
        if((int)($trail['is_active']??0)!==1)throw new RuntimeException('Ative a Trilha antes de publicá-la no AVA.');
        if((int)($trail['item_count']??0)<2)throw new RuntimeException('A Trilha precisa ter pelo menos dois Cursos individuais.');
        foreach((array)($trail['items']??[])as$item){
            if((string)($item['execution_environment']??'provider_ava')==='shared_ava'&&(int)($item['shared_ava_enabled']??0)!==1){
                throw new RuntimeException('A Formação '.trim((string)($item['item_catalog']??'selecionada')).' está pausada no AVA Cursos. Ative-a antes de publicar esta Trilha.');
            }
        }
        $connection=$this->connections->shared();
        if(!(bool)($connection['configured']??false)||!(bool)($connection['is_active']??false)||(int)($connection['id']??0)<1)throw new RuntimeException('Configure e ative primeiro a integração AVA Cursos.');

        $signature=$this->signature($trail);
        $publicationId=$this->catalog->markPublicationReady($trailId,(int)$connection['id'],$signature,$userId);
        try{
            $client=new MoodleClient((string)$connection['base_url'],(string)$connection['token'],true);
            $categoryId=$this->ensureTrailCategory($client,$trail);
            $idNumber='mi-trilha-'.$trailId;
            $shortName=$this->limitedCode('MI-TRILHA-'.$trailId.'-'.$trail['slug'],100);
            $summary=$this->summary($trail);
            $course=$client->publishCourse(['fullname'=>(string)$trail['name'],'shortname'=>$shortName,'idnumber'=>$idNumber,'categoryid'=>$categoryId,'summary'=>$summary]);
            $remoteCourseId=(int)($course['id']??0);
            if($remoteCourseId<1)throw new RuntimeException('O AVA não devolveu o identificador do curso publicado.');
            $sections=$this->sections($trail);
            $coverUrl=$this->coverUrl($trail);
            $sectionSync=$client->syncTrailSections($remoteCourseId,$sections,$coverUrl,(string)$trail['name']);
            $course['categoryid']=$categoryId;$course['fullname']=(string)$trail['name'];$course['shortname']=$shortName;$course['idnumber']=$idNumber;$course['visible']=1;
            $this->moodle->upsertCourse($course);
            $localCourseId=$this->moodle->localCourseIdByRemote($remoteCourseId);
            $this->catalog->markPublicationSuccess($publicationId,$localCourseId,$categoryId,$remoteCourseId,$signature,[
                'trail_id'=>$trailId,
                'shortname'=>$shortName,
                'idnumber'=>$idNumber,
                'category_path'=>['MUNDO INTER','Formação '.$this->trailFormation($trail),'Trilhas'],
                'item_count'=>(int)$trail['item_count'],
                'sections_synced'=>(int)($sectionSync['sections']??count($sections)),
                'url_activities_synced'=>(int)($sectionSync['activities']??0),
                'quizzes_synced'=>(int)($sectionSync['quizzes']??0),
                'quiz_questions_synced'=>(int)($sectionSync['quizquestions']??0),
                'quiz_conflicts'=>(int)($sectionSync['examconflicts']??0),
                'course_cover'=>[
                    'status'=>(string)($sectionSync['coverstatus']??($coverUrl===''?'missing':'failed')),
                    'filename'=>(string)($sectionSync['coverfilename']??''),
                    'official_image'=>(int)($sectionSync['courseimage']??0)===1,
                    'course_banner'=>(int)($sectionSync['coursebanner']??0)===1,
                ],
                'pedagogical_audit'=>[
                    'status'=>(string)($sectionSync['auditstatus']??'warning'),
                    'lesson_activities'=>(int)($sectionSync['auditurls']??0),
                    'valid_lesson_activities'=>(int)($sectionSync['auditvalidurls']??0),
                    'quizzes'=>(int)($sectionSync['auditquizzes']??0),
                    'valid_quizzes'=>(int)($sectionSync['auditvalidquizzes']??0),
                    'questions'=>(int)($sectionSync['auditquestions']??0),
                    'passing_grade'=>(float)($sectionSync['passinggrade']??6),
                    'max_attempts'=>(int)($sectionSync['maxattempts']??3),
                ],
            ],$userId);
            return['remote_course_id'=>$remoteCourseId,'remote_category_id'=>$categoryId,'created_or_updated'=>'published'];
        }catch(Throwable$exception){
            $this->catalog->markPublicationFailed($publicationId,$this->friendlyError($exception),$userId);
            throw new RuntimeException($this->friendlyError($exception),0,$exception);
        }
    }

    /** @return array{remote_course_id:int,remote_category_id:int,created_or_updated:string,reused_activity:bool,sections_synced:int,activities_synced:int,assessment_ready:bool,book_ready:bool} */
    public function publishMasterCourse(int$courseId,?int$userId):array
    {
        $context=$this->providers->coursePublicationContext($courseId);
        if($context===null)throw new RuntimeException('Curso Individual MASTER não encontrado.');
        if((string)($context['provider_code']??'')!=='iesde')throw new RuntimeException('Esta publicação assistida é exclusiva da Formação MASTER.');
        if((int)($context['is_available']??0)!==1)throw new RuntimeException('A disciplina não está mais disponível no fornecedor.');
        if((int)($context['resource_count']??0)<1)throw new RuntimeException('Esta disciplina ainda não possui aulas ou recursos LTI sincronizados.');
        $assessmentReady=(int)($context['assessment_resource_count']??0)>0;
        $bookReady=(int)($context['book_resource_count']??0)>0;
        $raw=is_array($context['source_raw']??null)?$context['source_raw']:[];
        $sourceCmId=(int)($raw['course_module_id']??0);
        if($sourceCmId<1)throw new RuntimeException('Sincronize novamente as seleções MASTER: a atividade LTI de origem não foi identificada.');
        $connection=$this->connections->shared();
        if(!(bool)($connection['configured']??false)||!(bool)($connection['is_active']??false)||(int)($connection['id']??0)<1)throw new RuntimeException('Configure e ative primeiro a integração AVA Cursos.');

        $name=$this->masterCourseName((string)($context['effective_name']??$context['name']??''));
        if($name==='')throw new RuntimeException('Informe o nome da disciplina antes de criar o curso.');
        $signature=hash('sha256',json_encode(['course_id'=>$courseId,'name'=>$name,'description'=>$context['effective_description']??'','summary'=>$context['commercial_summary']??'','category'=>$context['effective_category']??'','workload'=>$context['effective_workload']??'','source_cmid'=>$sourceCmId,'resource_count'=>(int)$context['resource_count'],'assessment_resource_count'=>(int)$context['assessment_resource_count'],'book_resource_count'=>(int)($context['book_resource_count']??0),'media_asset_id'=>(int)($context['media_asset_id']??0)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        $publicationId=$this->catalog->markEntityPublicationReady('provider_course',$courseId,(int)$connection['id'],$signature,$userId);
        try{
            $client=new MoodleClient((string)$connection['base_url'],(string)$connection['token'],true);
            $categoryId=$this->ensureMasterCategory($client,$name);
            $legacyContentId=(int)($context['legacy_content_id']??0);
            $idNumber=$legacyContentId>0?'mi-master-content-'.$legacyContentId:'mi-master-course-'.$courseId;
            $shortName=$this->limitedCode('MI-MASTER-'.$courseId.'-'.$name,100);
            $summary=$this->masterSummary($context);
            $course=$client->publishCourse(['fullname'=>$name,'shortname'=>$shortName,'idnumber'=>$idNumber,'categoryid'=>$categoryId,'summary'=>$summary]);
            $remoteCourseId=(int)($course['id']??0);
            if($remoteCourseId<1)throw new RuntimeException('O AVA não devolveu o identificador do Curso Individual MASTER.');
            $cover=(int)($context['media_asset_id']??0)>0?rtrim($this->publicBaseUrl,'/').'/catalog-media/'.(int)$context['media_asset_id']:trim((string)($context['commercial_cover_url']?:($context['cover_url']??'')));
            $activity=$client->materializeLtiCourse($sourceCmId,$remoteCourseId,'Aula - '.$name,'mi-master-course-lti-'.$courseId,$cover,$name);
            $course['categoryid']=$categoryId;$course['fullname']=$name;$course['shortname']=$shortName;$course['idnumber']=$idNumber;$course['visible']=1;
            $this->moodle->upsertCourse($course);
            $localCourseId=$this->moodle->localCourseIdByRemote($remoteCourseId);
            $this->catalog->markEntityPublicationSuccess($publicationId,$localCourseId,$categoryId,$remoteCourseId,$signature,'Curso Individual MASTER criado ou atualizado no AVA Cursos.',['course_id'=>$courseId,'resource_count'=>(int)$context['resource_count'],'provider_assessment_count'=>(int)$context['assessment_resource_count'],'provider_book_count'=>(int)($context['book_resource_count']??0),'source_course_module_id'=>$sourceCmId,'target_course_module_id'=>(int)($activity['cmid']??0),'activity_reused'=>(bool)($activity['reused']??false),'sections_synced'=>(int)($activity['sections']??1),'activities_synced'=>(int)($activity['activities']??1),'activities_reused'=>(int)($activity['reusedactivities']??0),'course_image'=>(int)($activity['courseimage']??0),'idnumber'=>$idNumber],$userId);
            return['remote_course_id'=>$remoteCourseId,'remote_category_id'=>$categoryId,'created_or_updated'=>'published','reused_activity'=>(bool)($activity['reused']??false),'sections_synced'=>(int)($activity['sections']??1),'activities_synced'=>(int)($activity['activities']??1),'assessment_ready'=>$assessmentReady,'book_ready'=>$bookReady];
        }catch(Throwable$exception){
            $this->catalog->markPublicationFailed($publicationId,$this->friendlyError($exception),$userId);
            throw new RuntimeException($this->friendlyError($exception),0,$exception);
        }
    }

    /** @return array{remote_course_id:int,remote_category_id:int,created_or_updated:string,reused_activity:bool,sections_synced:int,activities_synced:int} */
    public function publishMasterContent(int$contentId,?int$userId):array
    {
        $courseId=$this->providers->parentCourseIdForContent($contentId);
        if($courseId===null)throw new RuntimeException('Esta aula MASTER não está vinculada a uma disciplina completa.');
        return$this->publishMasterCourse($courseId,$userId);
    }

    private function ensureMasterCategory(MoodleClient$client,string$name):int
    {
        $tree=(new MoodleAcademicCategoryManager($client))->ensureFormation('MASTER');
        return preg_match('/\btest(?:e|es)?\b/iu',$name)===1?$tree['formation']:$tree['individuals'];
    }

    private function masterCourseName(string$name):string
    {
        $name=trim($name);
        $name=trim((string)preg_replace('/^aula\s*[-:–—]\s*/iu','',$name));
        return trim((string)preg_replace('/\s*[-:–—]\s*apresenta[cç][aã]o\s*$/iu','',$name));
    }

    /** @param array<string,mixed> $content */
    private function masterSummary(array$content):string
    {
        $name=trim((string)($content['effective_name']??$content['name']??''));
        $description=trim((string)($content['effective_description']??''));
        $commercialSummary=trim((string)($content['commercial_summary']??''));
        $category=trim((string)($content['effective_category']??''));
        $workload=trim((string)($content['effective_workload']??''));
        $cover=(int)($content['media_asset_id']??0)>0?rtrim($this->publicBaseUrl,'/').'/catalog-media/'.(int)$content['media_asset_id']:'';
        $image=$cover!==''?'<p><img src="'.htmlspecialchars($cover,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'" alt="'.htmlspecialchars($name,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'" style="display:block;width:100%;max-height:360px;object-fit:cover;border-radius:16px"></p>':'';
        $meta=[];if($category!=='')$meta[]='<strong>Categoria:</strong> '.htmlspecialchars($category,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');if($workload!=='')$meta[]='<strong>Carga horária:</strong> '.htmlspecialchars($workload,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        return$image.($commercialSummary!==''?'<p><strong>'.htmlspecialchars($commercialSummary,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</strong></p>':'').($description!==''?'<p>'.nl2br(htmlspecialchars($description,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')).'</p>':'').($meta!==[]?'<p>'.implode(' &middot; ',$meta).'</p>':'').'<p><small>Conteúdo MASTER disponibilizado por LTI 1.3 e gerenciado pelo Mundo Inter.</small></p>';
    }

    /** @param array<string,mixed> $trail */
    private function ensureTrailCategory(MoodleClient$client,array$trail):int
    {
        $tree=(new MoodleAcademicCategoryManager($client))->ensureFormation($this->trailFormation($trail));
        return$tree['trails'];
    }

    /** @param array<string,mixed> $trail */
    private function trailFormation(array$trail):string
    {
        $formations=[];
        foreach((array)($trail['items']??[])as$item){
            $name=trim((string)preg_replace('/^(?:Catálogo|Formação)\s+/iu','',(string)($item['item_catalog']??'')));
            if($name!=='')$formations[mb_strtoupper($name)]=$name;
        }
        if(count($formations)===1)return mb_strtoupper((string)array_values($formations)[0]);
        return'MUNDO INTER';
    }

    /** @param array<string,mixed> $trail */
    private function summary(array$trail):string
    {
        $description=trim((string)($trail['description']??$trail['short_description']??''));
        $workload=(float)($trail['workload_hours']??0);
        $workloadLabel=$workload>0?'<p><strong>Carga horária total:</strong> '.rtrim(rtrim(number_format($workload,2,',','.'),'0'),',').' horas</p>':'';
        $names=[];
        foreach((array)($trail['items']??[])as$item){$name=trim((string)($item['item_name']??''));if($name!=='')$names[]=$name;}
        $list=$names===[]?'':'<h3>Cursos individuais desta Trilha</h3><ol><li>'.implode('</li><li>',array_map(static fn(string$name):string=>htmlspecialchars($name,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'),$names)).'</li></ol>';
        return'<p>'.nl2br(htmlspecialchars($description,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')).'</p>'.$workloadLabel.$list.'<p><small>Publicação gerenciada pelo Mundo Inter.</small></p>';
    }

    /** @param array<string,mixed> $trail @return list<array<string,mixed>> */
    private function sections(array$trail):array
    {
        $sections=[];
        $conted=null;
        foreach((array)($trail['items']??[])as$position=>$item){
            $name=trim((string)($item['item_name']??''));
            if($name==='')continue;
            $execution=(string)($item['execution_environment']??'provider_ava');
            $catalog=trim((string)($item['item_catalog']??'Formação'));
            $remote=trim((string)($item['remote_reference']??''));
            $template=trim((string)($item['launch_url_template']??''));
            $accessUrl='';
            if($template!==''&&!str_contains($template,'{franquia}')){
                $accessUrl=str_replace(['{curso}','{id}'],rawurlencode($remote),$template);
                if(filter_var($accessUrl,FILTER_VALIDATE_URL)===false)$accessUrl='';
            }
            $section=[
                'key'=>(string)$item['item_type'].'-'.(int)$item['item_id'],
                'position'=>$position+1,
                'name'=>$name,
                'catalog'=>$catalog,
                'execution'=>$execution==='shared_ava'?'shared_ava':'provider_ava',
                'accessurl'=>$accessUrl,
            ];
            if((string)($item['provider_code']??'')==='conted_tech'&&in_array((string)($item['content_type']??''),['discipline','unit','object'],true)&&$remote!==''){
                if($conted===null)$conted=$this->contedClient();
                $exam=$conted->exam((string)$item['content_type'],$remote);
                if($exam!==null)$section['exam']=$exam;
            }
            $sections[]=$section;
        }
        return$sections;
    }

    private function contedClient():ContedTechClient
    {
        $settings=$this->providers->settingsForProvider('conted_tech',true);
        $client=new ContedTechClient((string)$settings['base_url'],(string)$settings['token'],(string)$settings['password'],(string)$settings['username'],(bool)$settings['is_active']);
        if(!$client->ready())throw new RuntimeException('A integração EXPERT precisa estar configurada e ativa para consultar as avaliações antes da publicação.');
        return$client;
    }

    /** @param array<string,mixed> $trail */
    private function signature(array$trail):string
    {
        return hash('sha256',json_encode(['name'=>$trail['name'],'slug'=>$trail['slug'],'category'=>$trail['category_id'],'workload_hours'=>$trail['workload_hours'],'description'=>$trail['description'],'media_asset_id'=>(int)($trail['media_asset_id']??0),'items'=>array_map(static fn(array$item):array=>[$item['item_type'],$item['item_id'],$item['sort_order']],(array)$trail['items'])],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    }

    /** @param array<string,mixed> $trail */
    private function coverUrl(array$trail):string
    {
        $assetId=(int)($trail['media_asset_id']??0);
        if($assetId<1)return'';
        return rtrim($this->publicBaseUrl,'/').'/catalog-media/'.$assetId;
    }

    private function limitedCode(string$value,int$length):string
    {
        $ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);$code=strtoupper((string)preg_replace('/[^A-Z0-9-]+/','-',strtoupper((string)($ascii===false?$value:$ascii))));
        return rtrim(mb_substr(trim($code,'-'),0,$length),'-');
    }

    private function friendlyError(Throwable$exception):string
    {
        $message=trim($exception->getMessage());
        if(str_contains($message,'local_mundointer_sync_trail_sections'))return'O plugin Mundo Inter do AVA precisa ser atualizado para organizar os Cursos individuais em blocos separados.';
        if(str_contains($message,'local_mundointer_materialize_lti_course'))return'O plugin Mundo Inter do AVA precisa ser atualizado para transformar a disciplina MASTER em Curso Individual.';
        if(str_contains($message,'core_course_create_courses')||str_contains($message,'core_course_update_courses')||str_contains($message,'core_course_create_categories'))return'O serviço web do AVA ainda não permite publicar cursos e categorias. Libere as funções acadêmicas no token do AVA Cursos e tente novamente.';
        return$message!==''?$message:'Não foi possível publicar a Trilha no AVA Cursos.';
    }
}
