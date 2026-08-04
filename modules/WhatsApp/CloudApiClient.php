<?php

declare(strict_types=1);

namespace Interferencia\Modules\WhatsApp;

use RuntimeException;

final readonly class CloudApiClient
{
    private const MAX_MEDIA_SIZE = 16777216;

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

    public function canReceiveMedia(): bool
    {
        return $this->token !== ''
            && preg_match('/^v\d+\.\d+$/', $this->graphVersion) === 1
            && function_exists('curl_init');
    }

    /** @return array{content:string,mime_type:string} */
    public function downloadMedia(string $mediaId): array
    {
        if (!$this->canReceiveMedia() || $mediaId === '') {
            throw new RuntimeException('As credenciais para receber mídias ainda não estão disponíveis.');
        }

        $metadata = $this->getJson(sprintf(
            'https://graph.facebook.com/%s/%s',
            $this->graphVersion,
            rawurlencode($mediaId),
        ));
        $url = (string) ($metadata['url'] ?? '');
        if (!$this->isTrustedMediaUrl($url)) {
            throw new RuntimeException('A Meta retornou um endereço de mídia inválido.');
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Não foi possível iniciar o download da mídia.');
        }
        $content = '';
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->token],
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$content): int {
                if (strlen($content) + strlen($chunk) > self::MAX_MEDIA_SIZE) {
                    return 0;
                }
                $content .= $chunk;
                return strlen($chunk);
            },
        ]);
        $result = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $mime = strtolower(trim((string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE)));
        $error = curl_error($curl);
        curl_close($curl);

        if ($result !== true || $status < 200 || $status >= 300 || $content === '') {
            throw new RuntimeException('Não foi possível baixar a mídia da Meta' . ($error !== '' ? ': ' . $error : '') . '.');
        }

        return ['content' => $content, 'mime_type' => strtok($mime, ';') ?: 'application/octet-stream'];
    }

    /** @return array<string,mixed> */
    private function getJson(string $url): array
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Não foi possível consultar a mídia na Meta.');
        }
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->token],
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        $data = is_string($response) ? json_decode($response, true) : null;
        if ($status < 200 || $status >= 300 || !is_array($data)) {
            throw new RuntimeException('A Meta não disponibilizou os dados da mídia.');
        }
        return $data;
    }

    private function isTrustedMediaUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https') {
            return false;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        return $host === 'lookaside.fbsbx.com'
            || str_ends_with($host, '.facebook.com')
            || $host === 'facebook.com';
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

    /** @param list<string> $parameters @return array{id:string,status:string} */
    public function sendTemplate(string $phoneNumberId,string $recipient,string $name,string $language,array $parameters):array
    {
        if(!$this->ready())throw new RuntimeException('O envio oficial ainda está bloqueado nas configurações.');
        if($phoneNumberId===''||!ctype_digit($phoneNumberId))throw new RuntimeException('A linha ainda não possui um Phone Number ID válido.');
        if(!preg_match('/^[a-z0-9_]+$/',$name)||$language==='')throw new RuntimeException('O modelo oficial possui identificação inválida.');
        if(!function_exists('curl_init'))throw new RuntimeException('A extensão cURL não está disponível no servidor.');
        $template=['name'=>$name,'language'=>['code'=>$language]];
        if($parameters!==[])$template['components']=[['type'=>'body','parameters'=>array_map(static fn(string$value):array=>['type'=>'text','text'=>$value],$parameters)]];
        $payload=json_encode(['messaging_product'=>'whatsapp','recipient_type'=>'individual','to'=>$recipient,'type'=>'template','template'=>$template],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $curl=curl_init(sprintf('https://graph.facebook.com/%s/%s/messages',$this->graphVersion,$phoneNumberId));if($curl===false)throw new RuntimeException('Não foi possível iniciar a conexão com a Meta.');
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->token,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>$payload]);
        $response=curl_exec($curl);$httpStatus=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$networkError=curl_error($curl);curl_close($curl);
        if(!is_string($response))throw new RuntimeException('Falha de comunicação com a Meta: '.($networkError?:'resposta indisponível').'.');
        $data=json_decode($response,true);if($httpStatus<200||$httpStatus>=300){$message=is_array($data)?(string)($data['error']['message']??'Erro não identificado pela Meta.'):'Resposta inválida da Meta.';$code=is_array($data)?(string)($data['error']['code']??$httpStatus):(string)$httpStatus;throw new RuntimeException("Meta {$code}: {$message}");}
        $id=is_array($data)?(string)($data['messages'][0]['id']??''):'';if($id==='')throw new RuntimeException('A Meta não retornou o identificador da mensagem.');return['id'=>$id,'status'=>'sent'];
    }
}
