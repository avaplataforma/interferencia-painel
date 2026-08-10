<?php

declare(strict_types=1);

namespace Interferencia\Modules\Catalog;

use RuntimeException;

final readonly class EscolaAvancadaClient
{
    public function __construct(
        private string $baseUrl,
        private string $token,
        private bool $active = true,
    ) {}

    public function ready(): bool
    {
        return $this->active
            && $this->token !== ''
            && $this->apiBase() !== ''
            && function_exists('curl_init');
    }

    /** @return list<array<string,mixed>> */
    public function listCourses(?string $category = null): array
    {
        $payload = ['token' => $this->token];
        if (trim((string)$category) !== '') $payload['categoria'] = trim((string)$category);

        $response = $this->request('cursos/listar', $payload);
        $courses = $response['resultado'] ?? [];
        if (!is_array($courses)) throw new RuntimeException('O fornecedor retornou um catálogo inválido.');

        return array_values(array_filter($courses, 'is_array'));
    }

    /** @param array<string,string> $payload @return array<string,mixed> */
    private function request(string $operation, array $payload): array
    {
        if (!$this->ready()) throw new RuntimeException('Configure e ative a conexão com a Escola Avançada antes de consultar o catálogo.');

        $curl = curl_init($this->apiBase() . '?' . $operation);
        if ($curl === false) throw new RuntimeException('Não foi possível iniciar a conexão com a Escola Avançada.');

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: MUNDO-INTER/1.0'],
        ]);

        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $contentType = strtolower(trim((string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE)));
        $error = curl_error($curl);
        curl_close($curl);

        if (!is_string($body)) throw new RuntimeException('Falha de comunicação com a Escola Avançada' . ($error !== '' ? ': ' . $error : '') . '.');

        $data = json_decode($body, true);
        if (!is_array($data)) {
            if ($status === 404 || str_contains($contentType, 'text/html')) {
                throw new RuntimeException('A URL informada não é a URL especial da API da Escola Avançada (HTTP ' . ($status > 0 ? $status : 'sem resposta') . '). Solicite ao fornecedor a URL da API V2; o endereço público do AVA deve ficar somente no campo “Endereço do AVA”.');
            }

            throw new RuntimeException('A API da Escola Avançada respondeu em um formato diferente de JSON' . ($status > 0 ? ' (HTTP ' . $status . ')' : '') . '. Confirme a URL especial da API com o fornecedor.');
        }

        $providerError = trim((string)($data['erro'] ?? ''));
        if ($providerError !== '') throw new RuntimeException($providerError);
        if ($status < 200 || $status >= 300) throw new RuntimeException('A API da Escola Avançada respondeu com HTTP ' . $status . '.');

        return $data;
    }

    private function apiBase(): string
    {
        $url = trim($this->baseUrl);
        if ($url === '') return '';
        if (filter_var($url, FILTER_VALIDATE_URL) === false || strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https') return '';

        $url = preg_replace('/[?#].*$/', '', $url) ?? '';
        $url = rtrim($url, '/');
        return str_ends_with(strtolower($url), '/api/v2') ? $url . '/' : $url . '/api/v2/';
    }
}
