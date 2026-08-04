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

    /** @param array{customer:string,billingType:string,value:float,dueDate:string,description:string,externalReference:string} $payload @return array<string,mixed> */
    public function createPayment(array $payload): array
    {
        if (!$this->paymentsWriteEnabled) throw new RuntimeException('A emissão real de cobranças ainda está bloqueada para o teste piloto.');
        return $this->request('POST', '/payments', $payload);
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
