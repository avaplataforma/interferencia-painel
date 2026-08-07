<?php

declare(strict_types=1);

namespace Interferencia\Modules\Storage;

use Interferencia\Kernel\Http\UploadedFile;
use PDO;
use RuntimeException;

final readonly class DocumentManager
{
    private const MAX_BYTES = 26214400;
    private const CENTRAL_CATEGORIES = ['institucional'=>'Institucional','juridico'=>'Jurídico','financeiro'=>'Financeiro','contratos'=>'Contratos','suporte'=>'Suporte','outros'=>'Outros'];
    private const MIME_TYPES = [
        'application/pdf','image/jpeg','image/png','image/webp','text/plain','text/csv',
        'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function __construct(private PDO $db, private SpacesStorageManager $storage, private DocumentTypeRepository $documentTypes) {}

    /** @return array<string,string> */
    public function categories(string $scope): array { return $scope === 'central' ? self::CENTRAL_CATEGORIES : $this->documentTypes->categories(); }

    /** @return list<array<string,mixed>> */
    public function all(string $scope, ?int $organizationId, string $category = ''): array
    {
        $sql="SELECT d.*,u.name created_by_name FROM managed_documents d LEFT JOIN platform_users u ON u.id=d.created_by WHERE d.scope=:scope AND d.deleted_at IS NULL AND ".($organizationId===null?'d.organization_id IS NULL':'d.organization_id=:organization');
        $params=['scope'=>$scope];if($organizationId!==null)$params['organization']=$organizationId;
        if($category!==''&&isset($this->categories($scope)[$category])){$sql.=' AND d.category=:category';$params['category']=$category;}
        $sql.=' ORDER BY d.created_at DESC,d.version_number DESC';$s=$this->db->prepare($sql);$s->execute($params);return$s->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id, string $scope, ?int $organizationId, bool $includeDeleted = false): ?array
    {
        $sql='SELECT d.*,u.name created_by_name FROM managed_documents d LEFT JOIN platform_users u ON u.id=d.created_by WHERE d.id=:id AND d.scope=:scope AND '.($organizationId===null?'d.organization_id IS NULL':'d.organization_id=:organization').($includeDeleted?'':' AND d.deleted_at IS NULL');
        $params=['id'=>$id,'scope'=>$scope];if($organizationId!==null)$params['organization']=$organizationId;
        $s=$this->db->prepare($sql);$s->execute($params);$row=$s->fetch();return is_array($row)?$row:null;
    }

    public function upload(string $scope, ?int $organizationId, UploadedFile $file, string $title, string $category, string $description, ?int $replaceId, int $userId): int
    {
        if(!$this->storage->active())throw new RuntimeException('Ative e valide primeiro a integração com a DigitalOcean Spaces.');
        $title=trim($title);$description=trim($description);$categories=$this->categories($scope);
        if(mb_strlen($description)>1000)throw new RuntimeException('A observação deve ter no máximo 1.000 caracteres.');
        if(!isset($categories[$category]))throw new RuntimeException('Selecione um tipo de documento válido.');
        if($file->isEmpty())throw new RuntimeException('Selecione um arquivo.');
        if($file->error!==UPLOAD_ERR_OK)throw new RuntimeException('Não foi possível receber o arquivo. Tente novamente.');
        if($file->size<1||$file->size>self::MAX_BYTES)throw new RuntimeException('O arquivo deve ter no máximo 25 MB.');
        if($file->temporaryPath===''||!is_file($file->temporaryPath))throw new RuntimeException('O arquivo temporário não está disponível.');
        $content=file_get_contents($file->temporaryPath);if($content===false)throw new RuntimeException('Não foi possível ler o arquivo enviado.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($content)?:'application/octet-stream';
        if(!in_array($mime,self::MIME_TYPES,true))throw new RuntimeException('Formato não permitido. Envie PDF, imagem, Word, Excel, CSV ou texto.');
        $group=$this->uuid();$version=1;
        if($replaceId!==null){$previous=$this->find($replaceId,$scope,$organizationId);if($previous===null)throw new RuntimeException('O documento escolhido para versionamento não foi encontrado.');$group=(string)$previous['document_group'];$title=(string)$previous['title'];$category=(string)$previous['category'];$s=$this->db->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM managed_documents WHERE document_group=:group');$s->execute(['group'=>$group]);$version=(int)$s->fetchColumn();}
        if($title==='')$title=$categories[$category]??'Documento';
        $originalName=mb_substr(trim($file->originalName)?:'documento',0,255);
        $path=$scope==='central'
            ?$this->storage->storeCentral('Documentos',$content,$originalName,$mime,$userId)
            :$this->storage->storeFranchise((int)$organizationId,'Documentos',$content,$originalName,$mime,$userId);
        if($path===null)throw new RuntimeException('O armazenamento externo não está disponível.');
        $sql='INSERT INTO managed_documents(document_group,version_number,organization_id,scope,category,title,description,storage_path,original_name,mime_type,bytes,checksum_sha256,created_by) VALUES(:group,:version,:organization,:scope,:category,:title,:description,:path,:name,:mime,:bytes,:checksum,:user)';
        $this->db->prepare($sql)->execute(['group'=>$group,'version'=>$version,'organization'=>$organizationId,'scope'=>$scope,'category'=>$category,'title'=>$title,'description'=>$description?:null,'path'=>$path,'name'=>$originalName,'mime'=>$mime,'bytes'=>strlen($content),'checksum'=>hash('sha256',$content),'user'=>$userId]);
        return(int)$this->db->lastInsertId();
    }

    public function archive(int $id, string $scope, ?int $organizationId, int $userId): void
    {
        if($this->find($id,$scope,$organizationId)===null)throw new RuntimeException('Documento não encontrado.');
        $s=$this->db->prepare('UPDATE managed_documents SET deleted_at=NOW(),deleted_by=:user WHERE id=:id');$s->execute(['user'=>$userId,'id'=>$id]);
    }

    public function read(array $document): string { return $this->storage->read((string)$document['storage_path']); }

    private function uuid(): string
    {
        $b=random_bytes(16);$b[6]=chr((ord($b[6])&0x0f)|0x40);$b[8]=chr((ord($b[8])&0x3f)|0x80);$h=bin2hex($b);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);
    }
}
