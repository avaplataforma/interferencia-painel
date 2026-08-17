<?php

declare(strict_types=1);

namespace Interferencia\Modules\Catalog;

use RuntimeException;

final class IesdeCommercialCatalogClient
{
    private ?string $token = null;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $email,
        private readonly string $password,
        private readonly bool $active = true,
    ) {}

    public function ready(): bool
    {
        return $this->active
            && $this->apiBase() !== ''
            && trim($this->email) !== ''
            && trim($this->password) !== ''
            && function_exists('curl_init');
    }

    /** @return list<array<string,mixed>> */
    public function listCatalog(int $pageSize = 50, int $maxPages = 100): array
    {
        if (!$this->ready()) throw new RuntimeException('Configure o acesso ao catálogo comercial MASTER antes de sincronizar.');
        $pageSize = max(1, min(100, $pageSize));
        $maxPages = max(1, min(100, $maxPages));
        $items = [];
        $seen = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            $response = $this->request('/api/Disciplines/catalog', [
                'page' => $page,
                'pageSize' => $pageSize,
                // O portal comercial usa "available" para os materiais ativos.
                'status' => 'available',
            ]);
            $batch = $this->records($response);
            foreach ($batch as $record) {
                $item = $this->normalize($record);
                if ($item['external_id'] === '' || $item['title'] === '' || isset($seen[$item['external_id']])) continue;
                $seen[$item['external_id']] = true;
                $items[] = $item;
            }
            if ($batch === [] || count($batch) < $pageSize || $this->isLastPage($response, $page)) break;
        }
        if ($items === []) throw new RuntimeException('O catálogo MASTER respondeu sem itens comerciais. Confira o acesso e tente novamente.');
        return $items;
    }

    /** @param array<string,mixed> $record @return array<string,mixed> */
    private function normalize(array $record): array
    {
        $external = $this->scalar($record, ['id', 'uuid', 'disciplineId', 'discipline_id', 'disciplineUuid']);
        $title = $this->scalar($record, ['title', 'name', 'nome', 'disciplineName', 'discipline']);
        $author = $this->textValue($record['author'] ?? $record['authors'] ?? $record['autor'] ?? '');
        $summary = $this->scalar($record, ['summary', 'synopsis', 'resumo', 'ementa', 'shortDescription']);
        $description = $this->scalar($record, ['description', 'descricao', 'content']);
        $category = $this->firstName($record, ['teachingAreas', 'teaching_area', 'academicLevel', 'academic_level', 'category', 'categoria']);
        $subcategory = $this->firstName($record, ['subAreas', 'sub_area', 'teachingArea', 'subcategory', 'subArea']);
        $materialType = $this->firstName($record, ['productionCategories', 'production_category', 'materialType', 'type']);
        $cover = $this->urlValue($record['coverUrl'] ?? $record['cover_url'] ?? $record['imageUrl'] ?? $record['image'] ?? $record['thumbnail'] ?? $record['cover'] ?? '');
        $slug = $this->scalar($record, ['slug']);
        if ($slug === '') $slug = $this->slug($title);

        return [
            'external_id' => $external,
            'title' => $title,
            'slug' => $slug,
            'author' => $author,
            'summary' => $summary,
            'description' => $description,
            'category' => $category,
            'subcategory' => $subcategory,
            'material_type' => $materialType,
            'cover_url' => $cover,
            'detail_url' => $external !== '' ? 'https://fornecimento.iesde.com.br/disciplines/' . rawurlencode($external) : '',
            'source_published_at' => $this->dateValue($record, ['publishedAt', 'published_at', 'publicationDate', 'publication_date', 'releasedAt', 'released_at']),
            'source_updated_at' => $this->dateValue($record, ['updatedAt', 'updated_at', 'lastUpdate', 'last_update', 'modifiedAt', 'modified_at']),
            'topics_count' => $this->integer($record, ['topicsCount', 'topics_count', 'themesCount', 'temas']),
            'resources_count' => $this->integer($record, ['resourcesCount', 'resources_count', 'recursos']),
            'questions_count' => $this->integer($record, ['questionsCount', 'questions_count', 'questoes']),
            'complementary_count' => $this->integer($record, ['complementaryCount', 'complementary_count', 'complementares']),
            'raw' => $record,
        ];
    }

    /** @param array<string,mixed> $record @param list<string> $keys */
    private function firstName(array $record, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $record)) continue;
            $value = $record[$key];
            if (is_array($value)) {
                foreach ($value as $entry) {
                    if (!is_array($entry)) continue;
                    $name = $this->textValue($entry['name'] ?? $entry['nome'] ?? '');
                    if ($name !== '') return $name;
                }
                continue;
            }
            $name = $this->textValue($value);
            if ($name !== '') return $name;
        }
        return '';
    }

    /** @param array<string,mixed> $record @param list<string> $keys */
    private function dateValue(array $record, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $record)) continue;
            $value = trim((string)$this->textValue($record[$key]));
            if ($value === '') continue;
            $timestamp = strtotime($value);
            if ($timestamp !== false) return date('Y-m-d H:i:s', $timestamp);
        }
        return null;
    }

    /** @param array<string,mixed> $response @return list<array<string,mixed>> */
    private function records(array $response): array
    {
        foreach (['items', 'results', 'records', 'disciplines', 'content'] as $key) {
            if (isset($response[$key]) && is_array($response[$key]) && array_is_list($response[$key])) return array_values(array_filter($response[$key], 'is_array'));
        }
        if (isset($response['data']) && is_array($response['data'])) {
            if (array_is_list($response['data'])) return array_values(array_filter($response['data'], 'is_array'));
            return $this->records($response['data']);
        }
        return [];
    }

    /** @param array<string,mixed> $response */
    private function isLastPage(array $response, int $page): bool
    {
        $meta = is_array($response['meta'] ?? null) ? $response['meta'] : (is_array($response['pagination'] ?? null) ? $response['pagination'] : $response);
        foreach (['totalPages', 'total_pages', 'lastPage', 'last_page', 'pages'] as $key) {
            if (isset($meta[$key]) && is_numeric($meta[$key])) return $page >= (int)$meta[$key];
        }
        return false;
    }

    /** @param array<string,mixed> $record @param list<string> $keys */
    private function scalar(array $record, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $record)) continue;
            $value = $this->textValue($record[$key]);
            if ($value !== '') return $value;
        }
        return '';
    }

    private function textValue(mixed $value): string
    {
        if (is_scalar($value)) return trim(strip_tags((string)$value));
        if (!is_array($value)) return '';
        if (array_is_list($value)) {
            $parts = array_values(array_filter(array_map(fn(mixed $part): string => $this->textValue($part), $value)));
            return implode(', ', array_unique($parts));
        }
        foreach (['name', 'title', 'label', 'value', 'nome', 'url', 'src'] as $key) {
            if (array_key_exists($key, $value)) {
                $text = $this->textValue($value[$key]);
                if ($text !== '') return $text;
            }
        }
        return '';
    }

    private function urlValue(mixed $value): string
    {
        $url = $this->textValue($value);
        return filter_var($url, FILTER_VALIDATE_URL) !== false && strtolower((string)parse_url($url, PHP_URL_SCHEME)) === 'https' ? $url : '';
    }

    /** @param array<string,mixed> $record @param list<string> $keys */
    private function integer(array $record, array $keys): int
    {
        foreach ($keys as $key) if (isset($record[$key]) && is_numeric($record[$key])) return max(0, (int)$record[$key]);
        return 0;
    }

    private function slug(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii === false ? $value : $ascii) ?? '');
        return trim($slug, '-');
    }

    /** @param array<string,string|int> $query @return array<string,mixed> */
    private function request(string $path, array $query = []): array
    {
        $url = $this->apiBase() . $path . ($query !== [] ? '?' . http_build_query($query) : '');
        return $this->jsonRequest($url, 'GET', null, ['Authorization: Bearer ' . $this->token()]);
    }

    private function token(): string
    {
        if ($this->token !== null) return $this->token;
        $response = $this->jsonRequest($this->apiBase() . '/api/v1/auth/login', 'POST', [
            'email' => trim($this->email),
            'password' => $this->password,
        ]);
        $token = $this->textValue($response['token'] ?? $response['accessToken'] ?? $response['access_token'] ?? ($response['data']['token'] ?? ''));
        if ($token === '') throw new RuntimeException('O portal MASTER autenticou o acesso, mas não retornou o token esperado.');
        return $this->token = $token;
    }

    /** @param array<string,mixed>|null $payload @param list<string> $headers @return array<string,mixed> */
    private function jsonRequest(string $url, string $method, ?array $payload = null, array $headers = []): array
    {
        $curl = curl_init($url);
        if ($curl === false) throw new RuntimeException('Não foi possível iniciar a conexão com o catálogo MASTER.');
        $requestHeaders = array_merge(['Accept: application/json', 'User-Agent: MUNDO-INTER/1.0'], $headers);
        $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 60, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_HTTPHEADER => $requestHeaders];
        if ($method === 'POST') {
            $requestHeaders[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $requestHeaders;
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if (!is_string($body)) throw new RuntimeException('Falha de comunicação com o catálogo MASTER' . ($error !== '' ? ': ' . $error : '') . '.');
        $data = json_decode($body, true);
        if (!is_array($data)) throw new RuntimeException('O catálogo MASTER respondeu em formato diferente de JSON (HTTP ' . $status . ').');
        if ($status < 200 || $status >= 300) {
            $message = $this->textValue($data['message'] ?? $data['error'] ?? $data['errors'] ?? '');
            throw new RuntimeException($message !== '' ? $message : 'O catálogo MASTER respondeu com HTTP ' . $status . '.');
        }
        return $data;
    }

    private function apiBase(): string
    {
        $base = rtrim(trim($this->baseUrl), '/');
        if (filter_var($base, FILTER_VALIDATE_URL) === false || strtolower((string)parse_url($base, PHP_URL_SCHEME)) !== 'https') return '';
        return $base;
    }
}
