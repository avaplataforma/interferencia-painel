<?php

declare(strict_types=1);

namespace Interferencia\Modules\Storage;

use RuntimeException;

final readonly class SpacesClient
{
    public function __construct(private string $endpoint,private string $region,private string $accessKey,private string $secretKey){}

    public function put(string$key,string$content,string$mime='application/octet-stream'):void{$this->request('PUT',$key,$content,$mime,[],true);}
    public function get(string$key):string{return$this->request('GET',$key);}
    public function delete(string$key):void{$this->request('DELETE',$key);}
    public function list(string$prefix='',int$limit=20):string{return$this->request('GET','', '', 'application/octet-stream',['list-type'=>'2','max-keys'=>(string)max(1,min(1000,$limit)),'prefix'=>$prefix]);}

    /** @param array<string,string> $query */
    private function request(string$method,string$key='',string$body='',string$mime='application/octet-stream',array$query=[],bool$private=false):string
    {
        if(!function_exists('curl_init'))throw new RuntimeException('A extensão cURL não está disponível no servidor.');
        $parts=parse_url(rtrim($this->endpoint,'/'));$scheme=(string)($parts['scheme']??'https');$host=(string)($parts['host']??'');if($host==='')throw new RuntimeException('Endpoint de armazenamento inválido.');
        $uri=$key===''?'/':'/'.implode('/',array_map('rawurlencode',explode('/',ltrim($key,'/'))));ksort($query);$queryPairs=[];foreach($query as$name=>$value)$queryPairs[]=rawurlencode($name).'='.rawurlencode($value);$canonicalQuery=implode('&',$queryPairs);
        $now=gmdate('Ymd\THis\Z');$day=substr($now,0,8);$payloadHash=hash('sha256',$body);$headers=['host'=>$host,'x-amz-content-sha256'=>$payloadHash,'x-amz-date'=>$now];if($private)$headers['x-amz-acl']='private';ksort($headers);
        $canonicalHeaders='';foreach($headers as$name=>$value)$canonicalHeaders.=$name.':'.trim($value)."\n";$signedHeaders=implode(';',array_keys($headers));
        $canonical=$method."\n".$uri."\n".$canonicalQuery."\n".$canonicalHeaders."\n".$signedHeaders."\n".$payloadHash;$scope=$day.'/'.$this->region.'/s3/aws4_request';$stringToSign="AWS4-HMAC-SHA256\n{$now}\n{$scope}\n".hash('sha256',$canonical);
        $dateKey=hash_hmac('sha256',$day,'AWS4'.$this->secretKey,true);$regionKey=hash_hmac('sha256',$this->region,$dateKey,true);$serviceKey=hash_hmac('sha256','s3',$regionKey,true);$signingKey=hash_hmac('sha256','aws4_request',$serviceKey,true);$signature=hash_hmac('sha256',$stringToSign,$signingKey);
        $authorization='AWS4-HMAC-SHA256 Credential='.$this->accessKey.'/'.$scope.', SignedHeaders='.$signedHeaders.', Signature='.$signature;
        $curlHeaders=['Authorization: '.$authorization,'Host: '.$host,'x-amz-content-sha256: '.$payloadHash,'x-amz-date: '.$now,'Content-Type: '.$mime,'Content-Length: '.strlen($body)];if($private)$curlHeaders[]='x-amz-acl: private';
        $url=$scheme.'://'.$host.$uri.($canonicalQuery!==''?'?'.$canonicalQuery:'');$ch=curl_init($url);if($ch===false)throw new RuntimeException('Não foi possível iniciar a conexão com o Spaces.');
        curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$curlHeaders,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>45,CURLOPT_FOLLOWLOCATION=>false]);if($method==='PUT')curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
        $response=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
        if($response===false||$status<200||$status>=300){$detail=$error!==''?$error:$this->xmlMessage(is_string($response)?$response:'');throw new RuntimeException('DigitalOcean Spaces recusou a operação'.($status>0?' (HTTP '.$status.')':'.').($detail!==''?' '.$detail:''));}
        return is_string($response)?$response:'';
    }

    private function xmlMessage(string$body):string
    {
        if(preg_match('/<Message>(.*?)<\/Message>/s',$body,$m)===1)return trim(html_entity_decode(strip_tags($m[1]),ENT_QUOTES|ENT_XML1,'UTF-8'));return'';
    }
}
