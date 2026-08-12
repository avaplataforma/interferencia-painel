<?php

declare(strict_types=1);

namespace Interferencia\Modules\Catalog;

use RuntimeException;

final readonly class OpenAiCatalogTextClient
{
    public function __construct(private string $apiKey,private string $model='gpt-5-mini'){}

    /** @param list<string> $items @return array{short_description:string,description:string} */
    public function generateTrailCopy(string $name,string $category,array $items,string $guidance=''):array
    {
        if(trim($this->apiKey)==='')throw new RuntimeException('Configure a chave da API da OpenAI em ADM Central > Integrações > IA - OpenAI.');
        $itemList=implode("\n- ",array_slice(array_values(array_filter(array_map('trim',$items))),0,80));
        $prompt="Crie o texto comercial de uma Trilha educacional para uma loja de cursos brasileira.\nNome: {$name}\nCategoria: {$category}\nCursos individuais:\n- {$itemList}\n";
        if(trim($guidance)!=='')$prompt.="Orientação adicional: ".mb_substr(trim($guidance),0,1000)."\n";
        $prompt.="O resumo deve ser direto, atrativo e ter no máximo 280 caracteres. A descrição completa deve ter de 2 a 4 parágrafos, explicar benefícios e perfil do aluno, sem inventar carga horária, certificado, garantia, reconhecimento oficial ou resultados assegurados.";
        $payload=[
            'model'=>$this->model,
            'input'=>[
                ['role'=>'system','content'=>[['type'=>'input_text','text'=>'Você é um redator educacional brasileiro. Escreva em português claro, comercial e responsável.']]],
                ['role'=>'user','content'=>[['type'=>'input_text','text'=>$prompt]]],
            ],
            'text'=>['format'=>[
                'type'=>'json_schema','name'=>'trail_copy','strict'=>true,
                'schema'=>['type'=>'object','properties'=>['short_description'=>['type'=>'string'],'description'=>['type'=>'string']],'required'=>['short_description','description'],'additionalProperties'=>false],
            ]],
        ];
        $handle=curl_init('https://api.openai.com/v1/responses');
        if($handle===false)throw new RuntimeException('Não foi possível iniciar a conexão com a OpenAI.');
        curl_setopt_array($handle,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->apiKey,'Content-Type: application/json','Accept: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>120]);
        $body=curl_exec($handle);$status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);$error=curl_error($handle);curl_close($handle);
        if(!is_string($body))throw new RuntimeException('Falha de conexão com a OpenAI: '.($error?:'sem resposta'));
        $decoded=json_decode($body,true);
        if(!is_array($decoded))throw new RuntimeException('A OpenAI retornou uma resposta inválida.');
        if($status<200||$status>=300)throw new RuntimeException('OpenAI: '.(string)($decoded['error']['message']??'não foi possível gerar os textos.'));
        $text='';foreach((array)($decoded['output']??[])as$output)foreach((array)($output['content']??[])as$content)if(($content['type']??'')==='output_text')$text.=(string)($content['text']??'');
        $result=json_decode($text,true);
        if(!is_array($result)||trim((string)($result['short_description']??''))===''||trim((string)($result['description']??''))==='')throw new RuntimeException('A OpenAI não retornou os textos esperados.');
        return['short_description'=>trim((string)$result['short_description']),'description'=>trim((string)$result['description'])];
    }
}
