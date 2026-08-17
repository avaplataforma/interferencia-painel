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
        $this->ensure($organizationId);
        $fulfillment=(string)($data['checkout_fulfillment_mode']??'manual_review');
        if(!in_array($fulfillment,['manual_review','automatic'],true))throw new RuntimeException('Selecione uma regra válida para a liberação das compras.');
        $statement=$this->database->prepare('UPDATE organization_sites SET is_enabled=:enabled,allow_catalog=:catalog,allow_store=:store,checkout_fulfillment_mode=:fulfillment,allow_custom_pages=:pages,max_banners=:banners,max_pages=:max_pages,max_featured_courses=:courses WHERE organization_id=:organization');
        $statement->execute([
            'enabled'=>(int)(($data['is_enabled']??false)===true),
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
        $template=(string)($data['template_key']??($current['template_key']??'modern'));
        if(!in_array($template,['modern','classic','minimal'],true))throw new RuntimeException('Selecione um modelo-base válido.');
        $email=strtolower(trim((string)($data['contact_email']??'')));
        if($email!==''&&filter_var($email,FILTER_VALIDATE_EMAIL)===false)throw new RuntimeException('Informe um e-mail de contato válido.');
        $scholarshipMode=(string)($data['scholarship_display_mode']??'floating');
        if(!in_array($scholarshipMode,['floating','popup','both'],true))throw new RuntimeException('Selecione uma forma de exibição válida para o formulário de bolsas.');
        $productIds=array_values(array_unique(array_filter(array_map('intval',$productIds),static fn(int$id):bool=>$id>0)));
        if(count($productIds)>(int)$current['max_featured_courses'])throw new RuntimeException('Selecione no máximo '.(int)$current['max_featured_courses'].' cursos em destaque.');
        $allowed=array_map('intval',array_column($this->availableProducts($organizationId),'id'));
        if(array_diff($productIds,$allowed)!==[])throw new RuntimeException('Um dos cursos selecionados não pertence ao catálogo disponível da franquia.');
        $values=[
            'mode'=>$mode,'status'=>$status,'template'=>$template,
            'site_primary_color'=>$this->color($data['site_primary_color']??($current['site_primary_color']??''),'#ed1c24','cor principal do site'),
            'site_secondary_color'=>$this->color($data['site_secondary_color']??($current['site_secondary_color']??''),'#102a56','cor secundária do site'),
            'site_title'=>$this->text($data['site_title']??'',160,'nome do site',true),
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
            'ga4_id'=>$this->text($data['analytics_ga4_id']??'',64,'ID do Google Analytics'),
            'privacy_policy'=>$this->text($data['privacy_policy']??'',50000,'política de privacidade'),
            'cookie_notice'=>$this->text($data['cookie_notice']??'',2000,'aviso de cookies'),
            'terms_text'=>$this->text($data['terms_text']??'',50000,'termos de uso'),
            'cookie_banner_enabled'=>(int)$this->checked($data['cookie_banner_enabled']??false),
            'status_value'=>$status,'organization'=>$organizationId,
        ];
        $this->database->beginTransaction();
        try{
            $statement=$this->database->prepare("UPDATE organization_sites SET selected_mode=:mode,publication_status=:status,template_key=:template,site_primary_color=:site_primary_color,site_secondary_color=:site_secondary_color,site_title=:site_title,hero_title=:hero_title,hero_text=:hero_text,about_title=:about_title,about_text=:about_text,contact_email=:email,contact_phone=:phone,whatsapp=:whatsapp,instagram_url=:instagram,facebook_url=:facebook,youtube_url=:youtube,linkedin_url=:linkedin,tiktok_url=:tiktok,classroom_url=:classroom_url,classroom_label=:classroom_label,webmail_url=:webmail_url,social_bar_enabled=:social_bar_enabled,site_search_enabled=:site_search_enabled,footer_text=:footer_text,footer_show_legal_data=:footer_show_legal_data,whatsapp_button_enabled=:whatsapp_enabled,whatsapp_button_label=:whatsapp_label,whatsapp_button_message=:whatsapp_message,scholarship_form_enabled=:scholarship_enabled,scholarship_display_mode=:scholarship_mode,scholarship_popup_delay_seconds=:scholarship_delay,scholarship_popup_repeat_hours=:scholarship_repeat,scholarship_title=:scholarship_title,scholarship_subtitle=:scholarship_subtitle,scholarship_button_label=:scholarship_button,seo_title=:seo_title,seo_description=:seo_description,analytics_ga4_id=:ga4_id,privacy_policy=:privacy_policy,cookie_notice=:cookie_notice,terms_text=:terms_text,cookie_banner_enabled=:cookie_banner_enabled,published_at=CASE WHEN :status_value='published' THEN COALESCE(published_at,NOW()) ELSE published_at END WHERE organization_id=:organization");
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
        $statement=$this->database->prepare("SELECT DISTINCT p.id,p.unit_id,p.name,p.description,p.value,p.max_installments,p.billing_types,p.minutes_to_expire FROM organization_finance_products scope INNER JOIN finance_products p ON p.id=scope.finance_product_id LEFT JOIN units u ON u.id=p.unit_id WHERE scope.organization_id=:organization AND scope.is_visible=1 AND p.is_active=1 AND p.value>=5 AND (p.unit_id IS NULL OR u.organization_id=:unit_organization) AND (scope.source<>'ava' OR EXISTS(SELECT 1 FROM course_catalogs catalog LEFT JOIN organization_course_catalog_access access ON access.course_catalog_id=catalog.id AND access.organization_id=:catalog_organization WHERE catalog.code='ava-cursos' AND catalog.is_active=1 AND catalog.is_globally_enabled=1 AND COALESCE(access.is_enabled,1)=1)) ORDER BY p.name,p.id");
        $statement->execute(['organization'=>$organizationId,'unit_organization'=>$organizationId,'catalog_organization'=>$organizationId]);return$statement->fetchAll();
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
        if(!$preview){$site=$this->publicSite($organizationId,false);if($site===null)return null;$wanted=$this->slug($slug);foreach(($site['pages']??[])as$page)if(($page['slug']??'')===$wanted&&($page['publication_status']??'published')==='published')return$page;return null;}
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
        $values=['organization'=>$organizationId,'title'=>$this->text($data['title']??'',190,'título da página',true),'slug'=>$slug,'summary'=>$this->text($data['summary']??'',500,'resumo'),'seo_title'=>$this->text($data['seo_title']??'',190,'título SEO'),'seo_description'=>$this->text($data['seo_description']??'',320,'descrição SEO'),'content'=>$this->text($data['content']??'',50000,'conteúdo',true),'status'=>$status,'menu'=>(int)(($data['show_in_menu']??false)===true),'sort_order'=>$this->limit($data['sort_order']??0,0,999,'na ordem')];
        try{if($id===null){$statement=$this->database->prepare('INSERT INTO organization_site_pages(organization_id,title,slug,summary,seo_title,seo_description,content,publication_status,show_in_menu,sort_order) VALUES(:organization,:title,:slug,:summary,:seo_title,:seo_description,:content,:status,:menu,:sort_order)');$statement->execute($values);return(int)$this->database->lastInsertId();}$values['id']=$id;$statement=$this->database->prepare('UPDATE organization_site_pages SET title=:title,slug=:slug,summary=:summary,seo_title=:seo_title,seo_description=:seo_description,content=:content,publication_status=:status,show_in_menu=:menu,sort_order=:sort_order WHERE id=:id AND organization_id=:organization');$statement->execute($values);return$id;}catch(\PDOException$e){if((string)$e->getCode()==='23000')throw new RuntimeException('Já existe uma página com esse endereço.');throw$e;}
    }

    public function deletePage(int $organizationId,int $id):void
    {
        $statement=$this->database->prepare('DELETE FROM organization_site_pages WHERE id=:id AND organization_id=:organization');$statement->execute(['id'=>$id,'organization'=>$organizationId]);if($statement->rowCount()!==1)throw new RuntimeException('Página não encontrada.');
    }

    /** @return array<string,mixed>|null */
    public function publicProduct(int $organizationId,int $productId):?array
    {
        $site=$this->publicSite($organizationId);if($site===null||($site['selected_mode']??'')!=='store'||(int)($site['allow_store']??0)!==1)return null;foreach(($site['products']??[])as$product)if((int)$product['id']===$productId&&(float)$product['value']>=5)return$product;return null;
    }

    /** @return array<string,mixed>|null */
    public function publicCatalogProduct(int $organizationId,int $productId):?array
    {
        $site=$this->publicSite($organizationId);if($site===null)return null;$mode=(string)($site['selected_mode']??'catalog');if($mode==='store'&&(int)($site['allow_store']??0)!==1)return null;if($mode==='catalog'&&(int)($site['allow_catalog']??0)!==1)return null;foreach(($site['products']??[])as$product)if((int)$product['id']===$productId&&($mode!=='store'||(float)$product['value']>=5))return$product+['selected_mode'=>$mode];return null;
    }

    /** @return list<array<string,mixed>> */
    public function publicUnits(int $organizationId,?int $productUnitId):array
    {
        $sql='SELECT id,code,name,city FROM units WHERE organization_id=:organization AND is_active=1'.($productUnitId!==null?' AND id=:unit':'').' ORDER BY name';
        $statement=$this->database->prepare($sql);$params=['organization'=>$organizationId];if($productUnitId!==null)$params['unit']=$productUnitId;$statement->execute($params);return$statement->fetchAll();
    }

    /** @return array{id:int,external_reference:string} */
    public function createOrderDraft(int $organizationId,int $unitId,int $contactId,int $productId,array $attribution=[]):array
    {
        $statement=$this->database->prepare('INSERT INTO organization_site_orders(organization_id,unit_id,crm_contact_id,finance_product_id,external_reference,session_hash,landing_page,utm_source,utm_medium,utm_campaign,utm_content,utm_term) SELECT :organization,:unit,:contact,:product,:temporary,:session,:landing,:source,:medium,:campaign,:content,:term FROM units u INNER JOIN finance_products p ON p.id=:product_check WHERE u.id=:unit_check AND u.organization_id=:organization_check AND u.is_active=1 AND p.id=:product_scope');
        $temporary='pending-'.bin2hex(random_bytes(12));$statement->execute(['organization'=>$organizationId,'unit'=>$unitId,'contact'=>$contactId,'product'=>$productId,'temporary'=>$temporary,'session'=>$this->nullable($attribution['session_hash']??null,64),'landing'=>$this->nullable($attribution['landing_page']??null,500),'source'=>$this->nullable($attribution['utm_source']??null,190),'medium'=>$this->nullable($attribution['utm_medium']??null,190),'campaign'=>$this->nullable($attribution['utm_campaign']??null,190),'content'=>$this->nullable($attribution['utm_content']??null,190),'term'=>$this->nullable($attribution['utm_term']??null,190),'product_check'=>$productId,'unit_check'=>$unitId,'organization_check'=>$organizationId,'product_scope'=>$productId]);
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

    /** @param array<string,mixed> $data @param list<int> $productIds */
    public function saveRevision(int $organizationId,array $data,array $productIds,string $action,?string $scheduledAt,?int $userId):int
    {
        if(!in_array($action,['draft','publish','schedule'],true))throw new RuntimeException('Escolha uma ação de publicação válida.');
        $before=$this->settings($organizationId);
        if(trim((string)($before['live_snapshot_json']??''))===''&&($before['publication_status']??'')==='published'){
            $initial=$this->snapshot($organizationId,$before);
            $this->database->prepare('UPDATE organization_sites SET live_snapshot_json=:snapshot,live_version=1 WHERE organization_id=:organization')->execute(['snapshot'=>$this->encode($initial),'organization'=>$organizationId]);
        }
        $data['publication_status']='draft';
        $this->saveContent($organizationId,$data,$productIds);
        $snapshot=$this->snapshot($organizationId,$this->settings($organizationId));
        foreach(['logo_path','favicon_path']as$brandField)if(array_key_exists($brandField,$data))$snapshot['settings'][$brandField]=$data[$brandField];
        $next=(int)$this->database->query('SELECT COALESCE(MAX(version_number),0)+1 FROM organization_site_versions WHERE organization_id='.(int)$organizationId)->fetchColumn();
        $status=$action==='draft'?'draft':($action==='schedule'?'scheduled':'published');
        $when=null;
        if($action==='schedule'){
            $date=$scheduledAt!==null?date_create($scheduledAt):false;
            if($date===false||$date->getTimestamp()<=time())throw new RuntimeException('Escolha uma data futura para a publicação.');
            $when=$date->format('Y-m-d H:i:s');
        }
        $statement=$this->database->prepare('INSERT INTO organization_site_versions(organization_id,version_number,status,label,snapshot_json,scheduled_at,published_at,created_by) VALUES(:organization,:version,:status,:label,:snapshot,:scheduled,:published,:user)');
        $statement->execute(['organization'=>$organizationId,'version'=>$next,'status'=>$status,'label'=>$action==='publish'?'Publicação manual':($action==='schedule'?'Publicação agendada':'Rascunho salvo'),'snapshot'=>$this->encode($snapshot),'scheduled'=>$when,'published'=>$action==='publish'?date('Y-m-d H:i:s'):null,'user'=>$userId]);
        $id=(int)$this->database->lastInsertId();
        if($action==='publish')$this->publishSnapshot($organizationId,$snapshot,$next);
        elseif($action==='schedule')$this->database->prepare('UPDATE organization_sites SET scheduled_snapshot_json=:snapshot,scheduled_publish_at=:scheduled WHERE organization_id=:organization')->execute(['snapshot'=>$this->encode($snapshot),'scheduled'=>$when,'organization'=>$organizationId]);
        return$id;
    }

    /** @return list<array<string,mixed>> */
    public function versions(int $organizationId,int $limit=20):array
    {
        $limit=max(1,min(100,$limit));$statement=$this->database->prepare("SELECT v.*,u.name created_by_name FROM organization_site_versions v LEFT JOIN users u ON u.id=v.created_by WHERE v.organization_id=:organization ORDER BY v.version_number DESC LIMIT {$limit}");$statement->execute(['organization'=>$organizationId]);return$statement->fetchAll();
    }

    public function publishVersion(int $organizationId,int $versionId):void
    {
        $statement=$this->database->prepare('SELECT * FROM organization_site_versions WHERE id=:id AND organization_id=:organization LIMIT 1');$statement->execute(['id'=>$versionId,'organization'=>$organizationId]);$version=$statement->fetch();if(!is_array($version))throw new RuntimeException('Versão não encontrada.');
        $snapshot=json_decode((string)$version['snapshot_json'],true,512,JSON_THROW_ON_ERROR);if(!is_array($snapshot))throw new RuntimeException('A versão está inválida.');
        $this->publishSnapshot($organizationId,$snapshot,(int)$version['version_number']);
        $this->database->prepare("UPDATE organization_site_versions SET status='published',published_at=NOW() WHERE id=:id")->execute(['id'=>$versionId]);
    }

    public function cancelSchedule(int $organizationId):void
    {
        $this->database->prepare('UPDATE organization_sites SET scheduled_snapshot_json=NULL,scheduled_publish_at=NULL WHERE organization_id=:organization')->execute(['organization'=>$organizationId]);
        $this->database->prepare("UPDATE organization_site_versions SET status='archived' WHERE organization_id=:organization AND status='scheduled'")->execute(['organization'=>$organizationId]);
    }

    /** @param array<string,mixed> $data */
    public function recordEvent(int $organizationId,array $data):void
    {
        $type=(string)($data['event_type']??'');if(!in_array($type,['page_view','course_view','course_click','whatsapp_click','contact_submit','scholarship_submit','checkout_start','lead_scholarship','lead_contact','lead_course','checkout_created'],true))throw new RuntimeException('Evento inválido.');
        $statement=$this->database->prepare('INSERT INTO organization_site_events(organization_id,session_hash,event_type,page_path,entity_type,entity_id,contact_id,order_id,unit_id,landing_page,utm_source,utm_medium,utm_campaign,utm_content,utm_term,metadata_json) VALUES(:organization,:session,:event,:page,:entity_type,:entity_id,:contact,:site_order,:unit,:landing,:utm_source,:utm_medium,:utm_campaign,:utm_content,:utm_term,:metadata)');
        $statement->execute(['organization'=>$organizationId,'session'=>$this->nullable($data['session_hash']??null,64),'event'=>$type,'page'=>$this->nullable($data['page_path']??null,500),'entity_type'=>$this->nullable($data['entity_type']??null,40),'entity_id'=>isset($data['entity_id'])&&is_numeric($data['entity_id'])?(int)$data['entity_id']:null,'contact'=>isset($data['contact_id'])&&is_numeric($data['contact_id'])?(int)$data['contact_id']:null,'site_order'=>isset($data['order_id'])&&is_numeric($data['order_id'])?(int)$data['order_id']:null,'unit'=>isset($data['unit_id'])&&is_numeric($data['unit_id'])?(int)$data['unit_id']:null,'landing'=>$this->nullable($data['landing_page']??null,500),'utm_source'=>$this->nullable($data['utm_source']??null,190),'utm_medium'=>$this->nullable($data['utm_medium']??null,190),'utm_campaign'=>$this->nullable($data['utm_campaign']??null,190),'utm_content'=>$this->nullable($data['utm_content']??null,190),'utm_term'=>$this->nullable($data['utm_term']??null,190),'metadata'=>isset($data['metadata'])?$this->encode($data['metadata']):null]);
    }

    /** @return array<string,int> */
    public function analyticsSummary(int $organizationId,int $days=30):array
    {
        $days=max(1,min(365,$days));$statement=$this->database->prepare("SELECT event_type,COUNT(*) total FROM organization_site_events WHERE organization_id=:organization AND occurred_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY) GROUP BY event_type");$statement->execute(['organization'=>$organizationId]);$summary=['page_view'=>0,'course_view'=>0,'course_click'=>0,'whatsapp_click'=>0,'contact_submit'=>0,'scholarship_submit'=>0,'checkout_start'=>0];foreach($statement->fetchAll()as$row)$summary[(string)$row['event_type']]=(int)$row['total'];return$summary;
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function commercialFunnel(int $organizationId,array $filters=[]):array
    {
        $days=in_array((int)($filters['days']??30),[7,30,90,365],true)?(int)$filters['days']:30;
        $unitId=max(0,(int)($filters['unit_id']??0));$productId=max(0,(int)($filters['product_id']??0));
        $source=mb_substr(trim((string)($filters['utm_source']??'')),0,190);$campaign=mb_substr(trim((string)($filters['utm_campaign']??'')),0,190);
        $scalar=function(string$sql,array$params=[]):int{$statement=$this->database->prepare($sql);$statement->execute($params);return(int)$statement->fetchColumn();};
        $decimal=function(string$sql,array$params=[]):float{$statement=$this->database->prepare($sql);$statement->execute($params);return(float)$statement->fetchColumn();};

        $eventWhere=['organization_id=?',"occurred_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY)"];$eventParams=[$organizationId];
        if($source!==''){$eventWhere[]="COALESCE(utm_source,'')=?";$eventParams[]=$source;}if($campaign!==''){$eventWhere[]="COALESCE(utm_campaign,'')=?";$eventParams[]=$campaign;}
        $events=' WHERE '.implode(' AND ',$eventWhere);

        $contactWhere=['u.organization_id=?',"c.registration_source='external_form'","c.privacy_notice_version LIKE 'site-%'", "c.registered_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY)"];$contactParams=[$organizationId];
        if($unitId>0){$contactWhere[]='c.unit_id=?';$contactParams[]=$unitId;}if($productId>0){$contactWhere[]='c.course=(SELECT name FROM finance_products WHERE id=? LIMIT 1)';$contactParams[]=$productId;}if($source!==''){$contactWhere[]="COALESCE(c.utm_source,'')=?";$contactParams[]=$source;}if($campaign!==''){$contactWhere[]="COALESCE(c.utm_campaign,'')=?";$contactParams[]=$campaign;}
        $contacts=' WHERE '.implode(' AND ',$contactWhere);

        $orderWhere=['o.organization_id=?',"o.created_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY)"];$orderParams=[$organizationId];
        if($unitId>0){$orderWhere[]='o.unit_id=?';$orderParams[]=$unitId;}if($productId>0){$orderWhere[]='o.finance_product_id=?';$orderParams[]=$productId;}if($source!==''){$orderWhere[]="COALESCE(o.utm_source,'')=?";$orderParams[]=$source;}if($campaign!==''){$orderWhere[]="COALESCE(o.utm_campaign,'')=?";$orderParams[]=$campaign;}
        $orders=' WHERE '.implode(' AND ',$orderWhere);

        $summary=[
            'sessions'=>$scalar('SELECT COUNT(DISTINCT session_hash) FROM organization_site_events'.$events.' AND session_hash IS NOT NULL',$eventParams),
            'page_views'=>$scalar("SELECT COUNT(*) FROM organization_site_events{$events} AND event_type='page_view'",$eventParams),
            'leads'=>$scalar('SELECT COUNT(DISTINCT c.id) FROM crm_contacts c INNER JOIN units u ON u.id=c.unit_id'.$contacts,$contactParams),
            'checkout_starts'=>$scalar("SELECT COUNT(DISTINCT session_hash) FROM organization_site_events{$events} AND event_type IN ('checkout_start','checkout_created')",$eventParams),
            'orders'=>$scalar('SELECT COUNT(*) FROM organization_site_orders o'.$orders,$orderParams),
            'paid'=>$scalar('SELECT COUNT(*) FROM organization_site_orders o'.$orders.' AND o.paid_at IS NOT NULL',$orderParams),
            'enrolled'=>$scalar('SELECT COUNT(*) FROM organization_site_orders o'.$orders.' AND o.student_enrollment_id IS NOT NULL',$orderParams),
            'released'=>$scalar("SELECT COUNT(*) FROM organization_site_orders o{$orders} AND o.fulfillment_status='released'",$orderParams),
            'recovery'=>$scalar("SELECT COUNT(*) FROM organization_site_recoveries r INNER JOIN organization_site_orders o ON o.id=r.site_order_id{$orders} AND r.status='pending' AND r.alert_stage>0",$orderParams),
            'recovered'=>$scalar("SELECT COUNT(*) FROM organization_site_recoveries r INNER JOIN organization_site_orders o ON o.id=r.site_order_id{$orders} AND r.status='recovered'",$orderParams),
            'recovered_amount'=>$decimal("SELECT COALESCE(SUM(r.recovered_amount),0) FROM organization_site_recoveries r INNER JOIN organization_site_orders o ON o.id=r.site_order_id{$orders} AND r.status='recovered'",$orderParams),
        ];
        $rate=static fn(int$to,int$from):float=>$from>0?round(($to/$from)*100,1):0.0;
        $rates=['lead'=>$rate($summary['leads'],$summary['sessions']),'order'=>$rate($summary['orders'],$summary['leads']),'paid'=>$rate($summary['paid'],$summary['orders']),'enrolled'=>$rate($summary['enrolled'],$summary['paid']),'released'=>$rate($summary['released'],$summary['enrolled'])];

        $channelStatement=$this->database->prepare("SELECT COALESCE(NULLIF(c.utm_source,''),'Direto') label,COUNT(DISTINCT c.id) leads FROM crm_contacts c INNER JOIN units u ON u.id=c.unit_id{$contacts} GROUP BY label ORDER BY leads DESC LIMIT 12");$channelStatement->execute($contactParams);$channels=[];foreach($channelStatement->fetchAll()as$row)$channels[(string)$row['label']]=['label'=>(string)$row['label'],'leads'=>(int)$row['leads'],'orders'=>0,'paid'=>0];
        $channelOrders=$this->database->prepare("SELECT COALESCE(NULLIF(o.utm_source,''),'Direto') label,COUNT(*) orders,SUM(o.paid_at IS NOT NULL) paid FROM organization_site_orders o{$orders} GROUP BY label ORDER BY orders DESC LIMIT 12");$channelOrders->execute($orderParams);foreach($channelOrders->fetchAll()as$row){$key=(string)$row['label'];$channels[$key]??=['label'=>$key,'leads'=>0,'orders'=>0,'paid'=>0];$channels[$key]['orders']=(int)$row['orders'];$channels[$key]['paid']=(int)$row['paid'];}usort($channels,static fn(array$a,array$b):int=>$b['leads']<=>$a['leads']);

        $courseStatement=$this->database->prepare("SELECT p.name label,COUNT(*) orders,SUM(o.paid_at IS NOT NULL) paid,SUM(o.fulfillment_status='released') released,COALESCE(SUM(CASE WHEN o.paid_at IS NOT NULL THEN p.value ELSE 0 END),0) revenue FROM organization_site_orders o INNER JOIN finance_products p ON p.id=o.finance_product_id{$orders} GROUP BY p.id,p.name ORDER BY orders DESC,p.name LIMIT 12");$courseStatement->execute($orderParams);$courses=$courseStatement->fetchAll();
        $unitStatement=$this->database->prepare("SELECT u.name label,COUNT(*) orders,SUM(o.paid_at IS NOT NULL) paid,SUM(o.fulfillment_status='released') released FROM organization_site_orders o INNER JOIN units u ON u.id=o.unit_id{$orders} GROUP BY u.id,u.name ORDER BY orders DESC,u.name LIMIT 20");$unitStatement->execute($orderParams);$units=$unitStatement->fetchAll();

        $recoveryStatement=$this->database->prepare("SELECT o.id,o.link,o.created_at,r.alert_stage,r.responsible_user_id,c.id contact_id,c.name contact_name,c.phone contact_phone,c.email contact_email,p.name product_name,u.name unit_name,TIMESTAMPDIFF(HOUR,o.created_at,NOW()) age_hours FROM organization_site_recoveries r INNER JOIN organization_site_orders o ON o.id=r.site_order_id LEFT JOIN crm_contacts c ON c.id=o.crm_contact_id INNER JOIN finance_products p ON p.id=o.finance_product_id INNER JOIN units u ON u.id=o.unit_id{$orders} AND r.status='pending' AND r.alert_stage>0 ORDER BY r.alert_stage DESC,o.created_at ASC LIMIT 30");$recoveryStatement->execute($orderParams);$recovery=$recoveryStatement->fetchAll();

        $optionStatement=$this->database->prepare("SELECT DISTINCT COALESCE(utm_source,'') source,COALESCE(utm_campaign,'') campaign FROM crm_contacts c INNER JOIN units u ON u.id=c.unit_id WHERE u.organization_id=? AND c.registration_source='external_form' AND c.privacy_notice_version LIKE 'site-%' ORDER BY source,campaign LIMIT 100");$optionStatement->execute([$organizationId]);$sources=[];$campaigns=[];foreach($optionStatement->fetchAll()as$row){if((string)$row['source']!=='')$sources[(string)$row['source']]=true;if((string)$row['campaign']!=='')$campaigns[(string)$row['campaign']]=true;}
        return['summary'=>$summary,'rates'=>$rates,'channels'=>array_values($channels),'courses'=>$courses,'units'=>$units,'recovery'=>$recovery,'options'=>['units'=>$this->publicUnits($organizationId,null),'products'=>$this->availableProducts($organizationId),'sources'=>array_keys($sources),'campaigns'=>array_keys($campaigns)],'filters'=>['days'=>$days,'unit_id'=>$unitId,'product_id'=>$productId,'utm_source'=>$source,'utm_campaign'=>$campaign]];
    }

    /** @return array<string,mixed> */
    public function checkDomain(int $organizationId):array
    {
        $statement=$this->database->prepare("SELECT host FROM organization_domains WHERE organization_id=:organization AND purpose='site' AND is_primary=1 LIMIT 1");$statement->execute(['organization'=>$organizationId]);$host=trim((string)($statement->fetchColumn()?:''));$dns=false;$ssl=false;$ip=null;$error=null;
        if($host==='')$error='Domínio personalizado ainda não configurado.';
        else{
            $resolved=gethostbyname($host);$dns=$resolved!==$host;$ip=$dns?$resolved:null;
            if($dns){$errno=0;$errstr='';$socket=@stream_socket_client('ssl://'.$host.':443',$errno,$errstr,4,STREAM_CLIENT_CONNECT);$ssl=is_resource($socket);if(is_resource($socket))fclose($socket);if(!$ssl)$error=$errstr!==''?$errstr:'Certificado HTTPS indisponível.';}
            else $error='DNS ainda não aponta para a plataforma.';
        }
        $save=$this->database->prepare('INSERT INTO organization_site_domain_checks(organization_id,host,dns_ok,ssl_ok,resolved_ip,error_message,checked_at) VALUES(:organization,:host,:dns,:ssl,:ip,:error,NOW()) ON DUPLICATE KEY UPDATE host=VALUES(host),dns_ok=VALUES(dns_ok),ssl_ok=VALUES(ssl_ok),resolved_ip=VALUES(resolved_ip),error_message=VALUES(error_message),checked_at=NOW()');$save->execute(['organization'=>$organizationId,'host'=>$host!==''?$host:null,'dns'=>(int)$dns,'ssl'=>(int)$ssl,'ip'=>$ip,'error'=>$error]);return['host'=>$host,'dns_ok'=>$dns,'ssl_ok'=>$ssl,'resolved_ip'=>$ip,'error_message'=>$error,'checked_at'=>date('Y-m-d H:i:s')];
    }

    /** @return array<string,mixed> */
    public function domainStatus(int $organizationId):array
    {
        $statement=$this->database->prepare('SELECT * FROM organization_site_domain_checks WHERE organization_id=:organization LIMIT 1');$statement->execute(['organization'=>$organizationId]);$row=$statement->fetch();return is_array($row)?$row:['host'=>null,'dns_ok'=>0,'ssl_ok'=>0,'resolved_ip'=>null,'error_message'=>'Verificação ainda não executada.','checked_at'=>null];
    }

    /** @return list<array<string,mixed>> */
    public function media(int $organizationId):array{$statement=$this->database->prepare('SELECT * FROM organization_site_media WHERE organization_id=:organization ORDER BY created_at DESC,id DESC');$statement->execute(['organization'=>$organizationId]);return$statement->fetchAll();}
    /** @return array<string,mixed>|null */
    public function mediaItem(int $organizationId,int $id):?array{$statement=$this->database->prepare('SELECT * FROM organization_site_media WHERE id=:id AND organization_id=:organization LIMIT 1');$statement->execute(['id'=>$id,'organization'=>$organizationId]);$row=$statement->fetch();return is_array($row)?$row:null;}
    public function registerMedia(int $organizationId,array $data,?int $userId):int{$statement=$this->database->prepare('INSERT INTO organization_site_media(organization_id,title,alt_text,storage_path,public_path,mime_type,width,height,file_size,created_by) VALUES(:organization,:title,:alt,:storage,:public,:mime,:width,:height,:bytes,:user)');$statement->execute(['organization'=>$organizationId,'title'=>$this->text($data['title']??'',190,'título da imagem',true),'alt'=>$this->text($data['alt_text']??'',255,'texto alternativo'),'storage'=>$data['storage_path'],'public'=>$data['public_path']??null,'mime'=>$data['mime_type'],'width'=>$data['width']??null,'height'=>$data['height']??null,'bytes'=>$data['file_size'],'user'=>$userId]);return(int)$this->database->lastInsertId();}
    public function deleteMedia(int $organizationId,int $id):void{$statement=$this->database->prepare('DELETE FROM organization_site_media WHERE id=:id AND organization_id=:organization');$statement->execute(['id'=>$id,'organization'=>$organizationId]);if($statement->rowCount()!==1)throw new RuntimeException('Imagem não encontrada.');}

    /** @return array<string,mixed> */
    public function productSeo(int $organizationId):array{$statement=$this->database->prepare('SELECT finance_product_id,seo_title,seo_description FROM organization_site_product_seo WHERE organization_id=:organization');$statement->execute(['organization'=>$organizationId]);$result=[];foreach($statement->fetchAll()as$row)$result[(int)$row['finance_product_id']]=$row;return$result;}
    public function saveProductSeo(int $organizationId,int $productId,string $title,string $description):void{$statement=$this->database->prepare('INSERT INTO organization_site_product_seo(organization_id,finance_product_id,seo_title,seo_description) VALUES(:organization,:product,:title,:description) ON DUPLICATE KEY UPDATE seo_title=VALUES(seo_title),seo_description=VALUES(seo_description)');$statement->execute(['organization'=>$organizationId,'product'=>$productId,'title'=>$this->text($title,190,'título SEO'),'description'=>$this->text($description,320,'descrição SEO')]);}

    /** @return array<int,array<string,mixed>> */
    public function productDetails(int $organizationId):array{$statement=$this->database->prepare('SELECT * FROM organization_site_product_details WHERE organization_id=:organization');$statement->execute(['organization'=>$organizationId]);$result=[];foreach($statement->fetchAll()as$row)$result[(int)$row['finance_product_id']]=$row;return$result;}
    /** @param array<string,mixed> $data */
    public function saveProductDetails(int $organizationId,int $productId,array$data):void
    {
        $allowed=array_map('intval',array_column($this->availableProducts($organizationId),'id'));if(!in_array($productId,$allowed,true))throw new RuntimeException('Curso indisponível para esta franquia.');
        $statement=$this->database->prepare('INSERT INTO organization_site_product_details(organization_id,finance_product_id,category,modality,workload_hours,target_audience,curriculum,requirements,certificate_text,faq_text,rating_average,rating_count) VALUES(:organization,:product,:category,:modality,:workload,:audience,:curriculum,:requirements,:certificate,:faq,:rating,:rating_count) ON DUPLICATE KEY UPDATE category=VALUES(category),modality=VALUES(modality),workload_hours=VALUES(workload_hours),target_audience=VALUES(target_audience),curriculum=VALUES(curriculum),requirements=VALUES(requirements),certificate_text=VALUES(certificate_text),faq_text=VALUES(faq_text),rating_average=VALUES(rating_average),rating_count=VALUES(rating_count)');
        $rating=max(0,min(5,(float)($data['rating_average']??0)));$statement->execute(['organization'=>$organizationId,'product'=>$productId,'category'=>$this->text($data['category']??'',120,'categoria'),'modality'=>$this->text($data['modality']??'',80,'modalidade'),'workload'=>max(0,(int)($data['workload_hours']??0))?:null,'audience'=>$this->text($data['target_audience']??'',4000,'público-alvo'),'curriculum'=>$this->text($data['curriculum']??'',20000,'conteúdo programático'),'requirements'=>$this->text($data['requirements']??'',4000,'requisitos'),'certificate'=>$this->text($data['certificate_text']??'',4000,'certificado'),'faq'=>$this->text($data['faq_text']??'',20000,'perguntas frequentes'),'rating'=>$rating>0?$rating:null,'rating_count'=>max(0,(int)($data['rating_count']??0))]);
    }
    /** @return list<array<string,mixed>> */
    public function blocks(int$organizationId,bool$onlyActive=false):array{$sql='SELECT * FROM organization_site_blocks WHERE organization_id=:organization'.($onlyActive?' AND is_active=1':'').' ORDER BY sort_order,id';$statement=$this->database->prepare($sql);$statement->execute(['organization'=>$organizationId]);return$statement->fetchAll();}
    /** @param array<string,mixed> $data */
    public function saveBlock(int$organizationId,?int$id,array$data):int{$type=(string)($data['block_type']??'text');if(!in_array($type,['text','video','testimonial','faq','partners','stats','cta','poles'],true))throw new RuntimeException('Tipo de bloco inválido.');$values=['organization'=>$organizationId,'type'=>$type,'title'=>$this->text($data['title']??'',190,'título do bloco',true),'subtitle'=>$this->text($data['subtitle']??'',500,'subtítulo'),'body'=>$this->text($data['body']??'',30000,'conteúdo'),'media'=>$this->url($data['media_url']??'','mídia'),'button'=>$this->text($data['button_label']??'',80,'texto do botão'),'button_url'=>$this->link($data['button_url']??''),'sort'=>$this->limit($data['sort_order']??0,0,999,'na ordem'),'active'=>(int)$this->checked($data['is_active']??false)];if($id===null){$statement=$this->database->prepare('INSERT INTO organization_site_blocks(organization_id,block_type,title,subtitle,body,media_url,button_label,button_url,sort_order,is_active) VALUES(:organization,:type,:title,:subtitle,:body,:media,:button,:button_url,:sort,:active)');$statement->execute($values);return(int)$this->database->lastInsertId();}$values['id']=$id;$statement=$this->database->prepare('UPDATE organization_site_blocks SET block_type=:type,title=:title,subtitle=:subtitle,body=:body,media_url=:media,button_label=:button,button_url=:button_url,sort_order=:sort,is_active=:active WHERE id=:id AND organization_id=:organization');$statement->execute($values);if($statement->rowCount()===0){$check=$this->database->prepare('SELECT 1 FROM organization_site_blocks WHERE id=:id AND organization_id=:organization');$check->execute(['id'=>$id,'organization'=>$organizationId]);if($check->fetchColumn()===false)throw new RuntimeException('Bloco não encontrado.');}return$id;}
    public function deleteBlock(int$organizationId,int$id):void{$statement=$this->database->prepare('DELETE FROM organization_site_blocks WHERE id=:id AND organization_id=:organization');$statement->execute(['id'=>$id,'organization'=>$organizationId]);if($statement->rowCount()!==1)throw new RuntimeException('Bloco não encontrado.');}

    /** @return list<array<string,mixed>> */
    public function externalProducts(int $organizationId):array
    {
        $statement=$this->database->prepare("SELECT offer.id,offer.organization_id,offer.price value,offer.max_installments,offer.sale_mode,COALESCE(NULLIF(offer.commercial_name,''),NULLIF(course.commercial_name,''),course.name) name,COALESCE(NULLIF(offer.commercial_description,''),NULLIF(course.commercial_description,''),course.description) description,COALESCE(NULLIF(course.commercial_category,''),course.category) category,COALESCE(NULLIF(course.commercial_area,''),'') area,COALESCE(NULLIF(course.commercial_workload,''),course.workload,catalog.central_default_module_workload) workload_text,COALESCE(catalog.central_default_module_access_months,3) access_months,course.lesson_count,COALESCE(NULLIF(course.commercial_cover_url,''),course.cover_url) cover_url,asset.id media_asset_id,catalog.name catalog_name,catalog.code catalog_code,catalog.execution_environment,provider.name provider_name,provider.delivery_mode,provider.launch_url_template,course.remote_id,course.external_key FROM organization_provider_course_offers offer INNER JOIN provider_courses course ON course.id=offer.provider_course_id INNER JOIN course_catalogs catalog ON catalog.id=course.catalog_id LEFT JOIN organization_course_catalog_access access ON access.course_catalog_id=catalog.id AND access.organization_id=offer.organization_id LEFT JOIN organization_catalog_item_access item_access ON item_access.organization_id=offer.organization_id AND item_access.item_type='course' AND item_access.item_id=course.id INNER JOIN course_provider_integrations provider ON provider.id=course.provider_id LEFT JOIN catalog_media_assets asset ON asset.entity_type='course' AND asset.entity_id=course.id AND asset.purpose='cover' AND asset.generation_status='ready' WHERE offer.organization_id=:organization AND offer.is_active=1 AND offer.is_visible=1 AND offer.price>=5 AND course.review_status='approved' AND course.release_status IN ('released','published') AND course.is_available=1 AND course.is_globally_enabled=1 AND catalog.is_active=1 AND catalog.is_globally_enabled=1 AND COALESCE(access.is_enabled,1)=1 AND COALESCE(item_access.is_enabled,1)=1 AND (CASE WHEN catalog.allow_franchise_commercial_override=1 THEN COALESCE(access.valid_from,catalog.central_valid_from) ELSE catalog.central_valid_from END IS NULL OR CASE WHEN catalog.allow_franchise_commercial_override=1 THEN COALESCE(access.valid_from,catalog.central_valid_from) ELSE catalog.central_valid_from END<=CURRENT_DATE) AND (CASE WHEN catalog.allow_franchise_commercial_override=1 THEN COALESCE(access.valid_until,catalog.central_valid_until) ELSE catalog.central_valid_until END IS NULL OR CASE WHEN catalog.allow_franchise_commercial_override=1 THEN COALESCE(access.valid_until,catalog.central_valid_until) ELSE catalog.central_valid_until END>=CURRENT_DATE) AND provider.is_active=1 ORDER BY catalog.name,name");
        $statement->execute(['organization'=>$organizationId]);$products=$statement->fetchAll()?:[];
        foreach($products as&$product){$product['is_external']=1;$product['product_kind']='provider_course';$product['modality']=($product['execution_environment']??'')==='shared_ava'?'AVA Cursos':'AVA do fornecedor';$product['workload_hours']=(int)(float)($product['workload_text']??0);$product['billing_types']='[]';$product['minutes_to_expire']=0;$product['seo_title']=$product['name'];$product['seo_description']=$product['description'];}
        unset($product);return$products;
    }

    /** @return list<array<string,mixed>> */
    public function externalContentProducts(int $organizationId):array
    {
        $statement=$this->database->prepare("SELECT offer.id,offer.organization_id,offer.price value,offer.max_installments,offer.sale_mode,COALESCE(NULLIF(offer.commercial_name,''),NULLIF(content.commercial_name,''),content.name) name,COALESCE(NULLIF(offer.commercial_description,''),NULLIF(content.commercial_description,''),(SELECT COALESCE(NULLIF(parent.commercial_description,''),NULLIF(parent.description,'')) FROM provider_course_content_links inherited_link INNER JOIN provider_courses parent ON parent.id=inherited_link.provider_course_id WHERE inherited_link.provider_content_id=content.id ORDER BY inherited_link.position,inherited_link.provider_course_id LIMIT 1),'Curso individual disponível para matrícula.') description,COALESCE(NULLIF(content.commercial_category,''),(SELECT COALESCE(NULLIF(parent.commercial_category,''),NULLIF(parent.category,'')) FROM provider_course_content_links inherited_link INNER JOIN provider_courses parent ON parent.id=inherited_link.provider_course_id WHERE inherited_link.provider_content_id=content.id ORDER BY inherited_link.position,inherited_link.provider_course_id LIMIT 1),'Curso individual') category,COALESCE(NULLIF(content.commercial_workload,''),(SELECT COALESCE(NULLIF(parent.commercial_workload,''),NULLIF(parent.workload,'')) FROM provider_course_content_links inherited_link INNER JOIN provider_courses parent ON parent.id=inherited_link.provider_course_id WHERE inherited_link.provider_content_id=content.id ORDER BY inherited_link.position,inherited_link.provider_course_id LIMIT 1),'') workload_text,COALESCE(NULLIF(content.commercial_cover_url,''),(SELECT COALESCE(NULLIF(parent.commercial_cover_url,''),NULLIF(parent.cover_url,'')) FROM provider_course_content_links inherited_link INNER JOIN provider_courses parent ON parent.id=inherited_link.provider_course_id WHERE inherited_link.provider_content_id=content.id ORDER BY inherited_link.position,inherited_link.provider_course_id LIMIT 1),'') cover_url,COALESCE(own_asset.id,(SELECT inherited_asset.id FROM provider_course_content_links inherited_link INNER JOIN catalog_media_assets inherited_asset ON inherited_asset.entity_type='course' AND inherited_asset.entity_id=inherited_link.provider_course_id AND inherited_asset.purpose='cover' AND inherited_asset.generation_status='ready' WHERE inherited_link.provider_content_id=content.id ORDER BY inherited_link.position,inherited_link.provider_course_id LIMIT 1)) media_asset_id,catalog.name catalog_name,catalog.code catalog_code,catalog.execution_environment,provider.name provider_name,provider.delivery_mode,provider.launch_url_template,content.external_key,content.content_type FROM organization_provider_content_offers offer INNER JOIN provider_catalog_contents content ON content.id=offer.provider_content_id INNER JOIN course_catalogs catalog ON catalog.id=content.catalog_id LEFT JOIN organization_course_catalog_access access ON access.course_catalog_id=catalog.id AND access.organization_id=offer.organization_id LEFT JOIN organization_catalog_item_access item_access ON item_access.organization_id=offer.organization_id AND item_access.item_type='content' AND item_access.item_id=content.id INNER JOIN course_provider_integrations provider ON provider.id=content.provider_id LEFT JOIN catalog_media_assets own_asset ON own_asset.entity_type='content' AND own_asset.entity_id=content.id AND own_asset.purpose='cover' AND own_asset.generation_status='ready' WHERE offer.organization_id=:organization AND offer.is_active=1 AND offer.is_visible=1 AND offer.price>=5 AND content.review_status='approved' AND content.release_status IN ('released','published') AND content.is_available=1 AND content.is_globally_enabled=1 AND catalog.is_active=1 AND catalog.is_globally_enabled=1 AND COALESCE(access.is_enabled,1)=1 AND COALESCE(item_access.is_enabled,1)=1 AND (CASE WHEN catalog.allow_franchise_commercial_override=1 THEN COALESCE(access.valid_from,catalog.central_valid_from) ELSE catalog.central_valid_from END IS NULL OR CASE WHEN catalog.allow_franchise_commercial_override=1 THEN COALESCE(access.valid_from,catalog.central_valid_from) ELSE catalog.central_valid_from END<=CURRENT_DATE) AND (CASE WHEN catalog.allow_franchise_commercial_override=1 THEN COALESCE(access.valid_until,catalog.central_valid_until) ELSE catalog.central_valid_until END IS NULL OR CASE WHEN catalog.allow_franchise_commercial_override=1 THEN COALESCE(access.valid_until,catalog.central_valid_until) ELSE catalog.central_valid_until END>=CURRENT_DATE) AND provider.is_active=1 ORDER BY catalog.name,name");
        $statement->execute(['organization'=>$organizationId]);$products=array_values(array_filter($statement->fetchAll()?:[],static fn(array$product):bool=>strcasecmp(trim((string)($product['provider_name']??'')),'IESDE')!==0));
        foreach($products as&$product){$product['is_external']=1;$product['product_kind']='provider_content';$product['modality']='AVA Cursos';$product['lesson_count']=1;$product['workload_hours']=(int)(float)($product['workload_text']??0);$product['billing_types']='[]';$product['minutes_to_expire']=0;$product['seo_title']=$product['name'];$product['seo_description']=$product['description'];}
        unset($product);return$products;
    }

    /** @return array<string,mixed>|null */
    public function publicExternalProduct(int $organizationId,int $offerId):?array
    {
        foreach($this->externalProducts($organizationId)as$product)if((int)$product['id']===$offerId)return$product;
        return null;
    }

    /** @return array<string,mixed>|null */
    public function publicExternalContent(int $organizationId,int $offerId):?array
    {
        foreach($this->externalContentProducts($organizationId)as$product)if((int)$product['id']===$offerId)return$product;
        return null;
    }

    /** @return list<array<string,mixed>> */
    public function trailProducts(int $organizationId):array
    {
        $statement=$this->database->prepare("SELECT trail.id,trail.name,COALESCE(NULLIF(trail.short_description,''),NULLIF(trail.description,''),'Trilha com Cursos organizados para uma formação completa.') description,category.name category,trail.workload_hours,trail.default_price,COALESCE(access.price_override,trail.default_price) value,COALESCE(access.max_installments_override,trail.max_installments,1) max_installments,trail.cover_url,asset.id media_asset_id,publication.remote_course_id,(SELECT COUNT(*) FROM catalog_trail_items counted WHERE counted.catalog_trail_id=trail.id) lesson_count,(SELECT COALESCE(central_default_trail_access_months,12) FROM course_catalogs WHERE code='catalogo-master' LIMIT 1) access_months
            FROM catalog_trails trail
            INNER JOIN catalog_categories category ON category.id=trail.category_id AND category.is_active=1
            INNER JOIN catalog_ava_publications publication ON publication.entity_type='trail' AND publication.entity_id=trail.id AND publication.publication_status='published' AND publication.remote_course_id IS NOT NULL
            INNER JOIN ava_connections connection ON connection.id=publication.ava_connection_id AND connection.is_active=1
            LEFT JOIN organization_catalog_trail_access access ON access.organization_id=:organization_access AND access.catalog_trail_id=trail.id
            LEFT JOIN catalog_media_assets asset ON asset.entity_type='trail' AND asset.entity_id=trail.id AND asset.purpose='cover' AND asset.generation_status='ready'
            WHERE trail.is_active=1 AND (connection.connection_type='shared' OR connection.organization_id=:organization_connection) AND COALESCE(access.is_enabled,1)=1 AND COALESCE(access.is_visible,1)=1 AND COALESCE(access.price_override,trail.default_price,0)>=5
            ORDER BY category.name,trail.name");
        $statement->execute(['organization_access'=>$organizationId,'organization_connection'=>$organizationId]);
        $products=$statement->fetchAll()?:[];
        foreach($products as&$product){
            $items=$this->publicTrailItems((int)$product['id']);
            $catalogs=[];
            foreach($items as$item){$code=(string)($item['catalog_code']??'');if($code!=='')$catalogs[$code]=(string)($item['catalog_name']??$code);}
            $catalogCode=count($catalogs)===1?(string)array_key_first($catalogs):'mundo-inter';
            $catalogName=count($catalogs)===1?(string)reset($catalogs):'Mundo Inter';
            $product['is_external']=1;$product['product_kind']='trail';$product['catalog_code']=$catalogCode;$product['catalog_name']=$catalogName;$product['execution_environment']='shared_ava';$product['modality']='AVA Cursos';$product['billing_types']='[]';$product['minutes_to_expire']=0;$product['seo_title']=$product['name'];$product['seo_description']=$product['description'];$product['curriculum']=implode("\n",array_map(static fn(array$item):string=>(string)$item['name'],$items));$product['trail_items']=$items;$product['certificate_text']='Certificado disponível conforme as regras acadêmicas da Trilha.';
        }
        unset($product);return$products;
    }

    /** @return array<string,mixed>|null */
    public function publicTrail(int $organizationId,int $trailId):?array
    {
        foreach($this->trailProducts($organizationId)as$product)if((int)$product['id']===$trailId)return$product;
        return null;
    }

    /** @return list<array<string,mixed>> */
    private function publicTrailItems(int $trailId):array
    {
        $statement=$this->database->prepare("SELECT item.item_type,item.item_id,item.sort_order,
            CASE item.item_type WHEN 'finance_product' THEN product.name WHEN 'provider_course' THEN COALESCE(NULLIF(course.commercial_name,''),course.name) ELSE COALESCE(NULLIF(content.commercial_name,''),content.name) END name,
            CASE item.item_type WHEN 'finance_product' THEN 'INTER' ELSE COALESCE(course_catalog.name,content_catalog.name,'Mundo Inter') END catalog_name,
            CASE item.item_type WHEN 'finance_product' THEN 'ava-cursos' ELSE COALESCE(course_catalog.code,content_catalog.code,'mundo-inter') END catalog_code
            FROM catalog_trail_items item
            LEFT JOIN finance_products product ON item.item_type='finance_product' AND product.id=item.item_id
            LEFT JOIN provider_courses course ON item.item_type='provider_course' AND course.id=item.item_id
            LEFT JOIN course_catalogs course_catalog ON course_catalog.id=course.catalog_id
            LEFT JOIN provider_catalog_contents content ON item.item_type='provider_content' AND content.id=item.item_id
            LEFT JOIN course_catalogs content_catalog ON content_catalog.id=content.catalog_id
            WHERE item.catalog_trail_id=:trail ORDER BY item.sort_order,item.id");
        $statement->execute(['trail'=>$trailId]);
        return array_values(array_filter($statement->fetchAll()?:[],static fn(array$item):bool=>trim((string)($item['name']??''))!==''));
    }

    /**
     * Builds the public commercial inventory with one stable shape for every source.
     * Catalogs remain an administrative concept; on the public site their commercial lines are formations.
     *
     * @param list<array<string,mixed>> $products
     * @param list<array<string,mixed>> $externalProducts
     * @return list<array<string,mixed>>
     */
    private function publicOffers(array $products,array $externalProducts):array
    {
        $offers=[];
        foreach($products as$product){
            $product['product_kind']='finance_product';
            $product['is_external']=0;
            $product['catalog_code']='ava-cursos';
            $product['catalog_name']='INTER';
            $product['formation_code']='inter';
            $product['formation_name']='INTER';
            $product['offer_key']='finance-product-'.(int)$product['id'];
            $product['detail_path']='/curso/'.(int)$product['id'];
            $offers[]=$product;
        }
        foreach($externalProducts as$product){
            $formationName=preg_replace('/^\s*(?:Cat[aá]logo|Forma[cç][aã]o)\s+/iu','',(string)($product['catalog_name']??''));
            $formationName=trim((string)$formationName);
            if($formationName==='')$formationName='Parceiros';
            $kind=(string)($product['product_kind']??'provider_course');
            $product['formation_code']=$this->publicFormationCode((string)($product['catalog_code']??$formationName));
            $product['formation_name']=mb_strtoupper($formationName);
            $product['offer_key']=$kind.'-'.(int)$product['id'];
            $product['detail_path']=($kind==='provider_content'?'/conteudo/':($kind==='trail'?'/trilha/':'/catalogo-pro/')).(int)$product['id'];
            $offers[]=$product;
        }
        return$offers;
    }

    /** @return list<array{code:string,name:string,count:int}> */
    private function publicFormations(array $offers):array
    {
        $formations=[];
        foreach($offers as$offer){
            $code=(string)($offer['formation_code']??'');
            if($code==='')continue;
            if(!isset($formations[$code]))$formations[$code]=['code'=>$code,'name'=>(string)($offer['formation_name']??$code),'count'=>0];
            $formations[$code]['count']++;
        }
        return array_values($formations);
    }

    private function publicFormationCode(string$value):string
    {
        $value=preg_replace('/^catalogo-/','',$value)??$value;
        $ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);
        $code=strtolower(is_string($ascii)?$ascii:$value);
        return trim(preg_replace('/[^a-z0-9]+/','-',$code)??'','-')?:'parceiros';
    }

    /** @return array<string,mixed>|null */
    public function publicSite(int $organizationId,bool $preview=false):?array
    {
        $this->processScheduled($organizationId);
        $statement=$this->database->prepare("SELECT s.*,o.display_name,o.legal_name,o.cnpj,o.panel_slug,o.logo_path,o.favicon_path,o.primary_color,o.secondary_color,o.support_email organization_email,o.support_phone organization_phone,o.status organization_status,d.host site_host,d.status domain_status FROM organization_sites s INNER JOIN organizations o ON o.id=s.organization_id LEFT JOIN organization_domains d ON d.organization_id=o.id AND d.purpose='site' AND d.is_primary=1 WHERE s.organization_id=:organization LIMIT 1");
        $statement->execute(['organization'=>$organizationId]);$site=$statement->fetch();
        if(!is_array($site)||(int)$site['is_enabled']!==1||($site['organization_status']??'')!=='active')return null;
        $live=null;if(!$preview&&trim((string)($site['live_snapshot_json']??''))!==''){$decoded=json_decode((string)$site['live_snapshot_json'],true);if(is_array($decoded))$live=$decoded;}
        if(!$preview&&$live===null&&($site['publication_status']??'')!=='published')return null;
        if($live!==null&&isset($live['settings'])&&is_array($live['settings']))$site=array_replace($site,$live['settings']);
        $ids=$live!==null&&isset($live['product_ids'])&&is_array($live['product_ids'])?array_map('intval',$live['product_ids']):$this->selectedProductIds($organizationId);$products=[];
        if($ids!==[]){$marks=implode(',',array_fill(0,count($ids),'?'));$query=$this->database->prepare("SELECT p.id,p.unit_id,p.name,p.description,p.value,p.max_installments,p.billing_types,p.minutes_to_expire FROM finance_products p INNER JOIN organization_finance_products scope ON scope.finance_product_id=p.id AND scope.organization_id=? AND scope.is_visible=1 WHERE p.id IN ($marks) AND p.is_active=1 AND (scope.source<>'ava' OR EXISTS(SELECT 1 FROM course_catalogs catalog LEFT JOIN organization_course_catalog_access access ON access.course_catalog_id=catalog.id AND access.organization_id=? WHERE catalog.code='ava-cursos' AND catalog.is_active=1 AND catalog.is_globally_enabled=1 AND COALESCE(access.is_enabled,1)=1))");$query->execute([$organizationId,...$ids,$organizationId]);$byId=[];foreach($query->fetchAll()as$row)$byId[(int)$row['id']]=$row;foreach($ids as$id)if(isset($byId[$id]))$products[]=$byId[$id];}
        $seo=$this->productSeo($organizationId);$details=$this->productDetails($organizationId);foreach($products as&$product){$id=(int)$product['id'];if(isset($seo[$id]))$product=array_replace($product,$seo[$id]);if(isset($details[$id]))$product=array_replace($product,$details[$id]);}unset($product);
        $externalProducts=array_merge($this->externalProducts($organizationId),$this->externalContentProducts($organizationId),$this->trailProducts($organizationId));
        $offers=$this->publicOffers($products,$externalProducts);
        $site['products']=$products;
        $site['external_products']=$externalProducts;
        $site['offers']=$offers;
        $site['formations']=$this->publicFormations($offers);
        $site['banners']=$live!==null&&isset($live['banners'])&&is_array($live['banners'])?$live['banners']:$this->banners($organizationId,!$preview);
        $site['pages']=$live!==null&&isset($live['pages'])&&is_array($live['pages'])?$live['pages']:$this->pages($organizationId,!$preview);
        $site['blocks']=$live!==null&&isset($live['blocks'])&&is_array($live['blocks'])?$live['blocks']:$this->blocks($organizationId,!$preview);
        return$site;
    }

    /** @return array<string,mixed> */
    private function snapshot(int $organizationId,array $settings):array
    {
        unset($settings['live_snapshot_json'],$settings['scheduled_snapshot_json']);
        $branding=$this->database->prepare('SELECT logo_path,favicon_path FROM organizations WHERE id=:organization LIMIT 1');
        $branding->execute(['organization'=>$organizationId]);$brand=$branding->fetch();
        if(is_array($brand)){$settings['logo_path']=$brand['logo_path']??null;$settings['favicon_path']=$brand['favicon_path']??null;}
        return['settings'=>$settings,'product_ids'=>$this->selectedProductIds($organizationId),'banners'=>$this->banners($organizationId),'pages'=>$this->pages($organizationId),'blocks'=>$this->blocks($organizationId),'captured_at'=>date(DATE_ATOM)];
    }
    private function publishSnapshot(int $organizationId,array $snapshot,int $version):void{$this->database->prepare("UPDATE organization_site_versions SET status='archived' WHERE organization_id=:organization AND status='published'")->execute(['organization'=>$organizationId]);$this->database->prepare("UPDATE organization_sites SET live_snapshot_json=:snapshot,live_version=:version,publication_status='published',published_at=NOW(),scheduled_snapshot_json=NULL,scheduled_publish_at=NULL WHERE organization_id=:organization")->execute(['snapshot'=>$this->encode($snapshot),'version'=>$version,'organization'=>$organizationId]);$this->database->prepare("UPDATE organization_site_versions SET status='published',published_at=COALESCE(published_at,NOW()) WHERE organization_id=:organization AND version_number=:version")->execute(['organization'=>$organizationId,'version'=>$version]);}
    private function processScheduled(int $organizationId):void{$statement=$this->database->prepare('SELECT scheduled_snapshot_json,scheduled_publish_at FROM organization_sites WHERE organization_id=:organization LIMIT 1');$statement->execute(['organization'=>$organizationId]);$row=$statement->fetch();if(!is_array($row)||empty($row['scheduled_snapshot_json'])||empty($row['scheduled_publish_at'])||strtotime((string)$row['scheduled_publish_at'])>time())return;$snapshot=json_decode((string)$row['scheduled_snapshot_json'],true);if(!is_array($snapshot))return;$version=$this->database->prepare("SELECT id,version_number FROM organization_site_versions WHERE organization_id=:organization AND status='scheduled' ORDER BY version_number DESC LIMIT 1");$version->execute(['organization'=>$organizationId]);$item=$version->fetch();$number=is_array($item)?(int)$item['version_number']:((int)$this->settings($organizationId)['live_version']+1);$this->publishSnapshot($organizationId,$snapshot,$number);if(is_array($item))$this->database->prepare("UPDATE organization_site_versions SET status='published',published_at=NOW() WHERE id=:id")->execute(['id'=>$item['id']]);}
    private function encode(mixed$value):string{return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    private function nullable(mixed$value,int$max):?string{$text=trim((string)$value);return$text!==''?mb_substr($text,0,$max):null;}

    /** @return array<string,mixed> */
    private function editableSettings(int $organizationId):array{$settings=$this->settings($organizationId);if((int)$settings['is_enabled']!==1)throw new RuntimeException('O Site Institucional ainda não foi liberado pelo ADM Central.');return$settings;}
    private function ensure(int $organizationId):void{$statement=$this->database->prepare("INSERT IGNORE INTO organization_sites(organization_id,site_title,hero_title,hero_text,about_title,about_text,contact_email,contact_phone) SELECT id,display_name,CONCAT('Aprenda e transforme seu futuro com ',display_name),'Conheça nossos cursos e encontre a formação ideal para o seu próximo passo.','Sobre nós',CONCAT(display_name,' conecta pessoas, conhecimento e novas oportunidades.'),manager_email,manager_phone FROM organizations WHERE id=:organization");$statement->execute(['organization'=>$organizationId]);}
    private function assertOrganization(int $organizationId):void{if($organizationId<1)throw new RuntimeException('Franquia inválida.');$s=$this->database->prepare('SELECT 1 FROM organizations WHERE id=:id');$s->execute(['id'=>$organizationId]);if($s->fetchColumn()===false)throw new RuntimeException('Franquia não encontrada.');}
    private function limit(mixed$value,int$min,int$max,string$label):int{$number=(int)$value;if($number<$min||$number>$max)throw new RuntimeException("Informe entre {$min} e {$max} {$label}.");return$number;}
    private function checked(mixed$value):bool{return $value===true||(string)$value==='1'||(string)$value==='on';}
    private function color(mixed$value,string$fallback,string$label):string{$color=strtolower(trim((string)$value));if($color==='')return$fallback;if(preg_match('/^#[0-9a-f]{6}$/',$color)!==1)throw new RuntimeException('Informe uma '.$label.' válida.');return$color;}
    private function text(mixed$value,int$max,string$label,bool$required=false):?string{$text=trim((string)$value);if($required&&$text==='')throw new RuntimeException('Informe o '.$label.'.');if(mb_strlen($text)>$max)throw new RuntimeException('O campo '.$label.' excede o tamanho permitido.');return$text!==''?$text:null;}
    private function url(mixed$value,string$label):?string{$url=trim((string)$value);if($url==='')return null;if(filter_var($url,FILTER_VALIDATE_URL)===false||!str_starts_with($url,'https://'))throw new RuntimeException('Informe uma URL HTTPS válida para '.$label.'.');return$url;}
    private function link(mixed$value):?string{$link=trim((string)$value);if($link==='')return null;if(str_starts_with($link,'#')||str_starts_with($link,'/'))return$link;return$this->url($link,'o botão do banner');}
    private function slug(string$value):string{$ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',trim($value));$slug=strtolower(is_string($ascii)?$ascii:$value);$slug=trim(preg_replace('/[^a-z0-9]+/','-',$slug)??'','-');if($slug===''||strlen($slug)>120)throw new RuntimeException('Informe um endereço válido para a página.');return$slug;}
    /** @return array<string,mixed> */
    private function defaults(int$organizationId):array{return['organization_id'=>$organizationId,'is_enabled'=>0,'template_key'=>'modern','allow_catalog'=>1,'allow_store'=>0,'checkout_fulfillment_mode'=>'manual_review','allow_custom_pages'=>0,'max_banners'=>3,'max_pages'=>5,'max_featured_courses'=>6,'selected_mode'=>'catalog','publication_status'=>'draft','site_title'=>'','hero_title'=>'','hero_text'=>'','about_title'=>'','about_text'=>'','contact_email'=>'','contact_phone'=>'','whatsapp'=>'','instagram_url'=>'','facebook_url'=>'','youtube_url'=>'','linkedin_url'=>'','tiktok_url'=>'','classroom_url'=>'','classroom_label'=>'Sala de Aula','webmail_url'=>'','social_bar_enabled'=>1,'site_search_enabled'=>1,'footer_text'=>'','footer_show_legal_data'=>1,'whatsapp_button_enabled'=>1,'whatsapp_button_label'=>'Fale pelo WhatsApp','whatsapp_button_message'=>'Olá! Gostaria de saber mais sobre os cursos.','scholarship_form_enabled'=>0,'scholarship_display_mode'=>'floating','scholarship_popup_delay_seconds'=>15,'scholarship_popup_repeat_hours'=>24,'scholarship_title'=>'GANHE BOLSAS DE ESTUDOS','scholarship_subtitle'=>'Preencha e participe!','scholarship_button_label'=>'Ganhe uma bolsa','seo_title'=>'','seo_description'=>'','analytics_ga4_id'=>'','privacy_policy'=>'','cookie_notice'=>'Usamos cookies essenciais e de medição para melhorar sua experiência.','terms_text'=>'','cookie_banner_enabled'=>1,'live_version'=>null,'scheduled_publish_at'=>null,'published_at'=>null];}

    public function submitTestimonial(array $input): void
    {
        $name=trim((string)($input['author_name']??''));
        $city=trim((string)($input['author_city']??''));
        $course=trim((string)($input['course_name']??''));
        $rating=(int)($input['rating']??0);
        $text=trim((string)($input['testimonial_text']??''));
        $organizationId=(int)($input['organization_id']??0);
        if($organizationId<1)throw new RuntimeException('Franquia não encontrada.');
        if(mb_strlen($name)<2||mb_strlen($name)>160)throw new RuntimeException('Informe seu nome completo.');
        if($city!==''&&mb_strlen($city)>190)throw new RuntimeException('Informe uma cidade válida.');
        if(mb_strlen($course)<2||mb_strlen($course)>190)throw new RuntimeException('Informe o curso concluído.');
        if($rating<1||$rating>5)throw new RuntimeException('Informe uma nota de 1 a 5 estrelas.');
        if(mb_strlen($text)<10||mb_strlen($text)>2000)throw new RuntimeException('Escreva um depoimento entre 10 e 2.000 caracteres.');
        $statement=$this->database->prepare('INSERT INTO site_testimonials(organization_id,author_name,author_city,course_name,rating,testimonial_text,status) VALUES(:organization,:name,:city,:course,:rating,:text,\'pending\')');
        $statement->execute(['organization'=>$organizationId,'name'=>$name,'city'=>$city!==''?$city:null,'course'=>$course,'rating'=>$rating,'text'=>$text]);
    }

    /** @return list<array<string,mixed>> */
    public function publishedTestimonials(int $organizationId,int $limit=12):array
    {
        $limit=max(1,min(24,$limit));
        $statement=$this->database->prepare("SELECT id,author_name,author_city,course_name,rating,testimonial_text,created_at FROM site_testimonials WHERE organization_id=:organization AND status='published' ORDER BY created_at DESC,id DESC LIMIT {$limit}");
        $statement->execute(['organization'=>$organizationId]);
        return $statement->fetchAll()?:[];
    }

    /** @return list<array<string,mixed>> */
    public function allTestimonials(int $organizationId):array
    {
        $statement=$this->database->prepare("SELECT id,author_name,author_city,course_name,rating,testimonial_text,status,created_at FROM site_testimonials WHERE organization_id=:organization ORDER BY CASE status WHEN 'pending' THEN 0 ELSE 1 END,created_at DESC,id DESC LIMIT 200");
        $statement->execute(['organization'=>$organizationId]);
        return $statement->fetchAll()?:[];
    }

    public function updateTestimonialStatus(int $organizationId,int $id,string $status):void
    {
        if(!in_array($status,['pending','published'],true))throw new RuntimeException('Status inválido.');
        $statement=$this->database->prepare('UPDATE site_testimonials SET status=:status WHERE id=:id AND organization_id=:organization');
        $statement->execute(['status'=>$status,'id'=>$id,'organization'=>$organizationId]);
        if($statement->rowCount()!==1)throw new RuntimeException('Depoimento não encontrado.');
    }

    public function deleteTestimonial(int $organizationId,int $id):void
    {
        $statement=$this->database->prepare('DELETE FROM site_testimonials WHERE id=:id AND organization_id=:organization');
        $statement->execute(['id'=>$id,'organization'=>$organizationId]);
        if($statement->rowCount()!==1)throw new RuntimeException('Depoimento não encontrado.');
    }

    /** @return list<array<string,mixed>> */
    public function publishedTestimonialsForCourse(int $organizationId,string $courseName):array
    {
        $name=trim($courseName);
        if($name==='')return [];
        $statement=$this->database->prepare("SELECT id,author_name,author_city,course_name,rating,testimonial_text,created_at FROM site_testimonials WHERE organization_id=:organization AND status='published' AND course_name=:name ORDER BY created_at DESC,id DESC LIMIT 6");
        $statement->execute(['organization'=>$organizationId,'name'=>$name]);
        return $statement->fetchAll()?:[];
    }

    /** @return array{host:?string,count:int,recent:list<array<string,mixed>>} */
    public function notFoundReport(int $organizationId,int $limit=100):array
    {
        $limit=max(1,min(500,$limit));
        $domain=$this->database->prepare("SELECT d.host FROM organization_domains d WHERE d.organization_id=:organization AND d.purpose='site' AND d.is_primary=1 LIMIT 1");
        $domain->execute(['organization'=>$organizationId]);
        $host=$domain->fetchColumn();
        if(!is_string($host)||$host==='')return ['host'=>null,'count'=>0,'recent'=>[]];
        $countStatement=$this->database->prepare('SELECT COUNT(*) FROM site_404_logs WHERE host=:host');
        $countStatement->execute(['host'=>$host]);
        $count=(int)$countStatement->fetchColumn();
        $statement=$this->database->prepare("SELECT id,path,referer,ip,user_agent,created_at FROM site_404_logs WHERE host=:host ORDER BY created_at DESC,id DESC LIMIT {$limit}");
        $statement->execute(['host'=>$host]);
        return ['host'=>$host,'count'=>$count,'recent'=>$statement->fetchAll()?:[]];
    }

    public function clearNotFoundLogs(int $organizationId):int
    {
        $domain=$this->database->prepare("SELECT d.host FROM organization_domains d WHERE d.organization_id=:organization AND d.purpose='site' AND d.is_primary=1 LIMIT 1");
        $domain->execute(['organization'=>$organizationId]);
        $host=$domain->fetchColumn();
        if(!is_string($host)||$host==='')return 0;
        $statement=$this->database->prepare('DELETE FROM site_404_logs WHERE host=:host');
        $statement->execute(['host'=>$host]);
        return $statement->rowCount();
    }

    /** @return array<string,array{avg:float,count:int}> Média de avaliações publicadas por nome de curso (normalizado). */
    public function testimonialRatings(int $organizationId):array
    {
        $statement=$this->database->prepare("SELECT course_name,ROUND(AVG(rating),1) avg_rating,COUNT(*) rating_count FROM site_testimonials WHERE organization_id=:organization AND status='published' GROUP BY course_name");
        $statement->execute(['organization'=>$organizationId]);
        $map=[];
        foreach($statement->fetchAll()?:[]as$row){
            $name=mb_strtolower(trim((string)$row['course_name']));
            if($name==='')continue;
            $map[$name]=['avg'=>(float)$row['avg_rating'],'count'=>(int)$row['rating_count']];
        }
        return $map;
    }
}
