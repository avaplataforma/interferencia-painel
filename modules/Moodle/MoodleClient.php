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

    /** @param array<string,mixed> $parameters @return array<mixed> */
    private function call(string$function,array$parameters=[]):array
    {
        if(!$this->ready())throw new RuntimeException('A conexão com o Moodle ainda não está configurada e ativa.');
        $url=rtrim($this->baseUrl,'/').'/webservice/rest/server.php';
        $payload=['wstoken'=>$this->token,'wsfunction'=>$function,'moodlewsrestformat'=>'json']+$parameters;
        $curl=curl_init($url);if($curl===false)throw new RuntimeException('Não foi possível iniciar a conexão com o Moodle.');
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($payload),CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>45,CURLOPT_HTTPHEADER=>['Accept: application/json','User-Agent: PAINEL-INTER/1.0']]);
        $response=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$error=curl_error($curl);curl_close($curl);
        if(!is_string($response))throw new RuntimeException('Falha de comunicação com o Moodle'.($error!==''?': '.$error:'').'.');
        $data=json_decode($response,true);
        if($status<200||$status>=300||!is_array($data))throw new RuntimeException('O Moodle retornou uma resposta inválida.');
        if(isset($data['exception'])){
            $message=trim((string)($data['message']??'O Moodle recusou a operação.'));
            $errorCode=trim((string)($data['errorcode']??''));
            $diagnostic='Função Moodle: '.$function.($errorCode!==''?' | Código: '.$errorCode:'');
            throw new RuntimeException($message.' ('.$diagnostic.')');
        }
        return$data;
    }
}
