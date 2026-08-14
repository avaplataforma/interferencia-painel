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

    /**
     * Produces a reviewable commercial presentation and a ten-question draft.
     * The source supplied to the model is intentionally limited to the course
     * and module names; the result must be approved by a human before Moodle
     * receives the assessment.
     *
     * @param list<string> $modules
     * @return array{short_description:string,description:string,questions:list<array{text:string,options:list<array{key:string,text:string}>,correct_key:string}>}
     */
    public function generateMasterPilot(string$name,string$category,array$modules,string$guidance=''):array
    {
        if(trim($this->apiKey)==='')throw new RuntimeException('Configure a chave da API da OpenAI em ADM Central > Integrações > IA - OpenAI.');
        $moduleList=implode("\n- ",array_slice(array_values(array_filter(array_map('trim',$modules))),0,120));
        if($moduleList==='')throw new RuntimeException('Sincronize as aulas e recursos MASTER antes de preparar o piloto.');
        $prompt="Prepare a curadoria de um Curso Individual da Formação MASTER.\nCurso: {$name}\nCategoria: {$category}\nMódulos e recursos disponíveis:\n- {$moduleList}\n";
        if(trim($guidance)!=='')$prompt.="Orientação adicional: ".mb_substr(trim($guidance),0,1000)."\n";
        $prompt.="Crie: (1) resumo comercial direto, com até 280 caracteres; (2) descrição completa em 2 a 4 parágrafos; (3) exatamente 10 questões objetivas, com quatro alternativas identificadas por a, b, c e d e apenas uma correta. Use somente conceitos claramente sustentados pelo nome do curso e pelos títulos dos módulos. Não invente carga horária, reconhecimento, legislação, estatísticas, garantias ou detalhes não fornecidos. As questões são um rascunho acadêmico para revisão humana, devem ser claras e não podem depender de pegadinhas.";
        $option=['type'=>'object','properties'=>['key'=>['type'=>'string','enum'=>['a','b','c','d']],'text'=>['type'=>'string']],'required'=>['key','text'],'additionalProperties'=>false];
        $question=['type'=>'object','properties'=>['text'=>['type'=>'string'],'options'=>['type'=>'array','minItems'=>4,'maxItems'=>4,'items'=>$option],'correct_key'=>['type'=>'string','enum'=>['a','b','c','d']]],'required'=>['text','options','correct_key'],'additionalProperties'=>false];
        $schema=['type'=>'object','properties'=>[
            'short_description'=>['type'=>'string'],
            'description'=>['type'=>'string'],
            'questions'=>['type'=>'array','minItems'=>10,'maxItems'=>10,'items'=>$question],
        ],'required'=>['short_description','description','questions'],'additionalProperties'=>false];
        $result=$this->structured('master_pilot',$prompt,$schema);
        $questions=array_values(array_filter((array)($result['questions']??[]),'is_array'));
        if(trim((string)($result['short_description']??''))===''||trim((string)($result['description']??''))===''||count($questions)!==10)throw new RuntimeException('A OpenAI não retornou o piloto MASTER completo. Tente novamente.');
        return['short_description'=>trim((string)$result['short_description']),'description'=>trim((string)$result['description']),'questions'=>$questions];
    }

    /** @param array<string,mixed> $schema @return array<string,mixed> */
    private function structured(string$name,string$prompt,array$schema):array
    {
        $payload=['model'=>$this->model,'input'=>[
            ['role'=>'system','content'=>[['type'=>'input_text','text'=>'Você é um curador educacional brasileiro. Produza conteúdo responsável, objetivo e fiel às informações fornecidas.']]],
            ['role'=>'user','content'=>[['type'=>'input_text','text'=>$prompt]]],
        ],'text'=>['format'=>['type'=>'json_schema','name'=>$name,'strict'=>true,'schema'=>$schema]]];
        $handle=curl_init('https://api.openai.com/v1/responses');
        if($handle===false)throw new RuntimeException('Não foi possível iniciar a conexão com a OpenAI.');
        curl_setopt_array($handle,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->apiKey,'Content-Type: application/json','Accept: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>180]);
        $body=curl_exec($handle);$status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);$error=curl_error($handle);curl_close($handle);
        if(!is_string($body))throw new RuntimeException('Falha de conexão com a OpenAI: '.($error?:'sem resposta'));
        $decoded=json_decode($body,true);
        if(!is_array($decoded))throw new RuntimeException('A OpenAI retornou uma resposta inválida.');
        if($status<200||$status>=300)throw new RuntimeException('OpenAI: '.(string)($decoded['error']['message']??'não foi possível gerar o piloto.'));
        $text='';foreach((array)($decoded['output']??[])as$output)foreach((array)($output['content']??[])as$content)if(($content['type']??'')==='output_text')$text.=(string)($content['text']??'');
        $result=json_decode($text,true);
        if(!is_array($result))throw new RuntimeException('A OpenAI não retornou o formato esperado.');
        return$result;
    }
}
