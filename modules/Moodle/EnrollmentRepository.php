<?php declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use PDO;
use RuntimeException;

final readonly class EnrollmentRepository
{
    public function __construct(private PDO $database,private int $organizationId=0){}

    public function all(array $unitIds): array
    {
        if($unitIds===[])return[];
        $marks=implode(',',array_fill(0,count($unitIds),'?'));
        $sql="SELECT e.*,f.name student_name,f.cpf_cnpj,COALESCE(m.fullname,trail.name,pco.commercial_name,pc.commercial_name,pc.name,pio.commercial_name,pci.commercial_name,pci.name) course_name,m.shortname,COALESCE(p.name,trail.name) product_name,COALESCE(e.final_value,p.value,trail_access.price_override,trail.default_price) value,COALESCE(trail_access.max_installments_override,trail.max_installments,p.max_installments,1) max_installments,c.name campaign_name,u.name unit_name,a.name attendant_name,fp.status payment_status,COALESCE(ac.name,CASE WHEN e.academic_provider_code='conted_tech' THEN 'Catálogo EXPERT' END) ava_connection_name,COALESCE(ac.connection_type,CASE WHEN e.academic_provider_code IS NOT NULL THEN 'provider' END) ava_connection_type FROM student_enrollments e INNER JOIN finance_customers f ON f.id=e.finance_customer_id LEFT JOIN moodle_courses m ON m.id=e.moodle_course_id LEFT JOIN catalog_trails trail ON trail.id=e.catalog_trail_id LEFT JOIN organization_catalog_trail_access trail_access ON trail_access.organization_id=e.organization_id AND trail_access.catalog_trail_id=trail.id LEFT JOIN organization_provider_course_offers pco ON pco.id=e.provider_course_offer_id LEFT JOIN provider_courses pc ON pc.id=pco.provider_course_id LEFT JOIN organization_provider_content_offers pio ON pio.id=e.provider_content_offer_id LEFT JOIN provider_catalog_contents pci ON pci.id=pio.provider_content_id LEFT JOIN finance_products p ON p.id=e.finance_product_id LEFT JOIN finance_campaigns c ON c.id=e.campaign_id INNER JOIN units u ON u.id=e.unit_id LEFT JOIN users a ON a.id=e.attendant_user_id LEFT JOIN finance_payments fp ON fp.id=e.finance_payment_id LEFT JOIN ava_connections ac ON ac.id=e.ava_connection_id WHERE e.unit_id IN ($marks) ORDER BY e.created_at DESC,e.id DESC LIMIT 500";
        $s=$this->database->prepare($sql);$s->execute($unitIds);return$s->fetchAll();
    }

    public function eventsFor(array $enrollmentIds): array
    {
        if($enrollmentIds===[])return[];
        $marks=implode(',',array_fill(0,count($enrollmentIds),'?'));
        $s=$this->database->prepare("SELECT e.*,u.name user_name FROM student_enrollment_events e LEFT JOIN users u ON u.id=e.created_by WHERE e.enrollment_id IN ($marks) ORDER BY e.created_at DESC,e.id DESC");
        $s->execute($enrollmentIds);$grouped=[];foreach($s->fetchAll()as$event)$grouped[(int)$event['enrollment_id']][]=$event;return$grouped;
    }

    public function create(int $customerId,int $productId,?int $campaignId,int $unitId,int $creatorId,int $attendantId,array $allowedUnits,?int $avaConnectionId=null,?int $avaCourseId=null): int
    {
        if(!in_array($unitId,$allowedUnits,true))throw new RuntimeException('Selecione uma unidade permitida.');
        if($productId<1)throw new RuntimeException('Selecione o curso contratado.');
        $s=$this->database->prepare('SELECT COUNT(*) FROM finance_customers WHERE id=:id AND unit_id=:unit AND is_deleted=0');$s->execute(['id'=>$customerId,'unit'=>$unitId]);if((int)$s->fetchColumn()!==1)throw new RuntimeException('Aluno não encontrado nesta unidade.');
        $s=$this->database->prepare('SELECT value,moodle_course_id FROM finance_products WHERE id=:id AND is_active=1 AND value>=5 AND moodle_course_id IS NOT NULL AND (unit_id=:unit OR unit_id IS NULL)');$s->execute(['id'=>$productId,'unit'=>$unitId]);$product=$s->fetch();if(!is_array($product))throw new RuntimeException('Curso contratado inválido ou ainda não configurado.');
        $courseId=(int)$product['moodle_course_id'];$base=(float)$product['value'];$discount=0.0;
        if($campaignId!==null){$s=$this->database->prepare('SELECT discount_percent FROM finance_campaigns WHERE id=:id AND is_active=1 AND valid_until>=CURRENT_DATE');$s->execute(['id'=>$campaignId]);$found=$s->fetchColumn();if($found===false)throw new RuntimeException('Campanha inválida ou expirada.');$discount=(float)$found;}
        $final=round($base*(1-$discount/100),2);if($final<5)throw new RuntimeException('O desconto deixa a cobrança abaixo do valor mínimo de R$ 5,00.');
        $poleId=$this->poleIdForUnit($unitId);
        [$avaConnectionId,$avaCourseId]=$this->validatedDestination($courseId,$avaConnectionId,$avaCourseId);
        $this->database->beginTransaction();
        try{$s=$this->database->prepare('INSERT INTO student_enrollments(organization_id,finance_customer_id,moodle_course_id,finance_product_id,campaign_id,base_value,discount_percent,final_value,unit_id,organization_pole_id,ava_connection_id,ava_course_id,attendant_user_id,created_by) VALUES(:organization,:customer,:course,:product,:campaign,:base,:discount,:final,:unit,:pole,:ava_connection,:ava_course,:attendant,:creator)');$s->execute(['organization'=>$this->organizationId,'customer'=>$customerId,'course'=>$courseId,'product'=>$productId,'campaign'=>$campaignId,'base'=>$base,'discount'=>$discount,'final'=>$final,'unit'=>$unitId,'pole'=>$poleId,'ava_connection'=>$avaConnectionId,'ava_course'=>$avaCourseId,'attendant'=>$attendantId,'creator'=>$creatorId]);$id=(int)$this->database->lastInsertId();$this->recordEvent($id,'enrollment-created:'.$id,'enrollment_created','Matrícula cadastrada no Painel e direcionada ao AVA selecionado.',$creatorId);$this->database->commit();return$id;}catch(\Throwable$e){$this->database->rollBack();throw$e;}
    }

    public function createTrail(int$customerId,int$trailId,?int$campaignId,int$unitId,int$creatorId,int$attendantId,array$allowedUnits,?int$avaConnectionId=null):int
    {
        if(!in_array($unitId,$allowedUnits,true))throw new RuntimeException('Selecione uma unidade permitida.');
        if($trailId<1)throw new RuntimeException('Selecione a Trilha contratada.');
        $student=$this->database->prepare("SELECT COUNT(*) FROM finance_customers WHERE id=:id AND organization_id=:organization AND unit_id=:unit AND student_status='active' AND is_deleted=0");
        $student->execute(['id'=>$customerId,'organization'=>$this->organizationId,'unit'=>$unitId]);
        if((int)$student->fetchColumn()!==1)throw new RuntimeException('Aluno não encontrado nesta unidade.');
        $statement=$this->database->prepare("SELECT trail.id,trail.name,COALESCE(access.price_override,trail.default_price) price,COALESCE(access.max_installments_override,trail.max_installments,1) max_installments,publication.moodle_course_id,publication.ava_connection_id,publication.remote_course_id FROM catalog_trails trail INNER JOIN catalog_ava_publications publication ON publication.entity_type='trail' AND publication.entity_id=trail.id AND publication.publication_status='published' INNER JOIN ava_connections connection ON connection.id=publication.ava_connection_id AND connection.is_active=1 LEFT JOIN organization_catalog_trail_access access ON access.organization_id=:organization_access AND access.catalog_trail_id=trail.id WHERE trail.id=:trail AND trail.is_active=1 AND publication.moodle_course_id IS NOT NULL AND publication.remote_course_id IS NOT NULL AND (connection.connection_type='shared' OR connection.organization_id=:organization_connection) AND COALESCE(access.is_enabled,1)=1 AND COALESCE(access.is_visible,1)=1 LIMIT 1");
        $statement->execute(['organization_access'=>$this->organizationId,'trail'=>$trailId,'organization_connection'=>$this->organizationId]);$trail=$statement->fetch();
        if(!is_array($trail))throw new RuntimeException('A Trilha ainda não está publicada ou não foi liberada para esta franquia.');
        $base=round((float)$trail['price'],2);if($base<5)throw new RuntimeException('Defina um preço válido para a Trilha antes da matrícula.');
        $discount=0.0;if($campaignId!==null){$campaign=$this->database->prepare('SELECT discount_percent FROM finance_campaigns WHERE id=:id AND is_active=1 AND valid_until>=CURRENT_DATE');$campaign->execute(['id'=>$campaignId]);$found=$campaign->fetchColumn();if($found===false)throw new RuntimeException('Campanha inválida ou expirada.');$discount=(float)$found;}
        $final=round($base*(1-$discount/100),2);if($final<5)throw new RuntimeException('O desconto deixa a cobrança abaixo do valor mínimo de R$ 5,00.');
        $connectionId=(int)$trail['ava_connection_id'];if(($avaConnectionId??0)>0&&(int)$avaConnectionId!==$connectionId)throw new RuntimeException('A Trilha deve ser liberada no AVA em que foi publicada.');
        $existing=$this->database->prepare("SELECT COUNT(*) FROM student_enrollments WHERE organization_id=:organization AND finance_customer_id=:customer AND catalog_trail_id=:trail AND status IN ('awaiting_charge','awaiting_payment','payment_confirmed','payment_waived') AND moodle_enrolment_status IN ('not_released','released')");
        $existing->execute(['organization'=>$this->organizationId,'customer'=>$customerId,'trail'=>$trailId]);if((int)$existing->fetchColumn()>0)throw new RuntimeException('Este aluno já possui uma matrícula ativa ou preparada nesta Trilha.');
        $poleId=$this->poleIdForUnit($unitId);$this->database->beginTransaction();
        try{$insert=$this->database->prepare('INSERT INTO student_enrollments(organization_id,finance_customer_id,moodle_course_id,catalog_trail_id,campaign_id,base_value,discount_percent,final_value,unit_id,organization_pole_id,ava_connection_id,ava_course_id,attendant_user_id,created_by) VALUES(:organization,:customer,:course,:trail,:campaign,:base,:discount,:final,:unit,:pole,:connection,:ava_course,:attendant,:creator)');$insert->execute(['organization'=>$this->organizationId,'customer'=>$customerId,'course'=>(int)$trail['moodle_course_id'],'trail'=>$trailId,'campaign'=>$campaignId,'base'=>$base,'discount'=>$discount,'final'=>$final,'unit'=>$unitId,'pole'=>$poleId,'connection'=>$connectionId,'ava_course'=>(int)$trail['remote_course_id'],'attendant'=>$attendantId,'creator'=>$creatorId]);$id=(int)$this->database->lastInsertId();$this->recordEvent($id,'enrollment-created:'.$id,'enrollment_created','Matrícula cadastrada no Painel e direcionada ao AVA Cursos.',$creatorId);$this->recordEvent($id,'trail-selected:'.$trailId,'trail_selected','Trilha '.$trail['name'].' vinculada ao curso já publicado no AVA Cursos.',$creatorId);$this->database->commit();return$id;}catch(\Throwable$exception){if($this->database->inTransaction())$this->database->rollBack();throw$exception;}
    }

    public function createProviderCourse(int$customerId,int$offerId,?int$campaignId,int$unitId,int$creatorId,int$attendantId,array$allowedUnits,?int$avaConnectionId=null):int
    {
        if(!in_array($unitId,$allowedUnits,true))throw new RuntimeException('Selecione uma unidade permitida.');
        if($offerId<1)throw new RuntimeException('Selecione o Curso individual contratado.');
        $student=$this->database->prepare("SELECT COUNT(*) FROM finance_customers WHERE id=:id AND organization_id=:organization AND unit_id=:unit AND student_status='active' AND is_deleted=0");
        $student->execute(['id'=>$customerId,'organization'=>$this->organizationId,'unit'=>$unitId]);
        if((int)$student->fetchColumn()!==1)throw new RuntimeException('Aluno não encontrado nesta unidade.');
        $statement=$this->database->prepare("SELECT offer.id,offer.price,offer.max_installments,COALESCE(NULLIF(offer.commercial_name,''),NULLIF(course.commercial_name,''),course.name) name,publication.moodle_course_id,publication.ava_connection_id,publication.remote_course_id FROM organization_provider_course_offers offer INNER JOIN provider_courses course ON course.id=offer.provider_course_id INNER JOIN course_catalogs catalog ON catalog.id=course.catalog_id INNER JOIN course_provider_integrations provider ON provider.id=course.provider_id AND provider.is_active=1 INNER JOIN catalog_ava_publications publication ON publication.entity_type='provider_course' AND publication.entity_id=course.id AND publication.publication_status='published' INNER JOIN ava_connections connection ON connection.id=publication.ava_connection_id AND connection.is_active=1 LEFT JOIN organization_course_catalog_access catalog_access ON catalog_access.organization_id=offer.organization_id AND catalog_access.course_catalog_id=catalog.id LEFT JOIN organization_catalog_item_access item_access ON item_access.organization_id=offer.organization_id AND item_access.item_type='course' AND item_access.item_id=course.id WHERE offer.id=:offer AND offer.organization_id=:organization_offer AND offer.is_active=1 AND offer.is_visible=1 AND offer.price>=5 AND course.review_status='approved' AND course.release_status IN ('released','published') AND course.is_available=1 AND course.is_globally_enabled=1 AND catalog.is_active=1 AND catalog.is_globally_enabled=1 AND COALESCE(catalog_access.is_enabled,1)=1 AND COALESCE(item_access.is_enabled,1)=1 AND publication.moodle_course_id IS NOT NULL AND publication.remote_course_id IS NOT NULL AND (connection.connection_type='shared' OR connection.organization_id=:organization_connection) LIMIT 1");
        $statement->execute(['offer'=>$offerId,'organization_offer'=>$this->organizationId,'organization_connection'=>$this->organizationId]);$offer=$statement->fetch();
        if(!is_array($offer))throw new RuntimeException('O Curso individual ainda não está publicado no AVA ou não foi liberado para esta franquia.');
        $base=round((float)$offer['price'],2);$discount=0.0;
        if($campaignId!==null){$campaign=$this->database->prepare('SELECT discount_percent FROM finance_campaigns WHERE id=:id AND is_active=1 AND valid_until>=CURRENT_DATE');$campaign->execute(['id'=>$campaignId]);$found=$campaign->fetchColumn();if($found===false)throw new RuntimeException('Campanha inválida ou expirada.');$discount=(float)$found;}
        $final=round($base*(1-$discount/100),2);if($final<5)throw new RuntimeException('O desconto deixa a cobrança abaixo do valor mínimo de R$ 5,00.');
        $connectionId=(int)$offer['ava_connection_id'];if(($avaConnectionId??0)>0&&(int)$avaConnectionId!==$connectionId)throw new RuntimeException('O Curso individual deve ser liberado no AVA em que foi publicado.');
        $existing=$this->database->prepare("SELECT COUNT(*) FROM student_enrollments WHERE organization_id=:organization AND finance_customer_id=:customer AND provider_course_offer_id=:offer AND status IN ('awaiting_charge','awaiting_payment','payment_confirmed','payment_waived') AND moodle_enrolment_status IN ('not_released','released')");
        $existing->execute(['organization'=>$this->organizationId,'customer'=>$customerId,'offer'=>$offerId]);if((int)$existing->fetchColumn()>0)throw new RuntimeException('Este aluno já possui uma matrícula ativa ou preparada neste Curso individual.');
        $poleId=$this->poleIdForUnit($unitId);$this->database->beginTransaction();
        try{$insert=$this->database->prepare('INSERT INTO student_enrollments(organization_id,finance_customer_id,moodle_course_id,provider_course_offer_id,campaign_id,base_value,discount_percent,final_value,unit_id,organization_pole_id,ava_connection_id,ava_course_id,attendant_user_id,created_by) VALUES(:organization,:customer,:course,:offer,:campaign,:base,:discount,:final,:unit,:pole,:connection,:ava_course,:attendant,:creator)');$insert->execute(['organization'=>$this->organizationId,'customer'=>$customerId,'course'=>(int)$offer['moodle_course_id'],'offer'=>$offerId,'campaign'=>$campaignId,'base'=>$base,'discount'=>$discount,'final'=>$final,'unit'=>$unitId,'pole'=>$poleId,'connection'=>$connectionId,'ava_course'=>(int)$offer['remote_course_id'],'attendant'=>$attendantId,'creator'=>$creatorId]);$id=(int)$this->database->lastInsertId();$this->recordEvent($id,'enrollment-created:'.$id,'enrollment_created','Matrícula cadastrada no Painel e direcionada ao AVA Cursos.',$creatorId);$this->recordEvent($id,'provider-course-selected:'.$offerId,'provider_course_selected','Curso individual '.$offer['name'].' vinculado ao curso publicado no AVA Cursos.',$creatorId);$this->database->commit();return$id;}catch(\Throwable$exception){if($this->database->inTransaction())$this->database->rollBack();throw$exception;}
    }

    public function createPaidFromSiteOrder(int$customerId,int$productId,int$unitId,int$contactId):int
    {
        $customer=$this->database->prepare('SELECT COUNT(*) FROM finance_customers WHERE id=:id AND organization_id=:organization AND unit_id=:unit AND is_deleted=0');
        $customer->execute(['id'=>$customerId,'organization'=>$this->organizationId,'unit'=>$unitId]);
        if((int)$customer->fetchColumn()!==1)throw new RuntimeException('O aluno pago não foi conciliado com a franquia e o polo corretos.');
        $product=$this->database->prepare('SELECT p.value,p.moodle_course_id FROM finance_products p LEFT JOIN units u ON u.id=p.unit_id WHERE p.id=:id AND p.is_active=1 AND p.value>=5 AND p.moodle_course_id IS NOT NULL AND (p.unit_id IS NULL OR (p.unit_id=:unit AND u.organization_id=:organization)) LIMIT 1');
        $product->execute(['id'=>$productId,'unit'=>$unitId,'organization'=>$this->organizationId]);$row=$product->fetch();
        if(!is_array($row))throw new RuntimeException('O curso pago ainda não possui uma configuração acadêmica válida.');
        $courseId=(int)$row['moodle_course_id'];
        $existing=$this->database->prepare("SELECT id FROM student_enrollments WHERE organization_id=:organization AND finance_customer_id=:customer AND moodle_course_id=:course AND status IN ('payment_confirmed','payment_waived') AND moodle_enrolment_status IN ('not_released','released') ORDER BY id DESC LIMIT 1");
        $existing->execute(['organization'=>$this->organizationId,'customer'=>$customerId,'course'=>$courseId]);$existingId=$existing->fetchColumn();if($existingId!==false)return(int)$existingId;
        $operator=$this->database->prepare("SELECT COALESCE((SELECT c.responsible_user_id FROM crm_contacts c INNER JOIN users ru ON ru.id=c.responsible_user_id AND ru.is_active=1 INNER JOIN organization_users rou ON rou.user_id=ru.id AND rou.organization_id=:organization_responsible AND rou.status='active' WHERE c.id=:contact AND c.organization_id=:organization_contact LIMIT 1),(SELECT ou.user_id FROM organization_users ou INNER JOIN users u ON u.id=ou.user_id AND u.is_active=1 WHERE ou.organization_id=:organization_user AND ou.status='active' AND (ou.is_owner=1 OR EXISTS(SELECT 1 FROM user_unit_scopes scope WHERE scope.user_id=ou.user_id AND scope.unit_id=:unit)) ORDER BY ou.is_owner DESC,ou.user_id LIMIT 1))");
        $operator->execute(['organization_responsible'=>$this->organizationId,'contact'=>$contactId,'organization_contact'=>$this->organizationId,'organization_user'=>$this->organizationId,'unit'=>$unitId]);$operatorId=(int)$operator->fetchColumn();
        if($operatorId<1)throw new RuntimeException('Defina ao menos um usuário ativo para acompanhar matrículas neste polo.');
        $poleId=$this->poleIdForUnit($unitId);[$connectionId,$avaCourseId]=$this->validatedDestination($courseId,null,null);$value=round((float)$row['value'],2);
        $this->database->beginTransaction();
        try{
            $insert=$this->database->prepare("INSERT INTO student_enrollments(organization_id,finance_customer_id,moodle_course_id,finance_product_id,base_value,discount_percent,final_value,unit_id,organization_pole_id,ava_connection_id,ava_course_id,attendant_user_id,status,created_by) VALUES(:organization,:customer,:course,:product,:base,0,:final,:unit,:pole,:connection,:ava_course,:attendant,'payment_confirmed',:creator)");
            $insert->execute(['organization'=>$this->organizationId,'customer'=>$customerId,'course'=>$courseId,'product'=>$productId,'base'=>$value,'final'=>$value,'unit'=>$unitId,'pole'=>$poleId,'connection'=>$connectionId,'ava_course'=>$avaCourseId,'attendant'=>$operatorId,'creator'=>$operatorId]);$id=(int)$this->database->lastInsertId();
            $this->recordEvent($id,'site-payment-confirmed:'.$id,'payment_confirmed','Pagamento confirmado no Site Institucional. Matrícula preparada para liberação no AVA.',$operatorId);
            $this->database->commit();return$id;
        }catch(\Throwable$e){if($this->database->inTransaction())$this->database->rollBack();throw$e;}
    }

    public function createWaived(int$customerId,int$courseId,int$unitId,string$reason,int$userId,array$allowedUnits,?int$avaConnectionId=null,?int$avaCourseId=null):int
    {
        $reason=trim($reason);if(!in_array($unitId,$allowedUnits,true))throw new RuntimeException('Selecione uma unidade permitida.');if(mb_strlen($reason)<10||mb_strlen($reason)>500)throw new RuntimeException('Informe o motivo da bolsa ou cortesia, entre 10 e 500 caracteres.');
        $s=$this->database->prepare("SELECT COUNT(*) FROM finance_customers WHERE id=:customer AND unit_id=:unit AND student_status='active' AND is_deleted=0");$s->execute(['customer'=>$customerId,'unit'=>$unitId]);if((int)$s->fetchColumn()!==1)throw new RuntimeException('Aluno não encontrado na unidade selecionada.');
        $s=$this->database->prepare("SELECT course.id,(SELECT publication.entity_id FROM catalog_ava_publications publication INNER JOIN catalog_trails trail ON trail.id=publication.entity_id LEFT JOIN organization_catalog_trail_access access ON access.organization_id=:organization_access AND access.catalog_trail_id=trail.id WHERE publication.entity_type='trail' AND publication.moodle_course_id=course.id AND publication.publication_status='published' AND trail.is_active=1 AND COALESCE(access.is_enabled,1)=1 AND COALESCE(access.is_visible,1)=1 LIMIT 1) catalog_trail_id FROM moodle_courses course WHERE course.id=:course AND course.visible=1 LIMIT 1");$s->execute(['organization_access'=>$this->organizationId,'course'=>$courseId]);$course=$s->fetch();if(!is_array($course))throw new RuntimeException('Curso indisponível no AVA.');$trailId=($course['catalog_trail_id']??null)!==null?(int)$course['catalog_trail_id']:null;
        $s=$this->database->prepare("SELECT COUNT(*) FROM student_enrollments WHERE finance_customer_id=:customer AND moodle_course_id=:course AND status IN ('payment_waived','payment_confirmed') AND moodle_enrolment_status IN ('not_released','released')");$s->execute(['customer'=>$customerId,'course'=>$courseId]);if((int)$s->fetchColumn()>0)throw new RuntimeException('Este aluno já possui uma matrícula ativa ou preparada nesse curso.');
        $poleId=$this->poleIdForUnit($unitId);
        [$avaConnectionId,$avaCourseId]=$this->validatedDestination($courseId,$avaConnectionId,$avaCourseId);
        $this->database->beginTransaction();try{$s=$this->database->prepare("INSERT INTO student_enrollments(organization_id,finance_customer_id,moodle_course_id,catalog_trail_id,unit_id,organization_pole_id,ava_connection_id,ava_course_id,attendant_user_id,status,payment_waiver_reason,payment_waived_at,payment_waived_by,created_by) VALUES(:organization,:customer,:course,:trail,:unit,:pole,:ava_connection,:ava_course,:attendant,'payment_waived',:reason,NOW(),:waived_by,:creator)");$s->execute(['organization'=>$this->organizationId,'customer'=>$customerId,'course'=>$courseId,'trail'=>$trailId,'unit'=>$unitId,'pole'=>$poleId,'ava_connection'=>$avaConnectionId,'ava_course'=>$avaCourseId,'attendant'=>$userId,'reason'=>$reason,'waived_by'=>$userId,'creator'=>$userId]);$id=(int)$this->database->lastInsertId();$this->recordEvent($id,'enrollment-created:'.$id,'enrollment_created','Matrícula cadastrada no Painel e direcionada ao AVA selecionado.',$userId);if($trailId!==null)$this->recordEvent($id,'trail-selected:'.$trailId,'trail_selected','A liberação especial preservará a organização acadêmica da Trilha publicada.',$userId);$this->recordEvent($id,'payment-waived:'.$id,'payment_waived','Pagamento dispensado por bolsa ou cortesia. Motivo: '.$reason,$userId);$this->database->commit();return$id;}catch(\Throwable$e){$this->database->rollBack();throw$e;}
    }

    /** @param array<string,mixed> $target */
    public function createProviderWaived(int $customerId,string $targetKind,array $target,int $unitId,string $reason,int $userId,array $allowedUnits): int
    {
        $reason=trim($reason);
        if(!in_array($targetKind,['course','content'],true))throw new RuntimeException('Selecione um curso externo válido.');
        if(!in_array($unitId,$allowedUnits,true))throw new RuntimeException('Selecione uma unidade permitida.');
        if(mb_strlen($reason)<10||mb_strlen($reason)>500)throw new RuntimeException('Informe o motivo da bolsa ou cortesia, entre 10 e 500 caracteres.');
        if((int)($target['organization_id']??0)!==$this->organizationId)throw new RuntimeException('Esta oferta não pertence à franquia atual.');
        if((string)($target['provider_code']??'')!=='conted_tech')throw new RuntimeException('O piloto externo está habilitado somente para o Catálogo EXPERT.');
        $offerId=(int)($target['offer_id']??0);$type=trim((string)($target['content_type']??''));$batch=trim((string)($target['batch']??''));
        if($offerId<1||$type===''||$batch==='')throw new RuntimeException('A oferta externa ainda não possui os identificadores acadêmicos necessários.');
        $s=$this->database->prepare("SELECT COUNT(*) FROM finance_customers WHERE id=:customer AND organization_id=:organization AND unit_id=:unit AND student_status='active' AND is_deleted=0");$s->execute(['customer'=>$customerId,'organization'=>$this->organizationId,'unit'=>$unitId]);if((int)$s->fetchColumn()!==1)throw new RuntimeException('Aluno não encontrado na unidade selecionada.');
        $column=$targetKind==='course'?'provider_course_offer_id':'provider_content_offer_id';$s=$this->database->prepare("SELECT COUNT(*) FROM student_enrollments WHERE organization_id=:organization AND finance_customer_id=:customer AND {$column}=:offer AND status IN ('payment_waived','payment_confirmed') AND moodle_enrolment_status IN ('not_released','released')");$s->execute(['organization'=>$this->organizationId,'customer'=>$customerId,'offer'=>$offerId]);if((int)$s->fetchColumn()>0)throw new RuntimeException('Este aluno já possui uma matrícula ativa ou preparada nesta oferta.');
        $poleId=$this->poleIdForUnit($unitId);$courseOffer=$targetKind==='course'?$offerId:null;$contentOffer=$targetKind==='content'?$offerId:null;
        $this->database->beginTransaction();
        try{
            $s=$this->database->prepare("INSERT INTO student_enrollments(organization_id,finance_customer_id,moodle_course_id,provider_course_offer_id,provider_content_offer_id,academic_provider_code,provider_content_type,provider_batch,unit_id,organization_pole_id,attendant_user_id,status,payment_waiver_reason,payment_waived_at,payment_waived_by,created_by) VALUES(:organization,:customer,NULL,:course_offer,:content_offer,'conted_tech',:content_type,:batch,:unit,:pole,:attendant,'payment_waived',:reason,NOW(),:waived_by,:creator)");
            $s->execute(['organization'=>$this->organizationId,'customer'=>$customerId,'course_offer'=>$courseOffer,'content_offer'=>$contentOffer,'content_type'=>$type,'batch'=>$batch,'unit'=>$unitId,'pole'=>$poleId,'attendant'=>$userId,'reason'=>$reason,'waived_by'=>$userId,'creator'=>$userId]);
            $id=(int)$this->database->lastInsertId();$name=trim((string)($target['name']??'curso EXPERT'));
            $this->recordEvent($id,'enrollment-created:'.$id,'enrollment_created','Matrícula externa cadastrada para '.$name.'.',$userId);
            $this->recordEvent($id,'payment-waived:'.$id,'payment_waived','Pagamento dispensado por bolsa ou cortesia. Motivo: '.$reason,$userId);
            $this->database->commit();return$id;
        }catch(\Throwable$e){if($this->database->inTransaction())$this->database->rollBack();throw$e;}
    }

    public function deleteDraft(int $id,array $allowedUnits): void
    {
        if($allowedUnits===[])throw new RuntimeException('Matrícula não encontrada.');$marks=implode(',',array_fill(0,count($allowedUnits),'?'));$s=$this->database->prepare("DELETE FROM student_enrollments WHERE id=? AND unit_id IN ($marks) AND finance_payment_id IS NULL AND status='awaiting_charge' AND moodle_enrolment_status='not_released'");$s->execute(array_merge([$id],$allowedUnits));if($s->rowCount()!==1)throw new RuntimeException('A matrícula não pode ser excluída porque já possui movimentação financeira ou acadêmica.');
    }

    public function chargeContext(int $id,array $units): ?array
    {
        if($units===[])return null;$marks=implode(',',array_fill(0,count($units),'?'));$s=$this->database->prepare("SELECT e.*,COALESCE(p.name,trail.name,pco.commercial_name,pc.commercial_name,pc.name) product_name,COALESCE(e.final_value,p.value,trail_access.price_override,trail.default_price,pco.price) value,COALESCE(trail_access.max_installments_override,trail.max_installments,p.max_installments,pco.max_installments,1) max_installments,c.name campaign_name FROM student_enrollments e LEFT JOIN finance_products p ON p.id=e.finance_product_id LEFT JOIN catalog_trails trail ON trail.id=e.catalog_trail_id LEFT JOIN organization_catalog_trail_access trail_access ON trail_access.organization_id=e.organization_id AND trail_access.catalog_trail_id=trail.id LEFT JOIN organization_provider_course_offers pco ON pco.id=e.provider_course_offer_id LEFT JOIN provider_courses pc ON pc.id=pco.provider_course_id LEFT JOIN finance_campaigns c ON c.id=e.campaign_id WHERE e.id=? AND e.unit_id IN ($marks) AND e.finance_payment_id IS NULL AND (e.finance_product_id IS NOT NULL OR e.catalog_trail_id IS NOT NULL OR e.provider_course_offer_id IS NOT NULL) LIMIT 1");$s->execute(array_merge([$id],$units));$row=$s->fetch();return is_array($row)?$row:null;
    }

    public function attachPayment(int $id,int $paymentId): void
    {
        $s=$this->database->prepare("UPDATE student_enrollments SET finance_payment_id=:payment,status='awaiting_payment' WHERE id=:id AND finance_payment_id IS NULL");$s->execute(['payment'=>$paymentId,'id'=>$id]);if($s->rowCount()===1)$this->recordEvent($id,'payment-linked:'.$id.':'.$paymentId,'charge_created','Cobrança emitida e vinculada à matrícula.');
    }

    public function handlePaymentUpdate(string $asaasPaymentId,string $status): ?int
    {
        $s=$this->database->prepare('SELECT e.id,e.status FROM student_enrollments e INNER JOIN finance_payments p ON p.id=e.finance_payment_id WHERE e.organization_id=:organization AND p.organization_id=:organization_payment AND p.asaas_payment_id=:payment LIMIT 1');$s->execute(['organization'=>$this->organizationId,'organization_payment'=>$this->organizationId,'payment'=>$asaasPaymentId]);$enrollment=$s->fetch();if(!is_array($enrollment))return null;
        $id=(int)$enrollment['id'];
        if(in_array($status,['RECEIVED','CONFIRMED','RECEIVED_IN_CASH'],true)){$this->database->prepare("UPDATE student_enrollments SET status='payment_confirmed' WHERE id=:id AND status<>'payment_confirmed'")->execute(['id'=>$id]);$this->recordEvent($id,'payment-confirmed:'.$asaasPaymentId,'payment_confirmed','Pagamento confirmado pelo Asaas. Liberação automática no AVA iniciada.');return$id;}
        if(in_array($status,['CANCELED','REFUNDED'],true)){$this->database->prepare("UPDATE student_enrollments SET status='payment_interrupted' WHERE id=:id")->execute(['id'=>$id]);$this->recordEvent($id,'payment-interrupted:'.$asaasPaymentId.':'.$status,'payment_interrupted',$status==='REFUNDED'?'Pagamento estornado no Asaas.':'Cobrança cancelada no Asaas.');}return null;
    }

    public function releaseContext(int$id,array$allowedUnits):?array
    {
        if($allowedUnits===[])return null;$marks=implode(',',array_fill(0,count($allowedUnits),'?'));
        $sql="SELECT e.id,e.finance_customer_id,e.organization_id,e.unit_id,e.organization_pole_id,e.ava_connection_id,e.ava_course_id,e.catalog_trail_id,e.status,e.moodle_enrolment_status,e.created_at,e.academic_provider_code,e.provider_content_type,e.provider_batch,e.provider_course_offer_id,e.provider_content_offer_id,f.name,f.email,f.cpf_cnpj,mc.id moodle_course_local_id,mc.moodle_course_id,mc.shortname course_shortname,mc.fullname course_fullname,ct.name trail_name FROM student_enrollments e INNER JOIN finance_customers f ON f.id=e.finance_customer_id LEFT JOIN moodle_courses mc ON mc.id=e.moodle_course_id LEFT JOIN catalog_trails ct ON ct.id=e.catalog_trail_id WHERE e.id=? AND e.unit_id IN ($marks) LIMIT 1";
        $s=$this->database->prepare($sql);$s->execute(array_merge([$id],$allowedUnits));$row=$s->fetch();return is_array($row)?$row:null;
    }

    public function releaseContextForAutomation(int$id):?array
    {
        $sql="SELECT e.id,e.finance_customer_id,e.organization_id,e.unit_id,e.organization_pole_id,e.ava_connection_id,e.ava_course_id,e.ava_user_id,e.catalog_trail_id,e.status,e.moodle_enrolment_status,e.created_at,e.academic_provider_code,e.provider_content_type,e.provider_batch,e.provider_course_offer_id,e.provider_content_offer_id,f.name,f.email,f.cpf_cnpj,mc.id moodle_course_local_id,mc.moodle_course_id,mc.shortname course_shortname,mc.fullname course_fullname,ct.name trail_name FROM student_enrollments e INNER JOIN finance_customers f ON f.id=e.finance_customer_id LEFT JOIN moodle_courses mc ON mc.id=e.moodle_course_id LEFT JOIN catalog_trails ct ON ct.id=e.catalog_trail_id WHERE e.id=:id LIMIT 1";
        $s=$this->database->prepare($sql);$s->execute(['id'=>$id]);$row=$s->fetch();
        if(is_array($row)){$organization=$this->database->prepare('SELECT organization_id FROM units WHERE id=:unit LIMIT 1');$organization->execute(['unit'=>(int)$row['unit_id']]);$row['organization_id']=(int)$organization->fetchColumn();}
        return is_array($row)?$row:null;
    }

    public function markReleased(int$id,int$avaUserId,int$courseId,int$customerId,?int$userId,array$avaUser,int$connectionId,string$connectionName,string$connectionType):void
    {
        $this->database->beginTransaction();
        try{$this->database->prepare("UPDATE student_enrollments SET moodle_enrolment_status='released',ava_connection_id=:connection,ava_course_id=:course,ava_user_id=:ava_user,ava_username=:username,ava_released_at=NOW(),ava_released_by=:released_by,ava_last_error=NULL WHERE id=:id AND status IN ('payment_confirmed','payment_waived') AND moodle_enrolment_status<>'released'")->execute(['connection'=>$connectionId,'course'=>$courseId,'ava_user'=>$avaUserId,'username'=>(string)($avaUser['username']??''),'released_by'=>$userId,'id'=>$id]);
            if($connectionType==='shared'){
                $this->database->prepare("UPDATE moodle_users SET finance_customer_id=:customer,reconciliation_status='linked',match_method='assisted_release',reviewed_by=:user,matched_at=NOW() WHERE moodle_user_id=:ava_user")->execute(['customer'=>$customerId,'user'=>$userId,'ava_user'=>$avaUserId]);
                $this->database->prepare('INSERT INTO moodle_enrolments(moodle_course_id,moodle_user_id,time_start,is_active) VALUES(:course,:ava_user,NOW(),1) ON DUPLICATE KEY UPDATE is_active=1,time_start=COALESCE(time_start,NOW()),synced_at=NOW()')->execute(['course'=>$courseId,'ava_user'=>$avaUserId]);
            }
            $this->recordEvent($id,'ava-released:'.$id.':'.$connectionId.':'.$avaUserId.':'.$courseId,'ava_released','Acesso ao curso liberado em '.$connectionName.'.',$userId);$this->database->commit();
        }catch(\Throwable$e){$this->database->rollBack();throw$e;}
    }

    /** @param array<string,mixed> $response */
    public function markProviderReleased(int $id,string $studentKey,string $accessUrl,array $response,?int $userId,string $providerName='Catálogo EXPERT'): void
    {
        $payload=json_encode($response,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $s=$this->database->prepare("UPDATE student_enrollments SET moodle_enrolment_status='released',provider_student_key=:student,provider_access_url=:url,provider_response=:response,ava_username=:username,ava_released_at=NOW(),ava_released_by=:released_by,ava_last_error=NULL WHERE id=:id AND academic_provider_code IS NOT NULL AND status IN ('payment_confirmed','payment_waived') AND moodle_enrolment_status<>'released'");
        $s->execute(['student'=>$studentKey,'url'=>$accessUrl,'response'=>$payload===false?null:$payload,'username'=>$studentKey,'released_by'=>$userId,'id'=>$id]);
        if($s->rowCount()!==1)throw new RuntimeException('A matrícula externa não pôde ser marcada como liberada.');
        $this->recordEvent($id,'ava-released:provider:'.$id,'ava_released','Acesso ao curso liberado em '.$providerName.'.',$userId);
    }

    public function recordReleaseFailure(int$id,string$message,?int$userId):void
    {
        $message=mb_substr(trim($message),0,500);$s=$this->database->prepare('UPDATE student_enrollments SET ava_last_error=:error WHERE id=:id');$s->execute(['error'=>$message,'id'=>$id]);if($s->rowCount()===1)$this->recordEvent($id,'ava-release-failed:'.$id.':'.hash('sha256',$message),'ava_release_failed','Falha ao liberar no AVA: '.$message,$userId);
    }

    public function recordAcademicOrganizationFailure(int$id,string$message,?int$userId):void
    {
        $message=mb_substr(trim($message),0,500);
        $this->recordEvent($id,'ava-academic-organization-failed:'.$id.':'.hash('sha256',$message),'ava_academic_organization_failed','Acesso liberado, mas a organização em coorte e grupo ficou pendente: '.$message,$userId);
    }

    /** @return array{ready:int,failed:int} */
    public function avaNotificationSummary(array$unitIds):array
    {
        if($unitIds===[])return['ready'=>0,'failed'=>0];$marks=implode(',',array_fill(0,count($unitIds),'?'));
        $sql="SELECT SUM(e.moodle_enrolment_status='released' AND NOT EXISTS(SELECT 1 FROM ava_access_communications c WHERE c.enrollment_id=e.id AND c.status IN ('opened','sent'))) ready,SUM((e.status='payment_confirmed' AND e.moodle_enrolment_status='not_released' AND e.ava_last_error IS NOT NULL) OR (e.moodle_enrolment_status='released' AND EXISTS(SELECT 1 FROM ava_access_communications c WHERE c.enrollment_id=e.id AND c.status='failed') AND NOT EXISTS(SELECT 1 FROM ava_access_communications ok WHERE ok.enrollment_id=e.id AND ok.status IN ('opened','sent')))) failed FROM student_enrollments e WHERE e.unit_id IN ($marks)";
        $s=$this->database->prepare($sql);$s->execute($unitIds);$row=$s->fetch()?:[];return['ready'=>(int)($row['ready']??0),'failed'=>(int)($row['failed']??0)];
    }

    public function accessCommunicationContext(int$id,array$allowedUnits):?array
    {
        if($allowedUnits===[])return null;$marks=implode(',',array_fill(0,count($allowedUnits),'?'));
        $sql="SELECT e.id,e.unit_id,e.moodle_enrolment_status,e.ava_user_id,e.academic_provider_code,f.name,f.email,f.mobile_phone,f.phone,f.cpf_cnpj,COALESCE(c.fullname,pco.commercial_name,pc.commercial_name,pc.name,pio.commercial_name,pci.commercial_name,pci.name) course_name,u.name unit_name,COALESCE(e.ava_username,mu.username) username,COALESCE(ac.name,CASE WHEN e.academic_provider_code='conted_tech' THEN 'Catálogo EXPERT' END) ava_connection_name,COALESCE(e.provider_access_url,ac.base_url) ava_base_url FROM student_enrollments e INNER JOIN finance_customers f ON f.id=e.finance_customer_id LEFT JOIN moodle_courses c ON c.id=e.moodle_course_id LEFT JOIN organization_provider_course_offers pco ON pco.id=e.provider_course_offer_id LEFT JOIN provider_courses pc ON pc.id=pco.provider_course_id LEFT JOIN organization_provider_content_offers pio ON pio.id=e.provider_content_offer_id LEFT JOIN provider_catalog_contents pci ON pci.id=pio.provider_content_id INNER JOIN units u ON u.id=e.unit_id LEFT JOIN moodle_users mu ON mu.moodle_user_id=e.ava_user_id LEFT JOIN ava_connections ac ON ac.id=e.ava_connection_id WHERE e.id=? AND e.unit_id IN ($marks) AND e.moodle_enrolment_status='released' LIMIT 1";
        $s=$this->database->prepare($sql);$s->execute(array_merge([$id],$allowedUnits));$row=$s->fetch();return is_array($row)?$row:null;
    }

    public function accessCommunicationContextForAutomation(int$id):?array
    {
        $sql="SELECT e.id,e.unit_id,u.organization_id,e.moodle_enrolment_status,e.ava_user_id,e.academic_provider_code,f.name,f.email,f.mobile_phone,f.phone,f.cpf_cnpj,COALESCE(c.fullname,pco.commercial_name,pc.commercial_name,pc.name,pio.commercial_name,pci.commercial_name,pci.name) course_name,u.name unit_name,COALESCE(e.ava_username,mu.username) username,COALESCE(ac.name,CASE WHEN e.academic_provider_code='conted_tech' THEN 'Catálogo EXPERT' END) ava_connection_name,COALESCE(e.provider_access_url,ac.base_url) ava_base_url FROM student_enrollments e INNER JOIN finance_customers f ON f.id=e.finance_customer_id LEFT JOIN moodle_courses c ON c.id=e.moodle_course_id LEFT JOIN organization_provider_course_offers pco ON pco.id=e.provider_course_offer_id LEFT JOIN provider_courses pc ON pc.id=pco.provider_course_id LEFT JOIN organization_provider_content_offers pio ON pio.id=e.provider_content_offer_id LEFT JOIN provider_catalog_contents pci ON pci.id=pio.provider_content_id INNER JOIN units u ON u.id=e.unit_id LEFT JOIN moodle_users mu ON mu.moodle_user_id=e.ava_user_id LEFT JOIN ava_connections ac ON ac.id=e.ava_connection_id WHERE e.id=:id AND e.moodle_enrolment_status='released' LIMIT 1";
        $s=$this->database->prepare($sql);$s->execute(['id'=>$id]);$row=$s->fetch();return is_array($row)?$row:null;
    }

    public function accessCommunications(int$id):array
    {
        $s=$this->database->prepare('SELECT c.*,u.name user_name FROM ava_access_communications c LEFT JOIN users u ON u.id=c.created_by WHERE c.enrollment_id=:id ORDER BY c.created_at DESC,c.id DESC');$s->execute(['id'=>$id]);return$s->fetchAll();
    }

    public function recordAccessCommunication(int$id,string$channel,string$destination,int$userId):void
    {
        if(!in_array($channel,['whatsapp','email'],true))throw new RuntimeException('Canal de comunicação inválido.');$destination=trim($destination);if($destination==='')throw new RuntimeException('Destinatário não informado.');
        $this->database->beginTransaction();try{$s=$this->database->prepare("INSERT INTO ava_access_communications(enrollment_id,channel,destination,created_by) VALUES(:enrollment,:channel,:destination,:user)");$s->execute(['enrollment'=>$id,'channel'=>$channel,'destination'=>$destination,'user'=>$userId]);$label=$channel==='whatsapp'?'WhatsApp':'e-mail';$communicationId=(int)$this->database->lastInsertId();$this->recordEvent($id,'access-opened:'.$communicationId,'access_sent','Instruções de acesso preparadas para envio por '.$label.'.',$userId);$this->database->commit();}catch(\Throwable$e){$this->database->rollBack();throw$e;}
    }

    public function recordAutomaticAccessCommunication(int$id,string$channel,string$destination,string$status,?string$error):void
    {
        $this->recordEmailAccessCommunication($id,$destination,$status,null,$error);
    }

    public function recordEmailAccessCommunication(int$id,string$destination,string$status,?int$userId,?string$error):void
    {
        if(!in_array($status,['sent','failed'],true))throw new RuntimeException('Situação de envio inválida.');$destination=trim($destination);if($destination==='')$destination='não informado';$error=$error===null?null:mb_substr(trim($error),0,500);
        $this->database->beginTransaction();try{$s=$this->database->prepare('INSERT INTO ava_access_communications(enrollment_id,channel,destination,status,error_message,created_by) VALUES(:enrollment,\'email\',:destination,:status,:error,:user)');$s->execute(['enrollment'=>$id,'destination'=>$destination,'status'=>$status,'error'=>$error,'user'=>$userId]);$communicationId=(int)$this->database->lastInsertId();$automatic=$userId===null;$success=$status==='sent';$description=$success?($automatic?'Instruções de acesso enviadas automaticamente pelo E-mail Central.':'Instruções de acesso enviadas pelo E-mail Central.'):'Falha no envio das instruções pelo E-mail Central: '.($error?:'erro não informado').'.';$this->recordEvent($id,'access-email:'.$communicationId,$success?'access_sent':'access_send_failed',$description,$userId);$this->database->commit();}catch(\Throwable$e){$this->database->rollBack();throw$e;}
    }

    private function poleIdForUnit(int $unitId): int
    {
        $statement=$this->database->prepare('SELECT COALESCE((SELECT id FROM organization_poles WHERE unit_id=:unit_direct AND is_active=1 LIMIT 1),(SELECT p.id FROM units u INNER JOIN organization_poles p ON p.organization_id=u.organization_id AND p.is_primary=1 AND p.is_active=1 WHERE u.id=:unit_primary LIMIT 1))');
        $statement->execute(['unit_direct'=>$unitId,'unit_primary'=>$unitId]);$id=(int)$statement->fetchColumn();if($id<1)throw new RuntimeException('Cadastre um polo ativo para esta unidade antes de criar a matrícula.');return$id;
    }

    /** @return array{0:int,1:int} */
    private function validatedDestination(int $moodleCourseId,?int $connectionId,?int $remoteCourseId): array
    {
        if(($connectionId??0)<1){$statement=$this->database->query("SELECT id FROM ava_connections WHERE connection_key='shared:ava-cursos' AND is_active=1 LIMIT 1");$connectionId=(int)$statement->fetchColumn();}
        if(($connectionId??0)<1)throw new RuntimeException('Nenhum AVA ativo foi definido para esta matrícula.');
        $statement=$this->database->prepare("SELECT COUNT(*) FROM ava_connections WHERE id=:connection AND is_active=1 AND (connection_type='shared' OR organization_id=:organization)");
        $statement->execute(['connection'=>$connectionId,'organization'=>$this->organizationId]);
        if((int)$statement->fetchColumn()!==1)throw new RuntimeException('O AVA escolhido não pertence a esta franquia ou está inativo.');
        if(($remoteCourseId??0)<1){$statement=$this->database->prepare('SELECT remote_course_id FROM ava_course_mappings WHERE connection_id=:connection AND moodle_course_id=:course LIMIT 1');$statement->execute(['connection'=>$connectionId,'course'=>$moodleCourseId]);$remoteCourseId=(int)$statement->fetchColumn();}
        if(($remoteCourseId??0)<1)throw new RuntimeException('O curso ainda não está mapeado no AVA escolhido.');
        return[(int)$connectionId,(int)$remoteCourseId];
    }

    private function recordEvent(int $enrollmentId,string $key,string $type,string $description,?int $userId=null): void
    {
        $s=$this->database->prepare('INSERT IGNORE INTO student_enrollment_events(enrollment_id,event_key,event_type,description,created_by) VALUES(:enrollment,:event_key,:event_type,:description,:created_by)');$s->execute(['enrollment'=>$enrollmentId,'event_key'=>$key,'event_type'=>$type,'description'=>$description,'created_by'=>$userId]);
    }
}
