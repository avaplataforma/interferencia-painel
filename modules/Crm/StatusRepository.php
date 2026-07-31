<?php
declare(strict_types=1);
namespace Interferencia\Modules\Crm;
use PDO;
final readonly class StatusRepository{
 public function __construct(private PDO $db){}
 public function all():array{return $this->db->query('SELECT id,code,name,color,sort_order,is_active FROM crm_statuses ORDER BY sort_order,name')->fetchAll();}
 public function find(int $id):?array{$s=$this->db->prepare('SELECT id,code,name,color,sort_order,is_active FROM crm_statuses WHERE id=:id');$s->execute(['id'=>$id]);$r=$s->fetch();return is_array($r)?$r:null;}
 public function save(?int $id,string $name,string $color,int $order,bool $active):int{$code=$id===null?'custom_'.bin2hex(random_bytes(8)):(string)($this->find($id)['code']??'');$sql=$id===null?'INSERT INTO crm_statuses(code,name,color,sort_order,is_active) VALUES(:code,:name,:color,:sort_order,:active)':'UPDATE crm_statuses SET name=:name,color=:color,sort_order=:sort_order,is_active=:active WHERE id=:id';$s=$this->db->prepare($sql);$p=['name'=>trim($name),'color'=>$color,'sort_order'=>$order,'active'=>(int)$active];if($id===null)$p['code']=$code;else$p['id']=$id;$s->execute($p);return$id??(int)$this->db->lastInsertId();}
}
