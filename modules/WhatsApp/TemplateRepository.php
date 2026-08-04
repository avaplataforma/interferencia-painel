<?php

declare(strict_types=1);

namespace Interferencia\Modules\WhatsApp;

use PDO;
use RuntimeException;
use Throwable;

final readonly class TemplateRepository
{
    private const VARIABLES = ['nome', 'curso', 'unidade', 'atendente'];

    public function __construct(private PDO $db) {}

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->db->query('SELECT * FROM whatsapp_templates ORDER BY name')->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function active(): array
    {
        return $this->db->query("SELECT * FROM whatsapp_templates WHERE is_active=1 ORDER BY approval_status='approved' DESC,name")->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function approved(): array
    {
        return $this->db->query("SELECT * FROM whatsapp_templates WHERE is_active=1 AND approval_status='approved' ORDER BY name")->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findApproved(int $id): ?array
    {
        $statement=$this->db->prepare("SELECT * FROM whatsapp_templates WHERE id=:id AND is_active=1 AND approval_status='approved' LIMIT 1");$statement->execute(['id'=>$id]);$row=$statement->fetch();return is_array($row)?$row:null;
    }

    /** @param array{nome:string,curso:string,unidade:string,atendente:string} $values @return array{body:string,parameters:list<string>,variables:array<string,string>} */
    public function render(array $template,array $values):array
    {
        $body=(string)($template['body']??'');preg_match_all('/\{\{\s*(nome|curso|unidade|atendente)\s*\}\}/u',$body,$matches);$parameters=[];
        foreach(($matches[1]??[]) as $variable){$value=trim((string)($values[$variable]??''));if($value==='')$value=$variable==='curso'?'curso de interesse':($variable==='atendente'?'Atendimento':'Contato');$parameters[]=$value;}
        foreach($values as$key=>$value)$body=(string)preg_replace('/\{\{\s*'.preg_quote($key,'/').'\s*\}\}/iu',$value,$body);
        return['body'=>$body,'parameters'=>$parameters,'variables'=>$values];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $statement=$this->db->prepare('SELECT * FROM whatsapp_templates WHERE id=:id');$statement->execute(['id'=>$id]);$row=$statement->fetch();return is_array($row)?$row:null;
    }

    public function save(?int $id,string $name,string $metaName,string $category,string $language,string $body,string $status,bool $active): int
    {
        $name=trim($name);$metaName=strtolower(trim($metaName));$language=trim($language);$body=trim($body);
        if(!preg_match('/^[a-z0-9_]+$/',$metaName))throw new RuntimeException('O nome na Meta deve usar somente letras minúsculas, números e sublinhado.');
        if(!in_array($category,['marketing','utility','authentication'],true))throw new RuntimeException('Selecione uma categoria válida.');
        if(!in_array($status,['draft','pending','approved','rejected'],true))throw new RuntimeException('Selecione uma situação válida.');
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/u',$body,$matches);
        foreach(array_unique($matches[1]??[])as$variable)if(!in_array($variable,self::VARIABLES,true))throw new RuntimeException("A variável {{$variable}} não é permitida.");
        $withoutAllowed=preg_replace('/\{\{\s*(nome|curso|unidade|atendente)\s*\}\}/u','',$body);
        if(is_string($withoutAllowed)&&(str_contains($withoutAllowed,'{{')||str_contains($withoutAllowed,'}}')))throw new RuntimeException('Use somente as variáveis disponíveis exatamente entre chaves duplas.');
        try{$sql=$id===null?'INSERT INTO whatsapp_templates(name,meta_name,category,language,body,approval_status,is_active) VALUES(:name,:meta,:category,:language,:body,:status,:active)':'UPDATE whatsapp_templates SET name=:name,meta_name=:meta,category=:category,language=:language,body=:body,approval_status=:status,is_active=:active WHERE id=:id';$statement=$this->db->prepare($sql);$params=['name'=>$name,'meta'=>$metaName,'category'=>$category,'language'=>$language,'body'=>$body,'status'=>$status,'active'=>(int)$active];if($id!==null)$params['id']=$id;$statement->execute($params);return$id??(int)$this->db->lastInsertId();}catch(Throwable$e){throw new RuntimeException('Já existe um modelo com esse nome na Meta.',0,$e);}
    }
}
