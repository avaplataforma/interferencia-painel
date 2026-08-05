<?php

declare(strict_types=1);

namespace Interferencia\Modules\Finance;

use RuntimeException;

final readonly class AsaasClient
{
    private const BASES = [
        'sandbox' => 'https://api-sandbox.asaas.com/v3',
        'production' => 'https://api.asaas.com/v3',
    ];

    public function __construct(private string $environment, private string $apiKey, private bool $paymentsWriteEnabled = false) {}

    public function environment(): string { return $this->environment; }
    public function ready(): bool
    {
        $prefix = $this->environment === 'production' ? '$aact_prod_' : '$aact_hmlg_';
        return isset(self::BASES[$this->environment]) && str_starts_with($this->apiKey, $prefix) && function_exists('curl_init');
    }

    public function paymentsWriteEnabled(): bool { return $this->paymentsWriteEnabled; }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function createCustomer(array $payload): array
    {
        if (!$this->paymentsWriteEnabled) throw new RuntimeException('A criação real de alunos está bloqueada.');
        return $this->request('POST', '/customers', $payload);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function updateCustomer(string $customerId, array $payload): array
    {
        if (!$this->paymentsWriteEnabled) throw new RuntimeException('A alteração real de clientes está bloqueada.');
        if (preg_match('/^cus_[A-Za-z0-9]+$/', $customerId) !== 1) throw new RuntimeException('Identificador do cliente inválido.');
        if ($payload === []) throw new RuntimeException('Nenhuma alteração foi informada.');
        return $this->request('PUT', '/customers/' . rawurlencode($customerId), $payload);
    }

    public function deleteCustomer(string $customerId): void
    {
        if (!$this->paymentsWriteEnabled) throw new RuntimeException('A exclusão real de clientes está bloqueada.');
        if (preg_match('/^cus_[A-Za-z0-9]+$/', $customerId) !== 1) throw new RuntimeException('Identificador do cliente inválido.');
        $this->request('DELETE', '/customers/' . rawurlencode($customerId), []);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function createCheckout(array$payload):array
    {
        if(!$this->paymentsWriteEnabled)throw new RuntimeException('A criação real de checkouts está bloqueada.');
        $checkout=$this->request('POST','/checkouts',$payload);$id=(string)($checkout['id']??'');
        if($id===''||preg_match('/^[A-Za-z0-9-]{20,80}$/',$id)!==1)throw new RuntimeException('O Asaas não retornou um identificador válido para o checkout.');
        $link=trim((string)($checkout['link']??''));
        if($link==='')$link=($this->environment==='sandbox'?'https://sandbox.asaas.com':'https://asaas.com').'/checkoutSession/show/'.rawurlencode($id);
        $host=strtolower((string)parse_url($link,PHP_URL_HOST));
        if(!str_ends_with($host,'.asaas.com')&&$host!=='asaas.com')throw new RuntimeException('O Asaas retornou um link de checkout inválido.');
        $checkout['link']=$link;
        return$checkout;
    }

    /** @param array{customer:string,billingType:string,value?:float,totalValue?:float,installmentCount?:int,dueDate:string,description:string,externalReference:string} $payload @return array<string,mixed> */
    public function createPayment(array $payload): array
    {
        if (!$this->paymentsWriteEnabled) throw new RuntimeException('A emissão real de cobranças ainda está bloqueada para o teste piloto.');
        return $this->request('POST', '/payments', $payload);
    }

    /** @return list<array<string,mixed>> */
    public function installmentPayments(string $installmentId): array
    {
        if (!preg_match('/^ins_[A-Za-z0-9]+$/', $installmentId)) throw new RuntimeException('Identificador do parcelamento inválido.');
        $result = $this->get('/installments/' . rawurlencode($installmentId) . '/payments', 0, 100);
        return $result['data'];
    }

    /** @param array<string,mixed> $firstPayment @return list<array<string,mixed>> */
    public function createdInstallmentPayments(array $firstPayment): array
    {
        $installmentId = (string)($firstPayment['installment'] ?? '');
        if (!preg_match('/^ins_[A-Za-z0-9]+$/', $installmentId)) return [$firstPayment];
        try {
            $payments = $this->installmentPayments($installmentId);
            return $payments !== [] ? $payments : [$firstPayment];
        } catch (RuntimeException) {
            // A cobrança já foi criada. A sincronização/webhook completará as parcelas sem induzir uma emissão duplicada.
            return [$firstPayment];
        }
    }

    /** @param array{billingType:string,value:float,dueDate:string,description:string,externalReference:string} $payload @return array<string,mixed> */
    public function updatePayment(string $paymentId,array $payload):array
    {
        $this->assertPaymentWrite($paymentId);
        return $this->request('PUT','/payments/'.rawurlencode($paymentId),$payload);
    }

    public function deletePayment(string $paymentId):void
    {
        $this->assertPaymentWrite($paymentId);
        $this->request('DELETE','/payments/'.rawurlencode($paymentId),[]);
    }

    /** @return array{encodedImage:string,payload:string,expirationDate:string} */
    public function pixQrCode(string $paymentId): array
    {
        if (!preg_match('/^pay_[A-Za-z0-9]+$/', $paymentId)) throw new RuntimeException('Identificador da cobrança inválido.');
        $data=$this->request('GET','/payments/'.rawurlencode($paymentId).'/pixQrCode',[]);
        return ['encodedImage'=>(string)($data['encodedImage']??''),'payload'=>(string)($data['payload']??''),'expirationDate'=>(string)($data['expirationDate']??'')];
    }

    /** @return array{data:list<array<string,mixed>>,hasMore:bool,totalCount:int,offset:int,limit:int} */
    public function listCustomers(int $offset = 0, int $limit = 100): array
    {
        return $this->get('/customers', $offset, $limit);
    }

    /** @return array{data:list<array<string,mixed>>,hasMore:bool,totalCount:int,offset:int,limit:int} */
    public function listPayments(int $offset = 0, int $limit = 100): array
    {
        return $this->get('/payments', $offset, $limit);
    }

    /** @return array{data:list<array<string,mixed>>,hasMore:bool,totalCount:int,offset:int,limit:int} */
    public function listSubscriptions(int $offset = 0, int $limit = 100): array{return $this->get('/subscriptions',$offset,$limit);}

    /** @param array{customer:string,billingType:string,value:float,nextDueDate:string,cycle:string,description:string,externalReference:string,maxPayments?:int,endDate?:string} $payload @return array<string,mixed> */
    public function createSubscription(array $payload):array
    {
        if(!$this->paymentsWriteEnabled)throw new RuntimeException('A criação real de assinaturas está bloqueada.');
        return$this->request('POST','/subscriptions',$payload);
    }

    /** @return array<string,mixed> */
    public function updateSubscriptionStatus(string$subscriptionId,string$status):array
    {
        if(!$this->paymentsWriteEnabled)throw new RuntimeException('As alterações reais de assinaturas estão bloqueadas.');
        if(!preg_match('/^sub_[A-Za-z0-9]+$/',$subscriptionId))throw new RuntimeException('Identificador da assinatura inválido.');
        if(!in_array($status,['ACTIVE','INACTIVE'],true))throw new RuntimeException('Situação da assinatura inválida.');
        return$this->request('PUT','/subscriptions/'.rawurlencode($subscriptionId),['status'=>$status]);
    }

    /** @return array{data:list<array<string,mixed>>,hasMore:bool,totalCount:int,offset:int,limit:int} */
    private function get(string $path, int $offset, int $limit): array
    {
        if (!$this->ready()) throw new RuntimeException('A conexão com o Asaas ainda não está configurada corretamente.');
        $offset = max(0, $offset); $limit = max(1, min(100, $limit));
        $url = self::BASES[$this->environment] . $path . '?' . http_build_query(['offset'=>$offset,'limit'=>$limit]);
        $curl = curl_init($url);
        if ($curl === false) throw new RuntimeException('Não foi possível iniciar a conexão com o Asaas.');
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['accept: application/json','access_token: ' . $this->apiKey,'User-Agent: PAINEL-INTER/1.0'],
        ]);
        $response = curl_exec($curl); $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE); $error = curl_error($curl); curl_close($curl);
        if (!is_string($response)) throw new RuntimeException('Falha de comunicação com o Asaas' . ($error !== '' ? ': ' . $error : '') . '.');
        $data = json_decode($response, true);
        if ($status < 200 || $status >= 300 || !is_array($data)) {
            $message = is_array($data) ? (string)($data['errors'][0]['description'] ?? 'O Asaas recusou a consulta.') : 'O Asaas retornou uma resposta inválida.';
            throw new RuntimeException($message);
        }
        return ['data'=>array_values(array_filter($data['data'] ?? [], 'is_array')),'hasMore'=>(bool)($data['hasMore'] ?? false),'totalCount'=>(int)($data['totalCount'] ?? 0),'offset'=>(int)($data['offset'] ?? $offset),'limit'=>(int)($data['limit'] ?? $limit)];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function request(string $method, string $path, array $payload): array
    {
        if (!$this->ready()) throw new RuntimeException('A conexão com o Asaas ainda não está configurada corretamente.');
        $curl = curl_init(self::BASES[$this->environment] . $path);
        if ($curl === false) throw new RuntimeException('Não foi possível iniciar a conexão com o Asaas.');
        $options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['accept: application/json','content-type: application/json','access_token: '.$this->apiKey,'User-Agent: PAINEL-INTER/1.0']];
        if($method!=='GET')$options[CURLOPT_POSTFIELDS]=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        curl_setopt_array($curl,$options);
        $response=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$error=curl_error($curl);curl_close($curl);
        if(!is_string($response))throw new RuntimeException('Falha de comunicação com o Asaas'.($error!==''?': '.$error:'').'.');
        $data=json_decode($response,true);
        if($status<200||$status>=300||!is_array($data)){$message=is_array($data)?(string)($data['errors'][0]['description']??'O Asaas recusou a cobrança.'):'O Asaas retornou uma resposta inválida.';throw new RuntimeException($message);}
        return $data;
    }

    private function assertPaymentWrite(string$paymentId):void
    {
        if(!$this->paymentsWriteEnabled)throw new RuntimeException('As alterações reais de cobranças estão bloqueadas.');
        if(!preg_match('/^pay_[A-Za-z0-9]+$/',$paymentId))throw new RuntimeException('Identificador da cobrança inválido.');
    }
}
