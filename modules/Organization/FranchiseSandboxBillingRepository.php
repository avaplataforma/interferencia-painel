<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

use PDO;
use RuntimeException;

final readonly class FranchiseSandboxBillingRepository
{
    public function __construct(private PDO $db) {}

    public function eligibleContracts(): array
    {
        return $this->db->query("SELECT c.id,c.organization_id,c.commercial_rule,c.financial_processing,c.monthly_fixed_amount,c.sales_fee_percentage,c.franchise_fee_percentage,a.display_name franchise_name,a.legal_name,a.cnpj,a.manager_name,a.manager_email,a.manager_phone FROM franchise_contracts c INNER JOIN franchise_applications a ON a.id=c.franchise_application_id WHERE c.status='signed' AND COALESCE(c.financial_processing,IF(c.sales_fee_percentage>0,'central_automatic_split','central_monthly_settlement'))='central_automatic_split' ORDER BY a.display_name,c.contract_number DESC")->fetchAll();
    }

    public function contract(int $id): array
    {
        $s=$this->db->prepare("SELECT c.id,c.organization_id,c.status,c.commercial_rule,c.financial_processing,c.monthly_fixed_amount,c.sales_fee_percentage,c.franchise_fee_percentage,a.display_name franchise_name,a.legal_name,a.cnpj,a.manager_name,a.manager_email,a.manager_phone FROM franchise_contracts c INNER JOIN franchise_applications a ON a.id=c.franchise_application_id WHERE c.id=:id");
        $s->execute(['id'=>$id]);$row=$s->fetch();
        if(!is_array($row)||$row['status']!=='signed'||($row['financial_processing']??'')!=='central_automatic_split')throw new RuntimeException('Selecione um contrato assinado com split automático.');
        return$row;
    }

    public function begin(array $contract,?int $userId): array
    {
        $central=max(0,min(100,round((float)$contract['sales_fee_percentage'],4)));$franchise=round((float)($contract['franchise_fee_percentage']??0),4);if($franchise<=0)$franchise=round(100-$central,4);$gross=5.00;
        $s=$this->db->prepare("INSERT INTO franchise_sandbox_billing_tests(organization_id,contract_id,external_reference,gross_value,central_percentage,central_value,franchise_percentage,franchise_value,created_by) VALUES(:organization,:contract,:temporary,:gross,:central,:central_value,:franchise,:franchise_value,:user)");
        $s->execute(['organization'=>$contract['organization_id'],'contract'=>$contract['id'],'temporary'=>'pending:'.bin2hex(random_bytes(12)),'gross'=>$gross,'central'=>$central,'central_value'=>round($gross*$central/100,2),'franchise'=>$franchise,'franchise_value'=>round($gross*$franchise/100,2),'user'=>$userId]);
        $id=(int)$this->db->lastInsertId();$reference='mundo-inter:sandbox:franchise-test:'.$id;
        $this->db->prepare('UPDATE franchise_sandbox_billing_tests SET external_reference=:reference WHERE id=:id')->execute(['reference'=>$reference,'id'=>$id]);
        return['id'=>$id,'external_reference'=>$reference,'gross_value'=>$gross];
    }

    public function complete(int$id,string$customerId,array$payment):void
    {
        $paymentId=trim((string)($payment['id']??''));if(!preg_match('/^pay_[A-Za-z0-9]+$/',$paymentId))throw new RuntimeException('O Sandbox não retornou uma cobrança válida.');
        $invoice=trim((string)($payment['invoiceUrl']??$payment['bankSlipUrl']??''));
        $s=$this->db->prepare("UPDATE franchise_sandbox_billing_tests SET asaas_customer_id=:customer,asaas_payment_id=:payment,status=:status,invoice_url=:invoice,error_message=NULL,last_synced_at=NOW() WHERE id=:id AND status='issuing'");
        $s->execute(['customer'=>$customerId,'payment'=>$paymentId,'status'=>(string)($payment['status']??'PENDING'),'invoice'=>$invoice!==''?$invoice:null,'id'=>$id]);
    }

    public function fail(int$id,string$message):void{$this->db->prepare("UPDATE franchise_sandbox_billing_tests SET status='FAILED',error_message=:error,last_synced_at=NOW() WHERE id=:id")->execute(['error'=>mb_substr($message,0,500),'id'=>$id]);}
    public function find(int$id):?array{$s=$this->db->prepare('SELECT * FROM franchise_sandbox_billing_tests WHERE id=:id');$s->execute(['id'=>$id]);$r=$s->fetch();return is_array($r)?$r:null;}
    public function recent(int$limit=30):array{$limit=max(1,min(100,$limit));return$this->db->query("SELECT t.*,a.display_name franchise_name FROM franchise_sandbox_billing_tests t INNER JOIN franchise_contracts c ON c.id=t.contract_id INNER JOIN franchise_applications a ON a.id=c.franchise_application_id ORDER BY t.id DESC LIMIT {$limit}")->fetchAll();}

    public function updateFromPayment(array$payment):void
    {
        $paymentId=trim((string)($payment['id']??''));$reference=trim((string)($payment['externalReference']??''));if($paymentId===''&&$reference==='')return;
        $invoice=trim((string)($payment['invoiceUrl']??$payment['bankSlipUrl']??''));$status=trim((string)($payment['status']??'PENDING'));
        $s=$this->db->prepare('UPDATE franchise_sandbox_billing_tests SET asaas_payment_id=COALESCE(asaas_payment_id,:payment),status=:status,invoice_url=COALESCE(:invoice,invoice_url),error_message=NULL,last_synced_at=NOW() WHERE asaas_payment_id=:payment_match OR external_reference=:reference');
        $s->execute(['payment'=>$paymentId!==''?$paymentId:null,'status'=>$status,'invoice'=>$invoice!==''?$invoice:null,'payment_match'=>$paymentId,'reference'=>$reference]);
    }

    public function markCancelled(int$id):void{$this->db->prepare("UPDATE franchise_sandbox_billing_tests SET status='DELETED',last_synced_at=NOW() WHERE id=:id")->execute(['id'=>$id]);}
}
