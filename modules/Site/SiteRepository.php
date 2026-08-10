<?php

declare(strict_types=1);

namespace Interferencia\Modules\Site;

use PDO;
use RuntimeException;
use Throwable;

final readonly class SiteRepository
{
    public function __construct(private PDO $database) {}

    /** @return array<string,mixed> */
    public function settings(int $organizationId): array
    {
        $statement=$this->database->prepare('SELECT * FROM organization_sites WHERE organization_id=:organization LIMIT 1');
        $statement->execute(['organization'=>$organizationId]);
        $row=$statement->fetch();
        return is_array($row)?$row:$this->defaults($organizationId);
    }

    /** @param array<string,mixed> $data */
    public function saveGovernance(int $organizationId,array $data):void
    {
        $this->assertOrganization($organizationId);
        $template=(string)($data['template_key']??'modern');
        if(!in_array($template,['modern','classic','minimal'],true))throw new RuntimeException('Selecione um modelo-base válido.');
        $this->ensure($organizationId);
        $fulfillment=(string)($data['checkout_fulfillment_mode']??'manual_review');
        if(!in_array($fulfillment,['manual_review','automatic'],true))throw new RuntimeException('Selecione uma regra válida para a liberação das compras.');
        $statement=$this->database->prepare('UPDATE organization_sites SET is_enabled=:enabled,template_key=:template,allow_catalog=:catalog,allow_store=:store,checkout_fulfillment_mode=:fulfillment,allow_custom_pages=:pages,max_banners=:banners,max_pages=:max_pages,max_featured_courses=:courses WHERE organization_id=:organization');
        $statement->execute([
            'enabled'=>(int)(($data['is_enabled']??false)===true),'template'=>$template,
            'catalog'=>(int)(($data['allow_catalog']??false)===true),'store'=>(int)(($data['allow_store']??false)===true),
            'fulfillment'=>$fulfillment,
            'pages'=>(int)(($data['allow_custom_pages']??false)===true),
            'banners'=>$this->limit($data['max_banners']??3,1,10,'banners'),
            'max_pages'=>$this->limit($data['max_pages']??5,1,30,'páginas'),
            'courses'=>$this->limit($data['max_featured_courses']??6,1,24,'cursos em destaque'),
            'organization'=>$organizationId,
        ]);
    }

    /** @param array<string,mixed> $data @param list<int> $productIds */
    public function saveContent(int $organizationId,array $data,array $productIds):void
    {
        $this->assertOrganization($organizationId);$this->ensure($organizationId);$current=$this->settings($organizationId);
        if((int)$current['is_enabled']!==1)throw new RuntimeException('O ADM Central ainda não liberou o Site Institucional para esta franquia.');
        $mode=(string)($data['selected_mode']??'catalog');
        if(!in_array($mode,['catalog','store'],true))throw new RuntimeException('Selecione o formato do site.');
        if($mode==='catalog'&&(int)$current['allow_catalog']!==1)throw new RuntimeException('O formato catálogo não está liberado pelo ADM Central.');
        if($mode==='store'&&(int)$current['allow_store']!==1)throw new RuntimeException('O formato loja não está liberado pelo ADM Central.');
        $status=(string)($data['publication_status']??'draft');
        if(!in_array($status,['draft','published','maintenance'],true))throw new RuntimeException('Situação de publicação inválida.');
        $email=strtolower(trim((string)($data['contact_email']??'')));
        if($email!==''&&filter_var($email,FILTER_VALIDATE_EMAIL)===false)throw new RuntimeException('Informe um e-mail de contato válido.');
        $scholarshipMode=(string)($data['scholarship_display_mode']??'floating');
        if(!in_array($scholarshipMode,['floating','popup','both'],true))throw new RuntimeException('Selecione uma forma de exibição válida para o formulário de bolsas.');
        $productIds=array_values(array_unique(array_filter(array_map('intval',$productIds),static fn(int$id):bool=>$id>0)));
        if(count($productIds)>(int)$current['max_featured_courses'])throw new RuntimeException('Selecione no máximo '.(int)$current['max_featured_courses'].' cursos em destaque.');
        $allowed=array_map('intval',array_column($this->availableProducts($organizationId),'id'));
        if(array_diff($productIds,$allowed)!==[])throw new RuntimeException('Um dos cursos selecionados não pertence ao catálogo disponível da franquia.');
        $values=[
            'mode'=>$mode,'status'=>$status,'site_title'=>$this->text($data['site_title']??'',160,'nome do site',true),
            'hero_title'=>$this->text($data['hero_title']??'',190,'título principal',true),'hero_text'=>$this->text($data['hero_text']??'',700,'texto principal'),
            'about_title'=>$this->text($data['about_title']??'',160,'título institucional'),'about_text'=>$this->text($data['about_text']??'',5000,'texto institucional'),
            'email'=>$email!==''?$email:null,'phone'=>$this->text($data['contact_phone']??'',30,'telefone'),'whatsapp'=>$this->text($data['whatsapp']??'',30,'WhatsApp'),
            'instagram'=>$this->url($data['instagram_url']??'','Instagram'),'facebook'=>$this->url($data['facebook_url']??'','Facebook'),
            'youtube'=>$this->url($data['youtube_url']??'','YouTube'),'linkedin'=>$this->url($data['linkedin_url']??'','LinkedIn'),'tiktok'=>$this->url($data['tiktok_url']??'','TikTok'),
            'classroom_url'=>$this->url($data['classroom_url']??'','Sala de Aula'),'classroom_label'=>$this->text($data['classroom_label']??'Sala de Aula',80,'texto da Sala de Aula',true),'webmail_url'=>$this->url($data['webmail_url']??'','Webmail'),
            'social_bar_enabled'=>(int)$this->checked($data['social_bar_enabled']??false),'site_search_enabled'=>(int)$this->checked($data['site_search_enabled']??false),
            'footer_text'=>$this->text($data['footer_text']??'',500,'texto do rodapé'),'footer_show_legal_data'=>(int)$this->checked($data['footer_show_legal_data']??false),
            'whatsapp_enabled'=>(int)$this->checked($data['whatsapp_button_enabled']??false),'whatsapp_label'=>$this->text($data['whatsapp_button_label']??'',80,'texto do botão do WhatsApp'),'whatsapp_message'=>$this->text($data['whatsapp_button_message']??'',500,'mensagem do WhatsApp'),
            'scholarship_enabled'=>(int)$this->checked($data['scholarship_form_enabled']??false),'scholarship_mode'=>$scholarshipMode,
            'scholarship_delay'=>$this->limit($data['scholarship_popup_delay_seconds']??15,5,300,'segundos para abrir o formulário de bolsas'),
            'scholarship_repeat'=>$this->limit($data['scholarship_popup_repeat_hours']??24,1,720,'horas para repetir o formulário de bolsas'),
            'scholarship_title'=>$this->text($data['scholarship_title']??'',160,'título do formulário de bolsas',true),'scholarship_subtitle'=>$this->text($data['scholarship_subtitle']??'',250,'subtítulo do formulário de bolsas'),'scholarship_button'=>$this->text($data['scholarship_button_label']??'',80,'texto do botão de bolsas',true),
            'seo_title'=>$this->text($data['seo_title']??'',190,'título de busca'),'seo_description'=>$this->text($data['seo_description']??'',320,'descrição de busca'),
            'status_value'=>$status,'organization'=>$organizationId,
        ];
        $this->database->beginTransaction();
        try{
            $statement=$this->database->prepare("UPDATE organization_sites SET selected_mode=:mode,publication_status=:status,site_title=:site_title,hero_title=:hero_title,hero_text=:hero_text,about_title=:about_title,about_text=:about_text,contact_email=:email,contact_phone=:phone,whatsapp=:whatsapp,instagram_url=:instagram,facebook_url=:facebook,youtube_url=:youtube,linkedin_url=:linkedin,tiktok_url=:tiktok,classroom_url=:classroom_url,classroom_label=:classroom_label,webmail_url=:webmail_url,social_bar_enabled=:social_bar_enabled,site_search_enabled=:site_search_enabled,footer_text=:footer_text,footer_show_legal_data=:footer_show_legal_data,whatsapp_button_enabled=:whatsapp_enabled,whatsapp_button_label=:whatsapp_label,whatsapp_button_message=:whatsapp_message,scholarship_form_enabled=:scholarship_enabled,scholarship_display_mode=:scholarship_mode,scholarship_popup_delay_seconds=:scholarship_delay,scholarship_popup_repeat_hours=:scholarship_repeat,scholarship_title=:scholarship_title,scholarship_subtitle=:scholarship_subtitle,scholarship_button_label=:scholarship_button,seo_title=:seo_title,seo_description=:seo_description,published_at=CASE WHEN :status_value='published' THEN COALESCE(published_at,NOW()) ELSE published_at END WHERE organization_id=:organization");
            $statement->execute($values);
            $this->database->prepare('DELETE FROM organization_site_products WHERE organization_id=:organization')->execute(['organization'=>$organizationId]);
            $insert=$this->database->prepare('INSERT INTO organization_site_products(organization_id,finance_product_id,sort_order) VALUES(:organization,:product,:sort_order)');
            foreach($productIds as$order=>$productId)$insert->execute(['organization'=>$organizationId,'product'=>$productId,'sort_order'=>$order]);
            $this->database->commit();
        }catch(Throwable$exception){if($this->database->inTransaction())$this->database->rollBack();throw$exception;}
    }

    /** @return list<array<string,mixed>> */
    public function availableProducts(int $organizationId):array
    {
        $statement=$this->database->prepare('SELECT DISTINCT p.id,p.unit_id,p.name,p.description,p.value,p.max_installments,p.billing_types,p.minutes_to_expire FROM organization_finance_products scope INNER JOIN finance_products p ON p.id=scope.finance_product_id LEFT JOIN units u ON u.id=p.unit_id WHERE scope.organization_id=:organization AND scope.is_visible=1 AND p.is_active=1 AND p.value>=5 AND (p.unit_id IS NULL OR u.organization_id=:unit_organization) ORDER BY p.name,p.id');
        $statement->execute(['organization'=>$organizationId,'unit_organization'=>$organizationId]);return$statement->fetchAll();
    }

    /** @return list<int> */
    public function selectedProductIds(int $organizationId):array
    {
        $statement=$this->database->prepare('SELECT finance_product_id FROM organization_site_products WHERE organization_id=:organization ORDER BY sort_order,finance_product_id');
        $statement->execute(['organization'=>$organizationId]);return array_map('intval',$statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<array<string,mixed>> */
    public function banners(int $organizationId,bool $onlyActive=false):array
    {
        $sql='SELECT * FROM organization_site_banners WHERE organization_id=:organization'.($onlyActive?' AND is_active=1':'').' ORDER BY sort_order,id';
        $statement=$this->database->prepare($sql);$statement->execute(['organization'=>$organizationId]);return$statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function banner(int $organizationId,int $id,bool $onlyActive=false):?array
    {
        $sql='SELECT * FROM organization_site_banners WHERE id=:id AND organization_id=:organization'.($onlyActive?' AND is_active=1':'').' LIMIT 1';
        $statement=$this->database->prepare($sql);$statement->execute(['id'=>$id,'organization'=>$organizationId]);$row=$statement->fetch();return is_array($row)?$row:null;
    }

    /** @param array<string,mixed> $data */
    public function saveBanner(int $organizationId,?int $id,array $data,?array $image=null):int
    {
        $settings=$this->editableSettings($organizationId);
        $existing=$id===null?null:$this->banner($organizationId,$id);
        if($id!==null&&$existing===null)throw new RuntimeException('Banner não encontrado.');
        if($id===null&&count($this->banners($organizationId))>=(int)$settings['max_banners'])throw new RuntimeException('O limite de banners definido pelo ADM Central foi atingido.');
        $path=$image['path']??$existing['image_path']??null;$mime=$image['mime']??$existing['image_mime']??null;$name=$image['name']??$existing['image_name']??null;
        if(!is_string($path)||$path==='')throw new RuntimeException('Selecione uma imagem para o banner.');
        $values=['organization'=>$organizationId,'title'=>$this->text($data['title']??'',190,'título do banner',true),'subtitle'=>$this->text($data['subtitle']??'',500,'texto do banner'),'cta_label'=>$this->text($data['cta_label']??'',80,'texto do botão'),'cta_url'=>$this->link($data['cta_url']??''),'path'=>$path,'mime'=>(string)$mime,'name'=>(string)$name,'active'=>(int)(($data['is_active']??false)===true),'sort_order'=>$this->limit($data['sort_order']??0,0,999,'na ordem')];
        if($id===null){$statement=$this->database->prepare('INSERT INTO organization_site_banners(organization_id,title,subtitle,cta_label,cta_url,image_path,image_mime,image_name,is_active,sort_order) VALUES(:organization,:title,:subtitle,:cta_label,:cta_url,:path,:mime,:name,:active,:sort_order)');$statement->execute($values);return(int)$this->database->lastInsertId();}
        $values['id']=$id;$statement=$this->database->prepare('UPDATE organization_site_banners SET title=:title,subtitle=:subtitle,cta_label=:cta_label,cta_url=:cta_url,image_path=:path,image_mime=:mime,image_name=:name,is_active=:active,sort_order=:sort_order WHERE id=:id AND organization_id=:organization');$statement->execute($values);return$id;
    }

    public function deleteBanner(int $organizationId,int $id):void
    {
        $statement=$this->database->prepare('DELETE FROM organization_site_banners WHERE id=:id AND organization_id=:organization');$statement->execute(['id'=>$id,'organization'=>$organizationId]);
        if($statement->rowCount()!==1)throw new RuntimeException('Banner não encontrado.');
    }

    /** @return list<array<string,mixed>> */
    public function pages(int $organizationId,bool $publishedOnly=false):array
    {
        $sql='SELECT * FROM organization_site_pages WHERE organization_id=:organization'.($publishedOnly?" AND publication_status='published'":'').' ORDER BY sort_order,title,id';
        $statement=$this->database->prepare($sql);$statement->execute(['organization'=>$organizationId]);return$statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function page(int $organizationId,int $id):?array
    {
        $statement=$this->database->prepare('SELECT * FROM organization_site_pages WHERE id=:id AND organization_id=:organization LIMIT 1');$statement->execute(['id'=>$id,'organization'=>$organizationId]);$row=$statement->fetch();return is_array($row)?$row:null;
    }

    /** @return array<string,mixed>|null */
    public function publicPage(int $organizationId,string $slug,bool $preview=false):?array
    {
        $sql="SELECT * FROM organization_site_pages WHERE organization_id=:organization AND slug=:slug".($preview?'':" AND publication_status='published'").' LIMIT 1';
        $statement=$this->database->prepare($sql);$statement->execute(['organization'=>$organizationId,'slug'=>$this->slug($slug)]);$row=$statement->fetch();return is_array($row)?$row:null;
    }

    /** @param array<string,mixed> $data */
    public function savePage(int $organizationId,?int $id,array $data):int
    {
        $settings=$this->editableSettings($organizationId);
        if((int)$settings['allow_custom_pages']!==1)throw new RuntimeException('Páginas personalizadas ainda não foram liberadas pelo ADM Central.');
        $existing=$id===null?null:$this->page($organizationId,$id);
        if($id!==null&&$existing===null)throw new RuntimeException('Página não encontrada.');
        if($id===null&&count($this->pages($organizationId))>=(int)$settings['max_pages'])throw new RuntimeException('O limite de páginas definido pelo ADM Central foi atingido.');
        $status=(string)($data['publication_status']??'draft');if(!in_array($status,['draft','published'],true))throw new RuntimeException('Situação da página inválida.');
        $slug=$this->slug((string)($data['slug']??$data['title']??''));if(in_array($slug,['admin','checkout','banner','site'],true))throw new RuntimeException('Escolha outro endereço para a página.');
        $values=['organization'=>$organizationId,'title'=>$this->text($data['title']??'',190,'título da página',true),'slug'=>$slug,'summary'=>$this->text($data['summary']??'',500,'resumo'),'content'=>$this->text($data['content']??'',50000,'conteúdo',true),'status'=>$status,'menu'=>(int)(($data['show_in_menu']??false)===true),'sort_order'=>$this->limit($data['sort_order']??0,0,999,'na ordem')];
        try{if($id===null){$statement=$this->database->prepare('INSERT INTO organization_site_pages(organization_id,title,slug,summary,content,publication_status,show_in_menu,sort_order) VALUES(:organization,:title,:slug,:summary,:content,:status,:menu,:sort_order)');$statement->execute($values);return(int)$this->database->lastInsertId();}$values['id']=$id;$statement=$this->database->prepare('UPDATE organization_site_pages SET title=:title,slug=:slug,summary=:summary,content=:content,publication_status=:status,show_in_menu=:menu,sort_order=:sort_order WHERE id=:id AND organization_id=:organization');$statement->execute($values);return$id;}catch(\PDOException$e){if((string)$e->getCode()==='23000')throw new RuntimeException('Já existe uma página com esse endereço.');throw$e;}
    }

    public function deletePage(int $organizationId,int $id):void
    {
        $statement=$this->database->prepare('DELETE FROM organization_site_pages WHERE id=:id AND organization_id=:organization');$statement->execute(['id'=>$id,'organization'=>$organizationId]);if($statement->rowCount()!==1)throw new RuntimeException('Página não encontrada.');
    }

    /** @return array<string,mixed>|null */
    public function publicProduct(int $organizationId,int $productId):?array
    {
        $statement=$this->database->prepare("SELECT p.* FROM organization_site_products sp INNER JOIN organization_finance_products scope ON scope.organization_id=sp.organization_id AND scope.finance_product_id=sp.finance_product_id AND scope.is_visible=1 INNER JOIN finance_products p ON p.id=sp.finance_product_id INNER JOIN organization_sites s ON s.organization_id=sp.organization_id LEFT JOIN units u ON u.id=p.unit_id WHERE sp.organization_id=:organization AND p.id=:product AND p.is_active=1 AND p.value>=5 AND s.is_enabled=1 AND s.publication_status='published' AND s.selected_mode='store' AND s.allow_store=1 AND (p.unit_id IS NULL OR u.organization_id=:organization_unit) LIMIT 1");
        $statement->execute(['organization'=>$organizationId,'product'=>$productId,'organization_unit'=>$organizationId]);$row=$statement->fetch();return is_array($row)?$row:null;
    }

    /** @return array<string,mixed>|null */
    public function publicCatalogProduct(int $organizationId,int $productId):?array
    {
        $statement=$this->database->prepare("SELECT p.*,s.selected_mode FROM organization_site_products sp INNER JOIN organization_finance_products scope ON scope.organization_id=sp.organization_id AND scope.finance_product_id=sp.finance_product_id AND scope.is_visible=1 INNER JOIN finance_products p ON p.id=sp.finance_product_id INNER JOIN organization_sites s ON s.organization_id=sp.organization_id LEFT JOIN units u ON u.id=p.unit_id WHERE sp.organization_id=:organization AND p.id=:product AND p.is_active=1 AND s.is_enabled=1 AND s.publication_status='published' AND ((s.selected_mode='store' AND s.allow_store=1 AND p.value>=5) OR (s.selected_mode='catalog' AND s.allow_catalog=1)) AND (p.unit_id IS NULL OR u.organization_id=:organization_unit) LIMIT 1");
        $statement->execute(['organization'=>$organizationId,'product'=>$productId,'organization_unit'=>$organizationId]);$row=$statement->fetch();return is_array($row)?$row:null;
    }

    /** @return list<array<string,mixed>> */
    public function publicUnits(int $organizationId,?int $productUnitId):array
    {
        $sql='SELECT id,code,name,city FROM units WHERE organization_id=:organization AND is_active=1'.($productUnitId!==null?' AND id=:unit':'').' ORDER BY name';
        $statement=$this->database->prepare($sql);$params=['organization'=>$organizationId];if($productUnitId!==null)$params['unit']=$productUnitId;$statement->execute($params);return$statement->fetchAll();
    }

    /** @return array{id:int,external_reference:string} */
    public function createOrderDraft(int $organizationId,int $unitId,int $contactId,int $productId):array
    {
        $statement=$this->database->prepare('INSERT INTO organization_site_orders(organization_id,unit_id,crm_contact_id,finance_product_id,external_reference) SELECT :organization,:unit,:contact,:product,:temporary FROM units u INNER JOIN finance_products p ON p.id=:product_check WHERE u.id=:unit_check AND u.organization_id=:organization_check AND u.is_active=1 AND p.id=:product_scope');
        $temporary='pending-'.bin2hex(random_bytes(12));$statement->execute(['organization'=>$organizationId,'unit'=>$unitId,'contact'=>$contactId,'product'=>$productId,'temporary'=>$temporary,'product_check'=>$productId,'unit_check'=>$unitId,'organization_check'=>$organizationId,'product_scope'=>$productId]);
        $id=(int)$this->database->lastInsertId();if($id<1)throw new RuntimeException('Não foi possível iniciar a solicitação de compra.');
        $external=sprintf('mundo-inter:site-order:%d:%d:unit:%d',$organizationId,$id,$unitId);$this->database->prepare('UPDATE organization_site_orders SET external_reference=:external WHERE id=:id')->execute(['external'=>$external,'id'=>$id]);return['id'=>$id,'external_reference'=>$external];
    }

    /** @param array<string,mixed> $checkout */
    public function completeOrder(int $id,array $checkout):void
    {
        $statement=$this->database->prepare('UPDATE organization_site_orders SET asaas_checkout_id=:asaas,status=:status,link=:link,error_message=NULL WHERE id=:id');
        $statement->execute(['asaas'=>(string)($checkout['id']??''),'status'=>(string)($checkout['status']??'ACTIVE'),'link'=>(string)($checkout['link']??''),'id'=>$id]);
    }

    public function failOrder(int $id,string $error):void{$this->database->prepare("UPDATE organization_site_orders SET status='FAILED',error_message=:error WHERE id=:id")->execute(['error'=>mb_substr($error,0,500),'id'=>$id]);}
    /** @return array<string,mixed>|null */
    public function orderForWebhook(array$checkout):?array
    {
        $asaasId=trim((string)($checkout['id']??''));$reference=trim((string)($checkout['externalReference']??''));
        if($asaasId===''&&$reference==='')return null;
        $statement=$this->database->prepare('SELECT o.*,s.checkout_fulfillment_mode,p.name product_name,p.value FROM organization_site_orders o INNER JOIN organization_sites s ON s.organization_id=o.organization_id INNER JOIN finance_products p ON p.id=o.finance_product_id WHERE o.asaas_checkout_id=:asaas OR o.external_reference=:reference LIMIT 1');
        $statement->execute(['asaas'=>$asaasId,'reference'=>$reference]);$row=$statement->fetch();return is_array($row)?$row:null;
    }

    public function recordPaidOrder(int$orderId,int$organizationId,int$customerId,int$enrollmentId,string$status,?string$link):void
    {
        $fulfillment=$status==='automatic'?'releasing':'manual_review';
        $statement=$this->database->prepare('UPDATE organization_site_orders SET status=:status,fulfillment_status=:fulfillment,finance_customer_id=:customer,student_enrollment_id=:enrollment,link=COALESCE(:link,link),paid_at=COALESCE(paid_at,NOW()),fulfillment_error=NULL WHERE id=:id AND organization_id=:organization');
        $statement->execute(['status'=>'PAID','fulfillment'=>$fulfillment,'customer'=>$customerId,'enrollment'=>$enrollmentId,'link'=>$link,'id'=>$orderId,'organization'=>$organizationId]);
    }

    public function claimPaidOrder(int$orderId,int$organizationId):bool
    {
        $statement=$this->database->prepare("UPDATE organization_site_orders SET fulfillment_status='releasing',fulfillment_error=NULL WHERE id=:id AND organization_id=:organization AND fulfillment_status IN ('awaiting_payment','payment_confirmed','failed')");
        $statement->execute(['id'=>$orderId,'organization'=>$organizationId]);return$statement->rowCount()===1;
    }

    public function markOrderReleased(int$orderId):void{$this->database->prepare("UPDATE organization_site_orders SET fulfillment_status='released',fulfillment_error=NULL WHERE id=:id")->execute(['id'=>$orderId]);}
    public function markOrderFulfillmentFailed(int$orderId,string$error):void{$this->database->prepare("UPDATE organization_site_orders SET fulfillment_status='failed',fulfillment_error=:error WHERE id=:id")->execute(['id'=>$orderId,'error'=>mb_substr(trim($error),0,500)]);}

    /** @return array<string,mixed>|null */
    public function orderForReview(int$organizationId,int$orderId):?array{$statement=$this->database->prepare("SELECT * FROM organization_site_orders WHERE id=:id AND organization_id=:organization AND fulfillment_status IN ('manual_review','failed') AND student_enrollment_id IS NOT NULL LIMIT 1");$statement->execute(['id'=>$orderId,'organization'=>$organizationId]);$row=$statement->fetch();return is_array($row)?$row:null;}

    /** @param array<string,mixed> $checkout */
    public function updateOrderFromWebhook(array $checkout):void{$id=trim((string)($checkout['id']??''));if($id==='')return;$this->database->prepare('UPDATE organization_site_orders SET status=:status,link=COALESCE(:link,link) WHERE asaas_checkout_id=:id')->execute(['status'=>(string)($checkout['status']??'ACTIVE'),'link'=>isset($checkout['link'])?(string)$checkout['link']:null,'id'=>$id]);}

    /** @return list<array<string,mixed>> */
    public function recentOrders(int $organizationId,int $limit=20):array
    {
        $limit=max(1,min(100,$limit));$statement=$this->database->prepare("SELECT o.*,c.name contact_name,c.email contact_email,p.name product_name,p.value,u.name unit_name FROM organization_site_orders o LEFT JOIN crm_contacts c ON c.id=o.crm_contact_id INNER JOIN finance_products p ON p.id=o.finance_product_id INNER JOIN units u ON u.id=o.unit_id WHERE o.organization_id=:organization ORDER BY o.created_at DESC,o.id DESC LIMIT {$limit}");$statement->execute(['organization'=>$organizationId]);return$statement->fetchAll();
    }

    /** @param list<int> $unitIds @return array{summary:array{total:int,awaiting_payment:int,attention:int,released:int},items:list<array<string,mixed>>} */
    public function orderDashboard(int$organizationId,array$unitIds,string$bucket='',string$search='',int$limit=100):array
    {
        $unitIds=array_values(array_unique(array_filter(array_map('intval',$unitIds),static fn(int$id):bool=>$id>0)));
        if($organizationId<1||$unitIds===[])return['summary'=>['total'=>0,'awaiting_payment'=>0,'attention'=>0,'released'=>0],'items'=>[]];
        $marks=implode(',',array_fill(0,count($unitIds),'?'));
        $summarySql="SELECT COUNT(*) total,SUM(o.fulfillment_status='awaiting_payment') awaiting_payment,SUM(o.fulfillment_status IN ('manual_review','failed','payment_confirmed','releasing')) attention,SUM(o.fulfillment_status='released') released FROM organization_site_orders o WHERE o.organization_id=? AND o.unit_id IN ($marks)";
        $summaryStatement=$this->database->prepare($summarySql);$summaryStatement->execute([$organizationId,...$unitIds]);$summaryRow=$summaryStatement->fetch()?:[];
        $summary=['total'=>(int)($summaryRow['total']??0),'awaiting_payment'=>(int)($summaryRow['awaiting_payment']??0),'attention'=>(int)($summaryRow['attention']??0),'released'=>(int)($summaryRow['released']??0)];

        $conditions=['o.organization_id=?',"o.unit_id IN ($marks)"];$parameters=[$organizationId,...$unitIds];
        if($bucket==='awaiting_payment')$conditions[]="o.fulfillment_status='awaiting_payment'";
        elseif($bucket==='attention')$conditions[]="o.fulfillment_status IN ('manual_review','failed','payment_confirmed','releasing')";
        elseif($bucket==='released')$conditions[]="o.fulfillment_status='released'";
        elseif($bucket==='failed')$conditions[]="o.fulfillment_status='failed'";
        $search=trim($search);
        if($search!==''){$like='%'.$search.'%';$conditions[]='(c.name LIKE ? OR c.email LIKE ? OR p.name LIKE ? OR u.name LIKE ? OR o.external_reference LIKE ?)';array_push($parameters,$like,$like,$like,$like,$like);}
        $limit=max(1,min(200,$limit));
        $sql="SELECT o.*,c.name contact_name,c.email contact_email,c.phone contact_phone,p.name product_name,p.value,u.name unit_name,fc.name finance_customer_name,e.status enrollment_status,e.moodle_enrolment_status,ac.name ava_connection_name FROM organization_site_orders o LEFT JOIN crm_contacts c ON c.id=o.crm_contact_id INNER JOIN finance_products p ON p.id=o.finance_product_id INNER JOIN units u ON u.id=o.unit_id LEFT JOIN finance_customers fc ON fc.id=o.finance_customer_id LEFT JOIN student_enrollments e ON e.id=o.student_enrollment_id LEFT JOIN ava_connections ac ON ac.id=e.ava_connection_id WHERE ".implode(' AND ',$conditions)." ORDER BY o.created_at DESC,o.id DESC LIMIT {$limit}";
        $statement=$this->database->prepare($sql);$statement->execute($parameters);
        return['summary'=>$summary,'items'=>$statement->fetchAll()];
    }

    /** @param list<int> $unitIds @return array<string,mixed>|null */
    public function orderForRetry(int$organizationId,int$orderId,array$unitIds):?array
    {
        $unitIds=array_values(array_unique(array_filter(array_map('intval',$unitIds),static fn(int$id):bool=>$id>0)));
        if($unitIds===[])return null;$marks=implode(',',array_fill(0,count($unitIds),'?'));
        $statement=$this->database->prepare("SELECT o.* FROM organization_site_orders o WHERE o.id=? AND o.organization_id=? AND o.unit_id IN ($marks) AND o.fulfillment_status IN ('manual_review','failed') LIMIT 1");
        $statement->execute([$orderId,$organizationId,...$unitIds]);$row=$statement->fetch();return is_array($row)?$row:null;
    }

    /** @return array<string,mixed>|null */
    public function publicSite(int $organizationId,bool $preview=false):?array
    {
        $statement=$this->database->prepare("SELECT s.*,o.display_name,o.legal_name,o.cnpj,o.panel_slug,o.logo_path,o.favicon_path,o.primary_color,o.secondary_color,o.support_email organization_email,o.support_phone organization_phone,o.status organization_status,d.host site_host,d.status domain_status FROM organization_sites s INNER JOIN organizations o ON o.id=s.organization_id LEFT JOIN organization_domains d ON d.organization_id=o.id AND d.purpose='site' AND d.is_primary=1 WHERE s.organization_id=:organization LIMIT 1");
        $statement->execute(['organization'=>$organizationId]);$site=$statement->fetch();
        if(!is_array($site)||(int)$site['is_enabled']!==1||($site['organization_status']??'')!=='active')return null;
        if(!$preview&&($site['publication_status']??'')!=='published')return null;
        $ids=$this->selectedProductIds($organizationId);$products=[];
        if($ids!==[]){$marks=implode(',',array_fill(0,count($ids),'?'));$query=$this->database->prepare("SELECT p.id,p.unit_id,p.name,p.description,p.value,p.max_installments,p.billing_types,p.minutes_to_expire FROM finance_products p INNER JOIN organization_finance_products scope ON scope.finance_product_id=p.id AND scope.organization_id=? AND scope.is_visible=1 WHERE p.id IN ($marks) AND p.is_active=1");$query->execute([$organizationId,...$ids]);$byId=[];foreach($query->fetchAll()as$row)$byId[(int)$row['id']]=$row;foreach($ids as$id)if(isset($byId[$id]))$products[]=$byId[$id];}
        $site['products']=$products;$site['banners']=$this->banners($organizationId,!$preview);$site['pages']=$this->pages($organizationId,!$preview);return$site;
    }

    /** @return array<string,mixed> */
    private function editableSettings(int $organizationId):array{$settings=$this->settings($organizationId);if((int)$settings['is_enabled']!==1)throw new RuntimeException('O Site Institucional ainda não foi liberado pelo ADM Central.');return$settings;}
    private function ensure(int $organizationId):void{$statement=$this->database->prepare("INSERT IGNORE INTO organization_sites(organization_id,site_title,hero_title,hero_text,about_title,about_text,contact_email,contact_phone) SELECT id,display_name,CONCAT('Aprenda e transforme seu futuro com ',display_name),'Conheça nossos cursos e encontre a formação ideal para o seu próximo passo.','Sobre nós',CONCAT(display_name,' conecta pessoas, conhecimento e novas oportunidades.'),manager_email,manager_phone FROM organizations WHERE id=:organization");$statement->execute(['organization'=>$organizationId]);}
    private function assertOrganization(int $organizationId):void{if($organizationId<1)throw new RuntimeException('Franquia inválida.');$s=$this->database->prepare('SELECT 1 FROM organizations WHERE id=:id');$s->execute(['id'=>$organizationId]);if($s->fetchColumn()===false)throw new RuntimeException('Franquia não encontrada.');}
    private function limit(mixed$value,int$min,int$max,string$label):int{$number=(int)$value;if($number<$min||$number>$max)throw new RuntimeException("Informe entre {$min} e {$max} {$label}.");return$number;}
    private function checked(mixed$value):bool{return $value===true||(string)$value==='1'||(string)$value==='on';}
    private function text(mixed$value,int$max,string$label,bool$required=false):?string{$text=trim((string)$value);if($required&&$text==='')throw new RuntimeException('Informe o '.$label.'.');if(mb_strlen($text)>$max)throw new RuntimeException('O campo '.$label.' excede o tamanho permitido.');return$text!==''?$text:null;}
    private function url(mixed$value,string$label):?string{$url=trim((string)$value);if($url==='')return null;if(filter_var($url,FILTER_VALIDATE_URL)===false||!str_starts_with($url,'https://'))throw new RuntimeException('Informe uma URL HTTPS válida para '.$label.'.');return$url;}
    private function link(mixed$value):?string{$link=trim((string)$value);if($link==='')return null;if(str_starts_with($link,'#')||str_starts_with($link,'/'))return$link;return$this->url($link,'o botão do banner');}
    private function slug(string$value):string{$ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',trim($value));$slug=strtolower(is_string($ascii)?$ascii:$value);$slug=trim(preg_replace('/[^a-z0-9]+/','-',$slug)??'','-');if($slug===''||strlen($slug)>120)throw new RuntimeException('Informe um endereço válido para a página.');return$slug;}
    /** @return array<string,mixed> */
    private function defaults(int$organizationId):array{return['organization_id'=>$organizationId,'is_enabled'=>0,'template_key'=>'modern','allow_catalog'=>1,'allow_store'=>0,'checkout_fulfillment_mode'=>'manual_review','allow_custom_pages'=>0,'max_banners'=>3,'max_pages'=>5,'max_featured_courses'=>6,'selected_mode'=>'catalog','publication_status'=>'draft','site_title'=>'','hero_title'=>'','hero_text'=>'','about_title'=>'','about_text'=>'','contact_email'=>'','contact_phone'=>'','whatsapp'=>'','instagram_url'=>'','facebook_url'=>'','youtube_url'=>'','linkedin_url'=>'','tiktok_url'=>'','classroom_url'=>'','classroom_label'=>'Sala de Aula','webmail_url'=>'','social_bar_enabled'=>1,'site_search_enabled'=>1,'footer_text'=>'','footer_show_legal_data'=>1,'whatsapp_button_enabled'=>1,'whatsapp_button_label'=>'Fale pelo WhatsApp','whatsapp_button_message'=>'Olá! Gostaria de saber mais sobre os cursos.','scholarship_form_enabled'=>0,'scholarship_display_mode'=>'floating','scholarship_popup_delay_seconds'=>15,'scholarship_popup_repeat_hours'=>24,'scholarship_title'=>'GANHE BOLSAS DE ESTUDOS','scholarship_subtitle'=>'Preencha e participe!','scholarship_button_label'=>'Ganhe uma bolsa','seo_title'=>'','seo_description'=>'','published_at'=>null];}
}
