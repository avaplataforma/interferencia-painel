<?php
declare(strict_types=1);
namespace Interferencia\Modules\Crm;
use PDO;
final readonly class TagRepository{
 public function __construct(private PDO $db,private int $organizationId){}
 public function all(bool $activeOnly=false):array{$s=$this->db->prepare('SELECT id,name,color,is_active FROM crm_tags WHERE organization_id=:organization'.($activeOnly?' AND is_active=1':'').' ORDER BY name');$s->execute(['organization'=>$this->organizationId]);return$s->fetchAll();}
 public function find(int $id):?array{$s=$this->db->prepare('SELECT id,name,color,is_active FROM crm_tags WHERE id=:id AND organization_id=:organization');$s->execute(['id'=>$id,'organization'=>$this->organizationId]);$r=$s->fetch();return is_array($r)?$r:null;}
 public function idsForContact(int $id):array{$s=$this->db->prepare('SELECT tag_id FROM crm_contact_tags WHERE contact_id=:id');$s->execute(['id'=>$id]);return array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));}
 public function validIds(array $ids):bool{if($ids===[])return true;$marks=implode(',',array_fill(0,count($ids),'?'));$s=$this->db->prepare("SELECT COUNT(*) FROM crm_tags WHERE is_active=1 AND organization_id=? AND id IN ($marks)");$s->execute([$this->organizationId,...$ids]);return(int)$s->fetchColumn()===count(array_unique($ids));}
 public function save(?int $id,string $name,string $color,bool $active):int{$sql=$id===null?'INSERT INTO crm_tags(organization_id,name,color,is_active) VALUES(:organization,:name,:color,:active)':'UPDATE crm_tags SET name=:name,color=:color,is_active=:active WHERE id=:id AND organization_id=:organization';$s=$this->db->prepare($sql);$p=['organization'=>$this->organizationId,'name'=>trim($name),'color'=>$color,'active'=>(int)$active];if($id!==null)$p['id']=$id;$s->execute($p);return$id??(int)$this->db->lastInsertId();}
 public function syncContact(int $contactId,array $ids):void{$d=$this->db->prepare('DELETE FROM crm_contact_tags WHERE contact_id=:id');$d->execute(['id'=>$contactId]);$i=$this->db->prepare('INSERT INTO crm_contact_tags(contact_id,tag_id) VALUES(:contact,:tag)');foreach($ids as$id)$i->execute(['contact'=>$contactId,'tag'=>$id]);}
 public function addToContact(int $contactId,int $tagId):void{$s=$this->db->prepare('INSERT IGNORE INTO crm_contact_tags(contact_id,tag_id) VALUES(:contact,:tag)');$s->execute(['contact'=>$contactId,'tag'=>$tagId]);}
 public function namesForIds(array $ids):array{if($ids===[])return[];$marks=implode(',',array_fill(0,count($ids),'?'));$s=$this->db->prepare("SELECT name FROM crm_tags WHERE organization_id=? AND id IN ($marks) ORDER BY name");$s->execute([$this->organizationId,...$ids]);return array_values(array_map('strval',$s->fetchAll(PDO::FETCH_COLUMN)));}
}
