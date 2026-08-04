<?php

declare(strict_types=1);

namespace Interferencia\Modules\Tickets;

use PDO;
use RuntimeException;

final readonly class TicketRepository
{
    public function __construct(private PDO $db) {}

    /** @param list<int> $unitIds @return list<array<string,mixed>> */
    public function all(int $userId, bool $manage, array $unitIds, string $scope='', string $status='', string $priority='', string $search=''): array
    {
        if ($unitIds === []) return [];
        $marks=implode(',',array_fill(0,count($unitIds),'?'));
        $sql="SELECT t.*,un.name unit_name,requester.name requester_name,assigned.name assigned_name,contact.name contact_name,(tr.last_read_at IS NULL OR t.updated_at>tr.last_read_at) unread FROM tickets t INNER JOIN units un ON un.id=t.unit_id INNER JOIN users requester ON requester.id=t.requester_user_id INNER JOIN users assigned ON assigned.id=t.assigned_user_id LEFT JOIN crm_contacts contact ON contact.id=t.crm_contact_id LEFT JOIN ticket_reads tr ON tr.ticket_id=t.id AND tr.user_id=? WHERE t.unit_id IN ($marks)";
        $params=[$userId,...$unitIds];
        if(!$manage){$sql.=' AND (t.requester_user_id=? OR t.assigned_user_id=?)';array_push($params,$userId,$userId);}
        if($scope==='mine'){$sql.=' AND t.assigned_user_id=?';$params[]=$userId;}elseif($scope==='created'){$sql.=' AND t.requester_user_id=?';$params[]=$userId;}elseif($scope==='overdue'){$sql.=" AND t.status NOT IN('resolved','closed') AND t.due_at<NOW()";}
        if(in_array($status,['open','in_progress','waiting','resolved','closed'],true)){$sql.=' AND t.status=?';$params[]=$status;}
        if(in_array($priority,['low','normal','high','urgent'],true)){$sql.=' AND t.priority=?';$params[]=$priority;}
        if($search!==''){$sql.=' AND (t.subject LIKE ? OR t.description LIKE ? OR requester.name LIKE ? OR assigned.name LIKE ?)';$term='%'.$search.'%';array_push($params,$term,$term,$term,$term);}
        $sql.=" ORDER BY CASE t.status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'waiting' THEN 2 ELSE 3 END,CASE t.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END,COALESCE(t.due_at,'9999-12-31'),t.updated_at DESC";
        $s=$this->db->prepare($sql);$s->execute($params);return$s->fetchAll();
    }

    /** @param list<int> $unitIds */
    public function find(int $id,int $userId,bool $manage,array $unitIds):?array
    {
        if($unitIds===[])return null;$marks=implode(',',array_fill(0,count($unitIds),'?'));
        $sql="SELECT t.*,un.name unit_name,requester.name requester_name,assigned.name assigned_name,contact.name contact_name,contact.phone contact_phone FROM tickets t INNER JOIN units un ON un.id=t.unit_id INNER JOIN users requester ON requester.id=t.requester_user_id INNER JOIN users assigned ON assigned.id=t.assigned_user_id LEFT JOIN crm_contacts contact ON contact.id=t.crm_contact_id WHERE t.id=? AND t.unit_id IN ($marks)";$params=[$id,...$unitIds];
        if(!$manage){$sql.=' AND (t.requester_user_id=? OR t.assigned_user_id=?)';array_push($params,$userId,$userId);}
        $s=$this->db->prepare($sql);$s->execute($params);$row=$s->fetch();return is_array($row)?$row:null;
    }

    /** @return list<array<string,mixed>> */
    public function usersForUnit(int $unitId):array{$s=$this->db->prepare('SELECT DISTINCT u.id,u.name FROM users u INNER JOIN user_unit_scopes sc ON sc.user_id=u.id WHERE sc.unit_id=:unit AND u.is_active=1 ORDER BY u.name');$s->execute(['unit'=>$unitId]);return$s->fetchAll();}

    public function create(int $unitId,int $requesterId,int $assignedId,?int $contactId,string $subject,string $description,string $priority,?string $dueAt):int
    {
        if(!$this->userCanAccessUnit($assignedId,$unitId))throw new RuntimeException('O responsável não possui acesso à unidade selecionada.');
        $this->db->beginTransaction();try{$s=$this->db->prepare("INSERT INTO tickets(unit_id,crm_contact_id,requester_user_id,assigned_user_id,subject,description,priority,status,due_at) VALUES(:unit,:contact,:requester,:assigned,:subject,:description,:priority,'open',:due)");$s->execute(['unit'=>$unitId,'contact'=>$contactId,'requester'=>$requesterId,'assigned'=>$assignedId,'subject'=>trim($subject),'description'=>trim($description),'priority'=>$priority,'due'=>$dueAt]);$id=(int)$this->db->lastInsertId();$this->event($id,$requesterId,'created',null,'Chamado aberto');$this->markRead($id,$requesterId);$this->db->commit();return$id;}catch(\Throwable$e){if($this->db->inTransaction())$this->db->rollBack();throw$e;}
    }

    public function comments(int $ticketId):array{$s=$this->db->prepare('SELECT c.*,u.name user_name FROM ticket_comments c INNER JOIN users u ON u.id=c.user_id WHERE c.ticket_id=:ticket ORDER BY c.created_at,c.id');$s->execute(['ticket'=>$ticketId]);return$s->fetchAll();}
    public function events(int $ticketId):array{$s=$this->db->prepare('SELECT e.*,u.name user_name FROM ticket_events e INNER JOIN users u ON u.id=e.user_id WHERE e.ticket_id=:ticket ORDER BY e.created_at,e.id');$s->execute(['ticket'=>$ticketId]);return$s->fetchAll();}
    public function addComment(int $ticketId,int $userId,string $body):void{$this->db->beginTransaction();try{$s=$this->db->prepare('INSERT INTO ticket_comments(ticket_id,user_id,body) VALUES(:ticket,:user,:body)');$s->execute(['ticket'=>$ticketId,'user'=>$userId,'body'=>trim($body)]);$this->touch($ticketId);$this->event($ticketId,$userId,'comment',null,'Comentário adicionado');$this->markRead($ticketId,$userId);$this->db->commit();}catch(\Throwable$e){if($this->db->inTransaction())$this->db->rollBack();throw$e;}}
    public function changeStatus(int $ticketId,int $userId,string $old,string $new):void{$resolved=in_array($new,['resolved','closed'],true)?date('Y-m-d H:i:s'):null;$s=$this->db->prepare('UPDATE tickets SET status=:status,resolved_at=:resolved WHERE id=:id');$s->execute(['status'=>$new,'resolved'=>$resolved,'id'=>$ticketId]);$this->event($ticketId,$userId,'status',$old,$new);$this->markRead($ticketId,$userId);}
    public function assign(int $ticketId,int $actorId,int $unitId,int $oldAssignee,int $newAssignee):void{if(!$this->userCanAccessUnit($newAssignee,$unitId))throw new RuntimeException('O responsável não possui acesso à unidade.');$s=$this->db->prepare('UPDATE tickets SET assigned_user_id=:assigned WHERE id=:id');$s->execute(['assigned'=>$newAssignee,'id'=>$ticketId]);$this->event($ticketId,$actorId,'assignment',(string)$oldAssignee,(string)$newAssignee);$this->markRead($ticketId,$actorId);}
    public function markRead(int $ticketId,int $userId):void{$s=$this->db->prepare('INSERT INTO ticket_reads(ticket_id,user_id,last_read_at) VALUES(:ticket,:user,NOW()) ON DUPLICATE KEY UPDATE last_read_at=VALUES(last_read_at)');$s->execute(['ticket'=>$ticketId,'user'=>$userId]);}
    /** @return array{open:int,unread:int,overdue:int} */
    public function notificationSummary(int $userId,array $unitIds=[]):array{if($unitIds===[])return['open'=>0,'unread'=>0,'overdue'=>0];$marks=implode(',',array_fill(0,count($unitIds),'?'));$s=$this->db->prepare("SELECT SUM(t.status NOT IN('resolved','closed')) open,SUM(t.status NOT IN('resolved','closed') AND (tr.last_read_at IS NULL OR t.updated_at>tr.last_read_at)) unread,SUM(t.status NOT IN('resolved','closed') AND t.due_at<NOW()) overdue FROM tickets t LEFT JOIN ticket_reads tr ON tr.ticket_id=t.id AND tr.user_id=? WHERE t.unit_id IN ($marks) AND (t.assigned_user_id=? OR t.requester_user_id=?)");$s->execute([$userId,...$unitIds,$userId,$userId]);$r=$s->fetch()?:[];return['open'=>(int)($r['open']??0),'unread'=>(int)($r['unread']??0),'overdue'=>(int)($r['overdue']??0)];}
    private function event(int $ticketId,int $userId,string $type,?string $old,?string $new):void{$s=$this->db->prepare('INSERT INTO ticket_events(ticket_id,user_id,event_type,old_value,new_value) VALUES(:ticket,:user,:type,:old,:new)');$s->execute(['ticket'=>$ticketId,'user'=>$userId,'type'=>$type,'old'=>$old,'new'=>$new]);}
    private function touch(int $ticketId):void{$s=$this->db->prepare('UPDATE tickets SET updated_at=NOW() WHERE id=:id');$s->execute(['id'=>$ticketId]);}
    private function userCanAccessUnit(int $userId,int $unitId):bool{$s=$this->db->prepare('SELECT COUNT(*) FROM user_unit_scopes WHERE user_id=:user AND unit_id=:unit');$s->execute(['user'=>$userId,'unit'=>$unitId]);return(int)$s->fetchColumn()>0;}
}
