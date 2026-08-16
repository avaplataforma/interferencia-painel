<?php

declare(strict_types=1);

namespace Interferencia\Modules\Catalog;

use RuntimeException;

final readonly class OpenAiCatalogTextClient
{
    public function __construct(private string $apiKey, private string $model = 'gpt-5-mini') {}

    /** @param list<string> $items @return array{short_description:string,description:string} */
    public function generateTrailCopy(string $name, string $category, array $items, string $guidance = ''): array
    {
        if (trim($this->apiKey) === '') {
            throw new RuntimeException('Configure a chave da API da OpenAI em ADM Central > Integrações > IA - OpenAI.');
        }

        $itemList = implode("\n- ", array_slice(array_values(array_filter(array_map('trim', $items))), 0, 80));
        $prompt = "Crie o texto comercial de uma Trilha educacional para uma loja de cursos brasileira.\nNome: {$name}\nCategoria: {$category}\nCursos individuais:\n- {$itemList}\n";
        if (trim($guidance) !== '') {
            $prompt .= 'Orientação adicional: '.mb_substr(trim($guidance), 0, 1000)."\n";
        }
        $prompt .= 'O resumo deve ser direto, atrativo e ter no máximo 280 caracteres. A descrição completa deve ter de 2 a 4 parágrafos, explicar benefícios e perfil do aluno, sem inventar carga horária, certificado, garantia, reconhecimento oficial ou resultados assegurados.';

        $schema = [
            'type' => 'object',
            'properties' => [
                'short_description' => ['type' => 'string'],
                'description' => ['type' => 'string'],
            ],
            'required' => ['short_description', 'description'],
            'additionalProperties' => false,
        ];
        $result = $this->structured('trail_copy', $prompt, $schema, 'Você é um redator educacional brasileiro. Escreva em português claro, comercial e responsável.');
        if (trim((string) ($result['short_description'] ?? '')) === '' || trim((string) ($result['description'] ?? '')) === '') {
            throw new RuntimeException('A OpenAI não retornou os textos esperados.');
        }

        return [
            'short_description' => trim((string) $result['short_description']),
            'description' => trim((string) $result['description']),
        ];
    }

    /** @param list<string> $modules @return array{short_description:string,description:string} */
    public function generateMasterCopy(string $name, string $category, array $modules, string $guidance = ''): array
    {
        if (trim($this->apiKey) === '') {
            throw new RuntimeException('Configure a chave da API da OpenAI em ADM Central > Integrações > IA - OpenAI.');
        }

        $cleanModules = array_values(array_unique(array_filter(array_map('trim', $modules))));
        $moduleList = implode("\n- ", array_slice($cleanModules, 0, 120));
        if ($moduleList === '') {
            throw new RuntimeException('Sincronize as aulas e os recursos da Formação MASTER antes de preparar os textos.');
        }

        $prompt = "Crie a apresentação comercial de um Curso Individual da Formação MASTER.\nCurso: {$name}\nCategoria: {$category}\nAulas e recursos oficiais:\n- {$moduleList}\n";
        if (trim($guidance) !== '') {
            $prompt .= 'Orientação adicional: '.mb_substr(trim($guidance), 0, 1000)."\n";
        }
        $prompt .= 'Entregue um resumo comercial objetivo, atrativo e completo, com até 280 caracteres, e uma descrição comercial em 2 a 4 parágrafos. Nunca mencione fornecedores, catálogo interno, IESDE, LTI, integração, API ou origem tecnológica. Não crie perguntas e não invente carga horária, certificado, reconhecimento, legislação, estatísticas ou garantias. A avaliação é obtida exclusivamente do banco oficial da plataforma acadêmica.';

        $schema = [
            'type' => 'object',
            'properties' => [
                'short_description' => ['type' => 'string'],
                'description' => ['type' => 'string'],
            ],
            'required' => ['short_description', 'description'],
            'additionalProperties' => false,
        ];
        $result = $this->structured('master_copy', $prompt, $schema);
        $description = $this->sanitizeMasterText((string) ($result['description'] ?? ''));
        $summary = $this->sanitizeMasterText((string) ($result['short_description'] ?? ''));
        if ($summary === '' && $description !== '') {
            $summary = $this->summaryFromDescription($description);
        }
        if ($summary === '' || $description === '') {
            throw new RuntimeException('A OpenAI não retornou o resumo e a descrição esperados. Tente novamente.');
        }
        if (mb_strlen($summary) > 280) {
            $summary = rtrim(mb_substr($summary, 0, 277)).'...';
        }

        return ['short_description' => $summary, 'description' => $description];
    }

    /** @return array{short_description:string,description:string} */
    public function generateCourseCopy(string $name, string $category, string $sourceDescription = '', string $guidance = ''): array
    {
        if (trim($this->apiKey) === '') {
            throw new RuntimeException('Configure a chave da API da OpenAI em ADM Central > Integrações > IA - OpenAI.');
        }

        $prompt = "Crie a apresentação comercial de um Módulo educacional para uma loja de cursos brasileira.\nNome: {$name}\nCategoria: {$category}\n";
        if (trim($sourceDescription) !== '') {
            $prompt .= 'Informações oficiais disponíveis: '.mb_substr(trim(strip_tags($sourceDescription)), 0, 5000)."\n";
        }
        if (trim($guidance) !== '') {
            $prompt .= 'Orientação adicional: '.mb_substr(trim($guidance), 0, 1000)."\n";
        }
        $prompt .= 'Entregue um resumo atrativo com até 280 caracteres e uma descrição de 2 a 4 parágrafos. Nunca mencione fornecedor, catálogo interno, integração, API ou origem tecnológica. Não invente carga horária, certificado, reconhecimento oficial, legislação, estatísticas, preço, garantia ou resultados assegurados.';

        $schema = [
            'type' => 'object',
            'properties' => [
                'short_description' => ['type' => 'string'],
                'description' => ['type' => 'string'],
            ],
            'required' => ['short_description', 'description'],
            'additionalProperties' => false,
        ];
        $result = $this->structured('course_copy', $prompt, $schema);
        $summary = trim((string)($result['short_description'] ?? ''));
        $description = trim((string)($result['description'] ?? ''));
        if ($summary === '' || $description === '') {
            throw new RuntimeException('A OpenAI não retornou o resumo e a descrição esperados.');
        }
        if (mb_strlen($summary) > 280) {
            $summary = rtrim(mb_substr($summary, 0, 277)).'...';
        }

        return ['short_description' => $summary, 'description' => $description];
    }

    private function sanitizeMasterText(string $value): string
    {
        $value = (string) (preg_replace('/\bIESDE\b/iu', 'Formação MASTER', $value) ?? $value);
        $value = (string) (preg_replace('/[ \t]{2,}/u', ' ', $value) ?? $value);

        return trim($value);
    }

    private function summaryFromDescription(string $description): string
    {
        $summary = trim((string) (preg_replace('/\s+/u', ' ', strip_tags($description)) ?? $description));
        if (mb_strlen($summary) <= 280) {
            return $summary;
        }

        return rtrim(mb_substr($summary, 0, 277)).'...';
    }

    /** @param array<string,mixed> $schema @return array<string,mixed> */
    private function structured(string $name, string $prompt, array $schema, string $system = 'Você é um curador educacional brasileiro. Produza conteúdo responsável, objetivo e fiel às informações fornecidas.'): array
    {
        $payload = [
            'model' => $this->model,
            'input' => [
                ['role' => 'system', 'content' => [['type' => 'input_text', 'text' => $system]]],
                ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => $prompt]]],
            ],
            'text' => ['format' => ['type' => 'json_schema', 'name' => $name, 'strict' => true, 'schema' => $schema]],
        ];
        $handle = curl_init('https://api.openai.com/v1/responses');
        if ($handle === false) {
            throw new RuntimeException('Não foi possível iniciar a conexão com a OpenAI.');
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$this->apiKey, 'Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 180,
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($body)) {
            throw new RuntimeException('Falha de conexão com a OpenAI: '.($error ?: 'sem resposta'));
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('A OpenAI retornou uma resposta inválida.');
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('OpenAI: '.(string) ($decoded['error']['message'] ?? 'não foi possível gerar os textos.'));
        }
        $text = '';
        foreach ((array) ($decoded['output'] ?? []) as $output) {
            foreach ((array) ($output['content'] ?? []) as $content) {
                if (($content['type'] ?? '') === 'output_text') {
                    $text .= (string) ($content['text'] ?? '');
                }
            }
        }
        $result = json_decode($text, true);
        if (!is_array($result)) {
            throw new RuntimeException('A OpenAI não retornou o formato esperado.');
        }

        return $result;
    }
}
