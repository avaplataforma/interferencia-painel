<?php
declare(strict_types=1);
namespace Interferencia\Modules\WhatsApp;
use DateTimeImmutable;
use PDO;

final readonly class MessageRepository
{
    public function __construct(private PDO $db) {}

    public function receive(array $payload): void
    {
        foreach (($payload['entry'] ?? []) as $entry) foreach (($entry['changes'] ?? []) as $change) {
            $value=$change['value']??[];$phoneId=(string)($value['metadata']['phone_number_id']??'');
            if($phoneId==='')continue;$line=$this->lineByPhoneId($phoneId);$eventKey=hash('sha256',(string)json_encode($change,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            if(!$this->claimEvent($eventKey,$line['id']??null,(string)($change['field']??'unknown')))continue;
            if($line===null){$this->finishEvent($eventKey,'Número da Meta não vinculado a uma linha.');continue;}
            foreach(($value['messages']??[])as$message)$this->storeInbound((int)$line['id'],$value,$message);
            foreach(($value['statuses']??[])as$status)$this->updateStatus($status);
            $this->db->prepare("UPDATE whatsapp_lines SET connection_status='connected' WHERE id=:id")->execute(['id'=>$line['id']]);
            $this->finishEvent($eventKey,null);
        }
    }

    private function lineByPhoneId(string $phoneId):?array{$s=$this->db->prepare('SELECT * FROM whatsapp_lines WHERE phone_number_id=:id AND is_active=1 LIMIT 1');$s->execute(['id'=>$phoneId]);$r=$s->fetch();return is_array($r)?$r:null;}
    private function claimEvent(string $key,?int $lineId,string $type):bool{try{$s=$this->db->prepare('INSERT INTO whatsapp_webhook_events(event_key,line_id,event_type) VALUES(:event,:line,:type)');$s->execute(['event'=>$key,'line'=>$lineId,'type'=>$type]);return true;}catch(\Throwable){return false;}}
    private function finishEvent(string $key,?string $error):void{$s=$this->db->prepare('UPDATE whatsapp_webhook_events SET processed_at=CURRENT_TIMESTAMP,error_message=:error WHERE event_key=:event');$s->execute(['error'=>$error,'event'=>$key]);}
    private function storeInbound(int $lineId,array $value,array $message):void
    {
        $waId=(string)($message['from']??'');$wamid=(string)($message['id']??'');if($waId===''||$wamid==='')return;
        $name=null;foreach(($value['contacts']??[])as$contact)if((string)($contact['wa_id']??'')===$waId)$name=(string)($contact['profile']['name']??'');
        $at=(new DateTimeImmutable('@'.(int)($message['timestamp']??time())))->format('Y-m-d H:i:s');
        $s=$this->db->prepare("INSERT INTO whatsapp_conversations(line_id,wa_contact_id,contact_name,last_message_at,unread_count) VALUES(:line,:wa,:name,:at,1) ON DUPLICATE KEY UPDATE contact_name=COALESCE(VALUES(contact_name),contact_name),last_message_at=GREATEST(COALESCE(last_message_at,VALUES(last_message_at)),VALUES(last_message_at)),unread_count=unread_count+1,id=LAST_INSERT_ID(id)");
        $s->execute(['line'=>$lineId,'wa'=>$waId,'name'=>$name?:null,'at'=>$at]);$conversation=(int)$this->db->lastInsertId();
        $type=(string)($message['type']??'unknown');$body=$type==='text'?(string)($message['text']['body']??''):null;
        $s=$this->db->prepare("INSERT IGNORE INTO whatsapp_messages(conversation_id,line_id,wamid,direction,message_type,body,status,message_at) VALUES(:conversation,:line,:wamid,'inbound',:type,:body,'received',:at)");
        $s->execute(['conversation'=>$conversation,'line'=>$lineId,'wamid'=>$wamid,'type'=>$type,'body'=>$body,'at'=>$at]);
    }
    private function updateStatus(array $status):void{$wamid=(string)($status['id']??'');$state=(string)($status['status']??'');if($wamid===''||!in_array($state,['sent','delivered','read','failed'],true))return;$s=$this->db->prepare('UPDATE whatsapp_messages SET status=:status WHERE wamid=:wamid');$s->execute(['status'=>$state,'wamid'=>$wamid]);}

    /** @param list<int> $lineIds @return list<array<string,mixed>> */
    public function conversations(array $lineIds,string $scope='all',string $search='',?int $userId=null):array
    {
        if($lineIds===[])return[];$marks=implode(',',array_fill(0,count($lineIds),'?'));
        $sql="SELECT c.*,l.name line_name,l.phone_e164,u.name unit_name,a.name assigned_name,crm.name crm_name,crm.id crm_id,(SELECT m.body FROM whatsapp_messages m WHERE m.conversation_id=c.id ORDER BY m.message_at DESC,m.id DESC LIMIT 1) last_body FROM whatsapp_conversations c INNER JOIN whatsapp_lines l ON l.id=c.line_id INNER JOIN units u ON u.id=l.unit_id LEFT JOIN users a ON a.id=c.assigned_user_id LEFT JOIN crm_contacts crm ON crm.id=c.crm_contact_id WHERE c.line_id IN ($marks)";$params=$lineIds;
        if($scope==='mine'&&$userId!==null){$sql.=' AND c.assigned_user_id=?';$params[]=$userId;}elseif($scope==='unassigned'){$sql.=' AND c.assigned_user_id IS NULL';}elseif($scope==='unread'){$sql.=' AND c.unread_count>0';}
        if($search!==''){$sql.=' AND (c.contact_name LIKE ? OR c.wa_contact_id LIKE ? OR crm.name LIKE ?)';$term='%'.$search.'%';array_push($params,$term,$term,$term);}
        $s=$this->db->prepare($sql.' ORDER BY c.last_message_at DESC,c.id DESC');$s->execute($params);return$s->fetchAll();
    }
    /** @param list<int> $lineIds @return array<string,mixed>|null */
    public function conversation(int $id,array $lineIds):?array{if($lineIds===[])return null;$marks=implode(',',array_fill(0,count($lineIds),'?'));$s=$this->db->prepare("SELECT c.*,l.name line_name,l.phone_e164,u.name unit_name,a.name assigned_name,crm.name crm_name,crm.id crm_id FROM whatsapp_conversations c INNER JOIN whatsapp_lines l ON l.id=c.line_id INNER JOIN units u ON u.id=l.unit_id LEFT JOIN users a ON a.id=c.assigned_user_id LEFT JOIN crm_contacts crm ON crm.id=c.crm_contact_id WHERE c.id=? AND c.line_id IN ($marks) LIMIT 1");$s->execute(array_merge([$id],$lineIds));$row=$s->fetch();return is_array($row)?$row:null;}
    /** @return list<array<string,mixed>> */
    public function messages(int $conversationId):array{$s=$this->db->prepare('SELECT * FROM whatsapp_messages WHERE conversation_id=:id ORDER BY message_at,id');$s->execute(['id'=>$conversationId]);return$s->fetchAll();}
    public function markRead(int $conversationId):void{$s=$this->db->prepare('UPDATE whatsapp_conversations SET unread_count=0 WHERE id=:id');$s->execute(['id'=>$conversationId]);}
    /** @param list<int> $lineIds */
    public function assign(int $conversationId,int $userId,array $lineIds):bool{if($lineIds===[])return false;$marks=implode(',',array_fill(0,count($lineIds),'?'));$sql="UPDATE whatsapp_conversations c INNER JOIN whatsapp_lines l ON l.id=c.line_id INNER JOIN user_unit_scopes us ON us.unit_id=l.unit_id AND us.user_id=? SET c.assigned_user_id=? WHERE c.id=? AND c.line_id IN ($marks)";$s=$this->db->prepare($sql);$s->execute(array_merge([$userId,$userId,$conversationId],$lineIds));return$s->rowCount()>0;}
    /** @param list<int> $lineIds */
    public function linkContact(int $conversationId,int $contactId,array $lineIds):bool{if($lineIds===[])return false;$marks=implode(',',array_fill(0,count($lineIds),'?'));$s=$this->db->prepare("UPDATE whatsapp_conversations c INNER JOIN whatsapp_lines l ON l.id=c.line_id INNER JOIN crm_contacts crm ON crm.id=? AND crm.unit_id=l.unit_id SET c.crm_contact_id=crm.id WHERE c.id=? AND c.line_id IN ($marks)");$s->execute(array_merge([$contactId,$conversationId],$lineIds));return$s->rowCount()>0;}
    /** @param list<int> $lineIds @return list<array<string,mixed>> */
    public function attendants(array $lineIds):array{if($lineIds===[])return[];$marks=implode(',',array_fill(0,count($lineIds),'?'));$s=$this->db->prepare("SELECT DISTINCT u.id,u.name FROM users u INNER JOIN user_unit_scopes us ON us.user_id=u.id INNER JOIN whatsapp_lines l ON l.unit_id=us.unit_id WHERE l.id IN ($marks) AND u.is_active=1 ORDER BY u.name");$s->execute($lineIds);return$s->fetchAll();}
    /** @param list<int> $lineIds @return list<array<string,mixed>> */
    public function linkableContacts(int $conversationId,array $lineIds):array{if($lineIds===[])return[];$marks=implode(',',array_fill(0,count($lineIds),'?'));$s=$this->db->prepare("SELECT crm.id,crm.name,crm.phone FROM whatsapp_conversations c INNER JOIN whatsapp_lines l ON l.id=c.line_id INNER JOIN crm_contacts crm ON crm.unit_id=l.unit_id AND crm.is_active=1 WHERE c.id=? AND c.line_id IN ($marks) ORDER BY (REPLACE(REPLACE(REPLACE(REPLACE(crm.phone,'(',''),')',''),'-',''),' ','')=RIGHT(c.wa_contact_id,11)) DESC,crm.name LIMIT 100");$s->execute(array_merge([$conversationId],$lineIds));return$s->fetchAll();}
}
