<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

use Interferencia\Modules\Finance\AsaasClient;
use RuntimeException;
use Throwable;

final readonly class FranchiseContractBillingService
{
    public function __construct(private FranchiseContractRepository $contracts,private AsaasClient $asaas) {}

    public function issue(int$id,string$billingType,string$dueDate):void
    {
        $contract=$this->contracts->beginBilling($id,$billingType,$dueDate);
        try{
            $customerId=trim((string)($contract['asaas_customer_id']??''));
            if($customerId===''){$customerId=$this->findOrCreateCustomer($contract);$this->contracts->storeAsaasCustomer($id,$customerId);}
            $payment=$this->asaas->createPayment(['customer'=>$customerId,'billingType'=>$billingType,'value'=>(float)$contract['billing_amount'],'dueDate'=>$dueDate,'description'=>(string)($contract['billing_description']?:$contract['title']),'externalReference'=>'mundo-inter:franchise-contract:'.$id]);
            $this->contracts->completeBilling($id,$payment);
        }catch(Throwable$e){$this->contracts->failBilling($id,$e->getMessage());throw$e;}
    }

    public function sync(int$id):void
    {
        $contract=$this->contracts->find($id);if($contract===null)throw new RuntimeException('Contrato não encontrado.');
        $paymentId=trim((string)($contract['asaas_payment_id']??''));if($paymentId==='')throw new RuntimeException('Este contrato ainda não possui cobrança no Asaas.');
        $this->contracts->syncBilling($id,$this->asaas->payment($paymentId));
    }

    public function issueRecurringLink(int $id): void
    {
        $contract=$this->contracts->find($id);if($contract===null)throw new RuntimeException('Contrato não encontrado.');
        if($contract['status']!=='signed')throw new RuntimeException('O link só pode ser gerado depois da assinatura.');
        if((float)($contract['monthly_fixed_amount']??0)<=0)throw new RuntimeException('Este contrato não possui assinatura mensal fixa.');
        if(!empty($contract['asaas_payment_link_id']))throw new RuntimeException('Este contrato já possui link de assinatura.');
        try{
            $link=$this->asaas->createPaymentLink(['name'=>'Assinatura mensal #'.$id.' — '.(string)$contract['franchise_name'],'description'=>(string)($contract['billing_description']?:$contract['title']),'value'=>(float)$contract['monthly_fixed_amount'],'billingType'=>'UNDEFINED','chargeType'=>'RECURRENT','subscriptionCycle'=>'MONTHLY','notificationEnabled'=>false]);
            $this->contracts->storeRecurringLink($id,$link);
        }catch(Throwable$e){$this->contracts->failBilling($id,$e->getMessage());throw$e;}
    }

    private function findOrCreateCustomer(array$contract):string
    {
        $document=preg_replace('/\D/','',(string)$contract['cnpj'])??'';
        foreach($this->asaas->customersByCpfCnpj($document)as$customer){if(($customer['deleted']??false)!==true&&preg_replace('/\D/','',(string)($customer['cpfCnpj']??''))===$document){$id=(string)($customer['id']??'');if(preg_match('/^cus_[A-Za-z0-9]+$/',$id))return$id;}}
        $payload=['name'=>(string)$contract['legal_name'],'cpfCnpj'=>$document,'email'=>(string)$contract['manager_email'],'mobilePhone'=>preg_replace('/\D/','',(string)$contract['manager_phone']),'externalReference'=>'mundo-inter:franchise-application:'.$contract['franchise_application_id'],'groupName'=>'Franquias Mundo Inter'];
        foreach(['postalCode'=>'postal_code','address'=>'address','addressNumber'=>'address_number','complement'=>'address_complement','province'=>'neighborhood']as$target=>$source){$value=trim((string)($contract[$source]??''));if($value!=='')$payload[$target]=$value;}
        $customer=$this->asaas->createCustomer($payload);$id=(string)($customer['id']??'');if(!preg_match('/^cus_[A-Za-z0-9]+$/',$id))throw new RuntimeException('O Asaas não retornou um cliente válido.');return$id;
    }
}
