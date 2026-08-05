<?php

declare(strict_types=1);

namespace Interferencia\Modules\Finance;

use PDO;

final readonly class CatalogRepository
{
    public function __construct(private PDO $database){}
    /** @return list<array<string,mixed>> */
    public function all():array{return$this->database->query('SELECT p.*,u.name unit_name FROM finance_products p LEFT JOIN units u ON u.id=p.unit_id ORDER BY p.is_active DESC,p.name,p.id')->fetchAll();}
    /** @return list<array<string,mixed>> */
    public function availableForUnit(int$unitId):array{$s=$this->database->prepare('SELECT p.*,u.name unit_name FROM finance_products p LEFT JOIN units u ON u.id=p.unit_id WHERE p.is_active=1 AND (p.unit_id=:unit OR p.unit_id IS NULL) ORDER BY p.name,p.id');$s->execute(['unit'=>$unitId]);return$s->fetchAll();}
    /** @return array<string,mixed>|null */
    public function find(int$id):?array{$s=$this->database->prepare('SELECT p.*,u.name unit_name FROM finance_products p LEFT JOIN units u ON u.id=p.unit_id WHERE p.id=:id');$s->execute(['id'=>$id]);$row=$s->fetch();return is_array($row)?$row:null;}
    /** @param list<string> $billingTypes */
    public function save(?int$id,?int$unitId,string$name,?string$description,float$value,int$maxInstallments,array$billingTypes,int$minutes,bool$active):int
    {
        $billing=implode(',',array_values(array_intersect(['PIX','CREDIT_CARD'],$billingTypes)));if($id===null){$s=$this->database->prepare('INSERT INTO finance_products(unit_id,name,description,value,max_installments,billing_types,minutes_to_expire,is_active) VALUES(:unit,:name,:description,:value,:installments,:billing,:minutes,:active)');$s->execute(['unit'=>$unitId,'name'=>$name,'description'=>$description,'value'=>$value,'installments'=>$maxInstallments,'billing'=>$billing,'minutes'=>$minutes,'active'=>(int)$active]);return(int)$this->database->lastInsertId();}$s=$this->database->prepare('UPDATE finance_products SET unit_id=:unit,name=:name,description=:description,value=:value,max_installments=:installments,billing_types=:billing,minutes_to_expire=:minutes,is_active=:active WHERE id=:id');$s->execute(['unit'=>$unitId,'name'=>$name,'description'=>$description,'value'=>$value,'installments'=>$maxInstallments,'billing'=>$billing,'minutes'=>$minutes,'active'=>(int)$active,'id'=>$id]);return$id;
    }
    public function createCheckoutDraft(int$customerId,int$productId,int$unitId,int$userId):array
    {
        $temporary='pending-'.bin2hex(random_bytes(12));$s=$this->database->prepare('INSERT INTO finance_checkouts(finance_customer_id,finance_product_id,unit_id,external_reference,created_by) VALUES(:customer,:product,:unit,:external,:user)');$s->execute(['customer'=>$customerId,'product'=>$productId,'unit'=>$unitId,'external'=>$temporary,'user'=>$userId]);$id=(int)$this->database->lastInsertId();$external=sprintf('painel:checkout:%d:unit:%d',$id,$unitId);$this->database->prepare('UPDATE finance_checkouts SET external_reference=:external WHERE id=:id')->execute(['external'=>$external,'id'=>$id]);return['id'=>$id,'external_reference'=>$external];
    }
    /** @param array<string,mixed> $checkout */
    public function completeCheckout(int$id,array$checkout,int$minutes):void{$createdId=(string)($checkout['id']??'');$link=(string)($checkout['link']??'');$status=(string)($checkout['status']??'ACTIVE');$expires=(new \DateTimeImmutable())->modify('+'.$minutes.' minutes')->format('Y-m-d H:i:s');$s=$this->database->prepare('UPDATE finance_checkouts SET asaas_checkout_id=:asaas,status=:status,link=:link,expires_at=:expires,error_message=NULL WHERE id=:id');$s->execute(['asaas'=>$createdId,'status'=>$status,'link'=>$link?:null,'expires'=>$expires,'id'=>$id]);}
    public function failCheckout(int$id,string$error):void{$this->database->prepare("UPDATE finance_checkouts SET status='FAILED',error_message=:error WHERE id=:id")->execute(['error'=>mb_substr($error,0,500),'id'=>$id]);}
    /** @return list<array<string,mixed>> */
    public function customerCheckouts(int$customerId):array{$s=$this->database->prepare('SELECT c.*,p.name product_name,p.value FROM finance_checkouts c INNER JOIN finance_products p ON p.id=c.finance_product_id WHERE c.finance_customer_id=:customer ORDER BY c.created_at DESC,c.id DESC');$s->execute(['customer'=>$customerId]);return$s->fetchAll();}
    /** @param array<string,mixed> $checkout */
    public function updateFromWebhook(array$checkout):void{$id=(string)($checkout['id']??'');if($id==='')return;$s=$this->database->prepare('UPDATE finance_checkouts SET status=:status,link=COALESCE(:link,link) WHERE asaas_checkout_id=:id');$s->execute(['status'=>(string)($checkout['status']??'ACTIVE'),'link'=>isset($checkout['link'])?(string)$checkout['link']:null,'id'=>$id]);}
}
