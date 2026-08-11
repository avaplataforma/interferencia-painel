<?php

declare(strict_types=1);

namespace Interferencia\Modules\Catalog;

use RuntimeException;

final class ContedTechClient
{
    private const MAX_PAGES = 250;

    private ?string $jwt = null;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $secretKey,
        private readonly string $integrationKey = '',
        private readonly bool $active = true,
    ) {}

    public function ready(): bool
    {
        return $this->active
            && trim($this->apiKey) !== ''
            && trim($this->secretKey) !== ''
            && $this->apiBase() !== ''
            && function_exists('curl_init');
    }

    /** @return list<array<string,mixed>> */
    public function listCourses(int $pageSize = 100, int $maxPages = self::MAX_PAGES): array
    {
        $pageSize = max(1, min(500, $pageSize));
        $maxPages = max(1, min(self::MAX_PAGES, $maxPages));
        $courses = [];

        for ($page = 0; $page < $maxPages; $page++) {
            $response = $this->request('contents', [
                'type' => 'course',
                'limit' => $pageSize,
                'offset' => $page * $pageSize,
            ]);
            $batch = $this->records($response);
            foreach ($batch as $course) $courses[] = $this->normalizeCourse($course);
            if (count($batch) < $pageSize) break;
        }

        return $courses;
    }

    /** @return array<string,mixed> */
    public function contentLink(string $type, string $batch, string $student): array
    {
        $type = trim($type);
        $batch = trim($batch);
        $student = trim($student);
        if ($type === '' || $batch === '' || $student === '') {
            throw new RuntimeException('Informe o conteúdo, o lote e o aluno para gerar o acesso EXPERT.');
        }

        return $this->request('content/link', ['type' => $type, 'batch' => $batch, 'student' => $student]);
    }

    /** @return array<string,mixed> */
    public function inactiveStudent(string $student): array
    {
        $student = trim($student);
        if ($student === '') throw new RuntimeException('Informe o aluno que terá o acesso EXPERT suspenso.');
        return $this->request('student/inactive', ['student' => $student]);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function request(string $endpoint, array $payload): array
    {
        if (!$this->ready()) throw new RuntimeException('Configure e ative a conexão com a CONTED TECH antes de consultar o Catálogo EXPERT.');
        $jwt = $this->jwt();
        return $this->jsonRequest($endpoint, $payload, ['Authorization: Bearer ' . $jwt]);
    }

    private function jwt(): string
    {
        if ($this->jwt !== null && $this->jwt !== '') return $this->jwt;
        $response = $this->jsonRequest('login', [
            'api_key' => trim($this->apiKey),
            'secret_key' => $this->secretKey,
        ]);
        $token = trim((string)($response['token'] ?? ''));
        if ($token === '') throw new RuntimeException('A CONTED TECH autenticou a conexão, mas não retornou o token JWT esperado.');
        return $this->jwt = $token;
    }

    /** @param array<string,mixed> $payload @param list<string> $extraHeaders @return array<string,mixed> */
    private function jsonRequest(string $endpoint, array $payload, array $extraHeaders = []): array
    {
        $curl = curl_init($this->apiBase() . '/' . ltrim($endpoint, '/'));
        if ($curl === false) throw new RuntimeException('Não foi possível iniciar a conexão com a CONTED TECH.');

        $headers = array_merge([
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: MUNDO-INTER/1.0',
        ], $extraHeaders);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $contentType = strtolower(trim((string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE)));
        $error = curl_error($curl);
        curl_close($curl);

        if (!is_string($body)) throw new RuntimeException('Falha de comunicação com a CONTED TECH' . ($error !== '' ? ': ' . $error : '') . '.');
        $data = json_decode($body, true);
        if (!is_array($data)) {
            $detail = $status > 0 ? ' (HTTP ' . $status . ')' : '';
            if (str_contains($contentType, 'text/html')) $detail .= ' A resposta recebida foi uma página HTML.';
            throw new RuntimeException('A CONTED TECH respondeu em formato diferente de JSON' . $detail . '.');
        }

        if ($status < 200 || $status >= 300) {
            $message = $this->errorMessage($data);
            throw new RuntimeException($message !== '' ? $message : 'A CONTED TECH respondeu com HTTP ' . $status . '.');
        }

        return $data;
    }

    /** @param array<string,mixed> $response @return list<array<string,mixed>> */
    private function records(array $response): array
    {
        $candidate = $response['data'] ?? [];
        if (!is_array($candidate)) throw new RuntimeException('A CONTED TECH retornou um catálogo em formato inesperado.');
        if (!array_is_list($candidate)) $candidate = [$candidate];
        return array_values(array_filter($candidate, 'is_array'));
    }

    /** @param array<string,mixed> $course @return array<string,mixed> */
    private function normalizeCourse(array $course): array
    {
        $name = trim((string)($course['name'] ?? ''));
        $batch = trim((string)($course['batch'] ?? ''));
        $type = trim((string)($course['type'] ?? ''));
        $updated = trim((string)($course['updated'] ?? ''));
        $structure = $course['semesters'] ?? $course['disciplines'] ?? [];

        return [
            'id' => $batch,
            'batch' => $batch,
            'nome' => $name,
            'categoria' => $type,
            'tipo_acesso' => 'AVA Cursos',
            'data_atualizacao' => $updated,
            'estrutura' => is_array($structure) ? $structure : [],
        ];
    }

    /** @param array<string,mixed> $data */
    private function errorMessage(array $data): string
    {
        $message = trim((string)($data['message'] ?? ''));
        $errors = $data['errors'] ?? '';
        if (is_string($errors) && trim($errors) !== '') return trim($errors);
        if (is_array($errors)) {
            $parts = [];
            array_walk_recursive($errors, static function (mixed $value) use (&$parts): void {
                if (is_scalar($value) && trim((string)$value) !== '') $parts[] = trim((string)$value);
            });
            if ($parts !== []) return implode(' ', array_unique($parts));
        }
        return $message;
    }

    private function apiBase(): string
    {
        $url = trim($this->baseUrl);
        if ($url === '') return '';
        if (filter_var($url, FILTER_VALIDATE_URL) === false || strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https') return '';
        $url = rtrim((string)(preg_replace('/[?#].*$/', '', $url) ?? ''), '/');
        return str_ends_with(strtolower($url), '/api/v2') ? $url : $url . '/api/v2';
    }
}
