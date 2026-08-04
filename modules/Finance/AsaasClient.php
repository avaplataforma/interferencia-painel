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

    public function __construct(private string $environment, private string $apiKey) {}

    public function environment(): string { return $this->environment; }
    public function ready(): bool
    {
        $prefix = $this->environment === 'production' ? '$aact_prod_' : '$aact_hmlg_';
        return isset(self::BASES[$this->environment]) && str_starts_with($this->apiKey, $prefix) && function_exists('curl_init');
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
}
