<?php

declare(strict_types=1);

namespace Interferencia\Modules\Finance;

use PDO;

final readonly class FinanceRepository
{
    public function __construct(private PDO $database) {}

    /** @param list<int> $unitIds @return array{customers:int,payments:int,open_value:float,received_value:float,legacy_customers:int,legacy_payments:int} */
    public function summary(array $unitIds, bool $includeLegacy): array
    {
        $empty=['customers'=>0,'payments'=>0,'open_value'=>0.0,'received_value'=>0.0,'legacy_customers'=>0,'legacy_payments'=>0];
        $parts=[];$params=[];
        if($unitIds!==[]){$parts[]='unit_id IN ('.implode(',',array_fill(0,count($unitIds),'?')).')';$params=array_merge($params,$unitIds);}
        if($includeLegacy)$parts[]='unit_id IS NULL';
        if($parts===[])return$empty;
        $where='('.implode(' OR ',$parts).') AND is_deleted=0';
        $customers=$this->database->prepare("SELECT COUNT(*) FROM finance_customers WHERE {$where}");$customers->execute($params);
        $payments=$this->database->prepare("SELECT COUNT(*) payments,COALESCE(SUM(CASE WHEN status IN ('PENDING','OVERDUE') THEN value ELSE 0 END),0) open_value,COALESCE(SUM(CASE WHEN status IN ('RECEIVED','CONFIRMED','RECEIVED_IN_CASH') THEN value ELSE 0 END),0) received_value FROM finance_payments WHERE {$where}");$payments->execute($params);$row=$payments->fetch()?:[];
        return ['customers'=>(int)$customers->fetchColumn(),'payments'=>(int)($row['payments']??0),'open_value'=>(float)($row['open_value']??0),'received_value'=>(float)($row['received_value']??0),'legacy_customers'=>$includeLegacy?(int)$this->database->query('SELECT COUNT(*) FROM finance_customers WHERE unit_id IS NULL AND is_deleted=0')->fetchColumn():0,'legacy_payments'=>$includeLegacy?(int)$this->database->query('SELECT COUNT(*) FROM finance_payments WHERE unit_id IS NULL AND is_deleted=0')->fetchColumn():0];
    }

    /** @param array<string,mixed> $item */
    public function upsertCustomer(array $item): void
    {
        $sql='INSERT INTO finance_customers(asaas_customer_id,name,email,cpf_cnpj,phone,mobile_phone,external_reference,is_legacy,is_deleted,synced_at) VALUES(:id,:name,:email,:cpf,:phone,:mobile,:external,1,:deleted,NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),email=VALUES(email),cpf_cnpj=VALUES(cpf_cnpj),phone=VALUES(phone),mobile_phone=VALUES(mobile_phone),external_reference=VALUES(external_reference),is_deleted=VALUES(is_deleted),synced_at=NOW()';
        $this->database->prepare($sql)->execute(['id'=>(string)($item['id']??''),'name'=>(string)($item['name']??'Cliente sem nome'),'email'=>$this->nullable($item['email']??null),'cpf'=>$this->nullable($item['cpfCnpj']??null),'phone'=>$this->nullable($item['phone']??null),'mobile'=>$this->nullable($item['mobilePhone']??null),'external'=>$this->nullable($item['externalReference']??null),'deleted'=>(int)(bool)($item['deleted']??false)]);
    }

    /** @param array<string,mixed> $item */
    public function upsertPayment(array $item): void
    {
        $customer=(string)($item['customer']??'');$q=$this->database->prepare('SELECT id,unit_id FROM finance_customers WHERE asaas_customer_id=:id');$q->execute(['id'=>$customer]);$linked=$q->fetch()?:[];
        $sql='INSERT INTO finance_payments(asaas_payment_id,finance_customer_id,unit_id,billing_type,status,value,net_value,due_date,payment_date,description,external_reference,invoice_url,bank_slip_url,is_legacy,is_deleted,synced_at) VALUES(:id,:customer,:unit,:billing,:status,:value,:net,:due,:paid,:description,:external,:invoice,:slip,1,:deleted,NOW()) ON DUPLICATE KEY UPDATE finance_customer_id=VALUES(finance_customer_id),unit_id=COALESCE(finance_payments.unit_id,VALUES(unit_id)),billing_type=VALUES(billing_type),status=VALUES(status),value=VALUES(value),net_value=VALUES(net_value),due_date=VALUES(due_date),payment_date=VALUES(payment_date),description=VALUES(description),external_reference=VALUES(external_reference),invoice_url=VALUES(invoice_url),bank_slip_url=VALUES(bank_slip_url),is_deleted=VALUES(is_deleted),synced_at=NOW()';
        $this->database->prepare($sql)->execute(['id'=>(string)($item['id']??''),'customer'=>$linked['id']??null,'unit'=>$linked['unit_id']??null,'billing'=>$this->nullable($item['billingType']??null),'status'=>(string)($item['status']??'UNKNOWN'),'value'=>(float)($item['value']??0),'net'=>isset($item['netValue'])?(float)$item['netValue']:null,'due'=>$this->date($item['dueDate']??null),'paid'=>$this->date($item['paymentDate']??$item['clientPaymentDate']??null),'description'=>$this->nullable($item['description']??null),'external'=>$this->nullable($item['externalReference']??null),'invoice'=>$this->nullable($item['invoiceUrl']??null),'slip'=>$this->nullable($item['bankSlipUrl']??null),'deleted'=>(int)(bool)($item['deleted']??false)]);
    }

    public function registerWebhook(string $eventId,string $eventType,?string $resourceId): bool
    { $s=$this->database->prepare("INSERT INTO finance_webhook_events(asaas_event_id,event_type,resource_id,last_received_at,processing_status) VALUES(:id,:type,:resource,NOW(),'received') ON DUPLICATE KEY UPDATE delivery_count=delivery_count+1,last_received_at=NOW()");$s->execute(['id'=>$eventId,'type'=>$eventType,'resource'=>$resourceId]);return$s->rowCount()===1; }
    public function finishWebhook(string $eventId,?string $error=null):void{$s=$this->database->prepare("UPDATE finance_webhook_events SET processed_at=NOW(),error_message=:error,processing_status=:status WHERE asaas_event_id=:id");$s->execute(['error'=>$error,'status'=>$error===null?'processed':'failed','id'=>$eventId]);}
    /** @return array{total:int,failed:int,duplicates:int,last_received_at:?string,last_processed_at:?string} */
    public function webhookSummary():array{$row=$this->database->query("SELECT COUNT(*) total,SUM(processing_status='failed') failed,SUM(delivery_count>1) duplicates,MAX(last_received_at) last_received_at,MAX(processed_at) last_processed_at FROM finance_webhook_events")->fetch()?:[];return['total'=>(int)($row['total']??0),'failed'=>(int)($row['failed']??0),'duplicates'=>(int)($row['duplicates']??0),'last_received_at'=>isset($row['last_received_at'])?(string)$row['last_received_at']:null,'last_processed_at'=>isset($row['last_processed_at'])?(string)$row['last_processed_at']:null];}
    /** @return list<array<string,mixed>> */
    public function webhookEvents(int$limit=30):array{$limit=max(1,min(100,$limit));return$this->database->query("SELECT * FROM finance_webhook_events ORDER BY last_received_at DESC,id DESC LIMIT {$limit}")->fetchAll();}
    /** @return array{offset:int,complete:bool} */
    public function syncCursor(string$resource):array{$s=$this->database->prepare('SELECT next_offset,is_complete FROM finance_sync_cursors WHERE resource=:resource');$s->execute(['resource'=>$resource]);$row=$s->fetch()?:[];return['offset'=>(int)($row['next_offset']??0),'complete'=>(int)($row['is_complete']??0)===1];}
    public function advanceSync(string$resource,int$offset,bool$complete):void{$s=$this->database->prepare('INSERT INTO finance_sync_cursors(resource,next_offset,is_complete) VALUES(:resource,:offset,:complete) ON DUPLICATE KEY UPDATE next_offset=VALUES(next_offset),is_complete=VALUES(is_complete)');$s->execute(['resource'=>$resource,'offset'=>$offset,'complete'=>(int)$complete]);}
    public function resetSync():void{$this->database->exec('UPDATE finance_sync_cursors SET next_offset=0,is_complete=0');}
    public function localResourceCount(string$resource):int{$table=$resource==='customers'?'finance_customers':($resource==='payments'?'finance_payments':'');if($table==='')return 0;return(int)$this->database->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();}

    /** @param list<int> $unitIds @return array{items:list<array<string,mixed>>,total:int,page:int,pages:int} */
    public function customers(array$unitIds,bool$includeLegacy,string$search='',string$scope='all',string$order='name',int$page=1,int$perPage=50):array
    {
        $parts=[];$params=[];if($scope!=='legacy'&&$unitIds!==[]){$parts[]='c.unit_id IN ('.implode(',',array_fill(0,count($unitIds),'?')).')';$params=array_merge($params,$unitIds);}if($includeLegacy&&$scope!=='units')$parts[]='c.unit_id IS NULL';if($parts===[])return['items'=>[],'total'=>0,'page'=>1,'pages'=>1];
        $where='('.implode(' OR ',$parts).') AND c.is_deleted=0';if($search!==''){$where.=' AND (c.name LIKE ? OR c.email LIKE ? OR c.cpf_cnpj LIKE ? OR c.phone LIKE ? OR c.mobile_phone LIKE ? OR c.asaas_customer_id LIKE ?)';$term='%'.$search.'%';array_push($params,$term,$term,$term,$term,$term,$term);}
        $count=$this->database->prepare("SELECT COUNT(*) FROM finance_customers c WHERE {$where}");$count->execute($params);$total=(int)$count->fetchColumn();$perPage=max(10,min(100,$perPage));$pages=max(1,(int)ceil($total/$perPage));$page=max(1,min($pages,$page));$offset=($page-1)*$perPage;
        $ordering=match($order){'recent'=>'c.created_at DESC,c.id DESC','open'=>'open_value DESC,c.name','charges'=>'payment_count DESC,c.name',default=>'c.name,c.id'};
        $sql="SELECT c.*,u.name unit_name,crm.name crm_name,(SELECT COUNT(*) FROM finance_payments p WHERE p.finance_customer_id=c.id AND p.is_deleted=0) payment_count,(SELECT COALESCE(SUM(p.value),0) FROM finance_payments p WHERE p.finance_customer_id=c.id AND p.status IN ('PENDING','OVERDUE') AND p.is_deleted=0) open_value FROM finance_customers c LEFT JOIN units u ON u.id=c.unit_id LEFT JOIN crm_contacts crm ON crm.id=c.crm_contact_id WHERE {$where} ORDER BY {$ordering} LIMIT {$perPage} OFFSET {$offset}";$statement=$this->database->prepare($sql);$statement->execute($params);return['items'=>$statement->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }

    /** @param list<int> $unitIds @return array<string,mixed>|null */
    public function customer(int$id,array$unitIds,bool$includeLegacy):?array
    {
        $parts=[];$params=['id'=>$id];if($unitIds!==[]){$marks=[];foreach($unitIds as$i=>$unitId){$key='unit'.$i;$marks[]=':'.$key;$params[$key]=$unitId;}$parts[]='c.unit_id IN ('.implode(',',$marks).')';}if($includeLegacy)$parts[]='c.unit_id IS NULL';if($parts===[])return null;$s=$this->database->prepare('SELECT c.*,u.name unit_name,crm.name crm_name FROM finance_customers c LEFT JOIN units u ON u.id=c.unit_id LEFT JOIN crm_contacts crm ON crm.id=c.crm_contact_id WHERE c.id=:id AND ('.implode(' OR ',$parts).') LIMIT 1');$s->execute($params);$row=$s->fetch();return is_array($row)?$row:null;
    }
    /** @return list<array<string,mixed>> */
    public function customerPayments(int$customerId):array{$s=$this->database->prepare('SELECT * FROM finance_payments WHERE finance_customer_id=:customer AND is_deleted=0 ORDER BY due_date DESC,id DESC LIMIT 200');$s->execute(['customer'=>$customerId]);return$s->fetchAll();}
    /** @param list<int> $unitIds @return list<array<string,mixed>> */
    public function crmCandidates(array$customer,array$unitIds):array
    {
        if($unitIds===[])return[];$marks=implode(',',array_fill(0,count($unitIds),'?'));$conditions=[];$params=$unitIds;$document=preg_replace('/\D/','',(string)($customer['cpf_cnpj']??''));$email=strtolower(trim((string)($customer['email']??'')));$phone=preg_replace('/\D/','',(string)($customer['mobile_phone']?:$customer['phone']??''));if($document!==''){$conditions[]="REPLACE(REPLACE(REPLACE(crm.document,'.',''),'-',''),'/','')=?";$params[]=$document;}if($email!==''){$conditions[]='LOWER(crm.email)=?';$params[]=$email;}if($phone!==''){$conditions[]="RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(crm.phone,'+',''),'(',''),')',''),'-',''),8)=?";$params[]=substr($phone,-8);}if($conditions===[])return[];$s=$this->database->prepare("SELECT crm.id,crm.name,crm.email,crm.phone,crm.unit_id,u.name unit_name FROM crm_contacts crm INNER JOIN units u ON u.id=crm.unit_id WHERE crm.unit_id IN ({$marks}) AND (".implode(' OR ',$conditions).') ORDER BY crm.name LIMIT 20');$s->execute($params);return$s->fetchAll();
    }
    public function reconcileCustomer(int$id,int$unitId,?int$crmContactId):void
    {
        $this->database->beginTransaction();try{if($crmContactId!==null){$c=$this->database->prepare('SELECT COUNT(*) FROM crm_contacts WHERE id=:contact AND unit_id=:unit');$c->execute(['contact'=>$crmContactId,'unit'=>$unitId]);if((int)$c->fetchColumn()!==1)throw new \RuntimeException('O contato selecionado não pertence à unidade.');}$s=$this->database->prepare('UPDATE finance_customers SET unit_id=:unit,crm_contact_id=:contact,is_legacy=0 WHERE id=:id');$s->execute(['unit'=>$unitId,'contact'=>$crmContactId,'id'=>$id]);$p=$this->database->prepare('UPDATE finance_payments SET unit_id=:unit,is_legacy=0 WHERE finance_customer_id=:customer');$p->execute(['unit'=>$unitId,'customer'=>$id]);$this->database->commit();}catch(\Throwable$e){$this->database->rollBack();throw$e;}
    }
    private function nullable(mixed $value):?string{$v=trim((string)$value);return$v===''?null:$v;}
    private function date(mixed $value):?string{$v=(string)$value;return preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)===1?$v:null;}
}
