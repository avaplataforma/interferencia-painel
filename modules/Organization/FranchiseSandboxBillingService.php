<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

use Interferencia\Modules\Finance\AsaasClient;
use RuntimeException;
use Throwable;

final readonly class FranchiseSandboxBillingService
{
    public function __construct(private FranchiseSandboxBillingRepository$tests,private AsaasClient$asaas){}

    public function issue(int$contractId,?int$userId):void
    {
        if(!$this->asaas->ready())throw new RuntimeException('Configure e ative a chave do Asaas Sandbox antes do teste.');
        $contract=$this->tests->contract($contractId);$attempt=$this->tests->begin($contract,$userId);
        try{
            $document=preg_replace('/\D/','',(string)$contract['cnpj'])??'';$customer=null;
            if(in_array(strlen($document),[11,14],true)){$matches=$this->asaas->customersByCpfCnpj($document);$customer=$matches[0]??null;}
            if(!is_array($customer))$customer=$this->asaas->createCustomer(array_filter(['name'=>(string)($contract['legal_name']?:$contract['franchise_name']),'cpfCnpj'=>$document,'email'=>(string)$contract['manager_email'],'phone'=>preg_replace('/\D/','',(string)$contract['manager_phone']),'externalReference'=>'mundo-inter:franchise:'.$contract['organization_id']],static fn(mixed$v):bool=>$v!==''));
            $customerId=(string)($customer['id']??'');if(!preg_match('/^cus_[A-Za-z0-9]+$/',$customerId))throw new RuntimeException('O Sandbox não retornou um cliente válido.');
            $payment=$this->asaas->createPayment(['customer'=>$customerId,'billingType'=>'PIX','value'=>5.00,'dueDate'=>(new \DateTimeImmutable('tomorrow'))->format('Y-m-d'),'description'=>'Teste financeiro Mundo Inter — '.$contract['franchise_name'],'externalReference'=>$attempt['external_reference']]);
            $this->tests->complete((int)$attempt['id'],$customerId,$payment);
        }catch(Throwable$e){$this->tests->fail((int)$attempt['id'],$e->getMessage());throw$e;}
    }

    public function sync(int$id):void{$test=$this->required($id);if(empty($test['asaas_payment_id']))throw new RuntimeException('Este teste não possui cobrança para sincronizar.');$this->tests->updateFromPayment($this->asaas->payment((string)$test['asaas_payment_id']));}
    public function cancel(int$id):void{$test=$this->required($id);if(empty($test['asaas_payment_id']))throw new RuntimeException('Este teste não possui cobrança para cancelar.');if(in_array((string)$test['status'],['RECEIVED','CONFIRMED','REFUNDED','DELETED'],true))throw new RuntimeException('A situação atual não permite cancelamento por esta ação.');$this->asaas->deletePayment((string)$test['asaas_payment_id']);$this->tests->markCancelled($id);}
    private function required(int$id):array{$test=$this->tests->find($id);if($test===null)throw new RuntimeException('Teste financeiro não encontrado.');return$test;}
}
