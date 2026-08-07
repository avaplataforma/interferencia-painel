<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

use PDO;
use RuntimeException;
use Throwable;

final readonly class FranchiseApplicationRepository
{
    public function __construct(private PDO $db) {}

    public function createInvitation(): array
    {
        $token=bin2hex(random_bytes(32));
        $s=$this->db->prepare("INSERT INTO franchise_applications(public_token,status) VALUES(:token,'invited')");
        $s->execute(['token'=>$token]);
        return ['id'=>(int)$this->db->lastInsertId(),'token'=>$token];
    }

    public function all(): array
    {
        return $this->db->query("SELECT a.*,o.display_name organization_name FROM franchise_applications a LEFT JOIN organizations o ON o.id=a.organization_id ORDER BY CASE a.status WHEN 'submitted' THEN 0 WHEN 'reviewing' THEN 1 WHEN 'invited' THEN 2 ELSE 3 END,a.updated_at DESC")->fetchAll();
    }

    public function find(int $id): ?array
    {
        $s=$this->db->prepare('SELECT a.*,o.display_name organization_name FROM franchise_applications a LEFT JOIN organizations o ON o.id=a.organization_id WHERE a.id=:id');$s->execute(['id'=>$id]);$row=$s->fetch();return is_array($row)?$row:null;
    }

    public function findPublic(string $token): ?array
    {
        if(preg_match('/^[a-f0-9]{64}$/',$token)!==1)return null;
        $s=$this->db->prepare("SELECT * FROM franchise_applications WHERE public_token=:token AND status='invited' LIMIT 1");$s->execute(['token'=>$token]);$row=$s->fetch();return is_array($row)?$row:null;
    }

    public function submit(string $token,array $data): int
    {
        $record=$this->findPublic($token);if($record===null)throw new RuntimeException('Este convite não está disponível.');
        $display=trim((string)($data['display_name']??''));$legal=trim((string)($data['legal_name']??''));$cnpj=self::digits((string)($data['cnpj']??''));$manager=trim((string)($data['manager_name']??''));$email=strtolower(trim((string)($data['manager_email']??'')));$phone=trim((string)($data['manager_phone']??''));
        if(mb_strlen($display)<2||mb_strlen($legal)<3)throw new RuntimeException('Informe o nome da franquia e a razão social.');
        if(!OrganizationRepository::validCnpjDocument($cnpj))throw new RuntimeException('Informe um CNPJ válido.');
        if(mb_strlen($manager)<3||filter_var($email,FILTER_VALIDATE_EMAIL)===false||strlen(self::digits($phone))<10)throw new RuntimeException('Informe nome, e-mail e telefone válidos para o gestor.');
        $state=strtoupper(trim((string)($data['state']??'')));if($state!==''&&preg_match('/^[A-Z]{2}$/',$state)!==1)throw new RuntimeException('Informe a UF com duas letras.');
        $managerDocument=self::digits((string)($data['manager_document']??''));if($managerDocument!==''&&!OrganizationRepository::validCpfDocument($managerDocument))throw new RuntimeException('Informe um CPF válido para o gestor.');
        $generalEmail=strtolower(trim((string)($data['general_manager_email']??'')));if($generalEmail!==''&&filter_var($generalEmail,FILTER_VALIDATE_EMAIL)===false)throw new RuntimeException('Informe um e-mail válido para o gerente.');
        $host=trim((string)($data['site_host']??''));if($host!==''&&OrganizationRepository::normalizeHost($host)===null)throw new RuntimeException('Informe um domínio válido, sem protocolo ou caminho.');
        $payload=['display'=>$display,'legal'=>$legal,'cnpj'=>$cnpj,'state_registration'=>self::nullable($data['state_registration']??''),'municipal_registration'=>self::nullable($data['municipal_registration']??''),'postal_code'=>self::nullable($data['postal_code']??''),'address'=>self::nullable($data['address']??''),'address_number'=>self::nullable($data['address_number']??''),'address_complement'=>self::nullable($data['address_complement']??''),'neighborhood'=>self::nullable($data['neighborhood']??''),'city'=>self::nullable($data['city']??''),'state'=>$state!==''?$state:null,'manager'=>$manager,'manager_document'=>$managerDocument!==''?$managerDocument:null,'manager_email'=>$email,'manager_phone'=>$phone,'general_manager_name'=>self::nullable($data['general_manager_name']??''),'general_manager_email'=>$generalEmail!==''?$generalEmail:null,'general_manager_phone'=>self::nullable($data['general_manager_phone']??''),'site_host'=>$host!==''?OrganizationRepository::normalizeHost($host):null,'notes'=>self::nullable($data['negotiation_notes']??''),'billing_required'=>($data['billing_required']??false)===true?1:0,'id'=>(int)$record['id']];
        $this->db->beginTransaction();
        try{
            $s=$this->db->prepare("UPDATE franchise_applications SET display_name=:display,legal_name=:legal,cnpj=:cnpj,state_registration=:state_registration,municipal_registration=:municipal_registration,postal_code=:postal_code,address=:address,address_number=:address_number,address_complement=:address_complement,neighborhood=:neighborhood,city=:city,state=:state,manager_name=:manager,manager_document=:manager_document,manager_email=:manager_email,manager_phone=:manager_phone,general_manager_name=:general_manager_name,general_manager_email=:general_manager_email,general_manager_phone=:general_manager_phone,site_host=:site_host,negotiation_notes=:notes,billing_required=:billing_required,status='submitted',submitted_at=NOW() WHERE id=:id");$s->execute($payload);
            $description="Nova solicitação de franquia recebida pelo formulário público.\n\nFranquia: {$display}\nRazão social: {$legal}\nCNPJ: {$cnpj}\nGestor: {$manager}\nE-mail: {$email}\nTelefone: {$phone}";
            $t=$this->db->prepare("INSERT INTO platform_tickets(franchise_application_id,subject,requester_name,requester_email,description,priority,status) VALUES(:application,:subject,:name,:email,:description,'high','open')");$t->execute(['application'=>(int)$record['id'],'subject'=>'Analisar nova solicitação de franquia — '.$display,'name'=>$manager,'email'=>$email,'description'=>$description]);
            $this->db->commit();return(int)$record['id'];
        }catch(Throwable$e){if($this->db->inTransaction())$this->db->rollBack();if($e instanceof RuntimeException)throw$e;throw new RuntimeException('Não foi possível enviar. Verifique se o CNPJ já foi cadastrado.',0,$e);}
    }

    public function updateReview(int$id,string$status):void
    {
        if(!in_array($status,['reviewing','rejected','cancelled'],true))throw new RuntimeException('Situação inválida.');
        $s=$this->db->prepare('UPDATE franchise_applications SET status=:status,reviewed_at=NOW() WHERE id=:id AND organization_id IS NULL');$s->execute(['status'=>$status,'id'=>$id]);if($s->rowCount()===0)throw new RuntimeException('Solicitação não encontrada ou já concluída.');
    }

    public function approve(int$id,int$organizationId):void
    {
        $s=$this->db->prepare("UPDATE franchise_applications SET organization_id=:organization,status='approved',reviewed_at=NOW() WHERE id=:id AND organization_id IS NULL");$s->execute(['organization'=>$organizationId,'id'=>$id]);if($s->rowCount()===0)throw new RuntimeException('Solicitação não encontrada ou já aprovada.');
        $this->db->prepare('UPDATE franchise_contracts SET organization_id=:organization WHERE franchise_application_id=:id')->execute(['organization'=>$organizationId,'id'=>$id]);
    }

    private static function digits(string$value):string{return preg_replace('/\D/','',$value)??'';}
    private static function nullable(mixed$value):?string{$v=trim((string)$value);return$v===''?null:$v;}
}
