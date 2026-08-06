<?php
declare(strict_types=1);
namespace Interferencia\Modules\Crm;
use PDO;
final readonly class ExternalFormRepository{
 public function __construct(private PDO $db,private int $organizationId){}
 public function all():array{$s=$this->db->prepare('SELECT f.*,t.name tag_name,t.color tag_color,s.name status_name FROM external_forms f INNER JOIN crm_tags t ON t.id=f.tag_id INNER JOIN crm_statuses s ON s.id=f.initial_status_id WHERE f.organization_id=? ORDER BY f.is_active DESC,f.name');$s->execute([$this->organizationId]);return$s->fetchAll();}
 public function find(int $id):?array{$s=$this->db->prepare('SELECT * FROM external_forms WHERE id=:id AND organization_id=:organization');$s->execute(['id'=>$id,'organization'=>$this->organizationId]);$r=$s->fetch();return is_array($r)?$r:null;}
 public function findPublic(string $slug):?array{$s=$this->db->prepare('SELECT f.*,t.name tag_name,t.color tag_color FROM external_forms f INNER JOIN crm_tags t ON t.id=f.tag_id WHERE f.slug=:slug AND f.organization_id=:organization AND f.is_active=1 AND t.is_active=1');$s->execute(['slug'=>$slug,'organization'=>$this->organizationId]);$r=$s->fetch();return is_array($r)?$r:null;}
 public function save(?int $id,string $name,string $slug,string $domain,int $tagId,int $statusId,string $title,bool $active):int{$sql=$id===null?'INSERT INTO external_forms(organization_id,name,slug,allowed_domain,tag_id,initial_status_id,title,is_active) VALUES(:organization,:name,:slug,:domain,:tag,:status,:title,:active)':'UPDATE external_forms SET name=:name,slug=:slug,allowed_domain=:domain,tag_id=:tag,initial_status_id=:status,title=:title,is_active=:active WHERE id=:id AND organization_id=:organization';$s=$this->db->prepare($sql);$p=['organization'=>$this->organizationId,'name'=>trim($name),'slug'=>$slug,'domain'=>$domain,'tag'=>$tagId,'status'=>$statusId,'title'=>trim($title),'active'=>(int)$active];if($id!==null)$p['id']=$id;$s->execute($p);return$id??(int)$this->db->lastInsertId();}
 public function increment(int $id):void{$s=$this->db->prepare('UPDATE external_forms SET submission_count=submission_count+1 WHERE id=:id AND organization_id=:organization');$s->execute(['id'=>$id,'organization'=>$this->organizationId]);}
}
