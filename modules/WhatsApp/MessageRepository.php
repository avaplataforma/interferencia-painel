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
        $s=$this->db->prepare("INSERT INTO whatsapp_conversations(line_id,wa_contact_id,contact_name,last_message_at) VALUES(:line,:wa,:name,:at) ON DUPLICATE KEY UPDATE contact_name=COALESCE(VALUES(contact_name),contact_name),last_message_at=GREATEST(COALESCE(last_message_at,VALUES(last_message_at)),VALUES(last_message_at)),id=LAST_INSERT_ID(id)");
        $s->execute(['line'=>$lineId,'wa'=>$waId,'name'=>$name?:null,'at'=>$at]);$conversation=(int)$this->db->lastInsertId();
        $type=(string)($message['type']??'unknown');$body=$type==='text'?(string)($message['text']['body']??''):null;
        $s=$this->db->prepare("INSERT IGNORE INTO whatsapp_messages(conversation_id,line_id,wamid,direction,message_type,body,status,message_at) VALUES(:conversation,:line,:wamid,'inbound',:type,:body,'received',:at)");
        $s->execute(['conversation'=>$conversation,'line'=>$lineId,'wamid'=>$wamid,'type'=>$type,'body'=>$body,'at'=>$at]);
    }
    private function updateStatus(array $status):void{$wamid=(string)($status['id']??'');$state=(string)($status['status']??'');if($wamid===''||!in_array($state,['sent','delivered','read','failed'],true))return;$s=$this->db->prepare('UPDATE whatsapp_messages SET status=:status WHERE wamid=:wamid');$s->execute(['status'=>$state,'wamid'=>$wamid]);}
}
