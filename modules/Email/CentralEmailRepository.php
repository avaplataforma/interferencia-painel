<?php

declare(strict_types=1);

namespace Interferencia\Modules\Email;

use Interferencia\Kernel\Security\SecretCipher;
use PDO;
use RuntimeException;
use Throwable;

final readonly class CentralEmailRepository
{
    public function __construct(private PDO $database, private SecretCipher $cipher) {}

    /** @return array<string,mixed> */
    public function settings(bool $includeSecrets = false): array
    {
        try {$row=$this->database->query('SELECT * FROM central_email_integrations WHERE id=1')->fetch()?:[];} catch(Throwable){$row=[];}
        $username=$includeSecrets?$this->cipher->decrypt(isset($row['username_encrypted'])?(string)$row['username_encrypted']:null):'';
        $password=$includeSecrets?$this->cipher->decrypt(isset($row['password_encrypted'])?(string)$row['password_encrypted']:null):'';
        return[
            'provider'=>(string)($row['provider']??'smtp'),'smtp_host'=>(string)($row['smtp_host']??''),'smtp_port'=>(int)($row['smtp_port']??587),
            'encryption'=>(string)($row['encryption']??'tls'),'username'=>$username,'password'=>$password,
            'username_last4'=>(string)($row['username_last4']??''),'password_last4'=>(string)($row['password_last4']??''),
            'from_name'=>(string)($row['from_name']??'Mundo Inter'),'from_email'=>(string)($row['from_email']??'no-reply@mundointer.com.br'),
            'reply_to_email'=>(string)($row['reply_to_email']??''),'is_active'=>(int)($row['is_active']??0)===1,
            'configured'=>isset($row['id'])&&trim((string)($row['smtp_host']??''))!==''&&!empty($row['username_encrypted'])&&!empty($row['password_encrypted']),
            'last_tested_at'=>$row['last_tested_at']??null,'last_error'=>$row['last_error']??null,
        ];
    }

    /** @param array<string,mixed> $data */
    public function save(array $data, ?int $userId): void
    {
        if(!$this->cipher->ready())throw new RuntimeException('A chave-mestra de criptografia ainda não foi configurada.');
        $current=$this->settings(true);$host=strtolower(trim((string)($data['smtp_host']??'')));$port=(int)($data['smtp_port']??587);
        $encryption=(string)($data['encryption']??'tls');$username=trim((string)($data['username']??''));$password=(string)($data['password']??'');
        $fromName=trim((string)($data['from_name']??''));$fromEmail=strtolower(trim((string)($data['from_email']??'')));$reply=strtolower(trim((string)($data['reply_to_email']??'')));
        if($host===''||preg_match('/^[a-z0-9.-]+$/',$host)!==1)throw new RuntimeException('Informe um servidor SMTP válido.');
        if($port<1||$port>65535)throw new RuntimeException('Informe uma porta SMTP válida.');
        if(!in_array($encryption,['tls','ssl','none'],true))throw new RuntimeException('Selecione uma criptografia SMTP válida.');
        if($username==='')$username=(string)$current['username'];if($password==='')$password=(string)$current['password'];
        if($username===''||$password==='')throw new RuntimeException('Informe o usuário e a senha SMTP.');
        if($fromName===''||mb_strlen($fromName)>160)throw new RuntimeException('Informe o nome do remetente central.');
        if(filter_var($fromEmail,FILTER_VALIDATE_EMAIL)===false)throw new RuntimeException('Informe um e-mail remetente válido.');
        if($reply!==''&&filter_var($reply,FILTER_VALIDATE_EMAIL)===false)throw new RuntimeException('Informe um e-mail de resposta válido.');
        $sql="INSERT INTO central_email_integrations(id,provider,smtp_host,smtp_port,encryption,username_encrypted,username_last4,password_encrypted,password_last4,from_name,from_email,reply_to_email,is_active,updated_by) VALUES(1,'smtp',:host,:port,:encryption,:username,:username4,:password,:password4,:from_name,:from_email,:reply,:active,:user) ON DUPLICATE KEY UPDATE smtp_host=VALUES(smtp_host),smtp_port=VALUES(smtp_port),encryption=VALUES(encryption),username_encrypted=VALUES(username_encrypted),username_last4=VALUES(username_last4),password_encrypted=VALUES(password_encrypted),password_last4=VALUES(password_last4),from_name=VALUES(from_name),from_email=VALUES(from_email),reply_to_email=VALUES(reply_to_email),is_active=VALUES(is_active),updated_by=VALUES(updated_by)";
        $this->database->prepare($sql)->execute(['host'=>$host,'port'=>$port,'encryption'=>$encryption,'username'=>$this->cipher->encrypt($username),'username4'=>substr($username,-4),'password'=>$this->cipher->encrypt($password),'password4'=>substr($password,-4),'from_name'=>$fromName,'from_email'=>$fromEmail,'reply'=>$reply!==''?$reply:null,'active'=>(int)(($data['is_active']??false)===true),'user'=>$userId]);
    }

    public function markTest(?string $error): void
    {
        $this->database->prepare('UPDATE central_email_integrations SET last_tested_at=NOW(),last_error=:error WHERE id=1')->execute(['error'=>$error===null?null:mb_substr($error,0,500)]);
    }

    /** @return list<array<string,mixed>> */
    public function senders(): array
    {
        try{return$this->database->query("SELECT o.id organization_id,o.display_name,o.support_email,o.manager_email,d.host primary_host,d.status site_domain_status,s.from_name,s.from_email,s.reply_to_email,s.domain_status,s.is_active,s.verified_at FROM organizations o LEFT JOIN organization_domains d ON d.organization_id=o.id AND d.is_primary=1 AND d.purpose='site' LEFT JOIN organization_email_senders s ON s.organization_id=o.id ORDER BY o.display_name")->fetchAll()?:[];}catch(Throwable){return[];}
    }

    /** @return array<string,mixed>|null */
    public function senderForOrganization(int $organizationId): ?array
    {
        $statement=$this->database->prepare("SELECT o.display_name,o.support_email,o.manager_email,d.host primary_host,s.from_name,s.from_email,s.reply_to_email,s.domain_status,s.is_active FROM organizations o LEFT JOIN organization_domains d ON d.organization_id=o.id AND d.is_primary=1 AND d.purpose='site' LEFT JOIN organization_email_senders s ON s.organization_id=o.id WHERE o.id=:id LIMIT 1");
        $statement->execute(['id'=>$organizationId]);$row=$statement->fetch();return is_array($row)?$row:null;
    }

    public function saveSender(int $organizationId,array $data,?int$userId):void
    {
        $organization=$this->senderForOrganization($organizationId);if($organization===null)throw new RuntimeException('Franquia não encontrada.');
        $name=trim((string)($data['from_name']??$organization['display_name']));$email=strtolower(trim((string)($data['from_email']??'')));$reply=strtolower(trim((string)($data['reply_to_email']??'')));$status=(string)($data['domain_status']??'pending');$active=($data['is_active']??false)===true;
        if($name===''||mb_strlen($name)>160)throw new RuntimeException('Informe o nome que aparecerá no remetente.');
        if(filter_var($email,FILTER_VALIDATE_EMAIL)===false)throw new RuntimeException('Informe um endereço de envio válido.');
        if($reply!==''&&filter_var($reply,FILTER_VALIDATE_EMAIL)===false)throw new RuntimeException('Informe um endereço de resposta válido.');
        if(!in_array($status,['pending','verified','rejected'],true))throw new RuntimeException('Situação de domínio inválida.');
        $host=strtolower((string)($organization['primary_host']??''));$mailDomain=preg_replace('/^www\./','',$host)?:$host;$emailDomain=substr(strrchr($email,'@')?:'',1);
        if($host===''||($emailDomain!==$host&&$emailDomain!==$mailDomain&&!str_ends_with($emailDomain,'.'.$mailDomain)))throw new RuntimeException('O remetente deve usar o domínio público cadastrado para a franquia.');
        if($active&&$status!=='verified')throw new RuntimeException('Autentique SPF e DKIM antes de ativar o remetente da franquia.');
        $sql="INSERT INTO organization_email_senders(organization_id,from_name,from_email,reply_to_email,domain_status,is_active,verified_at,updated_by) VALUES(:organization,:name,:email,:reply,:status,:active,:verified,:user) ON DUPLICATE KEY UPDATE from_name=VALUES(from_name),from_email=VALUES(from_email),reply_to_email=VALUES(reply_to_email),domain_status=VALUES(domain_status),is_active=VALUES(is_active),verified_at=VALUES(verified_at),updated_by=VALUES(updated_by)";
        $this->database->prepare($sql)->execute(['organization'=>$organizationId,'name'=>$name,'email'=>$email,'reply'=>$reply!==''?$reply:null,'status'=>$status,'active'=>(int)$active,'verified'=>$status==='verified'?date('Y-m-d H:i:s'):null,'user'=>$userId]);
    }

    public function record(array$data):void
    {
        $sql='INSERT INTO email_delivery_logs(organization_id,message_type,recipient_email,sender_email,subject,status,provider_message_id,error_message,related_type,related_id) VALUES(:organization,:type,:recipient,:sender,:subject,:status,:message_id,:error,:related_type,:related_id)';
        $this->database->prepare($sql)->execute(['organization'=>$data['organization_id']??null,'type'=>$data['message_type']??'transactional','recipient'=>$data['recipient_email'],'sender'=>$data['sender_email'],'subject'=>mb_substr((string)$data['subject'],0,255),'status'=>$data['status'],'message_id'=>isset($data['provider_message_id'])?mb_substr((string)$data['provider_message_id'],0,190):null,'error'=>isset($data['error_message'])?mb_substr((string)$data['error_message'],0,500):null,'related_type'=>$data['related_type']??null,'related_id'=>$data['related_id']??null]);
    }

    public function summary():array
    {
        try{$row=$this->database->query("SELECT COUNT(*) total,SUM(status='sent') sent,SUM(status='failed') failed FROM email_delivery_logs WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)")->fetch()?:[];return['total'=>(int)($row['total']??0),'sent'=>(int)($row['sent']??0),'failed'=>(int)($row['failed']??0)];}catch(Throwable){return['total'=>0,'sent'=>0,'failed'=>0];}
    }

    public function recent(int$limit=15):array
    {
        $limit=max(1,min(50,$limit));try{return$this->database->query("SELECT l.*,o.display_name organization_name FROM email_delivery_logs l LEFT JOIN organizations o ON o.id=l.organization_id ORDER BY l.id DESC LIMIT {$limit}")->fetchAll()?:[];}catch(Throwable){return[];}
    }
}
