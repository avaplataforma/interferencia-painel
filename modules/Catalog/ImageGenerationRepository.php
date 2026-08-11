<?php

declare(strict_types=1);

namespace Interferencia\Modules\Catalog;

use Interferencia\Kernel\Security\SecretCipher;
use PDO;
use RuntimeException;
use Throwable;

final readonly class ImageGenerationRepository
{
    public function __construct(private PDO $database, private SecretCipher $cipher) {}

    /** @return array<string,mixed> */
    public function settings(bool $includeSecret = false): array
    {
        try { $row = $this->database->query('SELECT * FROM catalog_image_generation_settings WHERE id=1')->fetch() ?: []; }
        catch (Throwable) { $row = []; }
        $key = $includeSecret ? $this->cipher->decrypt(isset($row['api_key_encrypted']) ? (string)$row['api_key_encrypted'] : null) : '';
        return [
            'provider'=>(string)($row['provider']??'openai'),
            'api_key'=>$key,
            'api_key_last4'=>(string)($row['api_key_last4']??''),
            'model'=>(string)($row['model']??'gpt-image-2'),
            'quality'=>(string)($row['quality']??'low'),
            'size'=>(string)($row['size']??'1536x1024'),
            'style_prompt'=>(string)($row['style_prompt']??'Fotografia editorial contemporânea, iluminação profissional, composição limpa, visual educacional premium, sem textos, sem logotipos e sem marcas.'),
            'is_active'=>(int)($row['is_active']??0)===1,
            'auto_generate_missing'=>(int)($row['auto_generate_missing']??0)===1,
            'configured'=>$includeSecret ? $key!=='' : (string)($row['api_key_last4']??'')!=='',
            'last_tested_at'=>$row['last_tested_at']??null,
            'last_error'=>$row['last_error']??null,
            'encryption_ready'=>$this->cipher->ready(),
        ];
    }

    /** @param array<string,mixed> $data */
    public function save(array $data, ?int $userId): void
    {
        if (!$this->cipher->ready()) throw new RuntimeException('A chave-mestra de criptografia ainda não foi configurada.');
        $current=$this->settings(true);$apiKey=trim((string)($data['api_key']??''));
        if($apiKey===''&&!(bool)$current['configured'])throw new RuntimeException('Informe a chave da API da OpenAI.');
        if($apiKey!==''&&!str_starts_with($apiKey,'sk-'))throw new RuntimeException('A chave informada não possui o formato esperado da OpenAI.');
        $model=(string)($data['model']??'gpt-image-2');
        if(!in_array($model,['gpt-image-2','gpt-image-1.5','gpt-image-1-mini'],true))throw new RuntimeException('Modelo de imagem não permitido.');
        $quality=(string)($data['quality']??'low');
        if(!in_array($quality,['low','medium','high'],true))throw new RuntimeException('Qualidade de imagem inválida.');
        $style=trim((string)($data['style_prompt']??''));
        if($style===''||mb_strlen($style)>3000)throw new RuntimeException('Defina uma orientação visual de até 3.000 caracteres.');
        $active=(int)(($data['is_active']??false)===true);
        $automatic=(int)(($data['auto_generate_missing']??false)===true);
        if($apiKey===''){
            $statement=$this->database->prepare('UPDATE catalog_image_generation_settings SET model=:model,quality=:quality,size=:size,style_prompt=:style,is_active=:active,auto_generate_missing=:automatic,updated_by=:user WHERE id=1');
            $statement->execute(['model'=>$model,'quality'=>$quality,'size'=>'1536x1024','style'=>$style,'active'=>$active,'automatic'=>$automatic,'user'=>$userId]);
            return;
        }
        $statement=$this->database->prepare("INSERT INTO catalog_image_generation_settings(id,provider,api_key_encrypted,api_key_last4,model,quality,size,style_prompt,is_active,auto_generate_missing,updated_by) VALUES(1,'openai',:key,:last4,:model,:quality,:size,:style,:active,:automatic,:user) ON DUPLICATE KEY UPDATE api_key_encrypted=VALUES(api_key_encrypted),api_key_last4=VALUES(api_key_last4),model=VALUES(model),quality=VALUES(quality),size=VALUES(size),style_prompt=VALUES(style_prompt),is_active=VALUES(is_active),auto_generate_missing=VALUES(auto_generate_missing),updated_by=VALUES(updated_by)");
        $statement->execute(['key'=>$this->cipher->encrypt($apiKey),'last4'=>substr($apiKey,-4),'model'=>$model,'quality'=>$quality,'size'=>'1536x1024','style'=>$style,'active'=>$active,'automatic'=>$automatic,'user'=>$userId]);
    }

    public function markTest(?string $error): void
    {
        $statement=$this->database->prepare('UPDATE catalog_image_generation_settings SET last_tested_at=NOW(),last_error=:error WHERE id=1');
        $statement->execute(['error'=>$error]);
    }

    public function queue(string $entityType, int $entityId, ?string $prompt, ?int $userId): int
    {
        if(!in_array($entityType,['course','content'],true)||$entityId<1)throw new RuntimeException('Item do catálogo inválido.');
        $existing=$this->database->prepare("SELECT id FROM catalog_image_generation_jobs WHERE entity_type=:type AND entity_id=:entity AND status IN ('pending','processing') ORDER BY id DESC LIMIT 1");
        $existing->execute(['type'=>$entityType,'entity'=>$entityId]);$id=(int)($existing->fetchColumn()?:0);if($id>0)return$id;
        $statement=$this->database->prepare("INSERT INTO catalog_image_generation_jobs(entity_type,entity_id,prompt,status,requested_by) VALUES(:type,:entity,:prompt,'pending',:user)");
        $statement->execute(['type'=>$entityType,'entity'=>$entityId,'prompt'=>trim((string)$prompt)?:null,'user'=>$userId]);
        return(int)$this->database->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function claimNext(): ?array
    {
        $this->database->beginTransaction();
        try{
            $row=$this->database->query("SELECT * FROM catalog_image_generation_jobs WHERE status='pending' ORDER BY id LIMIT 1 FOR UPDATE")->fetch();
            if(!is_array($row)){$this->database->commit();return null;}
            $this->database->prepare("UPDATE catalog_image_generation_jobs SET status='processing',attempts=attempts+1,started_at=NOW(),error_message=NULL WHERE id=:id")->execute(['id'=>(int)$row['id']]);
            $this->database->commit();$row['status']='processing';return$row;
        }catch(Throwable$e){if($this->database->inTransaction())$this->database->rollBack();throw$e;}
    }

    public function complete(int $jobId): void
    {
        $this->database->prepare("UPDATE catalog_image_generation_jobs SET status='ready',finished_at=NOW(),error_message=NULL WHERE id=:id")->execute(['id'=>$jobId]);
    }

    public function fail(int $jobId, string $error): void
    {
        $this->database->prepare("UPDATE catalog_image_generation_jobs SET status='failed',finished_at=NOW(),error_message=:error WHERE id=:id")->execute(['id'=>$jobId,'error'=>mb_substr($error,0,3000)]);
    }

    /** @return array{pending:int,processing:int,ready:int,failed:int} */
    public function summary(): array
    {
        try{$rows=$this->database->query('SELECT status,COUNT(*) total FROM catalog_image_generation_jobs GROUP BY status')->fetchAll()?:[];}catch(Throwable){$rows=[];}
        $summary=['pending'=>0,'processing'=>0,'ready'=>0,'failed'=>0];foreach($rows as$row){$status=(string)$row['status'];if(array_key_exists($status,$summary))$summary[$status]=(int)$row['total'];}return$summary;
    }
}
