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
        if(isset($data['exception']))throw new RuntimeException((string)($data['message']??'O Moodle recusou a operação.'));
        return$data;
    }
}
