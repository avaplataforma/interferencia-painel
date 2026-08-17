<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

use PDO;
use RuntimeException;

final readonly class OrganizationPoleRepository
{
    public const FRANCHISE_FIELD = 'mundointer_franchise';
    public const POLE_FIELD = 'mundointer_pole';

    public function __construct(private PDO $database) {}

    /** @return list<array<string,mixed>> */
    public function allForOrganization(int $organizationId): array
    {
        $sql = "SELECT p.*,u.code unit_code,u.name unit_name,u.city unit_city,(SELECT COUNT(*) FROM student_enrollments e WHERE e.organization_pole_id=p.id) enrollment_count FROM organization_poles p LEFT JOIN units u ON u.id=p.unit_id WHERE p.organization_id=:organization ORDER BY p.is_primary DESC,p.is_active DESC,p.name";
        $statement=$this->database->prepare($sql);$statement->execute(['organization'=>$organizationId]);return$statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function availableUnits(int $organizationId): array
    {
        $statement=$this->database->prepare('SELECT u.id,u.code,u.name,u.city,u.is_active,p.id pole_id FROM units u LEFT JOIN organization_poles p ON p.unit_id=u.id WHERE u.organization_id=:organization ORDER BY u.is_active DESC,u.name');
        $statement->execute(['organization'=>$organizationId]);return$statement->fetchAll();
    }

    public function save(?int $id,int $organizationId,?int $unitId,string $code,string $name,string $legacyValue,bool $primary,bool $active): int
    {
        $code=strtolower(trim($code));$name=trim(preg_replace('/\s+/u',' ',$name)??'');$legacyValue=trim(preg_replace('/\s+/u',' ',$legacyValue)??'');
        if(preg_match('/^[a-z0-9][a-z0-9-]{1,98}[a-z0-9]$/',$code)!==1)throw new RuntimeException('Informe um código estável com letras minúsculas, números e hífens.');
        if(mb_strlen($name)<2||mb_strlen($name)>160)throw new RuntimeException('Informe o nome do polo.');
        if($unitId===null&&$id!==null){$pole=$this->find($id,$organizationId);if($pole!==null)$unitId=(int)($pole['unit_id']??0)?:null;}
        if($unitId===null){
            $unitCode=$code;
            $exists=$this->database->prepare('SELECT COUNT(*) FROM units WHERE code=:code');$exists->execute(['code'=>$unitCode]);
            if((int)$exists->fetchColumn()>0)$unitCode=$code.'-'.$organizationId;
            $unit=$this->database->prepare("INSERT INTO units(organization_id,code,name,city,is_active) VALUES(:organization,:code,:name,'',1)");
            $unit->execute(['organization'=>$organizationId,'code'=>$unitCode,'name'=>$name]);
            $unitId=(int)$this->database->lastInsertId();
        }else{
            $check=$this->database->prepare('SELECT COUNT(*) FROM units WHERE id=:unit AND organization_id=:organization');$check->execute(['unit'=>$unitId,'organization'=>$organizationId]);if((int)$check->fetchColumn()!==1)throw new RuntimeException('Selecione uma unidade desta franquia.');
        }
        $this->database->beginTransaction();
        try{
            if($primary)$this->database->prepare('UPDATE organization_poles SET is_primary=0 WHERE organization_id=:organization')->execute(['organization'=>$organizationId]);
            if($id===null){$statement=$this->database->prepare('INSERT INTO organization_poles(organization_id,unit_id,code,name,legacy_value,is_primary,is_active) VALUES(:organization,:unit,:code,:name,:legacy,:primary,:active)');$statement->execute(['organization'=>$organizationId,'unit'=>$unitId,'code'=>$code,'name'=>$name,'legacy'=>$legacyValue!==''?$legacyValue:null,'primary'=>(int)$primary,'active'=>(int)$active]);$id=(int)$this->database->lastInsertId();}
            else{$statement=$this->database->prepare('UPDATE organization_poles SET unit_id=:unit,code=:code,name=:name,legacy_value=:legacy,is_primary=:primary,is_active=:active WHERE id=:id AND organization_id=:organization');$statement->execute(['unit'=>$unitId,'code'=>$code,'name'=>$name,'legacy'=>$legacyValue!==''?$legacyValue:null,'primary'=>(int)$primary,'active'=>(int)$active,'id'=>$id,'organization'=>$organizationId]);if($statement->rowCount()===0&&$this->find($id,$organizationId)===null)throw new RuntimeException('Polo não encontrado.');}
            if(!$this->hasPrimary($organizationId))$this->database->prepare('UPDATE organization_poles SET is_primary=1 WHERE id=:id')->execute(['id'=>$id]);
            $this->database->commit();return$id;
        }catch(\Throwable$exception){if($this->database->inTransaction())$this->database->rollBack();if($exception instanceof RuntimeException)throw$exception;throw new RuntimeException('Não foi possível salvar. O código ou a unidade pode já estar vinculado a outro polo.',0,$exception);}
    }

    public function delete(int $id,int $organizationId): void
    {
        $record=$this->find($id,$organizationId);if($record===null)throw new RuntimeException('Polo não encontrado.');
        $count=$this->database->prepare('SELECT COUNT(*) FROM student_enrollments WHERE organization_pole_id=:id');$count->execute(['id'=>$id]);if((int)$count->fetchColumn()>0)throw new RuntimeException('Este polo já possui matrículas e deve ser apenas desativado.');
        $statement=$this->database->prepare('DELETE FROM organization_poles WHERE id=:id AND organization_id=:organization');$statement->execute(['id'=>$id,'organization'=>$organizationId]);
        if((int)$record['is_primary']===1){$next=$this->database->prepare('SELECT id FROM organization_poles WHERE organization_id=:organization ORDER BY is_active DESC,name LIMIT 1');$next->execute(['organization'=>$organizationId]);$nextId=(int)$next->fetchColumn();if($nextId>0)$this->database->prepare('UPDATE organization_poles SET is_primary=1 WHERE id=:id')->execute(['id'=>$nextId]);}
    }

    /** @return array<string,mixed>|null */
    public function find(int $id,int $organizationId): ?array
    {
        $statement=$this->database->prepare('SELECT * FROM organization_poles WHERE id=:id AND organization_id=:organization');$statement->execute(['id'=>$id,'organization'=>$organizationId]);$row=$statement->fetch();return is_array($row)?$row:null;
    }

    /** @return array{pole_id:int,organization_id:int,franchise_code:string,franchise_name:string,pole_code:string,pole_name:string,legacy_value:string}|null */
    public function identityForEnrollment(int $unitId,?int $poleId=null): ?array
    {
        $where=$poleId===null?'(p.unit_id=u.id OR p.is_primary=1)':'p.id=:pole';
        $order=$poleId===null?'(p.unit_id=u.id) DESC,p.is_primary DESC':'p.id DESC';
        $sql="SELECT p.id pole_id,p.organization_id,o.code franchise_code,o.display_name franchise_name,p.code pole_code,p.name pole_name,COALESCE(NULLIF(p.legacy_value,''),p.name) legacy_value FROM units u INNER JOIN organizations o ON o.id=u.organization_id INNER JOIN organization_poles p ON p.organization_id=o.id AND p.is_active=1 WHERE u.id=:unit AND {$where} ORDER BY {$order} LIMIT 1";
        $statement=$this->database->prepare($sql);$statement->bindValue(':unit',$unitId,PDO::PARAM_INT);if($poleId!==null)$statement->bindValue(':pole',$poleId,PDO::PARAM_INT);$statement->execute();$row=$statement->fetch();return is_array($row)?['pole_id'=>(int)$row['pole_id'],'organization_id'=>(int)$row['organization_id'],'franchise_code'=>(string)$row['franchise_code'],'franchise_name'=>(string)$row['franchise_name'],'pole_code'=>(string)$row['pole_code'],'pole_name'=>(string)$row['pole_name'],'legacy_value'=>(string)$row['legacy_value']]:null;
    }

    private function hasPrimary(int $organizationId): bool
    {
        $statement=$this->database->prepare('SELECT COUNT(*) FROM organization_poles WHERE organization_id=:organization AND is_primary=1');$statement->execute(['organization'=>$organizationId]);return(int)$statement->fetchColumn()>0;
    }
}
