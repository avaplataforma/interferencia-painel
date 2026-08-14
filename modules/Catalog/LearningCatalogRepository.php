<?php

declare(strict_types=1);

namespace Interferencia\Modules\Catalog;

use PDO;
use RuntimeException;

final readonly class LearningCatalogRepository
{
    public function __construct(private PDO $database)
    {
    }

    /** @return list<array<string,mixed>> */
    public function categories(): array
    {
        $statement = $this->database->query("SELECT category.*,parent.name parent_name,
            (SELECT COUNT(*) FROM catalog_categories child WHERE child.parent_id=category.id) child_count,
            (SELECT COUNT(*) FROM catalog_trails trail WHERE trail.category_id=category.id) trail_count
            FROM catalog_categories category
            LEFT JOIN catalog_categories parent ON parent.id=category.parent_id
            ORDER BY COALESCE(parent.sort_order,category.sort_order),COALESCE(parent.name,category.name),category.parent_id IS NOT NULL,category.sort_order,category.name");
        return $statement->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function categoryOptions(bool $activeOnly = true): array
    {
        $sql = "SELECT category.id,category.parent_id,category.name,category.code,parent.name parent_name
            FROM catalog_categories category LEFT JOIN catalog_categories parent ON parent.id=category.parent_id";
        if ($activeOnly) $sql .= ' WHERE category.is_active=1';
        $sql .= ' ORDER BY COALESCE(parent.sort_order,category.sort_order),COALESCE(parent.name,category.name),category.parent_id IS NOT NULL,category.sort_order,category.name';
        return $this->database->query($sql)->fetchAll() ?: [];
    }

    public function saveCategory(?int $id, array $data, ?int $userId): int
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') throw new RuntimeException('Informe o nome da categoria.');
        $code = $this->slug((string)($data['code'] ?? $name));
        if ($code === '') throw new RuntimeException('Informe um código válido para a categoria.');
        $parentId = (int)($data['parent_id'] ?? 0);
        if ($parentId < 1) $parentId = null;
        if ($id !== null && $parentId === $id) throw new RuntimeException('Uma categoria não pode ser subordinada a ela mesma.');
        if ($parentId !== null) {
            $parent = $this->database->prepare('SELECT parent_id FROM catalog_categories WHERE id=:id');
            $parent->execute(['id' => $parentId]);
            $parentRow = $parent->fetch();
            if (!is_array($parentRow)) throw new RuntimeException('Categoria principal não encontrada.');
            if ($parentRow['parent_id'] !== null) throw new RuntimeException('Use somente dois níveis: categoria principal e subcategoria.');
        }
        $values = [
            'parent' => $parentId,
            'name' => $name,
            'code' => $code,
            'description' => trim((string)($data['description'] ?? '')) ?: null,
            'sort_order' => max(0, min(999, (int)($data['sort_order'] ?? 0))),
            'is_active' => (int)(bool)($data['is_active'] ?? false),
        ];
        if ($id === null) {
            $statement = $this->database->prepare('INSERT INTO catalog_categories(parent_id,name,code,description,sort_order,is_active,created_by,updated_by) VALUES(:parent,:name,:code,:description,:sort_order,:is_active,:created_by,:updated_by)');
            $statement->execute($values + ['created_by' => $userId, 'updated_by' => $userId]);
            return (int)$this->database->lastInsertId();
        }
        $statement = $this->database->prepare('UPDATE catalog_categories SET parent_id=:parent,name=:name,code=:code,description=:description,sort_order=:sort_order,is_active=:is_active,updated_by=:updated_by WHERE id=:id');
        $statement->execute($values + ['updated_by' => $userId, 'id' => $id]);
        if ($statement->rowCount() < 1 && !$this->exists('catalog_categories', $id)) throw new RuntimeException('Categoria não encontrada.');
        return $id;
    }

    public function deleteCategory(int $id): void
    {
        $statement = $this->database->prepare('SELECT (SELECT COUNT(*) FROM catalog_categories WHERE parent_id=:id)+(SELECT COUNT(*) FROM catalog_trails WHERE category_id=:id) dependencies');
        $statement->execute(['id' => $id]);
        if ((int)$statement->fetchColumn() > 0) throw new RuntimeException('Esta categoria possui subcategorias ou Trilhas vinculadas. Desative-a em vez de excluir.');
        $delete = $this->database->prepare('DELETE FROM catalog_categories WHERE id=:id');
        $delete->execute(['id' => $id]);
        if ($delete->rowCount() !== 1) throw new RuntimeException('Categoria não encontrada.');
    }

    /** @return list<array<string,mixed>> */
    public function trails(?string $providerCode = null): array
    {
        $statement = $this->database->query("SELECT trail.*,category.name category_name,parent.name parent_category_name,
            (SELECT COUNT(*) FROM catalog_trail_items item WHERE item.catalog_trail_id=trail.id) item_count,
            (SELECT COUNT(*) FROM organization_catalog_trail_access access WHERE access.catalog_trail_id=trail.id AND access.is_enabled=0) blocked_organizations,
            COALESCE(publication.publication_status,CASE WHEN trail.is_active=1 THEN 'ready' ELSE 'draft' END) publication_status,publication.remote_course_id,publication.published_at,publication.last_error publication_error
            FROM catalog_trails trail
            INNER JOIN catalog_categories category ON category.id=trail.category_id
            LEFT JOIN catalog_categories parent ON parent.id=category.parent_id
            LEFT JOIN catalog_ava_publications publication ON publication.entity_type='trail' AND publication.entity_id=trail.id
            ORDER BY trail.is_active DESC,COALESCE(parent.name,category.name),category.name,trail.name");
        $trails = $statement->fetchAll() ?: [];
        $providerCode = trim((string)$providerCode);
        if ($providerCode === '') return $trails;
        return array_values(array_filter($trails, fn(array $trail): bool => $this->trailBelongsToProvider((int)$trail['id'], $providerCode)));
    }

    /** @return list<array<string,mixed>> */
    public function enrollmentTrailsForOrganization(int $organizationId): array
    {
        if ($organizationId < 1) return [];
        $statement = $this->database->prepare("SELECT trail.id,trail.name,trail.default_price,
            COALESCE(access.price_override,trail.default_price) price,
            COALESCE(access.max_installments_override,trail.max_installments,1) max_installments,
            publication.moodle_course_id,publication.ava_connection_id,publication.remote_course_id,
            connection.name ava_connection_name,connection.connection_type,
            (SELECT COUNT(*) FROM catalog_trail_items item WHERE item.catalog_trail_id=trail.id) item_count
            FROM catalog_trails trail
            INNER JOIN catalog_ava_publications publication ON publication.entity_type='trail' AND publication.entity_id=trail.id AND publication.publication_status='published'
            INNER JOIN ava_connections connection ON connection.id=publication.ava_connection_id AND connection.is_active=1
            LEFT JOIN organization_catalog_trail_access access ON access.organization_id=:organization_access AND access.catalog_trail_id=trail.id
            WHERE trail.is_active=1
              AND publication.moodle_course_id IS NOT NULL
              AND publication.remote_course_id IS NOT NULL
              AND (connection.connection_type='shared' OR connection.organization_id=:organization_connection)
              AND COALESCE(access.is_enabled,1)=1
              AND COALESCE(access.is_visible,1)=1
              AND COALESCE(access.price_override,trail.default_price,0)>=5
            ORDER BY trail.name");
        $statement->execute(['organization_access' => $organizationId, 'organization_connection' => $organizationId]);
        return $statement->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function enrollmentProviderCoursesForOrganization(int $organizationId): array
    {
        if ($organizationId < 1) return [];
        $statement = $this->database->prepare("SELECT offer.id offer_id,
            COALESCE(NULLIF(offer.commercial_name,''),NULLIF(course.commercial_name,''),course.name) name,
            offer.price,offer.max_installments,catalog.name formation_name,provider.name provider_name,
            publication.moodle_course_id,publication.ava_connection_id,publication.remote_course_id,
            connection.name ava_connection_name,connection.connection_type
            FROM organization_provider_course_offers offer
            INNER JOIN provider_courses course ON course.id=offer.provider_course_id
            INNER JOIN course_catalogs catalog ON catalog.id=course.catalog_id
            INNER JOIN course_provider_integrations provider ON provider.id=course.provider_id AND provider.is_active=1
            INNER JOIN catalog_ava_publications publication ON publication.entity_type='provider_course' AND publication.entity_id=course.id AND publication.publication_status='published'
            INNER JOIN ava_connections connection ON connection.id=publication.ava_connection_id AND connection.is_active=1
            LEFT JOIN organization_course_catalog_access catalog_access ON catalog_access.organization_id=offer.organization_id AND catalog_access.course_catalog_id=catalog.id
            LEFT JOIN organization_catalog_item_access item_access ON item_access.organization_id=offer.organization_id AND item_access.item_type='course' AND item_access.item_id=course.id
            WHERE offer.organization_id=:organization_offer
              AND offer.is_active=1 AND offer.is_visible=1 AND offer.price>=5
              AND course.review_status='approved' AND course.release_status IN ('released','published')
              AND course.is_available=1 AND course.is_globally_enabled=1
              AND catalog.is_active=1 AND catalog.is_globally_enabled=1
              AND COALESCE(catalog_access.is_enabled,1)=1 AND COALESCE(item_access.is_enabled,1)=1
              AND publication.moodle_course_id IS NOT NULL AND publication.remote_course_id IS NOT NULL
              AND (connection.connection_type='shared' OR connection.organization_id=:organization_connection)
            ORDER BY catalog.name,name");
        $statement->execute(['organization_offer' => $organizationId, 'organization_connection' => $organizationId]);
        return $statement->fetchAll() ?: [];
    }

    /** @return array<string,mixed>|null */
    public function trail(int $id): ?array
    {
        if ($id < 1) return null;
        $statement = $this->database->prepare("SELECT trail.*,COALESCE(publication.publication_status,CASE WHEN trail.is_active=1 THEN 'ready' ELSE 'draft' END) publication_status,publication.remote_course_id,publication.remote_category_id,publication.published_at,publication.last_error publication_error,asset.id media_asset_id,asset.generation_status media_status,asset.generation_error media_error FROM catalog_trails trail LEFT JOIN catalog_ava_publications publication ON publication.entity_type='trail' AND publication.entity_id=trail.id LEFT JOIN catalog_media_assets asset ON asset.entity_type='trail' AND asset.entity_id=trail.id AND asset.purpose='cover' WHERE trail.id=:id LIMIT 1");
        $statement->execute(['id' => $id]);
        $trail = $statement->fetch();
        if (!is_array($trail)) return null;
        $items = $this->database->prepare('SELECT item_type,item_id,sort_order FROM catalog_trail_items WHERE catalog_trail_id=:id ORDER BY sort_order,id');
        $items->execute(['id' => $id]);
        $trail['item_keys'] = array_map(static fn(array $item): string => $item['item_type'].':'.$item['item_id'], $items->fetchAll() ?: []);
        return $trail;
    }

    /** @return array<string,mixed>|null */
    public function trailPublicationContext(int $id): ?array
    {
        $statement=$this->database->prepare("SELECT trail.*,category.name category_name,category.code category_code,parent.name parent_category_name,parent.code parent_category_code,asset.id media_asset_id,asset.storage_path media_storage_path,asset.mime_type media_mime_type,(SELECT COUNT(*) FROM catalog_trail_items item WHERE item.catalog_trail_id=trail.id) item_count FROM catalog_trails trail INNER JOIN catalog_categories category ON category.id=trail.category_id LEFT JOIN catalog_categories parent ON parent.id=category.parent_id LEFT JOIN catalog_media_assets asset ON asset.entity_type='trail' AND asset.entity_id=trail.id AND asset.purpose='cover' AND asset.generation_status='ready' WHERE trail.id=:id LIMIT 1");
        $statement->execute(['id'=>$id]);$trail=$statement->fetch();
        if(!is_array($trail))return null;
        $items=$this->database->prepare("SELECT item.item_type,item.item_id,item.sort_order,
            CASE item.item_type WHEN 'finance_product' THEN product.name WHEN 'provider_course' THEN COALESCE(NULLIF(course.commercial_name,''),course.name) ELSE COALESCE(NULLIF(content.commercial_name,''),content.name) END item_name,
            CASE item.item_type WHEN 'finance_product' THEN 'INTER' ELSE COALESCE(course_catalog.name,content_catalog.name,'Formação externa') END item_catalog,
            CASE item.item_type WHEN 'finance_product' THEN 'shared_ava' ELSE COALESCE(course_catalog.execution_environment,content_catalog.execution_environment,'provider_ava') END execution_environment,
            CASE item.item_type WHEN 'finance_product' THEN 1 ELSE COALESCE(course_catalog.is_shared_ava_enabled,content_catalog.is_shared_ava_enabled,0) END shared_ava_enabled,
            COALESCE(course_provider.delivery_mode,content_provider.delivery_mode,'shared_ava') delivery_mode,
            COALESCE(course_provider.provider_code,content_provider.provider_code,'') provider_code,
            COALESCE(course_provider.launch_url_template,content_provider.launch_url_template) launch_url_template,
            CASE item.item_type WHEN 'provider_content' THEN content.content_type WHEN 'provider_course' THEN COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(course.raw_payload,'$.type')),''),'course') ELSE '' END content_type,
            CASE item.item_type WHEN 'finance_product' THEN CAST(product.id AS CHAR) WHEN 'provider_course' THEN COALESCE(course.remote_id,course.external_key) ELSE content.external_key END remote_reference
            FROM catalog_trail_items item
            LEFT JOIN finance_products product ON item.item_type='finance_product' AND product.id=item.item_id
            LEFT JOIN provider_courses course ON item.item_type='provider_course' AND course.id=item.item_id
            LEFT JOIN course_catalogs course_catalog ON course_catalog.id=course.catalog_id
            LEFT JOIN course_provider_integrations course_provider ON course_provider.id=course.provider_id
            LEFT JOIN provider_catalog_contents content ON item.item_type='provider_content' AND content.id=item.item_id
            LEFT JOIN course_catalogs content_catalog ON content_catalog.id=content.catalog_id
            LEFT JOIN course_provider_integrations content_provider ON content_provider.id=content.provider_id
            WHERE item.catalog_trail_id=:id ORDER BY item.sort_order,item.id");
        $items->execute(['id'=>$id]);$trail['items']=$items->fetchAll()?:[];
        return$trail;
    }

    /** @return list<array<string,mixed>> */
    public function publicationHistory(int $trailId,int $limit=12):array
    {
        $limit=max(1,min(50,$limit));
        $statement=$this->database->prepare("SELECT event.*,user.name user_name FROM catalog_ava_publication_events event INNER JOIN catalog_ava_publications publication ON publication.id=event.publication_id LEFT JOIN platform_users user ON user.id=event.created_by WHERE publication.entity_type='trail' AND publication.entity_id=:trail ORDER BY event.created_at DESC,event.id DESC LIMIT {$limit}");
        $statement->execute(['trail'=>$trailId]);
        $events=$statement->fetchAll()?:[];
        foreach($events as&$event){
            $decoded=json_decode((string)($event['details_json']??''),true);
            $event['details']=is_array($decoded)?$decoded:[];
        }
        unset($event);
        return$events;
    }

    public function updateTrailGeneratedText(int $id,string $shortDescription,string $description,?int $userId):void
    {
        $shortDescription=trim($shortDescription);$description=trim($description);
        if($shortDescription===''||$description==='')throw new RuntimeException('A IA não retornou textos completos para a Trilha.');
        $statement=$this->database->prepare('UPDATE catalog_trails SET short_description=:short_description,description=:description,updated_by=:user WHERE id=:id');
        $statement->execute(['short_description'=>mb_substr($shortDescription,0,500),'description'=>$description,'user'=>$userId,'id'=>$id]);
        if($statement->rowCount()<1&&!$this->exists('catalog_trails',$id))throw new RuntimeException('Trilha não encontrada.');
    }

    public function markPublicationReady(int$trailId,int$connectionId,string$signature,?int$userId):int
    {
        $sql="INSERT INTO catalog_ava_publications(entity_type,entity_id,ava_connection_id,publication_status,source_signature,prepared_at,created_by,updated_by) VALUES('trail',:entity,:connection,'ready',:signature,NOW(),:created_by,:updated_by) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),publication_status='ready',source_signature=VALUES(source_signature),prepared_at=NOW(),last_error=NULL,updated_by=VALUES(updated_by)";
        $this->database->prepare($sql)->execute(['entity'=>$trailId,'connection'=>$connectionId,'signature'=>$signature,'created_by'=>$userId,'updated_by'=>$userId]);
        return(int)$this->database->lastInsertId();
    }

    public function markEntityPublicationReady(string$entityType,int$entityId,int$connectionId,string$signature,?int$userId):int
    {
        if(!in_array($entityType,['finance_product','provider_course','provider_content'],true)||$entityId<1||$connectionId<1)throw new RuntimeException('Item inválido para publicação no AVA.');
        $sql="INSERT INTO catalog_ava_publications(entity_type,entity_id,ava_connection_id,publication_status,source_signature,prepared_at,created_by,updated_by) VALUES(:type,:entity,:connection,'ready',:signature,NOW(),:created_by,:updated_by) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),publication_status='ready',source_signature=VALUES(source_signature),prepared_at=NOW(),last_error=NULL,updated_by=VALUES(updated_by)";
        $this->database->prepare($sql)->execute(['type'=>$entityType,'entity'=>$entityId,'connection'=>$connectionId,'signature'=>$signature,'created_by'=>$userId,'updated_by'=>$userId]);
        return(int)$this->database->lastInsertId();
    }

    public function markPublicationSuccess(int$publicationId,int$localCourseId,int$remoteCategoryId,int$remoteCourseId,string$signature,array$details,?int$userId):void
    {
        $this->database->beginTransaction();
        try{
            $this->database->prepare("UPDATE catalog_ava_publications SET publication_status='published',moodle_course_id=:local_course,remote_category_id=:remote_category,remote_course_id=:remote_course,source_signature=:signature,last_error=NULL,published_at=NOW(),updated_by=:user WHERE id=:id")->execute(['local_course'=>$localCourseId>0?$localCourseId:null,'remote_category'=>$remoteCategoryId,'remote_course'=>$remoteCourseId,'signature'=>$signature,'user'=>$userId,'id'=>$publicationId]);
            $this->publicationEvent($publicationId,'publish','success',$remoteCategoryId,$remoteCourseId,'Trilha publicada ou atualizada no AVA Cursos.',$details,$userId);
            $this->database->commit();
        }catch(\Throwable$exception){if($this->database->inTransaction())$this->database->rollBack();throw$exception;}
    }

    public function markEntityPublicationSuccess(int$publicationId,int$localCourseId,int$remoteCategoryId,int$remoteCourseId,string$signature,string$message,array$details,?int$userId):void
    {
        $this->database->beginTransaction();
        try{
            $this->database->prepare("UPDATE catalog_ava_publications SET publication_status='published',moodle_course_id=:local_course,remote_category_id=:remote_category,remote_course_id=:remote_course,source_signature=:signature,last_error=NULL,published_at=NOW(),updated_by=:user WHERE id=:id")->execute(['local_course'=>$localCourseId>0?$localCourseId:null,'remote_category'=>$remoteCategoryId,'remote_course'=>$remoteCourseId,'signature'=>$signature,'user'=>$userId,'id'=>$publicationId]);
            $this->publicationEvent($publicationId,'publish','success',$remoteCategoryId,$remoteCourseId,$message,$details,$userId);
            $this->database->commit();
        }catch(\Throwable$exception){if($this->database->inTransaction())$this->database->rollBack();throw$exception;}
    }

    public function markPublicationFailed(int$publicationId,string$message,?int$userId):void
    {
        $message=mb_substr(trim($message),0,1000);
        $this->database->beginTransaction();
        try{
            $this->database->prepare("UPDATE catalog_ava_publications SET publication_status='failed',last_error=:error,updated_by=:user WHERE id=:id")->execute(['error'=>$message,'user'=>$userId,'id'=>$publicationId]);
            $this->publicationEvent($publicationId,'publish','failed',null,null,$message,[],$userId);
            $this->database->commit();
        }catch(\Throwable$exception){if($this->database->inTransaction())$this->database->rollBack();throw$exception;}
    }

    /** @return list<array<string,mixed>> */
    public function availableItems(?string $providerCode = null): array
    {
        $items = [];
        $providerCode = trim((string)$providerCode);
        if ($providerCode === '') {
            $finance = $this->database->query("SELECT product.id,product.name,product.description,product.value price,'INTER' catalog_name,'shared_ava' execution_environment,'finance_product' item_type
                FROM finance_products product WHERE product.is_active=1 ORDER BY product.name")->fetchAll() ?: [];
            foreach ($finance as $item) $items[] = $item;
        }
        $courseSql = "SELECT course.id,COALESCE(NULLIF(course.commercial_name,''),course.name) name,COALESCE(NULLIF(course.commercial_description,''),course.description) description,course.remote_reference_price price,catalog.name catalog_name,catalog.execution_environment,'provider_course' item_type,'' package_ids,0 package_count
            FROM provider_courses course INNER JOIN course_catalogs catalog ON catalog.id=course.catalog_id INNER JOIN course_provider_integrations provider ON provider.id=course.provider_id
            WHERE course.is_available=1 AND course.is_globally_enabled=1 AND catalog.is_active=1 AND catalog.is_globally_enabled=1
              AND (provider.provider_code='iesde' OR NOT EXISTS(SELECT 1 FROM provider_course_content_links package_link WHERE package_link.provider_course_id=course.id))";
        if ($providerCode !== '') $courseSql .= ' AND provider.provider_code=:provider';
        $courseSql .= ' ORDER BY catalog.name,name';
        $courseStatement = $this->database->prepare($courseSql);
        $courseStatement->execute($providerCode !== '' ? ['provider' => $providerCode] : []);
        $courses = $courseStatement->fetchAll() ?: [];
        foreach ($courses as $item) $items[] = $item;
        $contentSql = "SELECT content.id,COALESCE(NULLIF(content.commercial_name,''),content.name) name,content.commercial_description description,NULL price,catalog.name catalog_name,catalog.execution_environment,'provider_content' item_type,
                COALESCE((SELECT GROUP_CONCAT(DISTINCT package_link.provider_course_id ORDER BY package_link.provider_course_id SEPARATOR ',') FROM provider_course_content_links package_link WHERE package_link.provider_content_id=content.id),'') package_ids,
                (SELECT COUNT(DISTINCT package_link.provider_course_id) FROM provider_course_content_links package_link WHERE package_link.provider_content_id=content.id) package_count
            FROM provider_catalog_contents content INNER JOIN course_catalogs catalog ON catalog.id=content.catalog_id INNER JOIN course_provider_integrations provider ON provider.id=content.provider_id
            WHERE content.is_available=1 AND content.is_globally_enabled=1 AND catalog.is_active=1 AND catalog.is_globally_enabled=1 AND provider.provider_code<>'iesde'";
        if ($providerCode !== '') $contentSql .= ' AND provider.provider_code=:provider';
        $contentSql .= ' ORDER BY catalog.name,name';
        $contentStatement = $this->database->prepare($contentSql);
        $contentStatement->execute($providerCode !== '' ? ['provider' => $providerCode] : []);
        $contents = $contentStatement->fetchAll() ?: [];
        foreach ($contents as $item) $items[] = $item;
        return $items;
    }

    /** @return list<array<string,mixed>> */
    public function availablePackages(?string $providerCode = null): array
    {
        $providerCode = trim((string)$providerCode);
        if ($providerCode === 'iesde') return [];
        $sql = "SELECT course.id,COALESCE(NULLIF(course.commercial_name,''),course.name) name,catalog.name catalog_name,COUNT(DISTINCT link.provider_content_id) item_count
            FROM provider_courses course
            INNER JOIN course_catalogs catalog ON catalog.id=course.catalog_id
            INNER JOIN course_provider_integrations provider ON provider.id=course.provider_id
            INNER JOIN provider_course_content_links link ON link.provider_course_id=course.id
            INNER JOIN provider_catalog_contents content ON content.id=link.provider_content_id AND content.is_available=1 AND content.is_globally_enabled=1
            WHERE course.is_available=1 AND course.is_globally_enabled=1 AND catalog.is_active=1 AND catalog.is_globally_enabled=1 AND provider.provider_code<>'iesde'";
        if ($providerCode !== '') $sql .= ' AND provider.provider_code=:provider';
        $sql .= "
            GROUP BY course.id,course.commercial_name,course.name,catalog.name
            ORDER BY catalog.name,name";
        $statement = $this->database->prepare($sql);
        $statement->execute($providerCode !== '' ? ['provider' => $providerCode] : []);
        return $statement->fetchAll() ?: [];
    }

    /** @param list<string> $itemKeys */
    public function saveTrail(?int $id, array $data, array $itemKeys, ?int $userId, ?string $providerScope = null): int
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') throw new RuntimeException('Informe o nome da Trilha.');
        $categoryId = (int)($data['category_id'] ?? 0);
        $category = $this->database->prepare('SELECT id FROM catalog_categories WHERE id=:id AND is_active=1');
        $category->execute(['id' => $categoryId]);
        if ($category->fetchColumn() === false) throw new RuntimeException('Escolha uma categoria ativa para a Trilha.');
        $items = $this->normalizeItems($itemKeys);
        $providerScope = trim((string)$providerScope);
        if ($providerScope !== '') $this->assertItemsBelongToProvider($items, $providerScope);
        if (count($items) < 2) throw new RuntimeException('Uma Trilha precisa reunir pelo menos dois cursos ou conteúdos.');
        $slug = $this->slug((string)($data['slug'] ?? ''));
        if ($slug === '') $slug = $this->slug($name);
        if ($slug === '') throw new RuntimeException('Informe um endereço válido para a Trilha.');
        $workload = $this->positiveDecimal((string)($data['workload_hours'] ?? ''), 'Informe a carga horária total da Trilha.');
        $rawPrice = trim((string)($data['default_price'] ?? ''));
        $price = $rawPrice === '' ? null : $this->money($rawPrice);
        if ($price !== null && $price < 0) throw new RuntimeException('O preço não pode ser negativo.');
        $values = [
            'category' => $categoryId,
            'name' => $name,
            'slug' => $slug,
            'short_description' => trim((string)($data['short_description'] ?? '')) ?: null,
            'description' => trim((string)($data['description'] ?? '')) ?: null,
            'workload' => $workload,
            'price' => $price,
            'installments' => max(1, min(60, (int)($data['max_installments'] ?? 1))),
            'cover' => trim((string)($data['cover_url'] ?? '')) ?: null,
            'active' => (int)(bool)($data['is_active'] ?? false),
        ];
        $this->database->beginTransaction();
        try {
            if ($id === null) {
                $statement = $this->database->prepare('INSERT INTO catalog_trails(category_id,name,slug,short_description,description,workload_hours,default_price,max_installments,cover_url,is_active,created_by,updated_by) VALUES(:category,:name,:slug,:short_description,:description,:workload,:price,:installments,:cover,:active,:created_by,:updated_by)');
                $statement->execute($values + ['created_by' => $userId, 'updated_by' => $userId]);
                $id = (int)$this->database->lastInsertId();
            } else {
                $statement = $this->database->prepare('UPDATE catalog_trails SET category_id=:category,name=:name,slug=:slug,short_description=:short_description,description=:description,workload_hours=:workload,default_price=:price,max_installments=:installments,cover_url=:cover,is_active=:active,updated_by=:updated_by WHERE id=:id');
                $statement->execute($values + ['updated_by' => $userId, 'id' => $id]);
                if ($statement->rowCount() < 1 && !$this->exists('catalog_trails', $id)) throw new RuntimeException('Trilha não encontrada.');
            }
            $this->database->prepare('DELETE FROM catalog_trail_items WHERE catalog_trail_id=:id')->execute(['id' => $id]);
            $insert = $this->database->prepare('INSERT INTO catalog_trail_items(catalog_trail_id,item_type,item_id,sort_order) VALUES(:trail,:type,:item,:sort_order)');
            foreach ($items as $position => $item) $insert->execute(['trail' => $id, 'type' => $item['type'], 'item' => $item['id'], 'sort_order' => ($position + 1) * 10]);
            $this->database->prepare("UPDATE catalog_ava_publications SET publication_status='ready',last_error=NULL,updated_by=:user WHERE entity_type='trail' AND entity_id=:id")->execute(['user'=>$userId,'id'=>$id]);
            $this->database->commit();
            return $id;
        } catch (\Throwable $exception) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw $exception;
        }
    }

    public function deleteTrail(int $id): void
    {
        $delete = $this->database->prepare('DELETE FROM catalog_trails WHERE id=:id');
        $delete->execute(['id' => $id]);
        if ($delete->rowCount() !== 1) throw new RuntimeException('Trilha não encontrada.');
    }

    /** @param list<string> $keys @return list<array{type:string,id:int}> */
    private function normalizeItems(array $keys): array
    {
        $allowed = ['finance_product' => 'finance_products', 'provider_course' => 'provider_courses', 'provider_content' => 'provider_catalog_contents'];
        $result = [];
        $seen = [];
        foreach ($keys as $key) {
            if (!preg_match('/^(finance_product|provider_course|provider_content):(\d+)$/', (string)$key, $match)) continue;
            $type = $match[1];
            $itemId = (int)$match[2];
            $unique = $type.':'.$itemId;
            if ($itemId < 1 || isset($seen[$unique])) continue;
            if (!$this->exists($allowed[$type], $itemId)) throw new RuntimeException('Um dos itens selecionados não existe mais no catálogo.');
            $seen[$unique] = true;
            $result[] = ['type' => $type, 'id' => $itemId];
        }
        return $result;
    }

    /** @param list<array{type:string,id:int}> $items */
    private function assertItemsBelongToProvider(array $items, string $providerCode): void
    {
        foreach ($items as $item) {
            if (!in_array($item['type'], ['provider_course','provider_content'], true)) {
                throw new RuntimeException('Nesta aba, use somente Cursos individuais da Formação MASTER.');
            }
            $table = $item['type'] === 'provider_course' ? 'provider_courses' : 'provider_catalog_contents';
            $statement = $this->database->prepare("SELECT provider.provider_code FROM {$table} item INNER JOIN course_provider_integrations provider ON provider.id=item.provider_id WHERE item.id=:id LIMIT 1");
            $statement->execute(['id' => $item['id']]);
            if ((string)$statement->fetchColumn() !== $providerCode) {
                throw new RuntimeException('Nesta aba, use somente Cursos individuais da Formação MASTER.');
            }
        }
    }

    private function trailBelongsToProvider(int $trailId, string $providerCode): bool
    {
        $statement = $this->database->prepare("SELECT item.item_type,
            COALESCE(course_provider.provider_code,content_provider.provider_code,'') provider_code
            FROM catalog_trail_items item
            LEFT JOIN provider_courses course ON item.item_type='provider_course' AND course.id=item.item_id
            LEFT JOIN course_provider_integrations course_provider ON course_provider.id=course.provider_id
            LEFT JOIN provider_catalog_contents content ON item.item_type='provider_content' AND content.id=item.item_id
            LEFT JOIN course_provider_integrations content_provider ON content_provider.id=content.provider_id
            WHERE item.catalog_trail_id=:trail");
        $statement->execute(['trail' => $trailId]);
        $items = $statement->fetchAll() ?: [];
        if ($items === []) return false;
        foreach ($items as $item) {
            if (!in_array((string)$item['item_type'], ['provider_course','provider_content'], true) || (string)$item['provider_code'] !== $providerCode) return false;
        }
        return true;
    }

    private function exists(string $table, int $id): bool
    {
        if (!in_array($table, ['catalog_categories','catalog_trails','finance_products','provider_courses','provider_catalog_contents'], true)) return false;
        $statement = $this->database->prepare("SELECT 1 FROM {$table} WHERE id=:id");
        $statement->execute(['id' => $id]);
        return $statement->fetchColumn() !== false;
    }

    private function publicationEvent(int$publicationId,string$type,string$status,?int$remoteCategoryId,?int$remoteCourseId,string$message,array$details,?int$userId):void
    {
        $statement=$this->database->prepare('INSERT INTO catalog_ava_publication_events(publication_id,event_type,event_status,remote_category_id,remote_course_id,message,details_json,created_by) VALUES(:publication,:type,:status,:category,:course,:message,:details,:user)');
        $statement->execute(['publication'=>$publicationId,'type'=>$type,'status'=>$status,'category'=>$remoteCategoryId,'course'=>$remoteCourseId,'message'=>$message!==''?$message:null,'details'=>$details!==[]?json_encode($details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR):null,'user'=>$userId]);
    }

    private function slug(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($value));
        $slug = strtolower((string)($ascii === false ? $value : $ascii));
        return trim((string)preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
    }

    private function money(string $value): float
    {
        $normalized = str_contains($value, ',') ? str_replace(',', '.', str_replace('.', '', $value)) : $value;
        if (!is_numeric($normalized)) throw new RuntimeException('Informe um preço válido.');
        return round((float)$normalized, 2);
    }

    private function positiveDecimal(string $value, string $message): float
    {
        $normalized = str_contains($value, ',') ? str_replace(',', '.', str_replace('.', '', trim($value))) : trim($value);
        if ($normalized === '' || !is_numeric($normalized) || (float)$normalized <= 0 || (float)$normalized > 999999.99) throw new RuntimeException($message);
        return round((float)$normalized, 2);
    }
}
