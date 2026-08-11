<?php

declare(strict_types=1);

namespace Interferencia\Modules\Catalog;

use RuntimeException;

final readonly class PortalAvaClient
{
    private const COURSE_ENDPOINT = 'web_servicePg/getCursos/format/json';
    private const MAX_PAGES = 250;

    public function __construct(
        private string $baseUrl,
        private string $username,
        private string $password,
        private string $apiKey,
        private bool $active = true,
    ) {}

    public function ready(): bool
    {
        return $this->active
            && trim($this->username) !== ''
            && trim($this->password) !== ''
            && trim($this->apiKey) !== ''
            && $this->apiBase() !== ''
            && function_exists('curl_init');
    }

    /** @return list<array<string,mixed>> */
    public function listCourses(int $pageSize = 100, int $maxPages = self::MAX_PAGES): array
    {
        $pageSize = max(1, min(500, $pageSize));
        $maxPages = max(1, min(self::MAX_PAGES, $maxPages));
        $courses = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            $response = $this->request(self::COURSE_ENDPOINT, [
                'DtInicio' => '01/01/2020',
                'DtFim' => date('d/m/Y'),
                'registros_pagina' => $pageSize,
                'pagina' => $page,
            ]);
            $batch = $this->records($response);
            foreach ($batch as $course) $courses[] = $course;
            if (count($batch) < $pageSize || !$this->hasNextPage($response, $page)) break;
        }

        return $courses;
    }

    /** @param array<string,mixed> $student @return array<string,mixed> */
    public function createEnrollment(array $student): array
    {
        $required = ['CursoID', 'PoloID', 'Nome', 'CPF', 'Email', 'CEP', 'Numero'];
        foreach ($required as $field) {
            if (trim((string)($student[$field] ?? '')) === '') throw new RuntimeException('O campo ' . $field . ' é obrigatório para matricular no Catálogo MASTER.');
        }

        $student['CPF'] = preg_replace('/\D+/', '', (string)$student['CPF']) ?? '';
        $student['CEP'] = preg_replace('/\D+/', '', (string)$student['CEP']) ?? '';
        if (strlen((string)$student['CPF']) !== 11) throw new RuntimeException('Informe um CPF válido para a matrícula MASTER.');
        if (strlen((string)$student['CEP']) !== 8) throw new RuntimeException('Informe um CEP válido para a matrícula MASTER.');

        $payload = [];
        foreach (['CursoID', 'PoloID', 'Nome', 'CPF', 'Email', 'CEP', 'Numero', 'RG', 'OrgaoRG', 'UFRG', 'Endereco', 'Bairro', 'Compl', 'Telefone', 'Celular', 'DtNascto', 'EstadoCivil', 'Sexo', 'DtInicio', 'DtFim'] as $field) {
            if (isset($student[$field]) && trim((string)$student[$field]) !== '') $payload[$field] = (string)$student[$field];
        }

        return $this->request('web_service/cadastro/format/json', $payload);
    }

    /** @return array<string,mixed> */
    public function changeEnrollmentStatus(int $enrollmentId, bool $active): array
    {
        if ($enrollmentId < 1) throw new RuntimeException('Informe a matrícula MASTER que será alterada.');
        return $this->request('web_service/situacao/format/json', [
            'MatriculaID' => $enrollmentId,
            'Situacao' => $active ? 'A' : 'I',
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function listEnrollments(?string $cpf = null, int $pageSize = 100, int $maxPages = 10): array
    {
        $pageSize = max(1, min(500, $pageSize));
        $maxPages = max(1, min(self::MAX_PAGES, $maxPages));
        $cpf = preg_replace('/\D+/', '', trim((string)$cpf)) ?? '';
        $rows = [];
        for ($page = 1; $page <= $maxPages; $page++) {
            $payload = ['registros_pagina' => $pageSize, 'pagina' => $page];
            if ($cpf !== '') $payload['CPF'] = $cpf;
            $response = $this->request('web_servicePg/getMatriculas/format/json', $payload);
            $batch = $this->records($response);
            foreach ($batch as $row) $rows[] = $row;
            if (count($batch) < $pageSize || !$this->hasNextPage($response, $page)) break;
        }
        return $rows;
    }

    /** @param array<string,int|string> $payload @return array<string,mixed> */
    private function request(string $endpoint, array $payload): array
    {
        if (!$this->ready()) throw new RuntimeException('Informe URL, usuário, senha e chave EAD-API-KEY para conectar o Catálogo MASTER.');

        $curl = curl_init($this->apiBase() . '/' . ltrim($endpoint, '/'));
        if ($curl === false) throw new RuntimeException('Não foi possível iniciar a conexão com o Catálogo MASTER.');

        $responseHeaders = [];
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
            CURLOPT_USERPWD => trim($this->username) . ':' . $this->password,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
                'EAD-API-KEY: ' . trim($this->apiKey),
                'User-Agent: MUNDO-INTER/1.0',
            ],
            CURLOPT_HEADERFUNCTION => static function ($handle, string $header) use (&$responseHeaders): int {
                $length = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) $responseHeaders[mb_strtolower(trim($parts[0]))] = trim($parts[1]);
                return $length;
            },
        ]);

        // O Portal AVA exige um handshake HEAD para negociar o desafio HTTP Digest
        // antes do POST real. O projeto legado funcional do fornecedor segue esse fluxo.
        curl_exec($curl);
        curl_setopt_array($curl, [
            CURLOPT_NOBODY => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload, '', '&', PHP_QUERY_RFC3986),
        ]);

        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $contentType = strtolower(trim((string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE)));
        $error = curl_error($curl);
        curl_close($curl);

        if (!is_string($body)) throw new RuntimeException('Falha de comunicação com o Catálogo MASTER' . ($error !== '' ? ': ' . $error : '') . '.');
        $data = json_decode($body, true);
        if (!is_array($data)) {
            if (mb_strtolower((string)($responseHeaders['x-amzn-waf-action'] ?? '')) === 'captcha') {
                throw new RuntimeException('O firewall do Portal AVA bloqueou a integração com uma verificação CAPTCHA. Solicite ao fornecedor a liberação do IP público do servidor Mundo Inter para as rotas da API.');
            }
            $detail = $status > 0 ? ' (HTTP ' . $status . ')' : '';
            if (str_contains($contentType, 'text/html')) $detail .= ' A resposta recebida foi uma página HTML.';
            throw new RuntimeException('O Portal AVA respondeu em formato diferente de JSON' . $detail . ' Confirme a URL e as três credenciais com o fornecedor.');
        }

        if ($status < 200 || $status >= 300) throw new RuntimeException('O Portal AVA respondeu com HTTP ' . $status . '.');
        $providerStatus = mb_strtolower(trim((string)($data['Status'] ?? $data['status'] ?? '')));
        $message = trim((string)($data['Mensagem'] ?? $data['mensagem'] ?? $data['Message'] ?? $data['message'] ?? ''));
        if (in_array($providerStatus, ['erro', 'error', 'falha', 'failed'], true)) {
            throw new RuntimeException($message !== '' ? $message : 'O Portal AVA recusou a solicitação.');
        }

        return $data;
    }

    /** @param array<string,mixed> $response @return list<array<string,mixed>> */
    private function records(array $response): array
    {
        $candidate = $response['Info'] ?? $response['info'] ?? $response['Resultado'] ?? $response['resultado'] ?? $response;
        if (!is_array($candidate)) return [];

        foreach (['Cursos', 'cursos', 'Registros', 'registros', 'Dados', 'dados', 'Itens', 'itens'] as $key) {
            if (isset($candidate[$key]) && is_array($candidate[$key])) {
                $candidate = $candidate[$key];
                break;
            }
        }

        if (!array_is_list($candidate)) {
            $looksLikeCourse = array_intersect(['CursoID', 'curso_id', 'ID', 'id', 'Nome', 'nome', 'Curso', 'curso'], array_keys($candidate)) !== [];
            if ($looksLikeCourse) $candidate = [$candidate];
        }

        return array_values(array_filter($candidate, 'is_array'));
    }

    /** @param array<string,mixed> $response */
    private function hasNextPage(array $response, int $currentPage): bool
    {
        $source = $response['Paginacao'] ?? $response['paginacao'] ?? $response['Info'] ?? $response['info'] ?? $response;
        if (!is_array($source)) return true;
        $last = $source['total_paginas'] ?? $source['TotalPaginas'] ?? $source['totalPaginas'] ?? $source['paginas'] ?? $source['Paginas'] ?? null;
        return !is_numeric($last) || $currentPage < (int)$last;
    }

    private function apiBase(): string
    {
        $url = trim($this->baseUrl);
        if ($url === '') return '';
        if (filter_var($url, FILTER_VALIDATE_URL) === false || strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https') return '';
        return rtrim((string)(preg_replace('/[?#].*$/', '', $url) ?? ''), '/');
    }
}
