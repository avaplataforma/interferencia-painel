<?php
declare(strict_types=1);
namespace Interferencia\Modules\Crm;
use PDO;
final readonly class StatusRepository{
 public function __construct(private PDO $db,private int $organizationId){}
 public function all():array{$s=$this->db->prepare('SELECT id,code,name,color,sort_order,is_active FROM crm_statuses WHERE organization_id=? ORDER BY sort_order,name');$s->execute([$this->organizationId]);return$s->fetchAll();}
 public function find(int $id):?array{$s=$this->db->prepare('SELECT id,code,name,color,sort_order,is_active FROM crm_statuses WHERE id=:id AND organization_id=:organization');$s->execute(['id'=>$id,'organization'=>$this->organizationId]);$r=$s->fetch();return is_array($r)?$r:null;}
 public function save(?int $id,string $name,string $color,int $order,bool $active):int{$code=$id===null?'custom_'.bin2hex(random_bytes(8)):(string)($this->find($id)['code']??'');$sql=$id===null?'INSERT INTO crm_statuses(organization_id,code,name,color,sort_order,is_active) VALUES(:organization,:code,:name,:color,:sort_order,:active)':'UPDATE crm_statuses SET name=:name,color=:color,sort_order=:sort_order,is_active=:active WHERE id=:id AND organization_id=:organization';$s=$this->db->prepare($sql);$p=['organization'=>$this->organizationId,'name'=>trim($name),'color'=>$color,'sort_order'=>$order,'active'=>(int)$active];if($id===null)$p['code']=$code;else$p['id']=$id;$s->execute($p);return$id??(int)$this->db->lastInsertId();}
}
