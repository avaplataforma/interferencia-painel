<?php

declare(strict_types=1);

namespace Interferencia\Modules\Catalog;

use JsonException;
use RuntimeException;

final class IesdeLtiVerifier
{
    /** @param array<string,mixed> $settings */
    public function verify(array $settings): int
    {
        $required = [
            'lti_platform_url' => 'URL do LMS',
            'lti_tool_url' => 'Target Link URI',
            'lti_deep_link_url' => 'Deep Linking',
            'lti_login_url' => 'Login OIDC',
            'lti_jwks_url' => 'JWKS',
            'lti_client_id' => 'Client ID',
            'lti_deployment_id' => 'Deployment ID',
        ];
        foreach ($required as $field => $label) {
            if (trim((string)($settings[$field] ?? '')) === '') {
                throw new RuntimeException('Configuração LTI incompleta: informe '.$label.'.');
            }
        }

        foreach (['lti_tool_url', 'lti_deep_link_url', 'lti_login_url', 'lti_jwks_url'] as $field) {
            $url = (string)$settings[$field];
            if (!$this->isOfficialHttpsUrl($url)) {
                throw new RuntimeException('O endereço '.$field.' não pertence ao host oficial do IESDE.');
            }
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('O servidor não possui suporte HTTP para validar o LTI.');
        }

        $curl = curl_init((string)$settings['lti_jwks_url']);
        if ($curl === false) throw new RuntimeException('Não foi possível iniciar o teste LTI.');
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'MundoInter-LTI-Validator/1.0',
        ]);
        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if (!is_string($body) || $status < 200 || $status >= 300) {
            throw new RuntimeException('O conjunto de chaves do IESDE não respondeu corretamente'.($error !== '' ? ': '.$error : '.'));
        }
        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('O IESDE retornou um conjunto de chaves inválido.');
        }
        $keys = is_array($payload) ? ($payload['keys'] ?? null) : null;
        if (!is_array($keys) || $keys === []) {
            throw new RuntimeException('O IESDE não publicou nenhuma chave LTI válida.');
        }
        return count($keys);
    }

    private function isOfficialHttpsUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) return false;
        return strtolower((string)parse_url($url, PHP_URL_SCHEME)) === 'https'
            && strtolower((string)parse_url($url, PHP_URL_HOST)) === 'api-fornecimento.iesde.com.br';
    }
}
