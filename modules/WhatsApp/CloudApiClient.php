<?php

declare(strict_types=1);

namespace Interferencia\Modules\WhatsApp;

use RuntimeException;

final readonly class CloudApiClient
{
    public function __construct(
        private string $token,
        private string $graphVersion,
        private bool $enabled,
    ) {}

    public function ready(): bool
    {
        return $this->enabled
            && $this->token !== ''
            && preg_match('/^v\d+\.\d+$/', $this->graphVersion) === 1;
    }

    /** @return array{id:string,status:string} */
    public function sendText(string $phoneNumberId, string $recipient, string $body): array
    {
        if (!$this->ready()) {
            throw new RuntimeException('O envio oficial ainda está bloqueado nas configurações.');
        }
        if ($phoneNumberId === '' || !ctype_digit($phoneNumberId)) {
            throw new RuntimeException('A linha ainda não possui um Phone Number ID válido.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('A extensão cURL não está disponível no servidor.');
        }

        $curl = curl_init(sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $this->graphVersion,
            $phoneNumberId,
        ));
        if ($curl === false) {
            throw new RuntimeException('Não foi possível iniciar a conexão com a Meta.');
        }

        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipient,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $body],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
        ]);

        $response = curl_exec($curl);
        $httpStatus = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $networkError = curl_error($curl);
        curl_close($curl);

        if (!is_string($response)) {
            throw new RuntimeException('Falha de comunicação com a Meta: ' . ($networkError ?: 'resposta indisponível') . '.');
        }

        $data = json_decode($response, true);
        if ($httpStatus < 200 || $httpStatus >= 300) {
            $message = is_array($data) ? (string) ($data['error']['message'] ?? 'Erro não identificado pela Meta.') : 'Resposta inválida da Meta.';
            $code = is_array($data) ? (string) ($data['error']['code'] ?? $httpStatus) : (string) $httpStatus;
            throw new RuntimeException("Meta {$code}: {$message}");
        }

        $id = is_array($data) ? (string) ($data['messages'][0]['id'] ?? '') : '';
        if ($id === '') {
            throw new RuntimeException('A Meta não retornou o identificador da mensagem.');
        }

        return ['id' => $id, 'status' => 'sent'];
    }
}
