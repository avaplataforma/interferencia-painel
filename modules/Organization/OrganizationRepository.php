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
        $s=$this->database->prepare("SELECT * FROM organizations WHERE panel_slug=:slug AND status='active' LIMIT 1");$s->execute(['slug'=>$normalized]);$row=$s->fetch();return is_array($row)?$this->hydrate($row):null;
    }
    public function findActiveByCode(string $code): ?Organization
    {
        $s=$this->database->prepare("SELECT * FROM organizations WHERE code=:code AND status='active' LIMIT 1");$s->execute(['code'=>strtolower(trim($code))]);$row=$s->fetch();return is_array($row)?$this->hydrate($row):null;
    }
    public function userBelongsTo(int $userId,int $organizationId):bool
    {$s=$this->database->prepare("SELECT 1 FROM organization_users WHERE user_id=? AND organization_id=? AND status='active' LIMIT 1");$s->execute([$userId,$organizationId]);return$s->fetchColumn()!==false;}
    /** @return list<Organization> */
    public function forUser(int$userId):array{$s=$this->database->prepare("SELECT o.* FROM organizations o INNER JOIN organization_users m ON m.organization_id=o.id WHERE m.user_id=? AND m.status='active' AND o.status='active' ORDER BY o.display_name");$s->execute([$userId]);return array_map(fn(array$row):Organization=>$this->hydrate($row),$s->fetchAll());}
    public function allWithPrimaryDomain():array{return$this->database->query("SELECT o.*,d.host primary_host,d.status domain_status,(SELECT COUNT(*) FROM units u WHERE u.organization_id=o.id) unit_count,(SELECT COUNT(*) FROM organization_users m WHERE m.organization_id=o.id AND m.status='active') user_count,(SELECT a.id FROM franchise_applications a WHERE a.organization_id=o.id ORDER BY a.id DESC LIMIT 1) franchise_application_id,(SELECT COUNT(*) FROM franchise_contracts c WHERE c.organization_id=o.id) contract_count FROM organizations o LEFT JOIN organization_domains d ON d.organization_id=o.id AND d.is_primary=1 AND d.purpose='site' ORDER BY o.display_name")->fetchAll();}
    public function findRecord(int$id):?array{$s=$this->database->prepare('SELECT * FROM organizations WHERE id=:id');$s->execute(['id'=>$id]);$row=$s->fetch();return is_array($row)?$row:null;}
    public function domains(int$id):array{$s=$this->database->prepare('SELECT * FROM organization_domains WHERE organization_id=:id ORDER BY is_primary DESC,purpose,host');$s->execute(['id'=>$id]);return$s->fetchAll();}
    public function saveSiteDomain(int$id,string$siteHost,bool$active=false):void
    {
        if($this->findRecord($id)===null)throw new RuntimeException('Franquia não encontrada.');
        $host=self::normalizeHost($siteHost);
        if(trim($siteHost)!==''&&$host===null)throw new RuntimeException('Informe um domínio público válido, sem protocolo ou caminho.');
        if($host!==null){$owner=$this->database->prepare('SELECT organization_id FROM organization_domains WHERE host=:host LIMIT 1');$owner->execute(['host'=>$host]);$ownerId=$owner->fetchColumn();if($ownerId!==false&&(int)$ownerId!==$id)throw new RuntimeException('Este domínio já está vinculado a outra franquia.');}
        $this->database->beginTransaction();
        try{
            $this->database->prepare("UPDATE organization_domains SET is_primary=0 WHERE organization_id=:id AND purpose='site'")->execute(['id'=>$id]);
            if($host!==null){$statement=$this->database->prepare("INSERT INTO organization_domains(organization_id,host,purpose,is_primary,status,verified_at) VALUES(:id,:host,'site',1,:status,:verified) ON DUPLICATE KEY UPDATE organization_id=VALUES(organization_id),purpose='site',is_primary=1,status=VALUES(status),verified_at=VALUES(verified_at)");$statement->execute(['id'=>$id,'host'=>$host,'status'=>$active?'active':'pending','verified'=>$active?date('Y-m-d H:i:s'):null]);}
            $this->database->commit();
        }catch(Throwable$exception){if($this->database->inTransaction())$this->database->rollBack();throw$exception;}
    }
    /** @return array<string,int|string> */
    public function implementationFacts(int$id):array
    {
        $admins=$this->database->prepare("SELECT COUNT(DISTINCT membership.user_id) FROM organization_users membership INNER JOIN users u ON u.id=membership.user_id WHERE membership.organization_id=:organization AND membership.status='active' AND u.is_active=1 AND (membership.is_owner=1 OR EXISTS(SELECT 1 FROM user_roles ur INNER JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=membership.user_id AND r.code='super_admin'))");
        $admins->execute(['organization'=>$id]);
        $ava=$this->database->prepare("SELECT COUNT(*) FROM organization_ava_settings settings LEFT JOIN ava_connections shared ON shared.id=settings.shared_connection_id LEFT JOIN ava_connections own ON own.id=settings.own_connection_id WHERE settings.organization_id=:organization AND ((settings.access_mode='shared' AND shared.is_active=1 AND NULLIF(shared.base_url,'') IS NOT NULL AND NULLIF(shared.token_encrypted,'') IS NOT NULL AND shared.last_tested_at IS NOT NULL AND shared.last_error IS NULL) OR (settings.access_mode='own' AND own.is_active=1 AND NULLIF(own.base_url,'') IS NOT NULL AND NULLIF(own.token_encrypted,'') IS NOT NULL AND own.last_tested_at IS NOT NULL AND own.last_error IS NULL) OR (settings.access_mode='both' AND shared.is_active=1 AND NULLIF(shared.base_url,'') IS NOT NULL AND NULLIF(shared.token_encrypted,'') IS NOT NULL AND shared.last_tested_at IS NOT NULL AND shared.last_error IS NULL AND own.is_active=1 AND NULLIF(own.base_url,'') IS NOT NULL AND NULLIF(own.token_encrypted,'') IS NOT NULL AND own.last_tested_at IS NOT NULL AND own.last_error IS NULL))");
        $ava->execute(['organization'=>$id]);
        $poles=$this->database->prepare('SELECT COUNT(*) active_poles,COALESCE(SUM(is_primary=1),0) primary_poles FROM organization_poles WHERE organization_id=:organization AND is_active=1');
        $poles->execute(['organization'=>$id]);$poleFacts=$poles->fetch()?:[];
        $documents=$this->database->prepare("SELECT COUNT(*) FROM managed_documents WHERE organization_id=:organization AND scope='franchise' AND deleted_at IS NULL");
        $documents->execute(['organization'=>$id]);
        $finance=$this->database->prepare("SELECT account_mode,is_active,last_test_status,NULLIF(api_key_encrypted,'') IS NOT NULL configured FROM organization_finance_integrations WHERE organization_id=:organization AND provider='asaas' LIMIT 1");
        $finance->execute(['organization'=>$id]);$financeSettings=$finance->fetch()?:[];
        $financeMode=(string)($financeSettings['account_mode']??'central');
        if($financeMode==='exclusive'){$financeReady=(int)($financeSettings['is_active']??0)===1&&(int)($financeSettings['configured']??0)===1&&($financeSettings['last_test_status']??'')==='success';}
        else{$central=$this->database->query("SELECT COUNT(*) FROM finance_integrations WHERE provider='asaas' AND environment='production' AND is_active=1 AND NULLIF(api_key_encrypted,'') IS NOT NULL");$financeReady=(int)$central->fetchColumn()>0;}
        return[
            'active_admins'=>(int)$admins->fetchColumn(),
            'active_ava_integrations'=>(int)$ava->fetchColumn(),
            'active_poles'=>(int)($poleFacts['active_poles']??0),
            'primary_poles'=>(int)($poleFacts['primary_poles']??0),
            'franchise_documents'=>(int)$documents->fetchColumn(),
            'finance_account_ready'=>(int)$financeReady,
            'finance_account_mode'=>$financeMode,
        ];
    }
    public function setStatus(int$id,string$status):void
    {
        if(!in_array($status,['active','suspended'],true))throw new RuntimeException('Situação inválida.');
        $s=$this->database->prepare('UPDATE organizations SET status=:status WHERE id=:id');$s->execute(['status'=>$status,'id'=>$id]);
        if($s->rowCount()===0&&$this->findRecord($id)===null)throw new RuntimeException('Franquia não encontrada.');
    }

    /** @return array<string,mixed>|null Usuário master (papel Gestor) vinculado à franquia. */
    public function panelUser(int $organizationId):?array
    {
        $s=$this->database->prepare("SELECT u.id,u.name,u.email,u.is_active FROM users u INNER JOIN organization_users ou ON ou.user_id=u.id AND ou.status='active' INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id AND r.code='headquarters' WHERE ou.organization_id=:organization LIMIT 1");
        $s->execute(['organization'=>$organizationId]);
        $row=$s->fetch();
        return is_array($row)?$row:null;
    }
    public function financeIntegrationSummary():array
    {
        $row=$this->database->query("SELECT COUNT(*) total,SUM(asaas_wallet_id IS NOT NULL) configured,SUM(asaas_wallet_status='validated') validated,SUM(split_enabled=1) split_enabled FROM organizations")->fetch()?:[];
        return['total'=>(int)($row['total']??0),'configured'=>(int)($row['configured']??0),'validated'=>(int)($row['validated']??0),'split_enabled'=>(int)($row['split_enabled']??0)];
    }

    public function save(?int$id,array$data):int
    {
        $code=strtolower(trim((string)($data['code']??'')));$panelSlug=self::normalizeSlug((string)($data['panel_slug']??''))??'';$legal=trim((string)($data['legal_name']??''));$display=trim((string)($data['display_name']??''));$status=(string)($data['status']??'active');$siteHost=(string)($data['site_host']??'');$host=self::normalizeHost($siteHost);
        $cnpj=self::digits((string)($data['cnpj']??''));$stateRegistration=trim((string)($data['state_registration']??''));$municipalRegistration=trim((string)($data['municipal_registration']??''));$postalCode=trim((string)($data['postal_code']??''));$address=trim((string)($data['address']??''));$addressNumber=trim((string)($data['address_number']??''));$addressComplement=trim((string)($data['address_complement']??''));$neighborhood=trim((string)($data['neighborhood']??''));$city=trim((string)($data['city']??''));$state=strtoupper(trim((string)($data['state']??'')));
        $managerName=trim((string)($data['manager_name']??''));$managerDocument=self::digits((string)($data['manager_document']??''));$managerEmail=strtolower(trim((string)($data['manager_email']??'')));$managerPhone=trim((string)($data['manager_phone']??''));$generalManagerName=trim((string)($data['general_manager_name']??''));$generalManagerDocument=self::digits((string)($data['general_manager_document']??''));$generalManagerEmail=strtolower(trim((string)($data['general_manager_email']??'')));$generalManagerPhone=trim((string)($data['general_manager_phone']??''));
        $primary=self::normalizeColor((string)($data['primary_color']??''));$secondaryInput=trim((string)($data['secondary_color']??''));$secondary=$secondaryInput===''?null:self::normalizeColor($secondaryInput);$loginTitle=trim((string)($data['login_title']??''));$welcome=trim((string)($data['login_welcome_text']??''));$email=strtolower(trim((string)($data['support_email']??'')));$phone=trim((string)($data['support_phone']??''));$domainActive=($data['domain_active']??false)===true;
        if(preg_match('/^[a-z0-9][a-z0-9_-]{2,79}$/',$code)!==1)throw new RuntimeException('Informe um código interno válido.');
        if($panelSlug==='')throw new RuntimeException('Informe um endereço interno válido para a franquia.');
        if(mb_strlen($legal)<3||mb_strlen($display)<2)throw new RuntimeException('Informe a razão social e o nome de exibição.');
        if(!self::validCnpjDocument($cnpj))throw new RuntimeException('Informe um CNPJ válido para a franquia.');
        if(mb_strlen($managerName)<3||filter_var($managerEmail,FILTER_VALIDATE_EMAIL)===false||strlen(self::digits($managerPhone))<10)throw new RuntimeException('Informe nome, e-mail e telefone válidos para o gestor responsável.');
        if($managerDocument!==''&&!self::validCpfDocument($managerDocument))throw new RuntimeException('Informe um CPF válido para o gestor.');
        if($generalManagerEmail!==''&&filter_var($generalManagerEmail,FILTER_VALIDATE_EMAIL)===false)throw new RuntimeException('Informe um e-mail válido para o gerente.');
        if($generalManagerDocument!==''&&!self::validCpfDocument($generalManagerDocument))throw new RuntimeException('Informe um CPF válido para o gerente.');
        if($generalManagerPhone!==''&&strlen(self::digits($generalManagerPhone))<10)throw new RuntimeException('Informe um telefone válido para o gerente.');
        if($state!==''&&preg_match('/^[A-Z]{2}$/',$state)!==1)throw new RuntimeException('Informe a UF com duas letras.');
        if(!in_array($status,['active','suspended'],true))throw new RuntimeException('Situação inválida.');
        if(trim($siteHost)!==''&&$host===null)throw new RuntimeException('Informe um domínio público válido, sem protocolo ou caminho.');
        if($primary===null||($secondaryInput!==''&&$secondary===null))throw new RuntimeException('Informe cores válidas no formato hexadecimal.');
        if($email!==''&&filter_var($email,FILTER_VALIDATE_EMAIL)===false)throw new RuntimeException('Informe um e-mail de suporte válido.');
        if(mb_strlen($loginTitle)>160||mb_strlen($welcome)>500||mb_strlen($phone)>30)throw new RuntimeException('Um dos textos de personalização excede o tamanho permitido.');
        $identity=['code'=>$code,'slug'=>$panelSlug,'legal'=>$legal,'display'=>$display,'cnpj'=>$cnpj,'state_registration'=>$stateRegistration!==''?$stateRegistration:null,'municipal_registration'=>$municipalRegistration!==''?$municipalRegistration:null,'postal_code'=>$postalCode!==''?$postalCode:null,'address'=>$address!==''?$address:null,'address_number'=>$addressNumber!==''?$addressNumber:null,'address_complement'=>$addressComplement!==''?$addressComplement:null,'neighborhood'=>$neighborhood!==''?$neighborhood:null,'city'=>$city!==''?$city:null,'state'=>$state!==''?$state:null,'manager_name'=>$managerName,'manager_document'=>$managerDocument!==''?$managerDocument:null,'manager_email'=>$managerEmail,'manager_phone'=>$managerPhone,'general_manager_name'=>$generalManagerName!==''?$generalManagerName:null,'general_manager_document'=>$generalManagerDocument!==''?$generalManagerDocument:null,'general_manager_email'=>$generalManagerEmail!==''?$generalManagerEmail:null,'general_manager_phone'=>$generalManagerPhone!==''?$generalManagerPhone:null,'status'=>$status,'primary'=>$primary,'secondary'=>$secondary,'login_title'=>$loginTitle!==''?$loginTitle:null,'welcome'=>$welcome!==''?$welcome:null,'email'=>$email!==''?$email:null,'phone'=>$phone!==''?$phone:null];
        $this->database->beginTransaction();
        try{
            if($id===null){$s=$this->database->prepare('INSERT INTO organizations(public_id,code,panel_slug,legal_name,display_name,cnpj,state_registration,municipal_registration,postal_code,address,address_number,address_complement,neighborhood,city,state,manager_name,manager_document,manager_email,manager_phone,general_manager_name,general_manager_document,general_manager_email,general_manager_phone,status,primary_color,secondary_color,login_title,login_welcome_text,support_email,support_phone) VALUES(UUID(),:code,:slug,:legal,:display,:cnpj,:state_registration,:municipal_registration,:postal_code,:address,:address_number,:address_complement,:neighborhood,:city,:state,:manager_name,:manager_document,:manager_email,:manager_phone,:general_manager_name,:general_manager_document,:general_manager_email,:general_manager_phone,:status,:primary,:secondary,:login_title,:welcome,:email,:phone)');$s->execute($identity);$id=(int)$this->database->lastInsertId();}
            else{$s=$this->database->prepare('UPDATE organizations SET code=:code,panel_slug=:slug,legal_name=:legal,display_name=:display,cnpj=:cnpj,state_registration=:state_registration,municipal_registration=:municipal_registration,postal_code=:postal_code,address=:address,address_number=:address_number,address_complement=:address_complement,neighborhood=:neighborhood,city=:city,state=:state,manager_name=:manager_name,manager_document=:manager_document,manager_email=:manager_email,manager_phone=:manager_phone,general_manager_name=:general_manager_name,general_manager_document=:general_manager_document,general_manager_email=:general_manager_email,general_manager_phone=:general_manager_phone,status=:status,primary_color=:primary,secondary_color=:secondary,login_title=:login_title,login_welcome_text=:welcome,support_email=:email,support_phone=:phone WHERE id=:id');$s->execute($identity+['id'=>$id]);if($this->findRecord($id)===null)throw new RuntimeException('Franquia não encontrada.');}
            if($host!==null){$this->database->prepare("UPDATE organization_domains SET is_primary=0 WHERE organization_id=:id AND purpose='site'")->execute(['id'=>$id]);$s=$this->database->prepare("INSERT INTO organization_domains(organization_id,host,purpose,is_primary,status,verified_at) VALUES(:id,:host,'site',1,:status,:verified) ON DUPLICATE KEY UPDATE organization_id=VALUES(organization_id),purpose='site',is_primary=1,status=VALUES(status),verified_at=VALUES(verified_at)");$s->execute(['id'=>$id,'host'=>$host,'status'=>$domainActive?'active':'pending','verified'=>$domainActive?date('Y-m-d H:i:s'):null]);}
            $this->database->commit();return$id;
        }catch(Throwable$e){if($this->database->inTransaction())$this->database->rollBack();if($e instanceof RuntimeException)throw$e;throw new RuntimeException('Não foi possível salvar. O CNPJ, código, endereço interno ou domínio pode já estar em uso.',0,$e);}
    }
    public function updateBrandingPaths(int$id,?string$logoPath,?string$faviconPath):void
    {$s=$this->database->prepare('UPDATE organizations SET logo_path=:logo,favicon_path=:favicon WHERE id=:id');$s->execute(['logo'=>$logoPath,'favicon'=>$faviconPath,'id'=>$id]);if($s->rowCount()===0&&$this->findRecord($id)===null)throw new RuntimeException('Organização não encontrada.');}
    public function updateAvaCommunication(int$id,string$loginTitle,string$poloName,string$welcome,string$email,string$phone,string$avaAccessUrl='',string$avaPrimaryColor='',string$avaSecondaryColor=''):void
    {
        $loginTitle=trim($loginTitle);$poloName=trim(preg_replace('/\s+/u',' ',$poloName)??'');$welcome=trim($welcome);$email=strtolower(trim($email));$phone=trim($phone);$avaAccessUrl=trim($avaAccessUrl);$avaPrimaryColor=strtolower(trim($avaPrimaryColor));$avaSecondaryColor=strtolower(trim($avaSecondaryColor));
        if($this->findRecord($id)===null)throw new RuntimeException('Franquia não encontrada.');
        if($email!==''&&filter_var($email,FILTER_VALIDATE_EMAIL)===false)throw new RuntimeException('Informe um e-mail de suporte válido.');
        if($avaAccessUrl!==''&&filter_var($avaAccessUrl,FILTER_VALIDATE_URL)===false)throw new RuntimeException('Informe um endereço HTTPS válido para o acesso ao AVA.');
        if($avaPrimaryColor!==''&&preg_match('/^#[0-9a-f]{6}$/',$avaPrimaryColor)!==1)throw new RuntimeException('Informe uma cor primária válida em hexadecimal.');
        if($avaSecondaryColor!==''&&preg_match('/^#[0-9a-f]{6}$/',$avaSecondaryColor)!==1)throw new RuntimeException('Informe uma cor secundária válida em hexadecimal.');
        if(mb_strlen($loginTitle)>160||mb_strlen($poloName)>255||mb_strlen($welcome)>500||mb_strlen($phone)>30||mb_strlen($avaAccessUrl)>500)throw new RuntimeException('Um dos textos de identidade ou comunicação do AVA excede o tamanho permitido.');
        $s=$this->database->prepare('UPDATE organizations SET login_title=:login_title,ava_polo_name=:polo_name,login_welcome_text=:welcome,support_email=:email,support_phone=:phone,ava_access_url=:ava_access,ava_primary_color=:ava_primary,ava_secondary_color=:ava_secondary WHERE id=:id');
        $s->execute(['login_title'=>$loginTitle!==''?$loginTitle:null,'polo_name'=>$poloName!==''?$poloName:null,'welcome'=>$welcome!==''?$welcome:null,'email'=>$email!==''?$email:null,'phone'=>$phone!==''?$phone:null,'ava_access'=>$avaAccessUrl!==''?$avaAccessUrl:null,'ava_primary'=>$avaPrimaryColor!==''?$avaPrimaryColor:null,'ava_secondary'=>$avaSecondaryColor!==''?$avaSecondaryColor:null,'id'=>$id]);
    }
    public function saveFinanceSettings(int$id,array$data):void
    {
        $wallet=trim((string)($data['asaas_wallet_id']??''));$status=(string)($data['asaas_wallet_status']??'not_configured');$split=($data['split_enabled']??false)===true;$notes=trim((string)($data['asaas_finance_notes']??''));
        if($this->findRecord($id)===null)throw new RuntimeException('Franquia não encontrada.');
        if($wallet!==''&&preg_match('/^[A-Za-z0-9-]{20,80}$/',$wallet)!==1)throw new RuntimeException('Informe um Wallet ID válido do Asaas.');
        if(!in_array($status,['not_configured','pending','validated','invalid'],true))throw new RuntimeException('Situação de validação inválida.');
        if($wallet===''&&$status!=='not_configured')throw new RuntimeException('Informe o Wallet ID antes de alterar a situação.');
        if($split&&($wallet===''||$status!=='validated'))throw new RuntimeException('O split só pode ser ativado após validar o Wallet ID da franquia.');
        if(mb_strlen($notes)>500)throw new RuntimeException('As observações financeiras excedem 500 caracteres.');
        $s=$this->database->prepare('UPDATE organizations SET asaas_wallet_id=:wallet,asaas_wallet_status=:status,asaas_wallet_validated_at=:validated,split_enabled=:split,asaas_finance_notes=:notes WHERE id=:id');
        $s->execute(['wallet'=>$wallet!==''?$wallet:null,'status'=>$wallet===''?'not_configured':$status,'validated'=>$status==='validated'?date('Y-m-d H:i:s'):null,'split'=>(int)$split,'notes'=>$notes!==''?$notes:null,'id'=>$id]);
    }
    public static function normalizeSlug(string$slug):?string{$value=strtolower(trim($slug));if(preg_match('/^[a-z0-9](?:[a-z0-9-]{1,98}[a-z0-9])?$/',$value)!==1)return null;$reserved=['admin','api','assets','checkout','context','crm','finance','login','logout','notifications','status','students','tickets','units','users','roles','tags','whatsapp'];return in_array($value,$reserved,true)?null:$value;}
    public static function normalizeHost(string$host):?string{$value=strtolower(rtrim(trim($host),'.'));if(str_starts_with($value,'[')){$end=strpos($value,']');$value=$end===false?'':substr($value,1,$end-1);}elseif(substr_count($value,':')===1){$value=explode(':',$value,2)[0];}if($value===''||strlen($value)>253||filter_var($value,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)===false)return null;return$value;}
    private static function normalizeColor(string$color):?string{$value=strtolower(trim($color));return preg_match('/^#[0-9a-f]{6}$/',$value)===1?$value:null;}
    private static function digits(string$value):string{return preg_replace('/\D/','',$value)??'';}
    public static function validCpfDocument(string$value):bool
    {
        if(strlen($value)!==11||preg_match('/^(\d)\1{10}$/',$value)===1)return false;
        for($position=9;$position<=10;$position++){$sum=0;for($index=0;$index<$position;$index++)$sum+=(int)$value[$index]*(($position+1)-$index);$digit=(10*($sum%11))%11;if($digit===10)$digit=0;if((int)$value[$position]!==$digit)return false;}return true;
    }
    public static function validCnpjDocument(string$value):bool
    {
        if(strlen($value)!==14||preg_match('/^(\d)\1{13}$/',$value)===1)return false;
        foreach([[5,4,3,2,9,8,7,6,5,4,3,2],[6,5,4,3,2,9,8,7,6,5,4,3,2]]as$round=>$weights){$sum=0;foreach($weights as$index=>$weight)$sum+=(int)$value[$index]*$weight;$remainder=$sum%11;$digit=$remainder<2?0:11-$remainder;if((int)$value[12+$round]!==$digit)return false;}return true;
    }
    /** @param array<string,mixed> $row */
    private function hydrate(array$row):Organization{return new Organization((int)$row['id'],(string)$row['public_id'],(string)$row['code'],isset($row['panel_slug'])&&$row['panel_slug']!==null?(string)$row['panel_slug']:null,(string)$row['legal_name'],(string)$row['display_name'],(string)$row['timezone'],(string)$row['locale'],(string)$row['primary_color'],$row['secondary_color']===null?null:(string)$row['secondary_color'],$row['logo_path']===null?null:(string)$row['logo_path'],$row['favicon_path']===null?null:(string)$row['favicon_path'],isset($row['login_title'])&&$row['login_title']!==null?(string)$row['login_title']:null,isset($row['login_welcome_text'])&&$row['login_welcome_text']!==null?(string)$row['login_welcome_text']:null,isset($row['support_email'])&&$row['support_email']!==null?(string)$row['support_email']:null,isset($row['support_phone'])&&$row['support_phone']!==null?(string)$row['support_phone']:null);}
}
