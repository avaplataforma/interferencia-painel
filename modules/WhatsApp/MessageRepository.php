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
        $s=$this->db->prepare("INSERT INTO whatsapp_conversations(line_id,wa_contact_id,contact_name,last_message_at,unread_count) VALUES(:line,:wa,:name,:at,1) ON DUPLICATE KEY UPDATE contact_name=COALESCE(VALUES(contact_name),contact_name),status='open',last_message_at=GREATEST(COALESCE(last_message_at,VALUES(last_message_at)),VALUES(last_message_at)),unread_count=unread_count+1,id=LAST_INSERT_ID(id)");
        $s->execute(['line'=>$lineId,'wa'=>$waId,'name'=>$name?:null,'at'=>$at]);$conversation=(int)$this->db->lastInsertId();
        $this->ensureCrmContact($conversation,$lineId,$waId,$name?:null,$at);
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
        if($scope==='mine'&&$userId!==null){$sql.=" AND c.assigned_user_id=? AND c.status='open'";$params[]=$userId;}elseif($scope==='unassigned'){$sql.=" AND c.assigned_user_id IS NULL AND c.status='open'";}elseif($scope==='unread'){$sql.=" AND c.unread_count>0 AND c.status='open'";}elseif($scope==='open'){$sql.=" AND c.status='open'";}elseif($scope==='closed'){$sql.=" AND c.status='closed'";}elseif($scope==='overdue'){$sql.=" AND c.status='open' AND EXISTS(SELECT 1 FROM crm_follow_ups due WHERE due.contact_id=c.crm_contact_id AND due.status='pending' AND due.scheduled_at<CURRENT_TIMESTAMP)";}
        if($search!==''){$sql.=' AND (c.contact_name LIKE ? OR c.wa_contact_id LIKE ? OR crm.name LIKE ?)';$term='%'.$search.'%';array_push($params,$term,$term,$term);}
        $s=$this->db->prepare($sql.' ORDER BY c.last_message_at DESC,c.id DESC');$s->execute($params);return$s->fetchAll();
    }
    /** @param list<int> $lineIds @return array<string,mixed>|null */
    public function conversation(int $id,array $lineIds):?array{if($lineIds===[])return null;$marks=implode(',',array_fill(0,count($lineIds),'?'));$s=$this->db->prepare("SELECT c.*,l.name line_name,l.phone_e164,l.phone_number_id,l.connection_status,u.name unit_name,a.name assigned_name,crm.name crm_name,crm.id crm_id,(SELECT MAX(inbound.message_at) FROM whatsapp_messages inbound WHERE inbound.conversation_id=c.id AND inbound.direction='inbound') last_inbound_at,(SELECT COUNT(*) FROM crm_contacts duplicate WHERE duplicate.id<>COALESCE(c.crm_contact_id,0) AND duplicate.is_active=1 AND duplicate.unit_id<>l.unit_id AND ".$this->normalizedPhoneSql('duplicate.phone')."=RIGHT(c.wa_contact_id,11)) cross_unit_duplicates FROM whatsapp_conversations c INNER JOIN whatsapp_lines l ON l.id=c.line_id INNER JOIN units u ON u.id=l.unit_id LEFT JOIN users a ON a.id=c.assigned_user_id LEFT JOIN crm_contacts crm ON crm.id=c.crm_contact_id WHERE c.id=? AND c.line_id IN ($marks) LIMIT 1");$s->execute(array_merge([$id],$lineIds));$row=$s->fetch();return is_array($row)?$row:null;}
    /** @return list<array<string,mixed>> */
    public function messages(int $conversationId):array{$s=$this->db->prepare('SELECT * FROM whatsapp_messages WHERE conversation_id=:id ORDER BY message_at,id');$s->execute(['id'=>$conversationId]);return$s->fetchAll();}
    /** @param list<int> $lineIds @return array{allowed:bool,reason:string} */
    public function sendAvailability(int $conversationId,array $lineIds,int $actorId,bool $cloudReady):array
    {
        $conversation=$this->conversation($conversationId,$lineIds);
        if($conversation===null)return['allowed'=>false,'reason'=>'Conversa não encontrada ou sem permissão de acesso.'];
        if((int)$conversation['is_test']===1)return['allowed'=>false,'reason'=>'O envio é bloqueado em conversas de simulação.'];
        if((string)$conversation['status']!=='open')return['allowed'=>false,'reason'=>'Reabra a conversa antes de responder.'];
        if((int)($conversation['assigned_user_id']??0)!==$actorId)return['allowed'=>false,'reason'=>'Assuma a conversa antes de responder.'];
        if((string)$conversation['connection_status']!=='connected')return['allowed'=>false,'reason'=>'A linha ainda não está conectada à API oficial.'];
        if(!ctype_digit((string)($conversation['phone_number_id']??'')))return['allowed'=>false,'reason'=>'Cadastre o Phone Number ID da linha na área ADM.'];
        if(!$cloudReady)return['allowed'=>false,'reason'=>'O envio oficial permanece bloqueado até a conclusão segura das credenciais da Meta.'];
        $lastInbound=strtotime((string)($conversation['last_inbound_at']??''));
        if($lastInbound===false||$lastInbound<time()-86400)return['allowed'=>false,'reason'=>'A janela de atendimento de 24 horas terminou. Será necessário usar um modelo aprovado pela Meta.'];
        return['allowed'=>true,'reason'=>'Envio disponível dentro da janela de atendimento de 24 horas.'];
    }
    /** @param list<int> $lineIds */
    public function sendText(int $conversationId,array $lineIds,int $actorId,string $body,CloudApiClient $client):void
    {
        $body=trim($body);if($body===''||mb_strlen($body)>4096)throw new \RuntimeException('Digite uma mensagem com até 4.096 caracteres.');
        $availability=$this->sendAvailability($conversationId,$lineIds,$actorId,$client->ready());if(!$availability['allowed'])throw new \RuntimeException($availability['reason']);
        $conversation=$this->conversation($conversationId,$lineIds);if($conversation===null)throw new \RuntimeException('Conversa não encontrada.');
        $localId='local_'.bin2hex(random_bytes(16));$now=date('Y-m-d H:i:s');
        $insert=$this->db->prepare("INSERT INTO whatsapp_messages(conversation_id,line_id,wamid,direction,message_type,body,status,message_at,attempted_at) VALUES(:conversation,:line,:wamid,'outbound','text',:body,'queued',:at,:attempted)");
        $insert->execute(['conversation'=>$conversationId,'line'=>$conversation['line_id'],'wamid'=>$localId,'body'=>$body,'at'=>$now,'attempted'=>$now]);
        $messageId=(int)$this->db->lastInsertId();
        try{$result=$client->sendText((string)$conversation['phone_number_id'],(string)$conversation['wa_contact_id'],$body);$update=$this->db->prepare('UPDATE whatsapp_messages SET wamid=:wamid,status=:status,error_message=NULL WHERE id=:id');$update->execute(['wamid'=>$result['id'],'status'=>$result['status'],'id'=>$messageId]);$this->db->prepare('UPDATE whatsapp_conversations SET last_message_at=:at WHERE id=:id')->execute(['at'=>$now,'id'=>$conversationId]);}
        catch(\Throwable$e){$error=mb_substr($e->getMessage(),0,500);$this->db->prepare("UPDATE whatsapp_messages SET status='failed',error_message=:error WHERE id=:id")->execute(['error'=>$error,'id'=>$messageId]);throw new \RuntimeException($error,0,$e);}
    }
    public function markRead(int $conversationId):void{$s=$this->db->prepare('UPDATE whatsapp_conversations SET unread_count=0 WHERE id=:id');$s->execute(['id'=>$conversationId]);}
    /** @param list<int> $lineIds @return array{unread:int,unassigned:int} */
    public function notificationSummary(array $lineIds):array
    {
        if($lineIds===[])return['unread'=>0,'unassigned'=>0];$marks=implode(',',array_fill(0,count($lineIds),'?'));
        $s=$this->db->prepare("SELECT COALESCE(SUM(CASE WHEN status='open' THEN unread_count ELSE 0 END),0) unread,COALESCE(SUM(status='open' AND assigned_user_id IS NULL),0) unassigned FROM whatsapp_conversations WHERE line_id IN ($marks)");$s->execute($lineIds);$row=$s->fetch()?:[];return['unread'=>(int)($row['unread']??0),'unassigned'=>(int)($row['unassigned']??0)];
    }
    /** @param list<int> $lineIds */
    public function setConversationStatus(int $conversationId,string $status,array $lineIds,int $actorId,string $resolution=''):bool
    {
        if($lineIds===[]||!in_array($status,['open','closed'],true))return false;$marks=implode(',',array_fill(0,count($lineIds),'?'));
        $this->db->beginTransaction();
        try{$find=$this->db->prepare("SELECT c.crm_contact_id,c.status FROM whatsapp_conversations c WHERE c.id=? AND c.line_id IN ($marks) FOR UPDATE");$find->execute(array_merge([$conversationId],$lineIds));$row=$find->fetch();if(!is_array($row)){ $this->db->rollBack();return false;}$contactId=(int)($row['crm_contact_id']??0);if($status==='closed'&&$contactId>0&&$resolution==='followup'&&!$this->hasPendingFollowUp($contactId))throw new \RuntimeException('Agende um follow-up antes de encerrar ou escolha “Atendimento concluído”.');if($status==='closed'&&!in_array($resolution,['followup','completed'],true))throw new \RuntimeException('Informe se haverá retorno ou se o atendimento foi concluído.');$update=$this->db->prepare('UPDATE whatsapp_conversations SET status=:status WHERE id=:id');$update->execute(['status'=>$status,'id'=>$conversationId]);if($contactId>0&&$row['status']!==$status){$description=$status==='closed'?($resolution==='followup'?'Conversa do WhatsApp encerrada com retorno agendado.':'Conversa do WhatsApp encerrada como atendimento concluído.'):'Conversa do WhatsApp reaberta.';$this->recordCrmEvent($contactId,$actorId,$status==='closed'?'whatsapp_closed':'whatsapp_reopened',$description);}$this->db->commit();return true;}catch(\Throwable$e){if($this->db->inTransaction())$this->db->rollBack();throw$e;}
    }
    /** @param list<int> $lineIds */
    public function assign(int $conversationId,int $userId,array $lineIds,?int $actorId=null):bool
    {
        if($lineIds===[])return false;$marks=implode(',',array_fill(0,count($lineIds),'?'));
        $this->db->beginTransaction();
        try{$sql="UPDATE whatsapp_conversations c INNER JOIN whatsapp_lines l ON l.id=c.line_id INNER JOIN user_unit_scopes us ON us.unit_id=l.unit_id AND us.user_id=? SET c.assigned_user_id=? WHERE c.id=? AND c.line_id IN ($marks)";$s=$this->db->prepare($sql);$s->execute(array_merge([$userId,$userId,$conversationId],$lineIds));if($s->rowCount()<1){$this->db->rollBack();return false;}$contact=$this->db->prepare('SELECT crm_contact_id FROM whatsapp_conversations WHERE id=:id');$contact->execute(['id'=>$conversationId]);$contactId=(int)$contact->fetchColumn();if($contactId>0){$this->db->prepare('UPDATE crm_contacts SET responsible_user_id=:user WHERE id=:contact')->execute(['user'=>$userId,'contact'=>$contactId]);$this->recordCrmEvent($contactId,$actorId??$userId,'whatsapp_assigned','Atendente atualizado pela atribuição da conversa do WhatsApp.');}$this->db->commit();return true;}catch(\Throwable$e){if($this->db->inTransaction())$this->db->rollBack();throw$e;}
    }
    /** @param list<int> $lineIds */
    public function linkContact(int $conversationId,int $contactId,array $lineIds):bool{if($lineIds===[])return false;$marks=implode(',',array_fill(0,count($lineIds),'?'));$s=$this->db->prepare("UPDATE whatsapp_conversations c INNER JOIN whatsapp_lines l ON l.id=c.line_id INNER JOIN crm_contacts crm ON crm.id=? AND crm.unit_id=l.unit_id SET c.crm_contact_id=crm.id WHERE c.id=? AND c.line_id IN ($marks)");$s->execute(array_merge([$contactId,$conversationId],$lineIds));return$s->rowCount()>0;}
    /** @param list<int> $lineIds @return list<array<string,mixed>> */
    public function attendants(array $lineIds):array{if($lineIds===[])return[];$marks=implode(',',array_fill(0,count($lineIds),'?'));$s=$this->db->prepare("SELECT DISTINCT u.id,u.name FROM users u INNER JOIN user_unit_scopes us ON us.user_id=u.id INNER JOIN whatsapp_lines l ON l.unit_id=us.unit_id WHERE l.id IN ($marks) AND u.is_active=1 ORDER BY u.name");$s->execute($lineIds);return$s->fetchAll();}
    /** @param list<int> $lineIds @return list<array<string,mixed>> */
    public function linkableContacts(int $conversationId,array $lineIds):array{if($lineIds===[])return[];$marks=implode(',',array_fill(0,count($lineIds),'?'));$s=$this->db->prepare("SELECT crm.id,crm.name,crm.phone FROM whatsapp_conversations c INNER JOIN whatsapp_lines l ON l.id=c.line_id INNER JOIN crm_contacts crm ON crm.unit_id=l.unit_id AND crm.is_active=1 WHERE c.id=? AND c.line_id IN ($marks) ORDER BY (".$this->normalizedPhoneSql('crm.phone')."=RIGHT(c.wa_contact_id,11)) DESC,crm.name LIMIT 100");$s->execute(array_merge([$conversationId],$lineIds));return$s->fetchAll();}
    public function simulateInbound(int $lineId,string $name,string $phone,string $body):int
    {
        $digits=preg_replace('/\D/','',$phone);if(!is_string($digits)||!in_array(strlen($digits),[10,11,12,13],true))throw new \RuntimeException('Informe um telefone válido para o teste.');if(!str_starts_with($digits,'55'))$digits='55'.$digits;
        $this->db->beginTransaction();try{$at=date('Y-m-d H:i:s');$s=$this->db->prepare("INSERT INTO whatsapp_conversations(line_id,wa_contact_id,contact_name,status,unread_count,is_test,last_message_at) VALUES(:line,:phone,:name,'open',1,1,:at) ON DUPLICATE KEY UPDATE contact_name=VALUES(contact_name),status='open',unread_count=unread_count+1,is_test=1,last_message_at=VALUES(last_message_at),id=LAST_INSERT_ID(id)");$s->execute(['line'=>$lineId,'phone'=>$digits,'name'=>trim($name),'at'=>$at]);$conversation=(int)$this->db->lastInsertId();$this->ensureCrmContact($conversation,$lineId,$digits,trim($name),$at);$wamid='sim_'.bin2hex(random_bytes(16));$m=$this->db->prepare("INSERT INTO whatsapp_messages(conversation_id,line_id,wamid,direction,message_type,body,status,message_at) VALUES(:conversation,:line,:wamid,'inbound','text',:body,'received',:at)");$m->execute(['conversation'=>$conversation,'line'=>$lineId,'wamid'=>$wamid,'body'=>trim($body),'at'=>$at]);$this->db->commit();return$conversation;}catch(\Throwable$e){if($this->db->inTransaction())$this->db->rollBack();throw$e;}
    }

    private function ensureCrmContact(int $conversationId,int $lineId,string $waId,?string $name,string $registeredAt):void
    {
        $current=$this->db->prepare('SELECT crm_contact_id FROM whatsapp_conversations WHERE id=:id');$current->execute(['id'=>$conversationId]);if((int)$current->fetchColumn()>0)return;
        $line=$this->db->prepare('SELECT unit_id FROM whatsapp_lines WHERE id=:id');$line->execute(['id'=>$lineId]);$unitId=(int)$line->fetchColumn();if($unitId<1)return;
        $match=$this->db->prepare("SELECT id FROM crm_contacts WHERE unit_id=:unit AND is_active=1 AND ".$this->normalizedPhoneSql('phone')."=:phone ORDER BY id DESC LIMIT 1");$match->execute(['unit'=>$unitId,'phone'=>substr($waId,-11)]);$contactId=(int)$match->fetchColumn();
        if($contactId<1){$status=$this->db->query("SELECT id FROM crm_statuses WHERE code='new' AND is_active=1 ORDER BY sort_order,id LIMIT 1");$statusId=(int)$status->fetchColumn();if($statusId<1)throw new \RuntimeException('O status Novo do CRM não está disponível.');$insert=$this->db->prepare("INSERT INTO crm_contacts(unit_id,status_id,name,phone,registration_source,registered_at,notes,is_active) VALUES(:unit,:status,:name,:phone,'whatsapp',:registered,'Contato provisório criado automaticamente pelo WhatsApp.',1)");$insert->execute(['unit'=>$unitId,'status'=>$statusId,'name'=>trim((string)$name)!==''?trim((string)$name):'+'.$waId,'phone'=>$this->formatBrazilianPhone($waId),'registered'=>$registeredAt]);$contactId=(int)$this->db->lastInsertId();$this->recordCrmEvent($contactId,null,'whatsapp_created','Contato provisório criado automaticamente a partir de uma nova conversa do WhatsApp.');}else{$this->recordCrmEvent($contactId,null,'whatsapp_linked','Conversa do WhatsApp vinculada automaticamente pelo telefone na mesma unidade.');}
        $this->db->prepare('UPDATE whatsapp_conversations SET crm_contact_id=:contact WHERE id=:conversation AND crm_contact_id IS NULL')->execute(['contact'=>$contactId,'conversation'=>$conversationId]);
    }

    private function normalizedPhoneSql(string $column):string{return "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($column,'(',''),')',''),'-',''),' ',''),'+',''),11)";}
    private function hasPendingFollowUp(int $contactId):bool{$s=$this->db->prepare("SELECT COUNT(*) FROM crm_follow_ups WHERE contact_id=:contact AND status='pending'");$s->execute(['contact'=>$contactId]);return(int)$s->fetchColumn()>0;}
    private function formatBrazilianPhone(string $phone):string{$digits=substr(preg_replace('/\D/','',$phone)?:'',-11);return strlen($digits)===11?sprintf('(%s) %s-%s',substr($digits,0,2),substr($digits,2,5),substr($digits,7)):sprintf('(%s) %s-%s',substr($digits,0,2),substr($digits,2,4),substr($digits,6));}
    private function recordCrmEvent(int $contactId,?int $actorId,string $type,string $description):void{$s=$this->db->prepare('INSERT INTO crm_contact_events(contact_id,actor_user_id,event_type,description) VALUES(:contact,:actor,:type,:description)');$s->execute(['contact'=>$contactId,'actor'=>$actorId,'type'=>$type,'description'=>$description]);}
}
