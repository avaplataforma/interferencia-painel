<?php
declare(strict_types=1);
namespace Interferencia\Modules\WhatsApp;
use PDO;
use RuntimeException;
use Throwable;

final readonly class LineRepository
{
    public function __construct(private PDO $db) {}
    public function all():array{return$this->db->query("SELECT l.*,u.name unit_name,(SELECT COUNT(*) FROM whatsapp_line_user_scopes s WHERE s.line_id=l.id) user_count FROM whatsapp_lines l INNER JOIN units u ON u.id=l.unit_id ORDER BY u.name")->fetchAll();}
    public function find(int $id):?array{$s=$this->db->prepare('SELECT l.*,u.name unit_name FROM whatsapp_lines l INNER JOIN units u ON u.id=l.unit_id WHERE l.id=:id');$s->execute(['id'=>$id]);$r=$s->fetch();return is_array($r)?$r:null;}
    public function selectedUserIds(int $id):array{$s=$this->db->prepare('SELECT user_id FROM whatsapp_line_user_scopes WHERE line_id=:id');$s->execute(['id'=>$id]);return array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));}
    public function availableUsers():array{return$this->db->query("SELECT u.id,u.name,u.email,GROUP_CONCAT(un.name ORDER BY un.name SEPARATOR ', ') unit_names FROM users u LEFT JOIN user_unit_scopes sc ON sc.user_id=u.id LEFT JOIN units un ON un.id=sc.unit_id WHERE u.is_active=1 GROUP BY u.id,u.name,u.email ORDER BY u.name")->fetchAll();}
    public function validUsersForUnit(array $ids,int $unitId):bool{if($ids===[])return true;$marks=implode(',',array_fill(0,count($ids),'?'));$s=$this->db->prepare("SELECT COUNT(DISTINCT u.id) FROM users u INNER JOIN user_unit_scopes sc ON sc.user_id=u.id WHERE u.is_active=1 AND sc.unit_id=? AND u.id IN ($marks)");$s->execute(array_merge([$unitId],$ids));return(int)$s->fetchColumn()===count(array_unique($ids));}
    public function unitExists(int $id):bool{$s=$this->db->prepare('SELECT COUNT(*) FROM units WHERE id=:id AND is_active=1');$s->execute(['id'=>$id]);return(int)$s->fetchColumn()>0;}
    public function save(?int $id,int $unitId,string $name,string $phone,bool $active,array $userIds,?string $wabaId=null,?string $phoneNumberId=null):int
    {
        if(!$this->unitExists($unitId))throw new RuntimeException('Selecione uma unidade válida.');
        if(!$this->validUsersForUnit($userIds,$unitId))throw new RuntimeException('Todos os usuários selecionados precisam ter acesso à unidade da linha.');
        $this->db->beginTransaction();
        try{$sql=$id===null?"INSERT INTO whatsapp_lines(unit_id,name,phone_e164,is_active,waba_id,phone_number_id) VALUES(:unit,:name,:phone,:active,:waba,:phone_id)":"UPDATE whatsapp_lines SET unit_id=:unit,name=:name,phone_e164=:phone,is_active=:active,waba_id=:waba,phone_number_id=:phone_id WHERE id=:id";$s=$this->db->prepare($sql);$params=['unit'=>$unitId,'name'=>trim($name),'phone'=>$phone,'active'=>(int)$active,'waba'=>$wabaId,'phone_id'=>$phoneNumberId];if($id!==null)$params['id']=$id;$s->execute($params);$saved=$id??(int)$this->db->lastInsertId();$d=$this->db->prepare('DELETE FROM whatsapp_line_user_scopes WHERE line_id=:line');$d->execute(['line'=>$saved]);$insert=$this->db->prepare('INSERT INTO whatsapp_line_user_scopes(line_id,user_id) VALUES(:line,:user)');foreach(array_unique($userIds)as$userId)$insert->execute(['line'=>$saved,'user'=>$userId]);$this->db->commit();return$saved;}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw new RuntimeException($e->getPrevious()!==null?'Não foi possível salvar a linha.':'Já existe uma linha para esta unidade, número ou identificador da Meta.',0,$e);}
    }
    public function authorizedForUser(int $userId):array
    {
        $sql="SELECT DISTINCT l.*,u.name unit_name FROM whatsapp_lines l INNER JOIN units u ON u.id=l.unit_id LEFT JOIN whatsapp_line_user_scopes s ON s.line_id=l.id LEFT JOIN user_unit_scopes us ON us.user_id=:unit_user AND us.unit_id=l.unit_id LEFT JOIN user_roles ur ON ur.user_id=:admin_user LEFT JOIN roles r ON r.id=ur.role_id AND r.code='super_admin' WHERE l.is_active=1 AND ((s.user_id=:scope_user AND us.unit_id IS NOT NULL) OR r.id IS NOT NULL) ORDER BY u.name";
        $s=$this->db->prepare($sql);$s->execute(['unit_user'=>$userId,'admin_user'=>$userId,'scope_user'=>$userId]);return$s->fetchAll();
    }
}
