<?php

declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use Interferencia\Kernel\Security\SecretCipher;
use PDO;
use RuntimeException;
use Throwable;

final readonly class IntegrationRepository
{
    public function __construct(private PDO $database, private SecretCipher $cipher) {}

    public function encryptionReady(): bool { return $this->cipher->ready(); }

    /** @return array{base_url:string,token:string,token_last4:string,is_active:bool,initial_password_mode:string,configured:bool,sync_cursor:int,sync_complete:bool,last_tested_at:?string,last_synced_at:?string,last_error:?string} */
    public function settings(): array
    {
        try { $row=$this->database->query('SELECT * FROM moodle_integrations WHERE id=1')->fetch()?:[]; } catch (Throwable) { $row=[]; }
        $token=$this->cipher->decrypt(isset($row['token_encrypted'])?(string)$row['token_encrypted']:null);
        return ['base_url'=>(string)($row['base_url']??''),'token'=>$token,'token_last4'=>(string)($row['token_last4']??''),'is_active'=>(int)($row['is_active']??0)===1,'initial_password_mode'=>in_array(($row['initial_password_mode']??'automatic'),['automatic','cpf5'],true)?(string)$row['initial_password_mode']:'automatic','configured'=>(string)($row['base_url']??'')!==''&&$token!=='','sync_cursor'=>(int)($row['sync_cursor']??0),'sync_complete'=>(int)($row['sync_complete']??0)===1,'last_tested_at'=>isset($row['last_tested_at'])?(string)$row['last_tested_at']:null,'last_synced_at'=>isset($row['last_synced_at'])?(string)$row['last_synced_at']:null,'last_error'=>isset($row['last_error'])?(string)$row['last_error']:null];
    }

    public function save(string $baseUrl,string $token,int $userId,bool $active): void
    {
        $baseUrl=rtrim(trim($baseUrl),'/');
        if(filter_var($baseUrl,FILTER_VALIDATE_URL)===false||parse_url($baseUrl,PHP_URL_SCHEME)!=='https')throw new RuntimeException('Informe o endereço HTTPS válido do Moodle.');
        if(strlen($token)<20||strlen($token)>255)throw new RuntimeException('Informe um token válido do Moodle.');
        $sql='UPDATE moodle_integrations SET base_url=:url,token_encrypted=:token,token_last4=:last4,is_active=:active,updated_by=:user,last_error=NULL WHERE id=1';
        $this->database->prepare($sql)->execute(['url'=>$baseUrl,'token'=>$this->cipher->encrypt($token),'last4'=>substr($token,-4),'active'=>(int)$active,'user'=>$userId]);
    }

    public function recordTest(?string $error): void
    {
        $this->database->prepare('UPDATE moodle_integrations SET last_tested_at=NOW(),last_error=:error WHERE id=1')->execute(['error'=>$error]);
    }

    public function saveInitialPasswordMode(string$mode,?int$userId):void
    {
        if(!in_array($mode,['automatic','cpf5'],true))throw new RuntimeException('Selecione uma política de senha válida.');
        $this->database->prepare('UPDATE moodle_integrations SET initial_password_mode=:mode,updated_by=:user WHERE id=1')->execute(['mode'=>$mode,'user'=>$userId]);
    }

    public function recordSync(?string $error): void
    {
        $sql=$error===null?'UPDATE moodle_integrations SET last_synced_at=NOW(),last_error=NULL WHERE id=1':'UPDATE moodle_integrations SET last_error=:error WHERE id=1';
        $this->database->prepare($sql)->execute($error===null?[]:['error'=>$error]);
    }

    public function advanceSync(int$cursor,bool$complete):void{$this->database->prepare('UPDATE moodle_integrations SET sync_cursor=:cursor,sync_complete=:complete,last_synced_at=NOW(),last_error=NULL WHERE id=1')->execute(['cursor'=>$cursor,'complete'=>(int)$complete]);}
    public function resetSync():void{$this->database->exec('UPDATE moodle_integrations SET sync_cursor=0,sync_complete=0,last_error=NULL WHERE id=1');}
}
