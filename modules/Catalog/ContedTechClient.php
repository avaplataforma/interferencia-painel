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
        $type = mb_strtolower(trim($type));
        $batch = trim($batch);
        $student = trim($student);
        if ($type === '' || $batch === '' || $student === '') {
            throw new RuntimeException('Informe o conteúdo, o lote e o aluno para gerar o acesso EXPERT.');
        }
        if (!in_array($type, ['discipline', 'unit', 'object'], true)) {
            throw new RuntimeException('A CONTED TECH libera somente disciplina, unidade ou objeto individual. Selecione um conteúdo individual do Catálogo EXPERT.');
        }

        if (!$this->ready()) throw new RuntimeException('Configure e ative a conexão com a CONTED TECH antes de consultar o Catálogo EXPERT.');
        return $this->getRequest('content/link', [
            'type' => $type,
            'batch' => $batch,
            'student' => $student,
        ], ['Authorization: Bearer ' . $this->jwt()]);
    }

    /** @return array<string,mixed> */
    public function inactiveStudent(string $student): array
    {
        $student = trim($student);
        if ($student === '') throw new RuntimeException('Informe o aluno que terá o acesso EXPERT suspenso.');
        return $this->request('student/inactive', ['student' => $student]);
    }

    /**
     * Retorna a avaliação vinculada ao conteúdo ou null quando o fornecedor
     * informa explicitamente que aquele batch não possui exame.
     *
     * @return array<string,mixed>|null
     */
    public function exam(string $type, string $batch): ?array
    {
        $type = mb_strtolower(trim($type));
        $batch = trim($batch);
        if (!in_array($type, ['discipline', 'unit', 'object'], true) || $batch === '') {
            throw new RuntimeException('O conteúdo EXPERT não possui tipo e batch válidos para consultar a avaliação.');
        }

        try {
            return self::normalizeExamPayload($this->request('exam', [
                'type' => $type,
                'batch' => $batch,
            ]));
        } catch (RuntimeException $exception) {
            $message = mb_strtolower($exception->getMessage());
            $withoutAccents = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $message);
            $searchable = $withoutAccents === false ? $message : mb_strtolower($withoutAccents);
            if (str_contains($searchable, 'nao possui exame') || str_contains($searchable, 'nenhum conteudo encontrado')) {
                return null;
            }
            throw $exception;
        }
    }

    /** @param array<string,mixed> $response @return array<string,mixed>|null */
    public static function normalizeExamPayload(array $response): ?array
    {
        $data = $response['data'] ?? null;
        if (!is_array($data)) return null;
        $questions = $data['questions'] ?? [];
        if (!is_array($questions) || !array_is_list($questions)) return null;

        $normalized = [];
        foreach (array_slice($questions, 0, 100) as $question) {
            if (!is_array($question)) continue;
            $text = trim((string)($question['question'] ?? ''));
            $options = $question['options'] ?? [];
            $correctKey = trim((string)($question['correct_key'] ?? ''));
            if ($text === '' || $correctKey === '' || !is_array($options)) continue;

            $normalizedOptions = [];
            $hasCorrect = false;
            foreach (array_slice($options, 0, 10) as $option) {
                if (!is_array($option)) continue;
                $key = trim((string)($option['key'] ?? ''));
                $optionText = trim((string)($option['option'] ?? ''));
                if ($key === '' || $optionText === '') continue;
                $normalizedOptions[] = ['key' => $key, 'text' => $optionText];
                if ($key === $correctKey) $hasCorrect = true;
            }
            if (count($normalizedOptions) < 2 || !$hasCorrect) continue;
            $normalized[] = [
                'text' => $text,
                'options' => $normalizedOptions,
                'correct_key' => $correctKey,
            ];
        }
        if ($normalized === []) return null;

        $exam = [
            'title' => trim((string)($data['title'] ?? 'Avaliação')) ?: 'Avaliação',
            'content' => trim((string)($data['content'] ?? '')),
            'total_questions' => count($normalized),
            'questions' => $normalized,
        ];
        $exam['signature'] = hash('sha256', json_encode($exam, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return $exam;
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

    /** @param array<string,scalar> $query @param list<string> $extraHeaders @return array<string,mixed> */
    private function getRequest(string $endpoint, array $query, array $extraHeaders = []): array
    {
        $url = $this->apiBase() . '/' . ltrim($endpoint, '/') . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $curl = curl_init($url);
        if ($curl === false) throw new RuntimeException('Não foi possível iniciar a conexão com a CONTED TECH.');

        $headers = array_merge([
            'Accept: application/json',
            'User-Agent: MUNDO-INTER/1.0',
        ], $extraHeaders);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
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
        $contents = self::extractSellableContents($course);

        return [
            'id' => $batch,
            'batch' => $batch,
            'nome' => $name,
            'categoria' => $type,
            'tipo_acesso' => 'AVA Cursos',
            'data_atualizacao' => $updated,
            'estrutura' => is_array($structure) ? $structure : [],
            'conteudos' => $contents,
            'aulas' => count($contents),
        ];
    }

    /**
     * A CONTED identifica cada unidade vendável por type + batch. Disciplinas são
     * agrupadores e não são liberadas diretamente quando não possuem batch próprio.
     *
     * @param array<string,mixed> $course
     * @return list<array<string,mixed>>
     */
    public static function extractSellableContents(array $course): array
    {
        $semesters = $course['semesters'] ?? null;
        if (!is_array($semesters) || !array_is_list($semesters)) {
            $disciplines = $course['disciplines'] ?? [];
            $semesters = [['semester' => null, 'disciplines' => is_array($disciplines) ? $disciplines : []]];
        }

        $contents = [];
        $seen = [];
        $position = 0;

        /**
         * @param array<string,mixed> $node
         */
        $walk = static function (
            array $node,
            ?int $semesterNumber,
            string $disciplineName,
            string $fallbackType,
        ) use (&$walk, &$contents, &$seen, &$position): void {
            $name = trim((string)($node['name'] ?? ''));
            $rawType = mb_strtolower(trim((string)($node['type'] ?? $fallbackType)));
            $type = match (true) {
                $rawType === 'disciplina' || $rawType === 'discipline' => 'discipline',
                $rawType === 'unidade' || $rawType === 'unit' || $rawType === 'class' => 'unit',
                $rawType === 'objeto' || $rawType === 'object' || str_starts_with($rawType, 'objeto:') => 'object',
                default => $fallbackType,
            };
            $batch = trim((string)($node['batch'] ?? ''));
            $currentDiscipline = $type === 'discipline' && $name !== '' ? $name : $disciplineName;

            if ($name !== '' && $batch !== '' && in_array($type, ['discipline', 'unit', 'object'], true)) {
                $identity = $type . '|' . $batch;
                if (!isset($seen[$identity])) {
                    $seen[$identity] = true;
                    $position++;
                    $contents[] = [
                        'name' => $name,
                        'type' => $type,
                        'batch' => $batch,
                        'semester' => $semesterNumber,
                        'discipline' => $currentDiscipline,
                        'position' => $position,
                        'raw' => $node,
                    ];
                }
            }

            $childGroups = [
                'disciplines' => 'discipline',
                'classes' => 'unit',
                'units' => 'unit',
                'contents' => 'unit',
                'objects' => 'object',
            ];
            foreach ($childGroups as $key => $childType) {
                $children = $node[$key] ?? [];
                if (!is_array($children)) continue;
                foreach ($children as $child) {
                    if (!is_array($child)) continue;
                    $walk($child, $semesterNumber, $currentDiscipline, $childType);
                }
            }
        };

        foreach ($semesters as $semesterIndex => $semester) {
            if (!is_array($semester)) continue;
            $semesterNumber = isset($semester['semester']) && is_numeric($semester['semester'])
                ? max(1, (int)$semester['semester'])
                : ($semester['semester'] === null ? null : $semesterIndex + 1);
            $disciplines = $semester['disciplines'] ?? [];
            if (!is_array($disciplines)) continue;

            foreach ($disciplines as $discipline) {
                if (!is_array($discipline)) continue;
                $walk($discipline, $semesterNumber, '', 'discipline');
            }
        }

        foreach (['units' => 'unit', 'objects' => 'object'] as $key => $type) {
            $children = $course[$key] ?? [];
            if (!is_array($children)) continue;
            foreach ($children as $child) {
                if (is_array($child)) $walk($child, null, '', $type);
            }
        }

        return $contents;
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
