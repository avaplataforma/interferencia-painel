<?php

declare(strict_types=1);

namespace Interferencia\Modules\Finance;

use Interferencia\Kernel\Security\SecretCipher;
use PDO;
use RuntimeException;
use Throwable;

final readonly class IntegrationRepository
{
    public function __construct(private PDO$database,private SecretCipher$cipher){}
    public function encryptionReady():bool{return$this->cipher->ready();}
    /** @return array{environment:string,api_key:string,api_key_last4:string,webhook_token:string,is_active:bool,configured:bool} */
    public function asaas():array
    {
        try{$row=$this->database->query("SELECT * FROM finance_integrations WHERE provider='asaas' LIMIT 1")->fetch()?:[];}catch(Throwable){$row=[];}
        $api=$this->cipher->decrypt(isset($row['api_key_encrypted'])?(string)$row['api_key_encrypted']:null);
        return['environment'=>(string)($row['environment']??'sandbox'),'api_key'=>$api,'api_key_last4'=>(string)($row['api_key_last4']??''),'webhook_token'=>$this->cipher->decrypt(isset($row['webhook_token_encrypted'])?(string)$row['webhook_token_encrypted']:null),'is_active'=>(int)($row['is_active']??0)===1,'configured'=>$api!==''];
    }
    public function saveAsaas(string$apiKey,int$userId,bool$active):void
    {
        $environment=str_starts_with($apiKey,'$aact_hmlg_')?'sandbox':(str_starts_with($apiKey,'$aact_prod_')?'production':'');
        if($environment==='')throw new RuntimeException('A chave informada não possui um formato reconhecido pelo Asaas.');
        $webhook=bin2hex(random_bytes(32));$sql="UPDATE finance_integrations SET environment=:environment,api_key_encrypted=:api,api_key_last4=:last4,webhook_token_encrypted=:webhook,is_active=:active,updated_by=:user WHERE provider='asaas'";
        $this->database->prepare($sql)->execute(['environment'=>$environment,'api'=>$this->cipher->encrypt($apiKey),'last4'=>substr($apiKey,-4),'webhook'=>$this->cipher->encrypt($webhook),'active'=>(int)$active,'user'=>$userId]);
    }
    public function setActive(bool$active,int$userId):void{$s=$this->database->prepare("UPDATE finance_integrations SET is_active=:active,updated_by=:user WHERE provider='asaas'");$s->execute(['active'=>(int)$active,'user'=>$userId]);}
}
