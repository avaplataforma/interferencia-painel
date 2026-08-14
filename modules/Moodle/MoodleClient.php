<?php

declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use RuntimeException;

final readonly class MoodleClient
{
    public function __construct(private string $baseUrl,private string $token,private bool $active) {}

    public function ready():bool{return$this->active&&filter_var($this->baseUrl,FILTER_VALIDATE_URL)!==false&&str_starts_with($this->baseUrl,'https://')&&strlen($this->token)>=20&&function_exists('curl_init');}

    /** @return array<string,mixed> */
    public function siteInfo():array{return$this->call('core_webservice_get_site_info');}

    /** @return array<string,mixed> */
    public function connectorInfo():array{return$this->call('local_mundointer_ping');}

    /** @param array<string,mixed> $catalog @return array<string,mixed> */
    public function syncBrands(array$catalog):array
    {
        $json=json_encode($catalog,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        return$this->call('local_mundointer_sync_brands',['catalog'=>$json]);
    }

    /** @return array<string,mixed> */
    public function poloDiagnostics():array{return$this->call('local_mundointer_diagnose_poles');}

    /** @return list<array<string,mixed>> */
    public function courses():array
    {
        $data=$this->call('core_course_get_courses');return array_values(array_filter($data,'is_array'));
    }

    /** @return list<array<string,mixed>> */
    public function enrolledUsers(int$courseId):array
    {
        $data=$this->call('core_enrol_get_enrolled_users',['courseid'=>$courseId]);return array_values(array_filter($data,'is_array'));
    }

    /** @return list<array<string,mixed>> */
    public function usersByField(string$field,string$value):array
    {
        if(!in_array($field,['email','idnumber','username'],true)||trim($value)==='')return[];
        $data=$this->call('core_user_get_users_by_field',['field'=>$field,'values'=>[trim($value)]]);return array_values(array_filter($data,'is_array'));
    }

    /** @param array<string,mixed> $user @return array<string,mixed> */
    public function createUser(array$user):array
    {
        $data=$this->call('core_user_create_users',['users'=>[$user]]);$created=$data[0]??null;if(!is_array($created)||!isset($created['id']))throw new RuntimeException('O AVA não confirmou a criação do usuário.');return$created;
    }

    public function enrolStudent(int$userId,int$courseId):void
    {
        if($userId<1||$courseId<1)throw new RuntimeException('Usuário ou curso inválido para matrícula no AVA.');
        foreach($this->enrolledUsers($courseId)as$user)if((int)($user['id']??0)===$userId)return;
        $this->call('enrol_manual_enrol_users',['enrolments'=>[['roleid'=>5,'userid'=>$userId,'courseid'=>$courseId]]]);
    }

    /** @param array<string,mixed> $organization @return array<string,mixed> */
    public function organizeEnrollment(int$userId,int$courseId,array$organization):array
    {
        if($userId<1||$courseId<1)throw new RuntimeException('Aluno ou curso inválido para organizar a turma no AVA.');
        return$this->call('local_mundointer_organize_enrollment',['userid'=>$userId,'courseid'=>$courseId]+$organization);
    }

    /** @param list<array<string,mixed>> $sections @return array<string,mixed> */
    public function syncTrailSections(int$courseId,array$sections,string$coverUrl='',string$coverAlt=''):array
    {
        if($courseId<1)throw new RuntimeException('Curso inválido para organizar os blocos da Trilha no AVA.');
        $json=json_encode($sections,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        return$this->call('local_mundointer_sync_trail_sections',['courseid'=>$courseId,'sections'=>$json,'coverurl'=>trim($coverUrl),'coveralt'=>trim($coverAlt)]);
    }

    /** @return list<array<string,mixed>> */
    public function courseCategories():array
    {
        $data=$this->call('core_course_get_categories');
        return array_values(array_filter($data,'is_array'));
    }

    /** @return array<string,mixed> */
    public function createCourseCategory(string$name,string$idNumber,int$parent=0):array
    {
        $name=trim($name);$idNumber=trim($idNumber);
        if($name===''||$idNumber==='')throw new RuntimeException('Nome ou código inválido para a categoria do AVA.');
        $data=$this->call('core_course_create_categories',['categories'=>[['name'=>$name,'idnumber'=>$idNumber,'parent'=>max(0,$parent)]]]);
        $category=$data[0]??null;
        if(!is_array($category)||(int)($category['id']??0)<1)throw new RuntimeException('O AVA não confirmou a criação da categoria.');
        return$category;
    }

    /** @return array<string,mixed> */
    public function publishCourse(array$course):array
    {
        $idNumber=trim((string)($course['idnumber']??''));
        if($idNumber==='')throw new RuntimeException('O curso precisa de um código permanente antes da publicação.');
        $existing=null;
        foreach($this->courses()as$candidate)if(trim((string)($candidate['idnumber']??''))===$idNumber){$existing=$candidate;break;}
        $payload=['fullname'=>trim((string)($course['fullname']??'')),'shortname'=>trim((string)($course['shortname']??'')),'categoryid'=>(int)($course['categoryid']??0),'idnumber'=>$idNumber,'summary'=>(string)($course['summary']??''),'summaryformat'=>1,'format'=>'topics','visible'=>1];
        if($payload['fullname']===''||$payload['shortname']===''||$payload['categoryid']<1)throw new RuntimeException('A publicação está sem nome, código curto ou categoria do AVA.');
        if(is_array($existing)){
            $payload['id']=(int)$existing['id'];
            $this->call('core_course_update_courses',['courses'=>[$payload]]);
            return$payload+$existing;
        }
        $created=$this->call('core_course_create_courses',['courses'=>[$payload]]);
        $remote=$created[0]??null;
        if(!is_array($remote)||(int)($remote['id']??0)<1)throw new RuntimeException('O AVA não confirmou a criação do curso.');
        return$remote+$payload;
    }

    /** @param list<array{type:string,value:string}> $customFields */
    public function updateUserCustomFields(int$userId,array$customFields):void
    {
        if($userId<1||$customFields===[])return;$this->call('core_user_update_users',['users'=>[['id'=>$userId,'customfields'=>$customFields]]]);
    }

    public function setUserSuspended(int$userId,bool$suspended):void
    {
        if($userId<1)throw new RuntimeException('Usuário inválido para alteração de acesso ao AVA.');
        $this->call('core_user_update_users',['users'=>[['id'=>$userId,'suspended'=>$suspended?1:0]]]);
    }

    /** @return array<string,mixed> */
    public function courseCompletionStatus(int$userId,int$courseId):array
    {
        if($userId<1||$courseId<1)throw new RuntimeException('Aluno ou curso inválido para consultar o progresso.');
        return$this->call('core_completion_get_course_completion_status',['userid'=>$userId,'courseid'=>$courseId]);
    }

    /** @return array<string,mixed> */
    public function academicSnapshot(int$userId,int$courseId):array
    {
        if($userId<1||$courseId<1)throw new RuntimeException('Aluno ou curso inválido para consultar o acompanhamento acadêmico.');
        return$this->call('local_mundointer_academic_snapshot',['userid'=>$userId,'courseid'=>$courseId]);
    }

    /** @return array{provider:string,courses:list<array<string,mixed>>,coursecount:int,contentcount:int,syncedat:int} */
    public function ltiSelections(string$provider='iesde'):array
    {
        $response=$this->call('local_mundointer_lti_selections',['provider'=>$provider]);
        $payload=json_decode((string)($response['payload']??''),true);
        if(!is_array($payload)||!is_array($payload['courses']??null))throw new RuntimeException('O AVA Cursos retornou uma seleção LTI inválida.');
        $payload['courses']=array_values(array_filter($payload['courses'],'is_array'));
        return$payload;
    }

    /** @return array<string,mixed> */
    public function materializeLtiCourse(int$sourceCmId,int$targetCourseId,string$activityName,string$idNumber,string$coverUrl='',string$coverAlt='',array$assessment=[]):array
    {
        if($sourceCmId<1||$targetCourseId<1)throw new RuntimeException('A atividade MASTER ou o curso de destino é inválido.');
        return$this->call('local_mundointer_materialize_lti_course',['sourcecmid'=>$sourceCmId,'targetcourseid'=>$targetCourseId,'activityname'=>$activityName,'idnumber'=>$idNumber,'coverurl'=>$coverUrl,'coveralt'=>$coverAlt,'assessmentjson'=>$assessment===[]?'':json_encode($assessment,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
    }

    /** @param array<string,mixed> $parameters @return array<mixed> */
    private function call(string$function,array$parameters=[]):array
    {
        if(!$this->ready())throw new RuntimeException('A conexão com o Moodle ainda não está configurada e ativa.');
        $url=rtrim($this->baseUrl,'/').'/webservice/rest/server.php';
        $payload=['wstoken'=>$this->token,'wsfunction'=>$function,'moodlewsrestformat'=>'json']+$parameters;
        $curl=curl_init($url);if($curl===false)throw new RuntimeException('Não foi possível iniciar a conexão com o Moodle.');
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($payload),CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>in_array($function,['local_mundointer_sync_trail_sections','local_mundointer_materialize_lti_course'],true)?180:45,CURLOPT_HTTPHEADER=>['Accept: application/json','User-Agent: PAINEL-INTER/1.0']]);
        $response=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$error=curl_error($curl);curl_close($curl);
        if(!is_string($response))throw new RuntimeException('Falha de comunicação com o Moodle'.($error!==''?': '.$error:'').'.');
        $data=json_decode($response,true);
        $voidResponse=trim($response)==='null'||trim($response)==='';
        if($status<200||$status>=300||(!is_array($data)&&!$voidResponse)){
            $format=json_last_error()===JSON_ERROR_NONE?get_debug_type($data):'JSON inválido';
            throw new RuntimeException('O Moodle retornou uma resposta inválida. (Função Moodle: '.$function.' | HTTP '.$status.' | Formato: '.$format.')');
        }
        if($voidResponse)return[];
        if(isset($data['exception'])){
            $message=trim((string)($data['message']??'O Moodle recusou a operação.'));
            $errorCode=trim((string)($data['errorcode']??''));
            $diagnostic='Função Moodle: '.$function.($errorCode!==''?' | Código: '.$errorCode:'');
            throw new RuntimeException($message.' ('.$diagnostic.')');
        }
        return$data;
    }
}
