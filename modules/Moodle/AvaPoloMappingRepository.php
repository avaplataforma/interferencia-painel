<?php

declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use PDO;
use RuntimeException;

final readonly class AvaPoloMappingRepository
{
    public function __construct(private PDO $database) {}

    /** @return list<array<string,mixed>> */
    public function mappings(): array
    {
        $sql="SELECT m.id,m.organization_id,m.field_value,m.updated_at,o.display_name organization_name,o.panel_slug FROM ava_polo_mappings m INNER JOIN organizations o ON o.id=m.organization_id ORDER BY m.field_value";
        return $this->database->query($sql)->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function eligibleOrganizations(): array
    {
        $sql="SELECT o.id,o.display_name,o.panel_slug FROM organizations o INNER JOIN organization_ava_settings s ON s.organization_id=o.id WHERE o.status='active' AND s.access_mode IN('shared','both') ORDER BY o.display_name";
        return $this->database->query($sql)->fetchAll() ?: [];
    }

    public function save(string $fieldValue,int $organizationId,?int $userId): void
    {
        $fieldValue=trim(preg_replace('/\s+/u',' ',$fieldValue)??'');
        if($fieldValue===''||mb_strlen($fieldValue)>255)throw new RuntimeException('Informe um valor válido de Polo Presencial.');
        $statement=$this->database->prepare("SELECT COUNT(*) FROM organizations o INNER JOIN organization_ava_settings s ON s.organization_id=o.id WHERE o.id=:id AND o.status='active' AND s.access_mode IN('shared','both')");
        $statement->execute(['id'=>$organizationId]);
        if((int)$statement->fetchColumn()!==1)throw new RuntimeException('A franquia selecionada não está ativa no AVA Cursos compartilhado.');
        $sql="INSERT INTO ava_polo_mappings(organization_id,field_value,created_by) VALUES(:organization,:value,:user) ON DUPLICATE KEY UPDATE organization_id=VALUES(organization_id),created_by=VALUES(created_by)";
        $this->database->prepare($sql)->execute(['organization'=>$organizationId,'value'=>$fieldValue,'user'=>$userId]);
    }

    public function delete(int $id): void
    {
        $statement=$this->database->prepare('DELETE FROM ava_polo_mappings WHERE id=:id');
        $statement->execute(['id'=>$id]);
        if($statement->rowCount()!==1)throw new RuntimeException('Vínculo de Polo Presencial não encontrado.');
    }

    /** @param array<string,mixed> $diagnostic */
    public function recordDiagnostic(int $connectionId,array $diagnostic): void
    {
        if($connectionId<1)throw new RuntimeException('Conexão AVA Cursos inválida.');
        $values=[];
        foreach((array)($diagnostic['values']??[])as$value){
            if(!is_array($value))continue;
            $name=trim((string)($value['value']??''));
            if($name===''||mb_strlen($name)>255)continue;
            $values[]=['value'=>$name,'users'=>max(0,(int)($value['users']??0))];
        }
        $sql="INSERT INTO ava_polo_diagnostics(connection_id,profile_field,total_users,empty_users,values_json,last_error,checked_at) VALUES(:connection,:field,:total,:empty,:values,NULL,NOW()) ON DUPLICATE KEY UPDATE profile_field=VALUES(profile_field),total_users=VALUES(total_users),empty_users=VALUES(empty_users),values_json=VALUES(values_json),last_error=NULL,checked_at=NOW()";
        $this->database->prepare($sql)->execute([
            'connection'=>$connectionId,
            'field'=>(string)($diagnostic['profilefield']??'polo_presencial'),
            'total'=>max(0,(int)($diagnostic['totalusers']??0)),
            'empty'=>max(0,(int)($diagnostic['emptyusers']??0)),
            'values'=>json_encode($values,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
        ]);
    }

    public function recordDiagnosticError(int $connectionId,string $error): void
    {
        if($connectionId<1)return;
        $sql="INSERT INTO ava_polo_diagnostics(connection_id,profile_field,total_users,empty_users,values_json,last_error,checked_at) VALUES(:connection,'polo_presencial',0,0,'[]',:error,NOW()) ON DUPLICATE KEY UPDATE last_error=VALUES(last_error),checked_at=NOW()";
        $this->database->prepare($sql)->execute(['connection'=>$connectionId,'error'=>mb_substr(trim($error),0,2000)]);
    }

    /** @return array<string,mixed> */
    public function overview(int $connectionId): array
    {
        $diagnostic=['profile_field'=>'polo_presencial','total_users'=>0,'empty_users'=>0,'values'=>[],'last_error'=>null,'checked_at'=>null];
        if($connectionId>0){
            $statement=$this->database->prepare('SELECT * FROM ava_polo_diagnostics WHERE connection_id=:connection LIMIT 1');
            $statement->execute(['connection'=>$connectionId]);$row=$statement->fetch();
            if(is_array($row)){
                $values=json_decode((string)$row['values_json'],true);
                $diagnostic=['profile_field'=>(string)$row['profile_field'],'total_users'=>(int)$row['total_users'],'empty_users'=>(int)$row['empty_users'],'values'=>is_array($values)?$values:[],'last_error'=>$row['last_error'],'checked_at'=>$row['checked_at']];
            }
        }
        $mappings=$this->mappings();$byValue=[];
        foreach($mappings as$mapping)$byValue[$this->normalize((string)$mapping['field_value'])]=$mapping;
        $rows=[];$seen=[];
        foreach((array)$diagnostic['values']as$value){
            if(!is_array($value))continue;$key=$this->normalize((string)($value['value']??''));if($key==='')continue;
            $rows[]=['field_value'=>(string)$value['value'],'users'=>(int)($value['users']??0),'mapping'=>$byValue[$key]??null,'found'=>true];$seen[$key]=true;
        }
        foreach($mappings as$mapping){$key=$this->normalize((string)$mapping['field_value']);if(isset($seen[$key]))continue;$rows[]=['field_value'=>(string)$mapping['field_value'],'users'=>0,'mapping'=>$mapping,'found'=>false];}
        return['diagnostic'=>$diagnostic,'rows'=>$rows,'organizations'=>$this->eligibleOrganizations(),'mapped'=>count($mappings),'unmapped'=>count(array_filter($rows,static fn(array$row):bool=>$row['mapping']===null))];
    }

    private function normalize(string $value): string
    {
        $value=mb_strtolower(trim(preg_replace('/\s+/u',' ',$value)??''));
        return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value)?:$value;
    }
}
