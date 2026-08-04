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
    { $s=$this->database->prepare('INSERT IGNORE INTO finance_webhook_events(asaas_event_id,event_type,resource_id) VALUES(:id,:type,:resource)');$s->execute(['id'=>$eventId,'type'=>$eventType,'resource'=>$resourceId]);return$s->rowCount()===1; }
    public function finishWebhook(string $eventId,?string $error=null):void{$s=$this->database->prepare('UPDATE finance_webhook_events SET processed_at=NOW(),error_message=:error WHERE asaas_event_id=:id');$s->execute(['error'=>$error,'id'=>$eventId]);}
    /** @return array{offset:int,complete:bool} */
    public function syncCursor(string$resource):array{$s=$this->database->prepare('SELECT next_offset,is_complete FROM finance_sync_cursors WHERE resource=:resource');$s->execute(['resource'=>$resource]);$row=$s->fetch()?:[];return['offset'=>(int)($row['next_offset']??0),'complete'=>(int)($row['is_complete']??0)===1];}
    public function advanceSync(string$resource,int$offset,bool$complete):void{$s=$this->database->prepare('INSERT INTO finance_sync_cursors(resource,next_offset,is_complete) VALUES(:resource,:offset,:complete) ON DUPLICATE KEY UPDATE next_offset=VALUES(next_offset),is_complete=VALUES(is_complete)');$s->execute(['resource'=>$resource,'offset'=>$offset,'complete'=>(int)$complete]);}
    public function resetSync():void{$this->database->exec('UPDATE finance_sync_cursors SET next_offset=0,is_complete=0');}
    private function nullable(mixed $value):?string{$v=trim((string)$value);return$v===''?null:$v;}
    private function date(mixed $value):?string{$v=(string)$value;return preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)===1?$v:null;}
}
