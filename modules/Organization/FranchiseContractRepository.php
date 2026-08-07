<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

use DateTimeImmutable;
use PDO;
use RuntimeException;

final readonly class FranchiseContractRepository
{
    public function __construct(private PDO $db) {}

    public function templates(bool $onlyActive=false): array
    {
        return $this->db->query('SELECT t.*,(SELECT COUNT(*) FROM franchise_contracts c WHERE c.template_id=t.id) usage_count FROM franchise_contract_templates t'.($onlyActive?' WHERE t.is_active=1':'').' ORDER BY t.is_active DESC,t.title')->fetchAll();
    }

    public function template(?int $id): ?array
    {
        if($id===null)return null;$s=$this->db->prepare('SELECT * FROM franchise_contract_templates WHERE id=:id');$s->execute(['id'=>$id]);$row=$s->fetch();return is_array($row)?$row:null;
    }

    public function saveTemplate(?int $id,array $data): int
    {
        $title=trim((string)($data['title']??''));$version=trim((string)($data['version']??''));$body=ContractContent::sanitize((string)($data['body']??''));
        if(mb_strlen($title)<4||$version===''||mb_strlen(ContractContent::visibleText($body))<100)throw new RuntimeException('Informe título, versão e conteúdo completo do modelo.');
        $params=['title'=>$title,'version'=>$version,'body'=>$body,'active'=>($data['is_active']??false)?1:0];
        if($id===null){$s=$this->db->prepare('INSERT INTO franchise_contract_templates(title,version,body,is_active) VALUES(:title,:version,:body,:active)');$s->execute($params);return(int)$this->db->lastInsertId();}
        $params['id']=$id;$s=$this->db->prepare('UPDATE franchise_contract_templates SET title=:title,version=:version,body=:body,is_active=:active WHERE id=:id');$s->execute($params);return$id;
    }

    public function removeTemplate(int $id): string
    {
        if ($this->template($id) === null) throw new RuntimeException('Modelo de contrato não encontrado.');
        $usage = $this->db->prepare('SELECT COUNT(*) FROM franchise_contracts WHERE template_id=:id');
        $usage->execute(['id' => $id]);
        if ((int) $usage->fetchColumn() > 0) {
            $archive = $this->db->prepare('UPDATE franchise_contract_templates SET is_active=0 WHERE id=:id');
            $archive->execute(['id' => $id]);
            return 'archived';
        }
        $delete = $this->db->prepare('DELETE FROM franchise_contract_templates WHERE id=:id');
        $delete->execute(['id' => $id]);
        return 'deleted';
    }

    public function all(): array
    {
        return $this->db->query("SELECT c.*,a.display_name franchise_name,a.cnpj,o.asaas_wallet_id,o.asaas_wallet_status,o.split_enabled FROM franchise_contracts c INNER JOIN franchise_applications a ON a.id=c.franchise_application_id LEFT JOIN organizations o ON o.id=c.organization_id ORDER BY c.updated_at DESC")->fetchAll();
    }

    public function forApplication(int $applicationId): array
    {
        $s=$this->db->prepare('SELECT * FROM franchise_contracts WHERE franchise_application_id=:application ORDER BY created_at DESC');$s->execute(['application'=>$applicationId]);return$s->fetchAll();
    }

    public function find(int $id): ?array
    {
        $s=$this->db->prepare('SELECT c.*,a.display_name franchise_name,a.legal_name,a.cnpj,a.manager_name,a.manager_email,a.manager_phone,a.postal_code,a.address,a.address_number,a.address_complement,a.neighborhood,o.asaas_wallet_id,o.asaas_wallet_status,o.split_enabled FROM franchise_contracts c INNER JOIN franchise_applications a ON a.id=c.franchise_application_id LEFT JOIN organizations o ON o.id=c.organization_id WHERE c.id=:id');$s->execute(['id'=>$id]);$row=$s->fetch();return is_array($row)?$row:null;
    }

    public function create(int $applicationId,int $templateId,array $data,?int $userId): int
    {
        $a=$this->application($applicationId);$template=$this->template($templateId);if($template===null||(int)$template['is_active']!==1)throw new RuntimeException('Selecione um modelo de contrato ativo.');
        if(in_array((string)$a['status'],['invited','rejected','cancelled'],true))throw new RuntimeException('A solicitação ainda não está apta a receber contrato.');
        $conditions=trim((string)($data['commercial_terms']??''));$term=trim((string)($data['term']??''));if(mb_strlen($conditions)<5||mb_strlen($term)<3)throw new RuntimeException('Informe as condições comerciais e a vigência.');
        $content=$this->render((string)$template['body'],$a,$conditions,$term);$title=trim((string)($data['title']??''))?:((string)$template['title'].' — '.(string)$a['display_name']);
        $model=(string)($data['commercial_model']??'');if(!in_array($model,['fixed_plus_percentage','split_only'],true))throw new RuntimeException('Selecione o modelo comercial do contrato.');
        $monthly=self::money($data['monthly_fixed_amount']??null);$percentage=self::percentage($data['sales_fee_percentage']??null);
        if($model==='fixed_plus_percentage'&&($monthly===null||$monthly<=0))throw new RuntimeException('Informe o valor da assinatura mensal.');
        if($percentage===null||$percentage<0||$percentage>100)throw new RuntimeException('Informe um percentual por venda entre 0 e 100%.');
        if($model==='split_only'&&$percentage<=0)throw new RuntimeException('Informe o percentual do Mundo Inter no split.');
        $type=(string)($data['contract_type']??'new');if(!in_array($type,['new','renewal'],true))throw new RuntimeException('Tipo de contrato inválido.');
        $parentId=(int)($data['parent_contract_id']??0);$parent=$parentId>0?$this->find($parentId):null;if($type==='renewal'&&($parent===null||(int)$parent['franchise_application_id']!==$applicationId))throw new RuntimeException('Selecione o contrato anterior que será renovado.');
        $validFrom=self::date($data['valid_from']??null);$validUntil=self::date($data['valid_until']??null);if($validFrom!==null&&$validUntil!==null&&$validUntil<$validFrom)throw new RuntimeException('A vigência final deve ser posterior à inicial.');
        $number=(int)$this->db->query('SELECT COALESCE(MAX(contract_number),0)+1 FROM franchise_contracts WHERE franchise_application_id='.(int)$applicationId)->fetchColumn();
        $billing=$model==='fixed_plus_percentage';$description=self::nullable($data['billing_description']??'Assinatura mensal da franquia');
        $s=$this->db->prepare("INSERT INTO franchise_contracts(parent_contract_id,contract_number,contract_type,valid_from,valid_until,commercial_model,monthly_fixed_amount,sales_fee_percentage,franchise_application_id,organization_id,template_id,title,content,public_token,status,billing_required,billing_amount,billing_description,created_by) VALUES(:parent,:number,:type,:valid_from,:valid_until,:model,:monthly,:percentage,:application,:organization,:template,:title,:content,:token,'draft',:billing,:amount,:description,:created_by)");
        $s->execute(['parent'=>$parent['id']??null,'number'=>$number,'type'=>$type,'valid_from'=>$validFrom,'valid_until'=>$validUntil,'model'=>$model,'monthly'=>$monthly,'percentage'=>$percentage,'application'=>$applicationId,'organization'=>$a['organization_id'],'template'=>$templateId,'title'=>$title,'content'=>$content,'token'=>bin2hex(random_bytes(32)),'billing'=>$billing?1:0,'amount'=>$billing?$monthly:null,'description'=>$description,'created_by'=>$userId]);
        $this->db->prepare("UPDATE franchise_applications SET contract_status='draft' WHERE id=:id")->execute(['id'=>$applicationId]);return(int)$this->db->lastInsertId();
    }

    public function send(int $id): void
    {
        $s=$this->db->prepare("UPDATE franchise_contracts SET status='sent',sent_at=NOW() WHERE id=:id AND status='draft'");$s->execute(['id'=>$id]);if($s->rowCount()===0)throw new RuntimeException('Somente contratos em rascunho podem ser enviados.');
        $this->db->prepare("UPDATE franchise_applications a INNER JOIN franchise_contracts c ON c.franchise_application_id=a.id SET a.contract_status='sent' WHERE c.id=:id")->execute(['id'=>$id]);
    }

    public function publicContract(string $token,bool $markViewed=true): ?array
    {
        if(preg_match('/^[a-f0-9]{64}$/',$token)!==1)return null;
        $s=$this->db->prepare("SELECT c.*,a.display_name franchise_name,a.legal_name,a.cnpj,a.manager_name,a.manager_email FROM franchise_contracts c INNER JOIN franchise_applications a ON a.id=c.franchise_application_id WHERE c.public_token=:token AND c.status IN('sent','viewed','signed') LIMIT 1");$s->execute(['token'=>$token]);$row=$s->fetch();if(!is_array($row))return null;
        if($markViewed&&$row['status']==='sent'){$this->db->prepare("UPDATE franchise_contracts SET status='viewed',viewed_at=NOW() WHERE id=:id AND status='sent'")->execute(['id'=>$row['id']]);$this->db->prepare("UPDATE franchise_applications SET contract_status='viewed' WHERE id=:id")->execute(['id'=>$row['franchise_application_id']]);$row['status']='viewed';}
        return$row;
    }

    public function sign(string $token,array $data,string $ip,string $userAgent): int
    {
        $contract=$this->publicContract($token,false);if($contract===null||$contract['status']==='signed')throw new RuntimeException('Este contrato não está disponível para assinatura.');
        $name=trim((string)($data['signer_name']??''));$email=strtolower(trim((string)($data['signer_email']??'')));$document=preg_replace('/\D/','',(string)($data['signer_document']??''))??'';
        if(mb_strlen($name)<3||filter_var($email,FILTER_VALIDATE_EMAIL)===false||strlen($document)<11)throw new RuntimeException('Informe nome, e-mail e documento válidos do signatário.');
        if(($data['accepted']??false)!==true)throw new RuntimeException('É necessário declarar o aceite integral do contrato.');
        $signedAt=(new DateTimeImmutable())->format('Y-m-d H:i:s');$hash=hash('sha256',$contract['id'].'|'.$contract['content'].'|'.$name.'|'.$email.'|'.$document.'|'.$signedAt.'|'.$ip.'|'.$userAgent);
        $s=$this->db->prepare("UPDATE franchise_contracts SET status='signed',signer_name=:name,signer_email=:email,signer_document=:document,signer_ip=:ip,signer_user_agent=:agent,evidence_hash=:hash,signed_at=:signed_at,viewed_at=COALESCE(viewed_at,NOW()) WHERE id=:id AND status IN('sent','viewed')");
        $s->execute(['name'=>$name,'email'=>$email,'document'=>$document,'ip'=>mb_substr($ip,0,64),'agent'=>mb_substr($userAgent,0,500),'hash'=>$hash,'signed_at'=>$signedAt,'id'=>$contract['id']]);if($s->rowCount()===0)throw new RuntimeException('O contrato já foi concluído ou cancelado.');
        $this->db->prepare("UPDATE franchise_applications SET contract_status='signed' WHERE id=:id")->execute(['id'=>$contract['franchise_application_id']]);return(int)$contract['id'];
    }

    public function beginBilling(int $id,string $billingType,string $dueDate): array
    {
        $contract=$this->find($id);if($contract===null)throw new RuntimeException('Contrato não encontrado.');
        if($contract['status']!=='signed')throw new RuntimeException('A cobrança só pode ser emitida depois da assinatura do contrato.');
        if((int)$contract['billing_required']!==1||(float)$contract['billing_amount']<=0)throw new RuntimeException('Este contrato não possui cobrança prevista.');
        if(!in_array($billingType,['PIX','BOLETO','CREDIT_CARD'],true))throw new RuntimeException('Selecione uma forma de pagamento válida.');
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$dueDate);if($date===false||$date->format('Y-m-d')!==$dueDate||$date<new DateTimeImmutable('today'))throw new RuntimeException('Informe um vencimento válido, a partir de hoje.');
        $s=$this->db->prepare("UPDATE franchise_contracts SET billing_issue_state='issuing',billing_type=:type,billing_due_date=:due,billing_error=NULL WHERE id=:id AND asaas_payment_id IS NULL AND billing_issue_state IN('not_issued','failed')");
        $s->execute(['type'=>$billingType,'due'=>$dueDate,'id'=>$id]);if($s->rowCount()===0)throw new RuntimeException('A cobrança já foi emitida ou está sendo processada.');
        return$contract;
    }

    public function storeAsaasCustomer(int$id,string$customerId):void
    {
        $this->db->prepare('UPDATE franchise_contracts SET asaas_customer_id=:customer WHERE id=:id')->execute(['customer'=>$customerId,'id'=>$id]);
    }

    public function completeBilling(int$id,array$payment):void
    {
        $paymentId=(string)($payment['id']??'');if(!preg_match('/^pay_[A-Za-z0-9]+$/',$paymentId))throw new RuntimeException('O Asaas não retornou uma cobrança válida.');
        $status=(string)($payment['status']??'PENDING');$invoice=self::nullable($payment['invoiceUrl']??$payment['bankSlipUrl']??'');$paid=in_array($status,['RECEIVED','CONFIRMED','RECEIVED_IN_CASH'],true)?date('Y-m-d H:i:s'):null;
        $s=$this->db->prepare("UPDATE franchise_contracts SET asaas_payment_id=:payment,asaas_payment_status=:status,asaas_invoice_url=:invoice,billing_issue_state=:state,billing_issued_at=NOW(),billing_paid_at=:paid,billing_last_synced_at=NOW(),billing_error=NULL WHERE id=:id AND billing_issue_state='issuing'");
        $s->execute(['payment'=>$paymentId,'status'=>$status,'invoice'=>$invoice,'state'=>$paid===null?'issued':'paid','paid'=>$paid,'id'=>$id]);if($s->rowCount()===0)throw new RuntimeException('Não foi possível registrar a cobrança emitida.');
    }

    public function failBilling(int$id,string$message):void
    {
        $this->db->prepare("UPDATE franchise_contracts SET billing_issue_state='failed',billing_error=:error WHERE id=:id AND asaas_payment_id IS NULL")->execute(['error'=>mb_substr($message,0,500),'id'=>$id]);
    }

    public function syncBilling(int$id,array$payment):void
    {
        $status=(string)($payment['status']??'');if($status==='')throw new RuntimeException('O Asaas não retornou a situação da cobrança.');
        $paid=in_array($status,['RECEIVED','CONFIRMED','RECEIVED_IN_CASH'],true);$invoice=self::nullable($payment['invoiceUrl']??$payment['bankSlipUrl']??'');
        $this->db->prepare("UPDATE franchise_contracts SET asaas_payment_status=:status,asaas_invoice_url=COALESCE(:invoice,asaas_invoice_url),billing_issue_state=:state,billing_paid_at=CASE WHEN :paid=1 THEN COALESCE(billing_paid_at,NOW()) ELSE billing_paid_at END,billing_last_synced_at=NOW(),billing_error=NULL WHERE id=:id")->execute(['status'=>$status,'invoice'=>$invoice,'state'=>$paid?'paid':'issued','paid'=>$paid?1:0,'id'=>$id]);
    }

    public function storeRecurringLink(int $id,array $link): void
    {
        $linkId=trim((string)($link['id']??''));$url=trim((string)($link['url']??$link['shortUrl']??''));
        if($linkId===''||$url==='')throw new RuntimeException('O Asaas não retornou um link recorrente válido.');
        $s=$this->db->prepare("UPDATE franchise_contracts SET asaas_payment_link_id=:link_id,asaas_payment_link_url=:url,recurring_link_issued_at=NOW(),billing_issue_state='issued',billing_issued_at=NOW(),billing_error=NULL WHERE id=:id AND asaas_payment_link_id IS NULL");
        $s->execute(['link_id'=>$linkId,'url'=>$url,'id'=>$id]);if($s->rowCount()===0)throw new RuntimeException('Este contrato já possui link recorrente.');
    }

    public function activateCommercialFlow(int $id,?int $userId): void
    {
        $contract=$this->find($id);if($contract===null)throw new RuntimeException('Contrato não encontrado.');
        if($contract['status']!=='signed')throw new RuntimeException('O contrato precisa estar assinado antes de ativar a regra comercial.');
        $percentage=(float)($contract['sales_fee_percentage']??0);$wallet=self::nullable($contract['asaas_wallet_id']??null);
        if($percentage>0&&($wallet===null||($contract['asaas_wallet_status']??'')!=='validated'||(int)($contract['split_enabled']??0)!==1))throw new RuntimeException('Valide a carteira Asaas da franquia e habilite o split antes de ativar a regra comercial.');
        $s=$this->db->prepare("UPDATE franchise_contracts SET commercial_flow_status='active',commercial_flow_activated_at=NOW(),split_wallet_snapshot=:wallet WHERE id=:id AND commercial_flow_status<>'active'");
        $s->execute(['wallet'=>$wallet,'id'=>$id]);if($s->rowCount()===0)throw new RuntimeException('A regra comercial deste contrato já está ativa.');
        $this->recordBillingEvent($id,'commercial_flow_activated','Regra comercial ativada para novas vendas da franquia.',$userId);
    }

    public function billingDashboard(array$filters=[]): array
    {
        $where=[];$params=[];$organization=(int)($filters['organization_id']??0);$status=(string)($filters['status']??'');$from=(string)($filters['from']??'');$to=(string)($filters['to']??'');
        if($organization>0){$where[]='c.organization_id=:organization';$params['organization']=$organization;}
        if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)===1){$where[]='COALESCE(c.billing_due_date,DATE(c.updated_at))>=:date_from';$params['date_from']=$from;}
        if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)===1){$where[]='COALESCE(c.billing_due_date,DATE(c.updated_at))<=:date_to';$params['date_to']=$to;}
        $statusSql=['paid'=>"c.billing_issue_state='paid'",'overdue'=>"c.status='signed' AND c.billing_required=1 AND c.billing_issue_state IN('not_issued','issued') AND c.billing_due_date<CURDATE()",'failed'=>"(c.billing_issue_state='failed' OR c.commercial_flow_status='blocked')",'pending'=>"c.status='signed' AND (c.commercial_flow_status<>'active' OR c.billing_issue_state='not_issued')",'active'=>"c.commercial_flow_status='active'"];
        if(isset($statusSql[$status]))$where[]=$statusSql[$status];$clause=$where===[]?'':' WHERE '.implode(' AND ',$where);
        $summaryStatement=$this->db->prepare("SELECT COUNT(*) total_contracts,SUM(c.status='signed') signed_contracts,SUM(c.billing_issue_state='paid') paid,SUM(c.billing_issue_state='failed') failures,SUM(c.commercial_flow_status='active') active_flows,SUM(c.status='signed' AND c.commercial_flow_status<>'active') pending_activation,SUM(c.status='signed' AND c.billing_required=1 AND c.billing_issue_state IN('not_issued','issued') AND c.billing_due_date<CURDATE()) overdue,SUM(c.sales_fee_percentage>0 AND (o.asaas_wallet_id IS NULL OR o.asaas_wallet_status<>'validated' OR o.split_enabled<>1)) split_pending,SUM(CASE WHEN c.billing_issue_state='paid' THEN COALESCE(c.billing_amount,c.monthly_fixed_amount,0) ELSE 0 END) paid_amount,SUM(CASE WHEN c.billing_required=1 AND c.billing_issue_state IN('not_issued','issuing','issued') THEN COALESCE(c.billing_amount,c.monthly_fixed_amount,0) ELSE 0 END) open_amount,SUM(CASE WHEN c.billing_required=1 AND c.billing_issue_state IN('not_issued','issued') AND c.billing_due_date<CURDATE() THEN COALESCE(c.billing_amount,c.monthly_fixed_amount,0) ELSE 0 END) overdue_amount FROM franchise_contracts c LEFT JOIN organizations o ON o.id=c.organization_id".$clause);$summaryStatement->execute($params);$summary=$summaryStatement->fetch();
        $splitWhere=[];$splitParams=[];if($organization>0){$splitWhere[]='s.organization_id=:split_organization';$splitParams['split_organization']=$organization;}if(isset($params['date_from'])){$splitWhere[]='DATE(s.created_at)>=:split_from';$splitParams['split_from']=$params['date_from'];}if(isset($params['date_to'])){$splitWhere[]='DATE(s.created_at)<=:split_to';$splitParams['split_to']=$params['date_to'];}$splitClause=$splitWhere===[]?'':' WHERE '.implode(' AND ',$splitWhere);
        $splitStatement=$this->db->prepare("SELECT SUM(s.status='submitted') split_submitted,SUM(s.status='failed') split_failures,SUM(CASE WHEN s.status='submitted' THEN s.gross_value*s.central_percentage/100 ELSE 0 END) central_commission_amount,SUM(CASE WHEN s.status='submitted' THEN s.gross_value*s.franchise_percentage/100 ELSE 0 END) franchise_transfer_amount FROM franchise_split_attempts s".$splitClause);$splitStatement->execute($splitParams);$split=$splitStatement->fetch();
        $contractsStatement=$this->db->prepare("SELECT c.*,a.display_name franchise_name,o.asaas_wallet_status,o.asaas_wallet_id,o.split_enabled FROM franchise_contracts c INNER JOIN franchise_applications a ON a.id=c.franchise_application_id LEFT JOIN organizations o ON o.id=c.organization_id".$clause." ORDER BY FIELD(c.commercial_flow_status,'blocked','pending','active','inactive'),c.updated_at DESC LIMIT 100");$contractsStatement->execute($params);$contracts=$contractsStatement->fetchAll();
        $splitsStatement=$this->db->prepare("SELECT s.*,o.display_name franchise_name,c.contract_number FROM franchise_split_attempts s INNER JOIN organizations o ON o.id=s.organization_id INNER JOIN franchise_contracts c ON c.id=s.contract_id".$splitClause." ORDER BY s.created_at DESC,s.id DESC LIMIT 50");$splitsStatement->execute($splitParams);
        $organizations=$this->db->query("SELECT id,display_name FROM organizations WHERE status='active' ORDER BY display_name")->fetchAll();
        return['summary'=>array_merge(is_array($summary)?$summary:[],is_array($split)?$split:[]),'contracts'=>$contracts,'splits'=>$splitsStatement->fetchAll(),'organizations'=>$organizations];
    }

    public function billingAlerts():array
    {
        $row=$this->db->query("SELECT SUM(c.status='signed' AND c.billing_required=1 AND c.billing_issue_state IN('not_issued','issued') AND c.billing_due_date<CURDATE()) overdue,SUM(c.billing_issue_state='failed') billing_failures,SUM(c.status='signed' AND c.commercial_flow_status<>'active') pending_activation,SUM(c.sales_fee_percentage>0 AND (o.asaas_wallet_id IS NULL OR o.asaas_wallet_status<>'validated' OR o.split_enabled<>1)) split_pending,(SELECT COUNT(*) FROM franchise_split_attempts s WHERE s.status='failed') split_failures FROM franchise_contracts c LEFT JOIN organizations o ON o.id=c.organization_id")->fetch()?:[];
        return['overdue'=>(int)($row['overdue']??0),'billing_failures'=>(int)($row['billing_failures']??0),'pending_activation'=>(int)($row['pending_activation']??0),'split_pending'=>(int)($row['split_pending']??0),'split_failures'=>(int)($row['split_failures']??0)];
    }

    public function billingEvents(int $contractId): array
    {
        $s=$this->db->prepare('SELECT e.*,u.name user_name FROM franchise_billing_events e LEFT JOIN platform_users u ON u.id=e.platform_user_id WHERE e.contract_id=:id ORDER BY e.created_at DESC,e.id DESC');$s->execute(['id'=>$contractId]);return$s->fetchAll();
    }

    public function recordBillingEvent(int $contractId,string $type,string $description,?int $userId=null): void
    {
        $s=$this->db->prepare('INSERT INTO franchise_billing_events(contract_id,event_type,description,platform_user_id) VALUES(:contract,:type,:description,:user)');$s->execute(['contract'=>$contractId,'type'=>mb_substr($type,0,50),'description'=>mb_substr($description,0,500),'user'=>$userId]);
    }

    public function prepareSplit(int $organizationId,float $grossValue,string $externalReference): ?array
    {
        $s=$this->db->prepare("SELECT c.*,o.asaas_wallet_id,o.asaas_wallet_status,o.split_enabled FROM franchise_contracts c INNER JOIN organizations o ON o.id=c.organization_id WHERE c.organization_id=:organization AND c.status='signed' AND c.commercial_flow_status='active' AND (c.valid_from IS NULL OR c.valid_from<=CURDATE()) AND (c.valid_until IS NULL OR c.valid_until>=CURDATE()) ORDER BY c.contract_number DESC,c.id DESC LIMIT 1");$s->execute(['organization'=>$organizationId]);$contract=$s->fetch();
        if(!is_array($contract))return null;$central=round((float)$contract['sales_fee_percentage'],4);if($central<=0)return null;
        $wallet=self::nullable($contract['split_wallet_snapshot']??$contract['asaas_wallet_id']??null);if($wallet===null||$contract['asaas_wallet_status']!=='validated'||(int)$contract['split_enabled']!==1)throw new RuntimeException('O split desta franquia está bloqueado: valide a wallet no ADM Central.');
        $franchise=round(100-$central,4);if($franchise<=0)throw new RuntimeException('O percentual contratado não deixa saldo válido para a franquia.');
        $insert=$this->db->prepare("INSERT INTO franchise_split_attempts(organization_id,contract_id,external_reference,gross_value,central_percentage,franchise_percentage,wallet_id) VALUES(:organization,:contract,:reference,:gross,:central,:franchise,:wallet)");$insert->execute(['organization'=>$organizationId,'contract'=>$contract['id'],'reference'=>mb_substr($externalReference,0,120),'gross'=>round($grossValue,2),'central'=>$central,'franchise'=>$franchise,'wallet'=>$wallet]);
        return['attempt_id'=>(int)$this->db->lastInsertId(),'contract_id'=>(int)$contract['id'],'split'=>[['walletId'=>$wallet,'percentualValue'=>$franchise,'externalReference'=>'mundo-inter:contract:'.$contract['id']]]];
    }

    public function completeSplit(int $attemptId,array $payment): void
    {
        $paymentId=(string)($payment['id']??'');$s=$this->db->prepare("UPDATE franchise_split_attempts SET status='submitted',asaas_payment_id=:payment,error_message=NULL WHERE id=:id AND status='prepared'");$s->execute(['payment'=>$paymentId!==''?$paymentId:null,'id'=>$attemptId]);
    }

    public function failSplit(int $attemptId,string $message): void
    {
        $s=$this->db->prepare("UPDATE franchise_split_attempts SET status='failed',error_message=:error WHERE id=:id AND status='prepared'");$s->execute(['error'=>mb_substr($message,0,500),'id'=>$attemptId]);
    }

    private function application(int$id): array{$s=$this->db->prepare('SELECT * FROM franchise_applications WHERE id=:id');$s->execute(['id'=>$id]);$row=$s->fetch();if(!is_array($row))throw new RuntimeException('Solicitação não encontrada.');return$row;}
    private function render(string$body,array$a,string$conditions,string$term):string{$address=implode(', ',array_filter([$a['address'],$a['address_number'],$a['address_complement'],$a['neighborhood'],$a['city'],$a['state'],$a['postal_code']]));$escape=static fn(mixed$value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$map=['{{razao_social}}'=>$escape($a['legal_name']),'{{cnpj}}'=>$escape($a['cnpj']),'{{endereco_completo}}'=>$escape($address?:'endereço a confirmar'),'{{gestor_nome}}'=>$escape($a['manager_name']),'{{nome_franquia}}'=>$escape($a['display_name']),'{{condicoes_comerciais}}'=>nl2br($escape($conditions)),'{{vigencia}}'=>$escape($term),'{{cidade}}'=>$escape($a['city']?:'Tijucas/SC'),'{{data_extenso}}'=>$escape(date('d/m/Y'))];return strtr(ContractContent::toHtml($body),$map);}
    private static function money(mixed$value):?float{if($value===null||trim((string)$value)==='')return null;$normalized=trim((string)$value);if(str_contains($normalized,','))$normalized=str_replace(',','.',str_replace('.','',$normalized));return is_numeric($normalized)?round((float)$normalized,2):null;}
    private static function percentage(mixed$value):?float{$number=self::money($value);return$number===null?null:round($number,4);}
    private static function date(mixed$value):?string{$value=trim((string)$value);if($value==='')return null;$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);if($date===false||$date->format('Y-m-d')!==$value)throw new RuntimeException('Informe datas de vigência válidas.');return$value;}
    private static function nullable(mixed$value):?string{$v=trim((string)$value);return$v===''?null:$v;}
}
