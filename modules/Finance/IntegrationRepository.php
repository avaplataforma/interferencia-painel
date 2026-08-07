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
    public function asaas(string $environment='production'):array
    {
        if(!in_array($environment,['production','sandbox'],true))throw new RuntimeException('Ambiente do Asaas inválido.');
        try{$statement=$this->database->prepare("SELECT * FROM finance_integrations WHERE provider='asaas' AND environment=:environment LIMIT 1");$statement->execute(['environment'=>$environment]);$row=$statement->fetch()?:[];}catch(Throwable){$row=[];}
        $api=$this->cipher->decrypt(isset($row['api_key_encrypted'])?(string)$row['api_key_encrypted']:null);
        return['environment'=>$environment,'api_key'=>$api,'api_key_last4'=>(string)($row['api_key_last4']??''),'webhook_token'=>$this->cipher->decrypt(isset($row['webhook_token_encrypted'])?(string)$row['webhook_token_encrypted']:null),'is_active'=>(int)($row['is_active']??0)===1,'configured'=>$api!==''];
    }
    public function saveAsaas(string$apiKey,?int$userId,bool$active):void
    {
        $environment=str_starts_with($apiKey,'$aact_hmlg_')?'sandbox':(str_starts_with($apiKey,'$aact_prod_')?'production':'');
        if($environment==='')throw new RuntimeException('A chave informada não possui um formato reconhecido pelo Asaas.');
        $webhook=bin2hex(random_bytes(32));$sql="INSERT INTO finance_integrations(provider,environment,api_key_encrypted,api_key_last4,webhook_token_encrypted,is_active,updated_by) VALUES('asaas',:environment,:api,:last4,:webhook,:active,:user) ON DUPLICATE KEY UPDATE api_key_encrypted=VALUES(api_key_encrypted),api_key_last4=VALUES(api_key_last4),webhook_token_encrypted=VALUES(webhook_token_encrypted),is_active=VALUES(is_active),updated_by=VALUES(updated_by)";
        $this->database->prepare($sql)->execute(['environment'=>$environment,'api'=>$this->cipher->encrypt($apiKey),'last4'=>substr($apiKey,-4),'webhook'=>$this->cipher->encrypt($webhook),'active'=>(int)$active,'user'=>$userId]);
    }
    public function setActive(bool$active,int$userId,string$environment='production'):void{$s=$this->database->prepare("UPDATE finance_integrations SET is_active=:active,updated_by=:user WHERE provider='asaas' AND environment=:environment");$s->execute(['active'=>(int)$active,'user'=>$userId,'environment'=>$environment]);}
}
