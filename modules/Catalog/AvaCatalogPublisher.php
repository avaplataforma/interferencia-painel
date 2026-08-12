<?php

declare(strict_types=1);

namespace Interferencia\Modules\Catalog;

use Interferencia\Modules\Moodle\AvaConnectionRepository;
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
    ) {}

    /** @return array{remote_course_id:int,remote_category_id:int,created_or_updated:string} */
    public function publishTrail(int$trailId,?int$userId):array
    {
        $trail=$this->catalog->trailPublicationContext($trailId);
        if($trail===null)throw new RuntimeException('Trilha não encontrada.');
        if((int)($trail['is_active']??0)!==1)throw new RuntimeException('Ative a Trilha antes de publicá-la no AVA.');
        if((int)($trail['item_count']??0)<2)throw new RuntimeException('A Trilha precisa ter pelo menos dois Cursos individuais.');
        $connection=$this->connections->shared();
        if(!(bool)($connection['configured']??false)||!(bool)($connection['is_active']??false)||(int)($connection['id']??0)<1)throw new RuntimeException('Configure e ative primeiro a integração AVA Cursos.');

        $signature=$this->signature($trail);
        $publicationId=$this->catalog->markPublicationReady($trailId,(int)$connection['id'],$signature,$userId);
        try{
            $client=new MoodleClient((string)$connection['base_url'],(string)$connection['token'],true);
            $categoryId=$this->ensureCategoryPath($client,$trail);
            $idNumber='mi-trilha-'.$trailId;
            $shortName=$this->limitedCode('MI-TRILHA-'.$trailId.'-'.$trail['slug'],100);
            $summary=$this->summary($trail);
            $course=$client->publishCourse(['fullname'=>(string)$trail['name'],'shortname'=>$shortName,'idnumber'=>$idNumber,'categoryid'=>$categoryId,'summary'=>$summary]);
            $remoteCourseId=(int)($course['id']??0);
            if($remoteCourseId<1)throw new RuntimeException('O AVA não devolveu o identificador do curso publicado.');
            $sections=$this->sections($trail);
            $sectionSync=$client->syncTrailSections($remoteCourseId,$sections);
            $course['categoryid']=$categoryId;$course['fullname']=(string)$trail['name'];$course['shortname']=$shortName;$course['idnumber']=$idNumber;$course['visible']=1;
            $this->moodle->upsertCourse($course);
            $localCourseId=$this->moodle->localCourseIdByRemote($remoteCourseId);
            $this->catalog->markPublicationSuccess($publicationId,$localCourseId,$categoryId,$remoteCourseId,$signature,['trail_id'=>$trailId,'shortname'=>$shortName,'idnumber'=>$idNumber,'category_path'=>$this->categoryPath($trail),'item_count'=>(int)$trail['item_count'],'sections_synced'=>(int)($sectionSync['sections']??count($sections))],$userId);
            return['remote_course_id'=>$remoteCourseId,'remote_category_id'=>$categoryId,'created_or_updated'=>'published'];
        }catch(Throwable$exception){
            $this->catalog->markPublicationFailed($publicationId,$this->friendlyError($exception),$userId);
            throw new RuntimeException($this->friendlyError($exception),0,$exception);
        }
    }

    /** @param array<string,mixed> $trail */
    private function ensureCategoryPath(MoodleClient$client,array$trail):int
    {
        $categories=$client->courseCategories();
        $root=$this->findCategory($categories,'mi-mundo-inter');
        if($root===null){$root=$client->createCourseCategory('Mundo Inter','mi-mundo-inter');$categories[]=$root;}
        $parentId=(int)$root['id'];
        $path=$this->categoryPath($trail);
        foreach($path as$position=>$category){
            $idNumber='mi-categoria-'.$category['code'];
            $existing=$this->findCategory($categories,$idNumber);
            if($existing===null){$existing=$client->createCourseCategory($category['name'],$idNumber,$parentId);$categories[]=$existing;}
            $parentId=(int)$existing['id'];
        }
        return$parentId;
    }

    /** @param list<array<string,mixed>> $categories @return array<string,mixed>|null */
    private function findCategory(array$categories,string$idNumber):?array
    {
        foreach($categories as$category)if(trim((string)($category['idnumber']??''))===$idNumber)return$category;
        return null;
    }

    /** @param array<string,mixed> $trail @return list<array{name:string,code:string}> */
    private function categoryPath(array$trail):array
    {
        $path=[];
        if(trim((string)($trail['parent_category_name']??''))!=='')$path[]=['name'=>(string)$trail['parent_category_name'],'code'=>(string)$trail['parent_category_code']];
        $path[]=['name'=>(string)$trail['category_name'],'code'=>(string)$trail['category_code']];
        return$path;
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
        foreach((array)($trail['items']??[])as$position=>$item){
            $name=trim((string)($item['item_name']??''));
            if($name==='')continue;
            $execution=(string)($item['execution_environment']??'provider_ava');
            $catalog=trim((string)($item['item_catalog']??'Formação'));
            $remote=trim((string)($item['remote_reference']??''));
            $template=trim((string)($item['launch_url_template']??''));
            $accessUrl='';
            if($execution!=='shared_ava'&&$template!==''&&!str_contains($template,'{franquia}')){
                $accessUrl=str_replace(['{curso}','{id}'],rawurlencode($remote),$template);
                if(filter_var($accessUrl,FILTER_VALIDATE_URL)===false)$accessUrl='';
            }
            $sections[]=[
                'key'=>(string)$item['item_type'].'-'.(int)$item['item_id'],
                'position'=>$position+1,
                'name'=>$name,
                'catalog'=>$catalog,
                'execution'=>$execution==='shared_ava'?'shared_ava':'provider_ava',
                'accessurl'=>$accessUrl,
            ];
        }
        return$sections;
    }

    /** @param array<string,mixed> $trail */
    private function signature(array$trail):string
    {
        return hash('sha256',json_encode(['name'=>$trail['name'],'slug'=>$trail['slug'],'category'=>$trail['category_id'],'workload_hours'=>$trail['workload_hours'],'description'=>$trail['description'],'items'=>array_map(static fn(array$item):array=>[$item['item_type'],$item['item_id'],$item['sort_order']],(array)$trail['items'])],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
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
        if(str_contains($message,'core_course_create_courses')||str_contains($message,'core_course_update_courses')||str_contains($message,'core_course_create_categories'))return'O serviço web do AVA ainda não permite publicar cursos e categorias. Libere as funções acadêmicas no token do AVA Cursos e tente novamente.';
        return$message!==''?$message:'Não foi possível publicar a Trilha no AVA Cursos.';
    }
}
