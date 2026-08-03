<?php
declare(strict_types=1);
namespace Interferencia\Modules\Crm;
use PDO;

final readonly class FollowUpRepository
{
    public function __construct(private PDO $db) {}

    public function allForUnits(array $unitIds, string $status='', string $period='', int $responsibleId=0): array
    {
        if ($unitIds === []) return [];
        $marks=implode(',',array_fill(0,count($unitIds),'?'));
        $sql="SELECT f.*,c.name contact_name,c.unit_id,un.name unit_name,u.name responsible_name FROM crm_follow_ups f INNER JOIN crm_contacts c ON c.id=f.contact_id INNER JOIN units un ON un.id=c.unit_id INNER JOIN users u ON u.id=f.responsible_user_id WHERE c.unit_id IN ($marks)";
        $params=$unitIds;
        if(in_array($status,['pending','completed','cancelled'],true)){$sql.=' AND f.status=?';$params[]=$status;}
        if($period==='overdue')$sql.=" AND f.status='pending' AND f.scheduled_at<CURDATE()";
        elseif($period==='today')$sql.=" AND f.status='pending' AND f.scheduled_at>=CURDATE() AND f.scheduled_at<CURDATE()+INTERVAL 1 DAY";
        elseif($period==='future')$sql.=" AND f.status='pending' AND f.scheduled_at>=CURDATE()+INTERVAL 1 DAY";
        if($responsibleId>0){$sql.=' AND f.responsible_user_id=?';$params[]=$responsibleId;}
        $sql.=" ORDER BY CASE f.status WHEN 'pending' THEN 0 ELSE 1 END,f.scheduled_at ASC,f.id DESC";
        $s=$this->db->prepare($sql);$s->execute($params);return$s->fetchAll();
    }

    public function summary(array $unitIds): array
    {
        if($unitIds===[])return['overdue'=>0,'today'=>0,'future'=>0];
        $marks=implode(',',array_fill(0,count($unitIds),'?'));
        $sql="SELECT SUM(f.status='pending' AND f.scheduled_at<CURDATE()) overdue,SUM(f.status='pending' AND f.scheduled_at>=CURDATE() AND f.scheduled_at<CURDATE()+INTERVAL 1 DAY) today,SUM(f.status='pending' AND f.scheduled_at>=CURDATE()+INTERVAL 1 DAY) future FROM crm_follow_ups f INNER JOIN crm_contacts c ON c.id=f.contact_id WHERE c.unit_id IN ($marks)";
        $s=$this->db->prepare($sql);$s->execute($unitIds);$row=$s->fetch()?:[];return['overdue'=>(int)($row['overdue']??0),'today'=>(int)($row['today']??0),'future'=>(int)($row['future']??0)];
    }

    public function responsiblesForUnits(array $unitIds):array
    {
        if($unitIds===[])return[];$marks=implode(',',array_fill(0,count($unitIds),'?'));
        $s=$this->db->prepare("SELECT DISTINCT u.id,u.name FROM users u INNER JOIN user_unit_scopes sc ON sc.user_id=u.id WHERE sc.unit_id IN ($marks) AND u.is_active=1 ORDER BY u.name");$s->execute($unitIds);return$s->fetchAll();
    }

    public function forContact(int $contactId,int $unitId):array{$s=$this->db->prepare('SELECT f.*,u.name responsible_name FROM crm_follow_ups f INNER JOIN crm_contacts c ON c.id=f.contact_id INNER JOIN users u ON u.id=f.responsible_user_id WHERE f.contact_id=:contact AND c.unit_id=:unit ORDER BY f.scheduled_at DESC,f.id DESC');$s->execute(['contact'=>$contactId,'unit'=>$unitId]);return$s->fetchAll();}
    public function findInUnit(int $id,int $unitId):?array{$s=$this->db->prepare('SELECT f.*,c.unit_id FROM crm_follow_ups f INNER JOIN crm_contacts c ON c.id=f.contact_id WHERE f.id=:id AND c.unit_id=:unit');$s->execute(['id'=>$id,'unit'=>$unitId]);$row=$s->fetch();return is_array($row)?$row:null;}
    public function create(int $contactId,int $responsibleId,string $action,string $scheduledAt,string $notes,int $creatorId):int{$s=$this->db->prepare("INSERT INTO crm_follow_ups(contact_id,responsible_user_id,action,scheduled_at,status,notes,created_by) VALUES(:contact,:responsible,:action,:scheduled,'pending',:notes,:creator)");$s->execute(['contact'=>$contactId,'responsible'=>$responsibleId,'action'=>trim($action),'scheduled'=>$scheduledAt,'notes'=>trim($notes),'creator'=>$creatorId]);return(int)$this->db->lastInsertId();}
    public function setStatus(int $id,int $unitId,string $status):bool{$completed=$status==='completed'?date('Y-m-d H:i:s'):null;$s=$this->db->prepare('UPDATE crm_follow_ups f INNER JOIN crm_contacts c ON c.id=f.contact_id SET f.status=:status,f.completed_at=:completed WHERE f.id=:id AND c.unit_id=:unit');$s->execute(['status'=>$status,'completed'=>$completed,'id'=>$id,'unit'=>$unitId]);return$s->rowCount()>0;}
}
