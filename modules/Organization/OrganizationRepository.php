<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

use PDO;
use RuntimeException;
use Throwable;

final readonly class OrganizationRepository
{
    public function __construct(private PDO $database) {}

    public function findActiveByHost(string $host): ?Organization
    {
        $normalized=self::normalizeHost($host);if($normalized===null)return null;
        $s=$this->database->prepare("SELECT o.* FROM organizations o INNER JOIN organization_domains d ON d.organization_id=o.id WHERE d.host=:host AND d.status='active' AND o.status='active' LIMIT 1");
        $s->execute(['host'=>$normalized]);$row=$s->fetch();return is_array($row)?$this->hydrate($row):null;
    }

    public function findActiveByPanelSlug(string $slug): ?Organization
    {
        $normalized=self::normalizeSlug($slug);if($normalized===null)return null;
        $s=$this->database->prepare("SELECT * FROM organizations WHERE panel_slug=:slug AND status='active' LIMIT 1");
        $s->execute(['slug'=>$normalized]);$row=$s->fetch();return is_array($row)?$this->hydrate($row):null;
    }

    public function findActiveByCode(string $code): ?Organization
    {
        $s=$this->database->prepare("SELECT * FROM organizations WHERE code=:code AND status='active' LIMIT 1");
        $s->execute(['code'=>strtolower(trim($code))]);$row=$s->fetch();return is_array($row)?$this->hydrate($row):null;
    }

    public function userBelongsTo(int $userId,int $organizationId):bool
    {$s=$this->database->prepare("SELECT 1 FROM organization_users WHERE user_id=? AND organization_id=? AND status='active' LIMIT 1");$s->execute([$userId,$organizationId]);return$s->fetchColumn()!==false;}

    /** @return list<Organization> */
    public function forUser(int$userId):array{$s=$this->database->prepare("SELECT o.* FROM organizations o INNER JOIN organization_users m ON m.organization_id=o.id WHERE m.user_id=? AND m.status='active' AND o.status='active' ORDER BY o.display_name");$s->execute([$userId]);return array_map(fn(array$row):Organization=>$this->hydrate($row),$s->fetchAll());}

    public function allWithPrimaryDomain():array{return$this->database->query("SELECT o.*,d.host primary_host,d.status domain_status,(SELECT COUNT(*) FROM units u WHERE u.organization_id=o.id) unit_count,(SELECT COUNT(*) FROM organization_users m WHERE m.organization_id=o.id AND m.status='active') user_count FROM organizations o LEFT JOIN organization_domains d ON d.organization_id=o.id AND d.is_primary=1 AND d.purpose='site' ORDER BY o.display_name")->fetchAll();}
    public function findRecord(int$id):?array{$s=$this->database->prepare('SELECT * FROM organizations WHERE id=:id');$s->execute(['id'=>$id]);$row=$s->fetch();return is_array($row)?$row:null;}
    public function domains(int$id):array{$s=$this->database->prepare('SELECT * FROM organization_domains WHERE organization_id=:id ORDER BY is_primary DESC,purpose,host');$s->execute(['id'=>$id]);return$s->fetchAll();}

    public function save(?int$id,string$code,string$panelSlug,string$legal,string$display,string$status,string$siteHost,bool$domainActive):int
    {
        $code=strtolower(trim($code));$panelSlug=self::normalizeSlug($panelSlug)??'';$legal=trim($legal);$display=trim($display);$host=self::normalizeHost($siteHost);
        if(preg_match('/^[a-z0-9][a-z0-9_-]{2,79}$/',$code)!==1)throw new RuntimeException('Informe um código interno válido.');
        if($panelSlug==='')throw new RuntimeException('Informe um endereço interno válido para a franquia.');
        if(mb_strlen($legal)<3||mb_strlen($display)<2)throw new RuntimeException('Informe a razão social e o nome de exibição.');
        if(!in_array($status,['active','suspended'],true))throw new RuntimeException('Situação inválida.');
        if(trim($siteHost)!==''&&$host===null)throw new RuntimeException('Informe um domínio público válido, sem protocolo ou caminho.');
        $this->database->beginTransaction();
        try{
            if($id===null){$s=$this->database->prepare('INSERT INTO organizations(public_id,code,panel_slug,legal_name,display_name,status) VALUES(UUID(),:code,:slug,:legal,:display,:status)');$s->execute(['code'=>$code,'slug'=>$panelSlug,'legal'=>$legal,'display'=>$display,'status'=>$status]);$id=(int)$this->database->lastInsertId();}
            else{$s=$this->database->prepare('UPDATE organizations SET code=:code,panel_slug=:slug,legal_name=:legal,display_name=:display,status=:status WHERE id=:id');$s->execute(['code'=>$code,'slug'=>$panelSlug,'legal'=>$legal,'display'=>$display,'status'=>$status,'id'=>$id]);if($this->findRecord($id)===null)throw new RuntimeException('Organização não encontrada.');}
            if($host!==null){$this->database->prepare("UPDATE organization_domains SET is_primary=0 WHERE organization_id=:id AND purpose='site'")->execute(['id'=>$id]);$s=$this->database->prepare("INSERT INTO organization_domains(organization_id,host,purpose,is_primary,status,verified_at) VALUES(:id,:host,'site',1,:status,:verified) ON DUPLICATE KEY UPDATE organization_id=VALUES(organization_id),purpose='site',is_primary=1,status=VALUES(status),verified_at=VALUES(verified_at)");$s->execute(['id'=>$id,'host'=>$host,'status'=>$domainActive?'active':'pending','verified'=>$domainActive?date('Y-m-d H:i:s'):null]);}
            $this->database->commit();return$id;
        }catch(Throwable$e){if($this->database->inTransaction())$this->database->rollBack();if($e instanceof RuntimeException)throw$e;throw new RuntimeException('Não foi possível salvar. O código, endereço interno ou domínio pode já estar em uso.',0,$e);}
    }

    public static function normalizeSlug(string$slug):?string
    {
        $value=strtolower(trim($slug));if(preg_match('/^[a-z0-9](?:[a-z0-9-]{1,98}[a-z0-9])?$/',$value)!==1)return null;
        $reserved=['admin','api','assets','checkout','context','crm','finance','login','logout','notifications','status','students','tickets','units','users','roles','tags','whatsapp'];
        return in_array($value,$reserved,true)?null:$value;
    }

    public static function normalizeHost(string$host):?string
    {
        $value=strtolower(rtrim(trim($host),'.'));if(str_starts_with($value,'[')){$end=strpos($value,']');$value=$end===false?'':substr($value,1,$end-1);}elseif(substr_count($value,':')===1){$value=explode(':',$value,2)[0];}
        if($value===''||strlen($value)>253||filter_var($value,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)===false)return null;return$value;
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array$row):Organization
    {
        return new Organization((int)$row['id'],(string)$row['public_id'],(string)$row['code'],isset($row['panel_slug'])&&$row['panel_slug']!==null?(string)$row['panel_slug']:null,(string)$row['legal_name'],(string)$row['display_name'],(string)$row['timezone'],(string)$row['locale'],(string)$row['primary_color'],$row['secondary_color']===null?null:(string)$row['secondary_color'],$row['logo_path']===null?null:(string)$row['logo_path'],$row['favicon_path']===null?null:(string)$row['favicon_path'],isset($row['login_title'])&&$row['login_title']!==null?(string)$row['login_title']:null,isset($row['login_welcome_text'])&&$row['login_welcome_text']!==null?(string)$row['login_welcome_text']:null,isset($row['support_email'])&&$row['support_email']!==null?(string)$row['support_email']:null,isset($row['support_phone'])&&$row['support_phone']!==null?(string)$row['support_phone']:null);
    }
}
