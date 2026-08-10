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
        $maxBanners=$this->limit($data['max_banners']??3,1,10,'banners');
        $maxPages=$this->limit($data['max_pages']??5,1,30,'páginas');
        $maxCourses=$this->limit($data['max_featured_courses']??6,1,24,'cursos em destaque');
        $this->ensure($organizationId);
        $statement=$this->database->prepare('UPDATE organization_sites SET is_enabled=:enabled,template_key=:template,allow_catalog=:catalog,allow_store=:store,allow_custom_pages=:pages,max_banners=:banners,max_pages=:max_pages,max_featured_courses=:courses WHERE organization_id=:organization');
        $statement->execute(['enabled'=>(int)(($data['is_enabled']??false)===true),'template'=>$template,'catalog'=>(int)(($data['allow_catalog']??false)===true),'store'=>(int)(($data['allow_store']??false)===true),'pages'=>(int)(($data['allow_custom_pages']??false)===true),'banners'=>$maxBanners,'max_pages'=>$maxPages,'courses'=>$maxCourses,'organization'=>$organizationId]);
    }

    /** @param array<string,mixed> $data @param list<int> $productIds */
    public function saveContent(int $organizationId,array $data,array $productIds):void
    {
        $this->assertOrganization($organizationId);
        $this->ensure($organizationId);
        $current=$this->settings($organizationId);
        if((int)$current['is_enabled']!==1)throw new RuntimeException('O ADM Central ainda não liberou o Site Institucional para esta franquia.');
        $mode=(string)($data['selected_mode']??'catalog');
        if(!in_array($mode,['catalog','store'],true))throw new RuntimeException('Selecione o formato do site.');
        if($mode==='catalog'&&(int)$current['allow_catalog']!==1)throw new RuntimeException('O formato catálogo não está liberado pelo ADM Central.');
        if($mode==='store'&&(int)$current['allow_store']!==1)throw new RuntimeException('O formato loja não está liberado pelo ADM Central.');
        $status=(string)($data['publication_status']??'draft');
        if(!in_array($status,['draft','published','maintenance'],true))throw new RuntimeException('Situação de publicação inválida.');
        $siteTitle=$this->text($data['site_title']??'',160,'nome do site',true);
        $heroTitle=$this->text($data['hero_title']??'',190,'título principal',true);
        $heroText=$this->text($data['hero_text']??'',700,'texto principal');
        $aboutTitle=$this->text($data['about_title']??'',160,'título institucional');
        $aboutText=$this->text($data['about_text']??'',5000,'texto institucional');
        $email=strtolower(trim((string)($data['contact_email']??'')));
        if($email!==''&&filter_var($email,FILTER_VALIDATE_EMAIL)===false)throw new RuntimeException('Informe um e-mail de contato válido.');
        $phone=$this->text($data['contact_phone']??'',30,'telefone');
        $whatsapp=$this->text($data['whatsapp']??'',30,'WhatsApp');
        $instagram=$this->url($data['instagram_url']??'','Instagram');
        $facebook=$this->url($data['facebook_url']??'','Facebook');
        $seoTitle=$this->text($data['seo_title']??'',190,'título de busca');
        $seoDescription=$this->text($data['seo_description']??'',320,'descrição de busca');
        $productIds=array_values(array_unique(array_filter(array_map('intval',$productIds),static fn(int$id):bool=>$id>0)));
        if(count($productIds)>(int)$current['max_featured_courses'])throw new RuntimeException('Selecione no máximo '.(int)$current['max_featured_courses'].' cursos em destaque.');
        $allowed=array_column($this->availableProducts($organizationId),'id');
        if(array_diff($productIds,array_map('intval',$allowed))!==[])throw new RuntimeException('Um dos cursos selecionados não pertence ao catálogo disponível da franquia.');
        $this->database->beginTransaction();
        try{
            $statement=$this->database->prepare("UPDATE organization_sites SET selected_mode=:mode,publication_status=:status,site_title=:site_title,hero_title=:hero_title,hero_text=:hero_text,about_title=:about_title,about_text=:about_text,contact_email=:email,contact_phone=:phone,whatsapp=:whatsapp,instagram_url=:instagram,facebook_url=:facebook,seo_title=:seo_title,seo_description=:seo_description,published_at=CASE WHEN :status_value='published' THEN COALESCE(published_at,NOW()) ELSE published_at END WHERE organization_id=:organization");
            $statement->execute(['mode'=>$mode,'status'=>$status,'site_title'=>$siteTitle,'hero_title'=>$heroTitle,'hero_text'=>$heroText,'about_title'=>$aboutTitle,'about_text'=>$aboutText,'email'=>$email!==''?$email:null,'phone'=>$phone,'whatsapp'=>$whatsapp,'instagram'=>$instagram,'facebook'=>$facebook,'seo_title'=>$seoTitle,'seo_description'=>$seoDescription,'status_value'=>$status,'organization'=>$organizationId]);
            $this->database->prepare('DELETE FROM organization_site_products WHERE organization_id=:organization')->execute(['organization'=>$organizationId]);
            $insert=$this->database->prepare('INSERT INTO organization_site_products(organization_id,finance_product_id,sort_order) VALUES(:organization,:product,:sort_order)');
            foreach($productIds as$order=>$productId)$insert->execute(['organization'=>$organizationId,'product'=>$productId,'sort_order'=>$order]);
            $this->database->commit();
        }catch(Throwable$exception){if($this->database->inTransaction())$this->database->rollBack();throw$exception;}
    }

    /** @return list<array<string,mixed>> */
    public function availableProducts(int $organizationId):array
    {
        $statement=$this->database->prepare('SELECT DISTINCT p.id,p.name,p.description,p.value,p.max_installments FROM finance_products p LEFT JOIN units u ON u.id=p.unit_id WHERE p.is_active=1 AND p.value>=5 AND (p.unit_id IS NULL OR u.organization_id=:organization) ORDER BY p.name,p.id');
        $statement->execute(['organization'=>$organizationId]);
        return $statement->fetchAll();
    }

    /** @return list<int> */
    public function selectedProductIds(int $organizationId):array
    {
        $statement=$this->database->prepare('SELECT finance_product_id FROM organization_site_products WHERE organization_id=:organization ORDER BY sort_order,finance_product_id');
        $statement->execute(['organization'=>$organizationId]);
        return array_map('intval',$statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array<string,mixed>|null */
    public function publicSite(int $organizationId,bool $preview=false):?array
    {
        $statement=$this->database->prepare("SELECT s.*,o.display_name,o.legal_name,o.cnpj,o.panel_slug,o.logo_path,o.favicon_path,o.primary_color,o.secondary_color,o.support_email organization_email,o.support_phone organization_phone,o.status organization_status,d.host site_host,d.status domain_status FROM organization_sites s INNER JOIN organizations o ON o.id=s.organization_id LEFT JOIN organization_domains d ON d.organization_id=o.id AND d.purpose='site' AND d.is_primary=1 WHERE s.organization_id=:organization LIMIT 1");
        $statement->execute(['organization'=>$organizationId]);$site=$statement->fetch();
        if(!is_array($site)||(int)$site['is_enabled']!==1||($site['organization_status']??'')!=='active')return null;
        if(!$preview&&($site['publication_status']??'')!=='published')return null;
        $ids=$this->selectedProductIds($organizationId);$products=[];
        if($ids!==[]){$marks=implode(',',array_fill(0,count($ids),'?'));$query=$this->database->prepare("SELECT id,name,description,value,max_installments FROM finance_products WHERE id IN ($marks) AND is_active=1");$query->execute($ids);$byId=[];foreach($query->fetchAll()as$row)$byId[(int)$row['id']]=$row;foreach($ids as$id)if(isset($byId[$id]))$products[]=$byId[$id];}
        $site['products']=$products;
        return$site;
    }

    private function ensure(int $organizationId):void
    {
        $statement=$this->database->prepare("INSERT IGNORE INTO organization_sites(organization_id,site_title,hero_title,hero_text,about_title,about_text,contact_email,contact_phone) SELECT id,display_name,CONCAT('Aprenda e transforme seu futuro com ',display_name),'Conheça nossos cursos e encontre a formação ideal para o seu próximo passo.','Sobre nós',CONCAT(display_name,' conecta pessoas, conhecimento e novas oportunidades.'),manager_email,manager_phone FROM organizations WHERE id=:organization");
        $statement->execute(['organization'=>$organizationId]);
    }

    private function assertOrganization(int $organizationId):void
    {if($organizationId<1)throw new RuntimeException('Franquia inválida.');$s=$this->database->prepare('SELECT 1 FROM organizations WHERE id=:id');$s->execute(['id'=>$organizationId]);if($s->fetchColumn()===false)throw new RuntimeException('Franquia não encontrada.');}
    private function limit(mixed$value,int$min,int$max,string$label):int{$number=(int)$value;if($number<$min||$number>$max)throw new RuntimeException("Informe entre {$min} e {$max} {$label}.");return$number;}
    private function text(mixed$value,int$max,string$label,bool$required=false):?string{$text=trim((string)$value);if($required&&$text==='')throw new RuntimeException('Informe o '.$label.'.');if(mb_strlen($text)>$max)throw new RuntimeException('O campo '.$label.' excede o tamanho permitido.');return$text!==''?$text:null;}
    private function url(mixed$value,string$label):?string{$url=trim((string)$value);if($url==='' )return null;if(filter_var($url,FILTER_VALIDATE_URL)===false||!str_starts_with($url,'https://'))throw new RuntimeException('Informe uma URL HTTPS válida para '.$label.'.');return$url;}
    /** @return array<string,mixed> */
    private function defaults(int$organizationId):array{return['organization_id'=>$organizationId,'is_enabled'=>0,'template_key'=>'modern','allow_catalog'=>1,'allow_store'=>0,'allow_custom_pages'=>0,'max_banners'=>3,'max_pages'=>5,'max_featured_courses'=>6,'selected_mode'=>'catalog','publication_status'=>'draft','site_title'=>'','hero_title'=>'','hero_text'=>'','about_title'=>'','about_text'=>'','contact_email'=>'','contact_phone'=>'','whatsapp'=>'','instagram_url'=>'','facebook_url'=>'','seo_title'=>'','seo_description'=>'','published_at'=>null];}
}
