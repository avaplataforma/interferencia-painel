<?php

declare(strict_types=1);

namespace Interferencia\Modules\Tickets;

use PDO;

final readonly class TicketRepository
{
    public function __construct(private PDO $db) {}

    /** @return list<string> */
    public function implementationTicketSteps(int $applicationId):array
    {
        $s=$this->db->prepare("SELECT description FROM platform_tickets WHERE franchise_application_id=:application AND status NOT IN('resolved','closed') AND description LIKE '%[implantation-step:%'");
        $s->execute(['application'=>$applicationId]);$steps=[];
        foreach($s->fetchAll()as$row){if(preg_match('/\[implantation-step:([a-z0-9_-]+)\]/',(string)$row['description'],$match)===1)$steps[]=$match[1];}
        return array_values(array_unique($steps));
    }

    public function ensureImplementationTicket(int $applicationId,string $stepId,string $label,string $detail,string $requesterName,string $requesterEmail):int
    {
        if(preg_match('/^[a-z0-9_-]+$/',$stepId)!==1)throw new \RuntimeException('Etapa de implantação inválida.');
        $marker='[implantation-step:'.$stepId.']';
        $existing=$this->db->prepare("SELECT id FROM platform_tickets WHERE franchise_application_id=:application AND status NOT IN('resolved','closed') AND description LIKE :marker ORDER BY id DESC LIMIT 1");
        $existing->execute(['application'=>$applicationId,'marker'=>'%'.$marker.'%']);$id=(int)($existing->fetchColumn()?:0);if($id>0)return$id;
        $description=$marker."\nPendência do fluxo guiado de implantação da franquia.\n\nEtapa: ".$label."\nCritério: ".$detail."\n\nConcluir a configuração no ADM Central e, após a conferência, encerrar este ticket.";
        $s=$this->db->prepare("INSERT INTO platform_tickets(franchise_application_id,subject,requester_name,requester_email,description,priority,status) VALUES(:application,:subject,:name,:email,:description,'high','open')");
        $s->execute(['application'=>$applicationId,'subject'=>'Implantação — '.$label,'name'=>$requesterName,'email'=>$requesterEmail!==''?$requesterEmail:null,'description'=>$description]);return(int)$this->db->lastInsertId();
    }

    public function centralAll(string $status='',string $search=''):array
    {
        $sql="SELECT * FROM (SELECT CAST(t.id AS CHAR) ticket_ref,t.id,NULL franchise_application_id,t.subject,t.description,t.priority,t.status,t.updated_at,o.display_name organization_name,un.name unit_name,requester.name requester_name,department.name department_name,'internal' source FROM tickets t INNER JOIN organizations o ON o.id=t.organization_id INNER JOIN units un ON un.id=t.unit_id INNER JOIN users requester ON requester.id=t.requester_user_id INNER JOIN ticket_departments department ON department.id=t.department_id UNION ALL SELECT CONCAT('F-',p.id) ticket_ref,p.id,p.franchise_application_id,p.subject,p.description,p.priority,p.status,p.updated_at,COALESCE(a.display_name,'Futura franquia') organization_name,'Formulário público' unit_name,p.requester_name,'Novas franquias' department_name,'franchise_application' source FROM platform_tickets p LEFT JOIN franchise_applications a ON a.id=p.franchise_application_id) central WHERE 1=1";$params=[];
        if(in_array($status,['open','in_progress','waiting','resolved','closed'],true)){$sql.=' AND status=?';$params[]=$status;}
        if($search!==''){$sql.=' AND (ticket_ref LIKE ? OR subject LIKE ? OR description LIKE ? OR organization_name LIKE ? OR requester_name LIKE ?)';$term='%'.$search.'%';array_push($params,$term,$term,$term,$term,$term);}
        $sql.=" ORDER BY CASE status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'waiting' THEN 2 ELSE 3 END,CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END,updated_at DESC LIMIT 200";$s=$this->db->prepare($sql);$s->execute($params);return$s->fetchAll();
    }

    public function centralSummary():array
    {
        $row=$this->db->query("SELECT COUNT(*) total,SUM(status NOT IN('resolved','closed')) open,SUM(status NOT IN('resolved','closed') AND due_at IS NOT NULL AND due_at<NOW()) overdue,SUM(status IN('resolved','closed')) completed FROM (SELECT status,due_at FROM tickets UNION ALL SELECT status,NULL due_at FROM platform_tickets) central")->fetch()?:[];
        return['total'=>(int)($row['total']??0),'open'=>(int)($row['open']??0),'overdue'=>(int)($row['overdue']??0),'completed'=>(int)($row['completed']??0)];
    }

    public function all(int$userId,bool$manage,array$unitIds,string$scope='',string$status='',string$priority='',string$search=''):array
    {
        if($unitIds===[])return[];$marks=implode(',',array_fill(0,count($unitIds),'?'));
        $sql="SELECT t.*,un.name unit_name,requester.name requester_name,department.name department_name,COALESCE(student.name,contact.name) contact_name,(tr.last_read_at IS NULL OR t.updated_at>tr.last_read_at) unread FROM tickets t INNER JOIN units un ON un.id=t.unit_id INNER JOIN users requester ON requester.id=t.requester_user_id INNER JOIN ticket_departments department ON department.id=t.department_id LEFT JOIN finance_customers student ON student.id=t.finance_customer_id LEFT JOIN crm_contacts contact ON contact.id=t.crm_contact_id LEFT JOIN ticket_reads tr ON tr.ticket_id=t.id AND tr.user_id=? WHERE t.unit_id IN ($marks)";$params=[$userId,...$unitIds];
        if(!$manage){$sql.=' AND (t.requester_user_id=? OR EXISTS(SELECT 1 FROM ticket_department_users member WHERE member.department_id=t.department_id AND member.user_id=?))';array_push($params,$userId,$userId);}
        if($scope==='mine'){$sql.=' AND EXISTS(SELECT 1 FROM ticket_department_users mine WHERE mine.department_id=t.department_id AND mine.user_id=?)';$params[]=$userId;}elseif($scope==='created'){$sql.=' AND t.requester_user_id=?';$params[]=$userId;}elseif($scope==='overdue')$sql.=" AND t.status NOT IN('resolved','closed') AND t.due_at<NOW()";
        if(in_array($status,['open','in_progress','waiting','resolved','closed'],true)){$sql.=' AND t.status=?';$params[]=$status;}if(in_array($priority,['low','normal','high','urgent'],true)){$sql.=' AND t.priority=?';$params[]=$priority;}
        if($search!==''){$sql.=' AND (t.subject LIKE ? OR t.description LIKE ? OR requester.name LIKE ? OR department.name LIKE ? OR student.name LIKE ? OR contact.name LIKE ?)';$term='%'.$search.'%';array_push($params,$term,$term,$term,$term,$term,$term);}
        $sql.=" ORDER BY CASE t.status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'waiting' THEN 2 ELSE 3 END,CASE t.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END,COALESCE(t.due_at,'9999-12-31'),t.updated_at DESC";$s=$this->db->prepare($sql);$s->execute($params);return$s->fetchAll();
    }

    public function find(int$id,int$userId,bool$manage,array$unitIds):?array
    {
        if($unitIds===[])return null;$marks=implode(',',array_fill(0,count($unitIds),'?'));
        $sql="SELECT t.*,un.name unit_name,requester.name requester_name,department.name department_name,COALESCE(student.name,contact.name) contact_name,COALESCE(student.mobile_phone,student.phone,contact.phone) contact_phone FROM tickets t INNER JOIN units un ON un.id=t.unit_id INNER JOIN users requester ON requester.id=t.requester_user_id INNER JOIN ticket_departments department ON department.id=t.department_id LEFT JOIN finance_customers student ON student.id=t.finance_customer_id LEFT JOIN crm_contacts contact ON contact.id=t.crm_contact_id WHERE t.id=? AND t.unit_id IN ($marks)";$params=[$id,...$unitIds];
        if(!$manage){$sql.=' AND (t.requester_user_id=? OR EXISTS(SELECT 1 FROM ticket_department_users member WHERE member.department_id=t.department_id AND member.user_id=?))';array_push($params,$userId,$userId);}$s=$this->db->prepare($sql);$s->execute($params);$row=$s->fetch();return is_array($row)?$row:null;
    }

    public function create(int$unitId,int$requesterId,int$departmentId,int$studentId,string$subject,string$description,string$priority,?string$dueAt):int
    {
        $this->db->beginTransaction();try{$s=$this->db->prepare("INSERT INTO tickets(unit_id,crm_contact_id,finance_customer_id,department_id,requester_user_id,assigned_user_id,subject,description,priority,status,due_at) VALUES(:unit,NULL,:student,:department,:requester,NULL,:subject,:description,:priority,'open',:due)");$s->execute(['unit'=>$unitId,'student'=>$studentId,'department'=>$departmentId,'requester'=>$requesterId,'subject'=>trim($subject),'description'=>trim($description),'priority'=>$priority,'due'=>$dueAt]);$id=(int)$this->db->lastInsertId();$this->event($id,$requesterId,'created',null,'Chamado aberto');$this->markRead($id,$requesterId);$this->db->commit();return$id;}catch(\Throwable$e){if($this->db->inTransaction())$this->db->rollBack();throw$e;}
    }

    public function comments(int$ticketId):array{$s=$this->db->prepare('SELECT c.*,u.name user_name FROM ticket_comments c INNER JOIN users u ON u.id=c.user_id WHERE c.ticket_id=:ticket ORDER BY c.created_at,c.id');$s->execute(['ticket'=>$ticketId]);return$s->fetchAll();}
    public function events(int$ticketId):array{$s=$this->db->prepare('SELECT e.*,u.name user_name FROM ticket_events e INNER JOIN users u ON u.id=e.user_id WHERE e.ticket_id=:ticket ORDER BY e.created_at,e.id');$s->execute(['ticket'=>$ticketId]);return$s->fetchAll();}
    public function attachments(int$ticketId):array{$s=$this->db->prepare('SELECT a.*,u.name user_name FROM ticket_attachments a INNER JOIN users u ON u.id=a.user_id WHERE a.ticket_id=:ticket ORDER BY a.created_at,a.id');$s->execute(['ticket'=>$ticketId]);return$s->fetchAll();}
    public function attachment(int$id,int$ticketId):?array{$s=$this->db->prepare('SELECT * FROM ticket_attachments WHERE id=:id AND ticket_id=:ticket');$s->execute(['id'=>$id,'ticket'=>$ticketId]);$row=$s->fetch();return is_array($row)?$row:null;}
    public function addAttachment(int$ticketId,int$userId,array$file):void{$s=$this->db->prepare('INSERT INTO ticket_attachments(ticket_id,user_id,file_name,mime_type,file_size,storage_path) VALUES(:ticket,:user,:name,:mime,:size,:path)');$s->execute(['ticket'=>$ticketId,'user'=>$userId,'name'=>$file['file_name'],'mime'=>$file['mime_type'],'size'=>$file['file_size'],'path'=>$file['storage_path']]);$this->touch($ticketId);$this->event($ticketId,$userId,'attachment',null,(string)$file['file_name']);}
    public function addComment(int$ticketId,int$userId,string$body):void{$this->db->beginTransaction();try{$s=$this->db->prepare('INSERT INTO ticket_comments(ticket_id,user_id,body) VALUES(:ticket,:user,:body)');$s->execute(['ticket'=>$ticketId,'user'=>$userId,'body'=>trim($body)]);$this->touch($ticketId);$this->event($ticketId,$userId,'comment',null,'Comentário adicionado');$this->markRead($ticketId,$userId);$this->db->commit();}catch(\Throwable$e){if($this->db->inTransaction())$this->db->rollBack();throw$e;}}
    public function changeStatus(int$ticketId,int$userId,string$old,string$new):void{$resolved=in_array($new,['resolved','closed'],true)?date('Y-m-d H:i:s'):null;$s=$this->db->prepare('UPDATE tickets SET status=:status,resolved_at=:resolved WHERE id=:id');$s->execute(['status'=>$new,'resolved'=>$resolved,'id'=>$ticketId]);$this->event($ticketId,$userId,'status',$old,$new);$this->markRead($ticketId,$userId);}
    public function userInDepartment(int$userId,int$departmentId):bool{$s=$this->db->prepare('SELECT COUNT(*) FROM ticket_department_users WHERE user_id=:user AND department_id=:department');$s->execute(['user'=>$userId,'department'=>$departmentId]);return(int)$s->fetchColumn()>0;}
    public function markRead(int$ticketId,int$userId):void{$s=$this->db->prepare('INSERT INTO ticket_reads(ticket_id,user_id,last_read_at) VALUES(:ticket,:user,NOW()) ON DUPLICATE KEY UPDATE last_read_at=VALUES(last_read_at)');$s->execute(['ticket'=>$ticketId,'user'=>$userId]);}
    public function notificationSummary(int$userId,array$unitIds=[]):array{if($unitIds===[])return['open'=>0,'unread'=>0,'overdue'=>0];$marks=implode(',',array_fill(0,count($unitIds),'?'));$s=$this->db->prepare("SELECT SUM(t.status NOT IN('resolved','closed')) open,SUM(t.status NOT IN('resolved','closed') AND (tr.last_read_at IS NULL OR t.updated_at>tr.last_read_at)) unread,SUM(t.status NOT IN('resolved','closed') AND t.due_at<NOW()) overdue FROM tickets t LEFT JOIN ticket_reads tr ON tr.ticket_id=t.id AND tr.user_id=? WHERE t.unit_id IN ($marks) AND (t.requester_user_id=? OR EXISTS(SELECT 1 FROM ticket_department_users member WHERE member.department_id=t.department_id AND member.user_id=?))");$s->execute([$userId,...$unitIds,$userId,$userId]);$r=$s->fetch()?:[];return['open'=>(int)($r['open']??0),'unread'=>(int)($r['unread']??0),'overdue'=>(int)($r['overdue']??0)];}
    private function event(int$ticketId,int$userId,string$type,?string$old,?string$new):void{$s=$this->db->prepare('INSERT INTO ticket_events(ticket_id,user_id,event_type,old_value,new_value) VALUES(:ticket,:user,:type,:old,:new)');$s->execute(['ticket'=>$ticketId,'user'=>$userId,'type'=>$type,'old'=>$old,'new'=>$new]);}
    private function touch(int$ticketId):void{$s=$this->db->prepare('UPDATE tickets SET updated_at=NOW() WHERE id=:id');$s->execute(['id'=>$ticketId]);}
}
