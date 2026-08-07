<?php

declare(strict_types=1);

namespace Interferencia\Modules\Storage;

use PDO;
use RuntimeException;

final readonly class DocumentTypeRepository
{
    public function __construct(private PDO $db) {}

    /** @return list<array<string,mixed>> */
    public function all(bool $activeOnly = false): array
    {
        $sql = "SELECT * FROM document_types WHERE scope='franchise'".($activeOnly?' AND is_active=1':'').' ORDER BY sort_order,name,id';
        return $this->db->query($sql)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $statement=$this->db->prepare("SELECT * FROM document_types WHERE id=:id AND scope='franchise' LIMIT 1");
        $statement->execute(['id'=>$id]);
        $row=$statement->fetch();
        return is_array($row)?$row:null;
    }

    /** @return array<string,string> */
    public function categories(): array
    {
        $categories=[];
        foreach($this->all(true) as$type)$categories[(string)$type['code']]=(string)$type['name'];
        return $categories;
    }

    public function save(?int $id, string $name, bool $required, bool $active, int $sortOrder): int
    {
        $name=trim($name);
        if(mb_strlen($name)<2||mb_strlen($name)>120)throw new RuntimeException('Informe um nome entre 2 e 120 caracteres.');
        $sortOrder=max(0,min(9999,$sortOrder));
        if($id!==null){
            if($this->find($id)===null)throw new RuntimeException('Tipo de documento não encontrado.');
            $statement=$this->db->prepare("UPDATE document_types SET name=:name,is_required=:required,is_active=:active,sort_order=:sort_order WHERE id=:id AND scope='franchise'");
            $statement->execute(['name'=>$name,'required'=>(int)$required,'active'=>(int)$active,'sort_order'=>$sortOrder,'id'=>$id]);
            return$id;
        }
        $code=$this->uniqueCode($name);
        $statement=$this->db->prepare("INSERT INTO document_types(scope,code,name,is_required,is_active,sort_order) VALUES('franchise',:code,:name,:required,:active,:sort_order)");
        $statement->execute(['code'=>$code,'name'=>$name,'required'=>(int)$required,'active'=>(int)$active,'sort_order'=>$sortOrder]);
        return(int)$this->db->lastInsertId();
    }

    private function uniqueCode(string $name): string
    {
        $ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$name);
        $base=strtolower(is_string($ascii)?$ascii:$name);
        $base=trim((string)preg_replace('/[^a-z0-9]+/','_',$base),'_');
        $base=mb_substr($base!==''?$base:'documento',0,48);
        $code=$base;$suffix=2;
        $statement=$this->db->prepare("SELECT COUNT(*) FROM document_types WHERE scope='franchise' AND code=:code");
        while(true){$statement->execute(['code'=>$code]);if((int)$statement->fetchColumn()===0)return$code;$code=mb_substr($base,0,44).'_'.$suffix;$suffix++;}
    }
}
