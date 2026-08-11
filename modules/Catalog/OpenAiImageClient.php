<?php

declare(strict_types=1);

namespace Interferencia\Modules\Catalog;

use RuntimeException;

final readonly class OpenAiImageClient implements CatalogImageGenerator
{
    public function __construct(private string $apiKey, private string $model, private string $quality, private string $size) {}

    public function test(): void
    {
        $this->request('GET','https://api.openai.com/v1/models/'.rawurlencode($this->model));
    }

    /** @param array<string,mixed> $context @return array{contents:string,mime_type:string,provider:string,prompt:string} */
    public function generate(string $prompt,array $context=[]): array
    {
        $result=$this->request('POST','https://api.openai.com/v1/images/generations',[
            'model'=>$this->model,'prompt'=>$prompt,'n'=>1,'size'=>$this->size,'quality'=>$this->quality,'output_format'=>'webp',
        ]);
        $encoded=(string)($result['data'][0]['b64_json']??'');
        $contents=base64_decode($encoded,true);
        if(!is_string($contents)||$contents==='')throw new RuntimeException('A OpenAI não retornou os dados da imagem gerada.');
        return['contents'=>$contents,'mime_type'=>'image/webp','provider'=>$this->model,'prompt'=>$prompt];
    }

    /** @param array<string,mixed>|null $payload @return array<string,mixed> */
    private function request(string $method,string $url,?array$payload=null): array
    {
        if(trim($this->apiKey)==='')throw new RuntimeException('Configure a chave da API da OpenAI.');
        $handle=curl_init($url);if($handle===false)throw new RuntimeException('Não foi possível iniciar a conexão com a OpenAI.');
        $headers=['Authorization: Bearer '.$this->apiKey,'Accept: application/json'];
        if($payload!==null){$json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$headers[]='Content-Type: application/json';curl_setopt($handle,CURLOPT_POSTFIELDS,$json);}
        curl_setopt_array($handle,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>180]);
        $body=curl_exec($handle);$status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);$error=curl_error($handle);curl_close($handle);
        if(!is_string($body))throw new RuntimeException('Falha de conexão com a OpenAI: '.($error?:'sem resposta'));
        $decoded=json_decode($body,true);if(!is_array($decoded))throw new RuntimeException('A OpenAI retornou uma resposta inválida.');
        if($status<200||$status>=300){$message=(string)($decoded['error']['message']??'Falha ao gerar a imagem.');throw new RuntimeException('OpenAI: '.$message);}
        return$decoded;
    }
}
