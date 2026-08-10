<?php

declare(strict_types=1);

namespace Interferencia\Modules\Finance;

use PDO;
use RuntimeException;
use Throwable;

final readonly class CatalogRepository
{
    public function __construct(private PDO $database, private int $organizationId) {}

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        $statement=$this->database->prepare("SELECT p.*,u.name unit_name,m.shortname moodle_shortname,m.visible moodle_visible,scope.source catalog_source,scope.is_owner catalog_owner,scope.is_visible catalog_visible,CASE WHEN scope.source<>'ava' OR EXISTS(SELECT 1 FROM course_catalogs catalog LEFT JOIN organization_course_catalog_access access ON access.course_catalog_id=catalog.id AND access.organization_id=scope.organization_id WHERE catalog.code='ava-cursos' AND catalog.is_active=1 AND COALESCE(access.is_enabled,1)=1) THEN 1 ELSE 0 END catalog_enabled
            FROM organization_finance_products scope
            INNER JOIN finance_products p ON p.id=scope.finance_product_id
            LEFT JOIN units u ON u.id=p.unit_id
            LEFT JOIN moodle_courses m ON m.id=p.moodle_course_id
            WHERE scope.organization_id=:organization
            ORDER BY scope.is_visible DESC,p.is_active DESC,p.name,p.id");
        $statement->execute(['organization'=>$this->organizationId]);
        return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function availableForUnit(int $unitId): array
    {
        $statement=$this->database->prepare("SELECT p.*,u.name unit_name,scope.source catalog_source,scope.is_visible catalog_visible
            FROM organization_finance_products scope
            INNER JOIN finance_products p ON p.id=scope.finance_product_id
            LEFT JOIN units u ON u.id=p.unit_id
            WHERE scope.organization_id=:organization AND scope.is_visible=1 AND p.is_active=1 AND (p.unit_id=:unit OR p.unit_id IS NULL) AND (scope.source<>'ava' OR EXISTS(SELECT 1 FROM course_catalogs catalog LEFT JOIN organization_course_catalog_access access ON access.course_catalog_id=catalog.id AND access.organization_id=scope.organization_id WHERE catalog.code='ava-cursos' AND catalog.is_active=1 AND COALESCE(access.is_enabled,1)=1))
            ORDER BY p.name,p.id");
        $statement->execute(['organization'=>$this->organizationId,'unit'=>$unitId]);
        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $statement=$this->database->prepare("SELECT p.*,u.name unit_name,m.shortname moodle_shortname,scope.source catalog_source,scope.is_owner catalog_owner,scope.is_visible catalog_visible,CASE WHEN scope.source<>'ava' OR EXISTS(SELECT 1 FROM course_catalogs catalog LEFT JOIN organization_course_catalog_access access ON access.course_catalog_id=catalog.id AND access.organization_id=scope.organization_id WHERE catalog.code='ava-cursos' AND catalog.is_active=1 AND COALESCE(access.is_enabled,1)=1) THEN 1 ELSE 0 END catalog_enabled
            FROM organization_finance_products scope
            INNER JOIN finance_products p ON p.id=scope.finance_product_id
            LEFT JOIN units u ON u.id=p.unit_id
            LEFT JOIN moodle_courses m ON m.id=p.moodle_course_id
            WHERE scope.organization_id=:organization AND p.id=:id LIMIT 1");
        $statement->execute(['organization'=>$this->organizationId,'id'=>$id]);
        $row=$statement->fetch();
        return is_array($row)?$row:null;
    }

    public function syncFromMoodle(): int
    {
        $this->assertOrganization();
        $this->database->beginTransaction();
        try {
            $insert=$this->database->prepare("INSERT INTO finance_products(moodle_course_id,name,description,value,max_installments,billing_types,minutes_to_expire,is_active)
                SELECT m.id,m.fullname,CONCAT('Curso sincronizado do AVA: ',m.shortname),0,1,'PIX,CREDIT_CARD',1440,0
                FROM moodle_courses m
                LEFT JOIN finance_products p ON p.moodle_course_id=m.id
                WHERE p.id IS NULL AND m.organization_id=:organization AND m.moodle_course_id>1");
            $insert->execute(['organization'=>$this->organizationId]);
            $created=$insert->rowCount();
            $scope=$this->database->prepare("INSERT IGNORE INTO organization_finance_products(organization_id,finance_product_id,source,is_owner,is_visible)
                SELECT :organization,p.id,'ava',1,1
                FROM finance_products p
                INNER JOIN moodle_courses m ON m.id=p.moodle_course_id
                WHERE m.organization_id=:course_organization AND m.moodle_course_id>1");
            $scope->execute(['organization'=>$this->organizationId,'course_organization'=>$this->organizationId]);
            $this->database->commit();
            return $created;
        } catch (Throwable $exception) {
            if($this->database->inTransaction())$this->database->rollBack();
            throw $exception;
        }
    }

    /** @param list<string> $billingTypes */
    public function save(?int $id,?int $unitId,string $name,?string $description,float $value,int $maxInstallments,array $billingTypes,int $minutes,bool $active): int
    {
        $this->assertOrganization();
        $billing=implode(',',array_values(array_intersect(['PIX','CREDIT_CARD'],$billingTypes)));
        if($id===null){
            $this->database->beginTransaction();
            try {
                $statement=$this->database->prepare('INSERT INTO finance_products(unit_id,name,description,value,max_installments,billing_types,minutes_to_expire,is_active) VALUES(:unit,:name,:description,:value,:installments,:billing,:minutes,:active)');
                $statement->execute(['unit'=>$unitId,'name'=>$name,'description'=>$description,'value'=>$value,'installments'=>$maxInstallments,'billing'=>$billing,'minutes'=>$minutes,'active'=>(int)$active]);
                $id=(int)$this->database->lastInsertId();
                $scope=$this->database->prepare("INSERT INTO organization_finance_products(organization_id,finance_product_id,source,is_owner,is_visible) VALUES(:organization,:product,'manual',1,1)");
                $scope->execute(['organization'=>$this->organizationId,'product'=>$id]);
                $this->database->commit();
                return $id;
            } catch (Throwable $exception) {
                if($this->database->inTransaction())$this->database->rollBack();
                throw $exception;
            }
        }

        if($this->find($id)===null)throw new RuntimeException('Curso não encontrado nesta franquia.');
        $statement=$this->database->prepare('UPDATE finance_products p INNER JOIN organization_finance_products scope ON scope.finance_product_id=p.id AND scope.organization_id=:organization SET p.unit_id=:unit,p.name=:name,p.description=:description,p.value=:value,p.max_installments=:installments,p.billing_types=:billing,p.minutes_to_expire=:minutes,p.is_active=:active WHERE p.id=:id');
        $statement->execute(['organization'=>$this->organizationId,'unit'=>$unitId,'name'=>$name,'description'=>$description,'value'=>$value,'installments'=>$maxInstallments,'billing'=>$billing,'minutes'=>$minutes,'active'=>(int)$active,'id'=>$id]);
        return $id;
    }

    public function setVisible(int $id,bool $visible): void
    {
        $statement=$this->database->prepare('UPDATE organization_finance_products SET is_visible=:visible WHERE organization_id=:organization AND finance_product_id=:product');
        $statement->execute(['visible'=>(int)$visible,'organization'=>$this->organizationId,'product'=>$id]);
        if($statement->rowCount()!==1&&$this->find($id)===null)throw new RuntimeException('Curso não encontrado nesta franquia.');
        if(!$visible)$this->database->prepare('DELETE FROM organization_site_products WHERE organization_id=:organization AND finance_product_id=:product')->execute(['organization'=>$this->organizationId,'product'=>$id]);
    }

    public function deleteManual(int $id): void
    {
        $product=$this->find($id);
        if($product===null)throw new RuntimeException('Curso não encontrado nesta franquia.');
        if(($product['catalog_source']??'ava')!=='manual'||$product['moodle_course_id']!==null)throw new RuntimeException('Cursos sincronizados do AVA são protegidos e não podem ser excluídos.');
        foreach(['student_enrollments','finance_checkouts','organization_site_orders'] as $table){
            $statement=$this->database->prepare("SELECT 1 FROM {$table} WHERE finance_product_id=:product LIMIT 1");
            $statement->execute(['product'=>$id]);
            if($statement->fetchColumn()!==false)throw new RuntimeException('Este curso possui histórico. Desative ou oculte o curso em vez de excluí-lo.');
        }
        $owners=$this->database->prepare('SELECT COUNT(*) FROM organization_finance_products WHERE finance_product_id=:product');
        $owners->execute(['product'=>$id]);
        if((int)$owners->fetchColumn()!==1)throw new RuntimeException('Este curso está compartilhado e não pode ser excluído.');
        $delete=$this->database->prepare("DELETE p FROM finance_products p INNER JOIN organization_finance_products scope ON scope.finance_product_id=p.id WHERE p.id=:product AND scope.organization_id=:organization AND scope.source='manual' AND scope.is_owner=1");
        $delete->execute(['product'=>$id,'organization'=>$this->organizationId]);
        if($delete->rowCount()!==1)throw new RuntimeException('Não foi possível excluir o curso.');
    }

    public function createCheckoutDraft(int $customerId,int $productId,int $unitId,int $userId): array
    {
        $temporary='pending-'.bin2hex(random_bytes(12));$statement=$this->database->prepare('INSERT INTO finance_checkouts(finance_customer_id,finance_product_id,unit_id,external_reference,created_by) VALUES(:customer,:product,:unit,:external,:user)');$statement->execute(['customer'=>$customerId,'product'=>$productId,'unit'=>$unitId,'external'=>$temporary,'user'=>$userId]);$id=(int)$this->database->lastInsertId();$external=sprintf('painel:checkout:%d:unit:%d',$id,$unitId);$this->database->prepare('UPDATE finance_checkouts SET external_reference=:external WHERE id=:id')->execute(['external'=>$external,'id'=>$id]);return['id'=>$id,'external_reference'=>$external];
    }

    /** @param array<string,mixed> $checkout */
    public function completeCheckout(int $id,array $checkout,int $minutes): void{$createdId=(string)($checkout['id']??'');$link=(string)($checkout['link']??'');$status=(string)($checkout['status']??'ACTIVE');$expires=(new \DateTimeImmutable())->modify('+'.$minutes.' minutes')->format('Y-m-d H:i:s');$statement=$this->database->prepare('UPDATE finance_checkouts SET asaas_checkout_id=:asaas,status=:status,link=:link,expires_at=:expires,error_message=NULL WHERE id=:id');$statement->execute(['asaas'=>$createdId,'status'=>$status,'link'=>$link?:null,'expires'=>$expires,'id'=>$id]);}
    public function failCheckout(int $id,string $error): void{$this->database->prepare("UPDATE finance_checkouts SET status='FAILED',error_message=:error WHERE id=:id")->execute(['error'=>mb_substr($error,0,500),'id'=>$id]);}
    /** @return list<array<string,mixed>> */
    public function customerCheckouts(int $customerId): array{$statement=$this->database->prepare('SELECT c.*,p.name product_name,p.value FROM finance_checkouts c INNER JOIN finance_products p ON p.id=c.finance_product_id WHERE c.finance_customer_id=:customer ORDER BY c.created_at DESC,c.id DESC');$statement->execute(['customer'=>$customerId]);return$statement->fetchAll();}
    /** @param array<string,mixed> $checkout */
    public function updateFromWebhook(array $checkout): void{$id=(string)($checkout['id']??'');if($id==='')return;$statement=$this->database->prepare('UPDATE finance_checkouts SET status=:status,link=COALESCE(:link,link) WHERE asaas_checkout_id=:id');$statement->execute(['status'=>(string)($checkout['status']??'ACTIVE'),'link'=>isset($checkout['link'])?(string)$checkout['link']:null,'id'=>$id]);}

    private function assertOrganization(): void
    {
        if($this->organizationId<1)throw new RuntimeException('Selecione uma franquia para gerenciar o catálogo.');
    }
}
