<?php

declare(strict_types=1);

namespace Interferencia\Modules\Storage;

use Interferencia\Kernel\Security\SecretCipher;
use PDO;
use RuntimeException;
use Throwable;

final readonly class SpacesIntegrationRepository
{
    public function __construct(private PDO $db, private SecretCipher $cipher) {}

    public function encryptionReady(): bool { return $this->cipher->ready(); }

    /** @return array<string,mixed> */
    public function settings(bool $includeSecrets = true): array
    {
        try { $row = $this->db->query('SELECT * FROM object_storage_integrations WHERE id=1')->fetch() ?: []; }
        catch (Throwable) { $row = []; }
        $access = $includeSecrets ? $this->cipher->decrypt(isset($row['access_key_encrypted']) ? (string)$row['access_key_encrypted'] : null) : '';
        $secret = $includeSecrets ? $this->cipher->decrypt(isset($row['secret_key_encrypted']) ? (string)$row['secret_key_encrypted'] : null) : '';
        return [
            'endpoint'=>(string)($row['endpoint']??'https://avaplataforma.nyc3.digitaloceanspaces.com'),
            'bucket'=>(string)($row['bucket']??'avaplataforma'),
            'region'=>(string)($row['region']??'nyc3'),
            'access_key'=>$access,'secret_key'=>$secret,
            'access_key_last4'=>(string)($row['access_key_last4']??''),'secret_key_last4'=>(string)($row['secret_key_last4']??''),
            'central_prefix'=>(string)($row['central_prefix']??'Mundo Inter'),'franchises_prefix'=>(string)($row['franchises_prefix']??'Franquias'),
            'is_active'=>(int)($row['is_active']??0)===1,'configured'=>$includeSecrets ? $access!==''&&$secret!=='' : !empty($row),
            'last_tested_at'=>$row['last_tested_at']??null,'last_error'=>$row['last_error']??null,
        ];
    }

    /** @param array<string,mixed> $data */
    public function save(array $data, ?int $userId): void
    {
        if (!$this->cipher->ready()) throw new RuntimeException('A chave-mestra de criptografia ainda não foi configurada.');
        $current=$this->settings();$endpoint=rtrim(trim((string)($data['endpoint']??'')),'/');$bucket=strtolower(trim((string)($data['bucket']??'')));
        $access=trim((string)($data['access_key']??''));$secret=trim((string)($data['secret_key']??''));
        if($access==='')$access=(string)$current['access_key'];if($secret==='')$secret=(string)$current['secret_key'];
        $parts=parse_url($endpoint);$host=strtolower((string)($parts['host']??''));
        if(($parts['scheme']??'')!=='https'||$host===''||!str_ends_with($host,'.digitaloceanspaces.com'))throw new RuntimeException('Informe um endpoint HTTPS válido da DigitalOcean Spaces.');
        if(preg_match('/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/',$bucket)!==1)throw new RuntimeException('Informe o nome válido do bucket.');
        $hostParts=explode('.',$host);$region=$hostParts[0]===$bucket?($hostParts[1]??''):($hostParts[0]??'');
        if(preg_match('/^[a-z0-9-]{3,20}$/',$region)!==1)throw new RuntimeException('Não foi possível identificar a região pelo endpoint.');
        if(strlen($access)<12||strlen($secret)<16)throw new RuntimeException('Informe o Access Key e o Secret Key completos.');
        $central=$this->prefix((string)($data['central_prefix']??'Mundo Inter'));$franchises=$this->prefix((string)($data['franchises_prefix']??'Franquias'));
        $sql="INSERT INTO object_storage_integrations(id,provider,endpoint,bucket,region,access_key_encrypted,access_key_last4,secret_key_encrypted,secret_key_last4,central_prefix,franchises_prefix,is_active,updated_by) VALUES(1,'digitalocean_spaces',:endpoint,:bucket,:region,:access,:access4,:secret,:secret4,:central,:franchises,:active,:user) ON DUPLICATE KEY UPDATE endpoint=VALUES(endpoint),bucket=VALUES(bucket),region=VALUES(region),access_key_encrypted=VALUES(access_key_encrypted),access_key_last4=VALUES(access_key_last4),secret_key_encrypted=VALUES(secret_key_encrypted),secret_key_last4=VALUES(secret_key_last4),central_prefix=VALUES(central_prefix),franchises_prefix=VALUES(franchises_prefix),is_active=VALUES(is_active),updated_by=VALUES(updated_by)";
        $this->db->prepare($sql)->execute(['endpoint'=>$endpoint,'bucket'=>$bucket,'region'=>$region,'access'=>$this->cipher->encrypt($access),'access4'=>substr($access,-4),'secret'=>$this->cipher->encrypt($secret),'secret4'=>substr($secret,-4),'central'=>$central,'franchises'=>$franchises,'active'=>(int)(($data['is_active']??false)===true),'user'=>$userId]);
    }

    public function markTest(?string $error): void
    {
        $s=$this->db->prepare('UPDATE object_storage_integrations SET last_tested_at=NOW(),last_error=:error WHERE id=1');$s->execute(['error'=>$error]);
    }

    /** @param array<string,mixed> $meta */
    public function register(string $scope, ?int $organizationId, string $category, string $key, array $meta): void
    {
        $sql='INSERT INTO object_storage_objects(organization_id,scope,category,object_key,original_name,mime_type,bytes,checksum_sha256,created_by) VALUES(:organization,:scope,:category,:object_key,:name,:mime,:bytes,:checksum,:user) ON DUPLICATE KEY UPDATE original_name=VALUES(original_name),mime_type=VALUES(mime_type),bytes=VALUES(bytes),checksum_sha256=VALUES(checksum_sha256)';
        $this->db->prepare($sql)->execute(['organization'=>$organizationId,'scope'=>$scope,'category'=>$category,'object_key'=>$key,'name'=>$meta['name']??null,'mime'=>$meta['mime']??'application/octet-stream','bytes'=>$meta['bytes']??0,'checksum'=>$meta['checksum']??hash('sha256',''),'user'=>$meta['user']??null]);
    }

    /** @return array{objects:int,bytes:int} */
    public function summary(): array
    {
        try{$row=$this->db->query('SELECT COUNT(*) objects,COALESCE(SUM(bytes),0) bytes FROM object_storage_objects')->fetch()?:[];return['objects'=>(int)($row['objects']??0),'bytes'=>(int)($row['bytes']??0)];}
        catch(Throwable){return['objects'=>0,'bytes'=>0];}
    }

    public function organizationCode(int $id): string
    {
        $s=$this->db->prepare('SELECT code FROM organizations WHERE id=:id');$s->execute(['id'=>$id]);return (string)($s->fetchColumn()?:'franquia');
    }

    private function prefix(string $value): string
    {
        $value=trim(str_replace(['\\','/'], ' ', $value));$value=preg_replace('/\s+/u',' ',$value)??'';
        if($value===''||mb_strlen($value)>120||str_contains($value,'..'))throw new RuntimeException('Informe um nome de pasta válido.');return$value;
    }
}
