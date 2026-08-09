<?php

declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use Interferencia\Kernel\Security\SecretCipher;
use PDO;
use RuntimeException;
use Throwable;

final readonly class AvaConnectionRepository
{
    public function __construct(private PDO $database, private SecretCipher $cipher) {}

    public function encryptionReady(): bool { return $this->cipher->ready(); }

    /** @return array<string,mixed> */
    public function shared(): array
    {
        return $this->connectionByKey('shared:ava-cursos') ?? $this->emptyConnection('shared:ava-cursos','AVA Cursos','shared');
    }

    /** @return array<string,mixed> */
    public function organizationSettings(int $organizationId): array
    {
        try {
            $statement=$this->database->prepare("SELECT s.*,shared.connection_key shared_key,shared.name shared_name,shared.base_url shared_base_url,shared.token_encrypted shared_token_encrypted,shared.token_last4 shared_token_last4,shared.is_active shared_active,shared.last_tested_at shared_last_tested_at,shared.last_error shared_last_error,own.connection_key own_key,own.name own_name,own.base_url own_base_url,own.token_encrypted own_token_encrypted,own.token_last4 own_token_last4,own.is_active own_active,own.last_tested_at own_last_tested_at,own.last_error own_last_error FROM organization_ava_settings s LEFT JOIN ava_connections shared ON shared.id=s.shared_connection_id LEFT JOIN ava_connections own ON own.id=s.own_connection_id WHERE s.organization_id=:organization LIMIT 1");
            $statement->execute(['organization'=>$organizationId]);$row=$statement->fetch()?:[];
        } catch (Throwable) { $row=[]; }
        $shared=$this->shared();
        $own=$this->connectionByKey('franchise:'.$organizationId.':own') ?? $this->emptyConnection('franchise:'.$organizationId.':own','AVA próprio','franchise',$organizationId);
        $shared['mapped_courses']=$this->mappedCourseCount((int)($shared['id']??0));
        $own['mapped_courses']=$this->mappedCourseCount((int)($own['id']??0));
        return [
            'organization_id'=>$organizationId,
            'access_mode'=>in_array(($row['access_mode']??'shared'),['shared','own','both'],true)?(string)$row['access_mode']:'shared',
            'primary_ava'=>in_array(($row['primary_ava']??'shared'),['shared','own'],true)?(string)$row['primary_ava']:'shared',
            'shared'=>$shared,
            'own'=>$own,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function allConnections(): array
    {
        try {$rows=$this->database->query("SELECT c.*,o.display_name organization_name FROM ava_connections c LEFT JOIN organizations o ON o.id=c.organization_id ORDER BY c.connection_type,c.name")->fetchAll()?:[];}catch(Throwable){$rows=[];}
        return array_map(fn(array$row):array=>$this->hydrate($row),$rows);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        try{$statement=$this->database->prepare('SELECT * FROM ava_connections WHERE id=:id LIMIT 1');$statement->execute(['id'=>$id]);$row=$statement->fetch();return is_array($row)?$this->hydrate($row):null;}catch(Throwable){return null;}
    }

    public function saveShared(string $baseUrl,string $token,bool $active,int $userId): void
    {
        $this->saveConnection('shared:ava-cursos',null,'shared','AVA Cursos',$baseUrl,$token,$active,$userId);
    }

    public function saveOrganization(int $organizationId,string $mode,string $primary,string $ownBaseUrl,string $ownToken,bool $ownActive,int $userId): void
    {
        if(!in_array($mode,['shared','own','both'],true))throw new RuntimeException('Selecione como a franquia utilizará o AVA.');
        if(!in_array($primary,['shared','own'],true))throw new RuntimeException('Selecione o AVA principal.');
        if($mode==='shared')$primary='shared';if($mode==='own')$primary='own';
        $shared=$this->shared();
        if(in_array($mode,['shared','both'],true)&&!$shared['configured'])throw new RuntimeException('Configure primeiro a integração global AVA Cursos.');
        $ownId=null;
        if(in_array($mode,['own','both'],true)){
            $this->saveConnection('franchise:'.$organizationId.':own',$organizationId,'franchise','AVA próprio',$ownBaseUrl,$ownToken,$ownActive,$userId);
            $own=$this->connectionByKey('franchise:'.$organizationId.':own');$ownId=(int)($own['id']??0)?:null;
        }
        $sql="INSERT INTO organization_ava_settings(organization_id,access_mode,primary_ava,shared_connection_id,own_connection_id) VALUES(:organization,:mode,:primary,:shared,:own) ON DUPLICATE KEY UPDATE access_mode=VALUES(access_mode),primary_ava=VALUES(primary_ava),shared_connection_id=VALUES(shared_connection_id),own_connection_id=VALUES(own_connection_id)";
        $this->database->prepare($sql)->execute(['organization'=>$organizationId,'mode'=>$mode,'primary'=>$primary,'shared'=>(int)$shared['id']?:null,'own'=>$ownId]);
    }

    /** @return array<string,mixed>|null */
    public function forOrganization(int $organizationId,string $type): ?array
    {
        $settings=$this->organizationSettings($organizationId);
        return $type==='own'?$settings['own']:($type==='shared'?$settings['shared']:null);
    }

    /** @return list<array<string,mixed>> */
    public function destinationsForCourse(int $organizationId,int $moodleCourseId): array
    {
        $settings=$this->organizationSettings($organizationId);$allowed=[];
        if(in_array($settings['access_mode'],['shared','both'],true))$allowed['shared']=$settings['shared'];
        if(in_array($settings['access_mode'],['own','both'],true))$allowed['own']=$settings['own'];
        $destinations=[];
        foreach($allowed as$type=>$connection){
            if(!(bool)($connection['configured']??false)||!(bool)($connection['is_active']??false)||(int)($connection['id']??0)<1)continue;
            $statement=$this->database->prepare('SELECT remote_course_id,remote_shortname,remote_fullname FROM ava_course_mappings WHERE connection_id=:connection AND moodle_course_id=:course LIMIT 1');
            $statement->execute(['connection'=>(int)$connection['id'],'course'=>$moodleCourseId]);$mapping=$statement->fetch();
            if(!is_array($mapping)&&$type==='shared'){
                $statement=$this->database->prepare('SELECT moodle_course_id remote_course_id,shortname remote_shortname,fullname remote_fullname FROM moodle_courses WHERE id=:course AND visible=1 LIMIT 1');$statement->execute(['course'=>$moodleCourseId]);$mapping=$statement->fetch();
            }
            if(!is_array($mapping))continue;
            $destinations[]=['connection_id'=>(int)$connection['id'],'type'=>$type,'name'=>(string)$connection['name'],'remote_course_id'=>(int)$mapping['remote_course_id'],'remote_course_name'=>(string)$mapping['remote_fullname'],'primary'=>$settings['primary_ava']===$type];
        }
        usort($destinations,static fn(array$a,array$b):int=>(int)$b['primary']<=>(int)$a['primary']);
        return$destinations;
    }

    /** @return array<string,mixed> */
    public function resolveDestination(int $organizationId,int $moodleCourseId,int $connectionId): array
    {
        foreach($this->destinationsForCourse($organizationId,$moodleCourseId)as$destination)if((int)$destination['connection_id']===$connectionId)return$destination;
        throw new RuntimeException('O curso selecionado ainda não está disponível no AVA escolhido. Sincronize os cursos na configuração da franquia.');
    }

    /** @return array<string,mixed> */
    public function resolveEnrollmentDestination(int $organizationId,int $productId,int $connectionId): array
    {
        $statement=$this->database->prepare('SELECT moodle_course_id FROM finance_products WHERE id=:product AND is_active=1 LIMIT 1');
        $statement->execute(['product'=>$productId]);
        $moodleCourseId=(int)$statement->fetchColumn();
        if($moodleCourseId<1)throw new RuntimeException('O curso contratado não está vinculado ao catálogo do AVA.');
        return$this->resolveDestination($organizationId,$moodleCourseId,$connectionId);
    }

    /** @return array{mapped:int,unmatched:int,total:int} */
    public function synchronizeCourses(int $connectionId,array $remoteCourses): array
    {
        $connection=$this->find($connectionId);if($connection===null)throw new RuntimeException('Conexão AVA não encontrada.');
        $locals=$this->database->query('SELECT id,shortname,idnumber,fullname FROM moodle_courses WHERE visible=1')->fetchAll()?:[];$mapped=0;$unmatched=0;
        $this->database->beginTransaction();
        try{
            foreach($remoteCourses as$remote){
                if(!is_array($remote)||(int)($remote['id']??0)<1)continue;$match=null;$remoteNumber=trim((string)($remote['idnumber']??''));$remoteShort=trim((string)($remote['shortname']??''));
                foreach($locals as$local){$localNumber=trim((string)($local['idnumber']??''));if($remoteNumber!==''&&$localNumber!==''&&strcasecmp($remoteNumber,$localNumber)===0){$match=$local;break;}}
                if($match===null)foreach($locals as$local)if($remoteShort!==''&&strcasecmp($remoteShort,(string)$local['shortname'])===0){$match=$local;break;}
                if($match===null){$unmatched++;continue;}
                $sql="INSERT INTO ava_course_mappings(connection_id,moodle_course_id,remote_course_id,remote_shortname,remote_fullname,match_method) VALUES(:connection,:course,:remote,:short,:full,'automatic') ON DUPLICATE KEY UPDATE remote_course_id=VALUES(remote_course_id),remote_shortname=VALUES(remote_shortname),remote_fullname=VALUES(remote_fullname),match_method='automatic',synced_at=NOW()";
                $this->database->prepare($sql)->execute(['connection'=>$connectionId,'course'=>(int)$match['id'],'remote'=>(int)$remote['id'],'short'=>$remoteShort,'full'=>(string)($remote['fullname']??$remoteShort)]);$mapped++;
            }
            $this->database->commit();
        }catch(Throwable$exception){if($this->database->inTransaction())$this->database->rollBack();throw$exception;}
        return['mapped'=>$mapped,'unmatched'=>$unmatched,'total'=>count($remoteCourses)];
    }

    public function mappedCourseCount(int $connectionId): int
    {
        try{$statement=$this->database->prepare('SELECT COUNT(*) FROM ava_course_mappings WHERE connection_id=:connection');$statement->execute(['connection'=>$connectionId]);return(int)$statement->fetchColumn();}catch(Throwable){return 0;}
    }

    public function recordTest(int $connectionId,?string $error): void
    {
        $this->database->prepare('UPDATE ava_connections SET last_tested_at=NOW(),last_error=:error WHERE id=:id')->execute(['error'=>$error,'id'=>$connectionId]);
    }

    /** @param array<string,mixed>|null $info */
    public function recordConnectorTest(int $connectionId,?array $info,?string $error): void
    {
        if($info!==null){
            $this->database->prepare('UPDATE ava_connections SET plugin_version=:version,plugin_release=:release,plugin_status=:status,plugin_last_error=NULL,last_seen_at=NOW() WHERE id=:id')->execute(['version'=>(string)($info['pluginversion']??''),'release'=>(string)($info['release']??''),'status'=>(string)($info['status']??'ok'),'id'=>$connectionId]);
            return;
        }
        $this->database->prepare('UPDATE ava_connections SET plugin_last_error=:error WHERE id=:id')->execute(['error'=>$error,'id'=>$connectionId]);
    }

    /** @param array<string,mixed>|null $pluginInfo */
    public function recordHealthCheck(int $connectionId,bool $moodleConnected,?array $pluginInfo,?string $error,string $expectedVersion): void
    {
        $pluginStatus='unknown';$installedVersion=null;
        if($moodleConnected&&$pluginInfo!==null){
            $installedVersion=(string)($pluginInfo['pluginversion']??'');
            $pluginStatus=($pluginInfo['status']??'ok')==='disabled'?'disabled':($installedVersion!==''&&version_compare($installedVersion,$expectedVersion,'<')?'outdated':'current');
        }elseif($moodleConnected){$pluginStatus='missing';}
        $this->database->beginTransaction();
        try{
            if(!$moodleConnected){
                $this->database->prepare('UPDATE ava_connections SET last_tested_at=NOW(),last_error=:error WHERE id=:id')->execute(['error'=>$error,'id'=>$connectionId]);
            }elseif($pluginInfo===null){
                $this->database->prepare('UPDATE ava_connections SET last_tested_at=NOW(),last_error=NULL,plugin_last_error=:error WHERE id=:id')->execute(['error'=>$error,'id'=>$connectionId]);
            }else{
                $this->database->prepare('UPDATE ava_connections SET last_tested_at=NOW(),last_error=NULL,plugin_version=:version,plugin_release=:release,plugin_status=:status,plugin_last_error=NULL,last_seen_at=NOW() WHERE id=:id')->execute(['version'=>$installedVersion,'release'=>(string)($pluginInfo['release']??''),'status'=>(string)($pluginInfo['status']??'ok'),'id'=>$connectionId]);
            }
            $this->database->prepare('INSERT INTO ava_connection_checks(connection_id,moodle_status,plugin_status,installed_version,expected_version,message) VALUES(:connection,:moodle,:plugin,:installed,:expected,:message)')->execute(['connection'=>$connectionId,'moodle'=>$moodleConnected?'connected':'error','plugin'=>$pluginStatus,'installed'=>$installedVersion,'expected'=>$expectedVersion,'message'=>$error]);
            $this->database->commit();
        }catch(Throwable$exception){if($this->database->inTransaction())$this->database->rollBack();throw$exception;}
    }

    /** @return list<array<string,mixed>> */
    public function recentChecks(int $limit=20): array
    {
        $limit=max(1,min(100,$limit));
        try{return$this->database->query("SELECT h.*,c.name connection_name,c.connection_type,o.display_name organization_name FROM ava_connection_checks h JOIN ava_connections c ON c.id=h.connection_id LEFT JOIN organizations o ON o.id=c.organization_id ORDER BY h.checked_at DESC,h.id DESC LIMIT {$limit}")->fetchAll()?:[];}catch(Throwable){return[];}
    }

    /** @return array<string,mixed>|null */
    private function connectionByKey(string $key): ?array
    {
        try{$statement=$this->database->prepare('SELECT * FROM ava_connections WHERE connection_key=:key LIMIT 1');$statement->execute(['key'=>$key]);$row=$statement->fetch();return is_array($row)?$this->hydrate($row):null;}catch(Throwable){return null;}
    }

    private function saveConnection(string$key,?int$organizationId,string$type,string$name,string$baseUrl,string$token,bool$active,int$userId):void
    {
        $baseUrl=rtrim(trim($baseUrl),'/');$current=$this->connectionByKey($key);
        if(filter_var($baseUrl,FILTER_VALIDATE_URL)===false||parse_url($baseUrl,PHP_URL_SCHEME)!=='https')throw new RuntimeException('Informe o endereço HTTPS válido do Moodle.');
        $token=trim($token);if($token===''&&!($current['configured']??false))throw new RuntimeException('Informe o token do Moodle.');
        if($token!==''&&(strlen($token)<20||strlen($token)>255))throw new RuntimeException('Informe um token válido do Moodle.');
        $encrypted=$token!==''?$this->cipher->encrypt($token):($current['token_encrypted']??null);$last4=$token!==''?substr($token,-4):($current['token_last4']??null);
        $sql="INSERT INTO ava_connections(connection_key,organization_id,connection_type,name,base_url,token_encrypted,token_last4,is_active,created_by,updated_by) VALUES(:key,:organization,:type,:name,:url,:token,:last4,:active,:created_by,:updated_by) ON DUPLICATE KEY UPDATE organization_id=VALUES(organization_id),connection_type=VALUES(connection_type),name=VALUES(name),base_url=VALUES(base_url),token_encrypted=VALUES(token_encrypted),token_last4=VALUES(token_last4),is_active=VALUES(is_active),last_error=NULL,updated_by=VALUES(updated_by)";
        $this->database->prepare($sql)->execute(['key'=>$key,'organization'=>$organizationId,'type'=>$type,'name'=>$name,'url'=>$baseUrl,'token'=>$encrypted,'last4'=>$last4,'active'=>(int)$active,'created_by'=>$userId,'updated_by'=>$userId]);
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function hydrate(array$row):array
    {
        $token=$this->cipher->decrypt(isset($row['token_encrypted'])?(string)$row['token_encrypted']:null);
        $row['token']=$token;
        $row['is_active']=(int)($row['is_active']??0)===1;
        $row['configured']=(string)($row['base_url']??'')!==''&&$token!=='';
        $row['healthy']=$row['configured']&&$row['is_active']&&($row['last_error']??null)===null;
        return$row;
    }

    /** @return array<string,mixed> */
    private function emptyConnection(string$key,string$name,string$type,?int$organizationId=null):array
    {
        return ['id'=>0,'connection_key'=>$key,'organization_id'=>$organizationId,'connection_type'=>$type,'name'=>$name,'base_url'=>'','token_encrypted'=>null,'token'=>'','token_last4'=>'','is_active'=>false,'configured'=>false,'healthy'=>false,'plugin_version'=>null,'plugin_release'=>null,'plugin_status'=>null,'plugin_last_error'=>null,'last_seen_at'=>null,'last_tested_at'=>null,'last_error'=>null];
    }
}
