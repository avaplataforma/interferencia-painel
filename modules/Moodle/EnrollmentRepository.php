<?php declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use PDO;
use RuntimeException;

final readonly class EnrollmentRepository
{
    public function __construct(private PDO $database){}

    public function all(array $unitIds): array
    {
        if($unitIds===[])return[];
        $marks=implode(',',array_fill(0,count($unitIds),'?'));
        $sql="SELECT e.*,f.name student_name,f.cpf_cnpj,m.fullname course_name,m.shortname,p.name product_name,COALESCE(e.final_value,p.value) value,p.max_installments,c.name campaign_name,u.name unit_name,a.name attendant_name,fp.status payment_status FROM student_enrollments e INNER JOIN finance_customers f ON f.id=e.finance_customer_id INNER JOIN moodle_courses m ON m.id=e.moodle_course_id LEFT JOIN finance_products p ON p.id=e.finance_product_id LEFT JOIN finance_campaigns c ON c.id=e.campaign_id INNER JOIN units u ON u.id=e.unit_id LEFT JOIN users a ON a.id=e.attendant_user_id LEFT JOIN finance_payments fp ON fp.id=e.finance_payment_id WHERE e.unit_id IN ($marks) ORDER BY e.created_at DESC,e.id DESC LIMIT 500";
        $s=$this->database->prepare($sql);$s->execute($unitIds);return$s->fetchAll();
    }

    public function eventsFor(array $enrollmentIds): array
    {
        if($enrollmentIds===[])return[];
        $marks=implode(',',array_fill(0,count($enrollmentIds),'?'));
        $s=$this->database->prepare("SELECT e.*,u.name user_name FROM student_enrollment_events e LEFT JOIN users u ON u.id=e.created_by WHERE e.enrollment_id IN ($marks) ORDER BY e.created_at DESC,e.id DESC");
        $s->execute($enrollmentIds);$grouped=[];foreach($s->fetchAll()as$event)$grouped[(int)$event['enrollment_id']][]=$event;return$grouped;
    }

    public function create(int $customerId,int $productId,?int $campaignId,int $unitId,int $creatorId,int $attendantId,array $allowedUnits): int
    {
        if(!in_array($unitId,$allowedUnits,true))throw new RuntimeException('Selecione uma unidade permitida.');
        if($productId<1)throw new RuntimeException('Selecione o curso contratado.');
        $s=$this->database->prepare('SELECT COUNT(*) FROM finance_customers WHERE id=:id AND unit_id=:unit AND is_deleted=0');$s->execute(['id'=>$customerId,'unit'=>$unitId]);if((int)$s->fetchColumn()!==1)throw new RuntimeException('Aluno não encontrado nesta unidade.');
        $s=$this->database->prepare('SELECT value,moodle_course_id FROM finance_products WHERE id=:id AND is_active=1 AND value>=5 AND moodle_course_id IS NOT NULL AND (unit_id=:unit OR unit_id IS NULL)');$s->execute(['id'=>$productId,'unit'=>$unitId]);$product=$s->fetch();if(!is_array($product))throw new RuntimeException('Curso contratado inválido ou ainda não configurado.');
        $courseId=(int)$product['moodle_course_id'];$base=(float)$product['value'];$discount=0.0;
        if($campaignId!==null){$s=$this->database->prepare('SELECT discount_percent FROM finance_campaigns WHERE id=:id AND is_active=1 AND valid_until>=CURRENT_DATE');$s->execute(['id'=>$campaignId]);$found=$s->fetchColumn();if($found===false)throw new RuntimeException('Campanha inválida ou expirada.');$discount=(float)$found;}
        $final=round($base*(1-$discount/100),2);if($final<5)throw new RuntimeException('O desconto deixa a cobrança abaixo do valor mínimo de R$ 5,00.');
        $this->database->beginTransaction();
        try{$s=$this->database->prepare('INSERT INTO student_enrollments(finance_customer_id,moodle_course_id,finance_product_id,campaign_id,base_value,discount_percent,final_value,unit_id,attendant_user_id,created_by) VALUES(:customer,:course,:product,:campaign,:base,:discount,:final,:unit,:attendant,:creator)');$s->execute(['customer'=>$customerId,'course'=>$courseId,'product'=>$productId,'campaign'=>$campaignId,'base'=>$base,'discount'=>$discount,'final'=>$final,'unit'=>$unitId,'attendant'=>$attendantId,'creator'=>$creatorId]);$id=(int)$this->database->lastInsertId();$this->recordEvent($id,'enrollment-created:'.$id,'enrollment_created','Matrícula cadastrada no Painel.',$creatorId);$this->database->commit();return$id;}catch(\Throwable$e){$this->database->rollBack();throw$e;}
    }

    public function createWaived(int$customerId,int$courseId,int$unitId,string$reason,int$userId,array$allowedUnits):int
    {
        $reason=trim($reason);if(!in_array($unitId,$allowedUnits,true))throw new RuntimeException('Selecione uma unidade permitida.');if(mb_strlen($reason)<10||mb_strlen($reason)>500)throw new RuntimeException('Informe o motivo da bolsa ou cortesia, entre 10 e 500 caracteres.');
        $s=$this->database->prepare("SELECT COUNT(*) FROM finance_customers WHERE id=:customer AND unit_id=:unit AND student_status='active' AND is_deleted=0");$s->execute(['customer'=>$customerId,'unit'=>$unitId]);if((int)$s->fetchColumn()!==1)throw new RuntimeException('Aluno não encontrado na unidade selecionada.');
        $s=$this->database->prepare('SELECT COUNT(*) FROM moodle_courses WHERE id=:course AND visible=1');$s->execute(['course'=>$courseId]);if((int)$s->fetchColumn()!==1)throw new RuntimeException('Curso indisponível no AVA.');
        $s=$this->database->prepare("SELECT COUNT(*) FROM student_enrollments WHERE finance_customer_id=:customer AND moodle_course_id=:course AND status IN ('payment_waived','payment_confirmed') AND moodle_enrolment_status IN ('not_released','released')");$s->execute(['customer'=>$customerId,'course'=>$courseId]);if((int)$s->fetchColumn()>0)throw new RuntimeException('Este aluno já possui uma matrícula ativa ou preparada nesse curso.');
        $this->database->beginTransaction();try{$s=$this->database->prepare("INSERT INTO student_enrollments(finance_customer_id,moodle_course_id,unit_id,attendant_user_id,status,payment_waiver_reason,payment_waived_at,payment_waived_by,created_by) VALUES(:customer,:course,:unit,:attendant,'payment_waived',:reason,NOW(),:waived_by,:creator)");$s->execute(['customer'=>$customerId,'course'=>$courseId,'unit'=>$unitId,'attendant'=>$userId,'reason'=>$reason,'waived_by'=>$userId,'creator'=>$userId]);$id=(int)$this->database->lastInsertId();$this->recordEvent($id,'enrollment-created:'.$id,'enrollment_created','Matrícula cadastrada no Painel.',$userId);$this->recordEvent($id,'payment-waived:'.$id,'payment_waived','Pagamento dispensado por bolsa ou cortesia. Motivo: '.$reason,$userId);$this->database->commit();return$id;}catch(\Throwable$e){$this->database->rollBack();throw$e;}
    }

    public function deleteDraft(int $id,array $allowedUnits): void
    {
        if($allowedUnits===[])throw new RuntimeException('Matrícula não encontrada.');$marks=implode(',',array_fill(0,count($allowedUnits),'?'));$s=$this->database->prepare("DELETE FROM student_enrollments WHERE id=? AND unit_id IN ($marks) AND finance_payment_id IS NULL AND status='awaiting_charge' AND moodle_enrolment_status='not_released'");$s->execute(array_merge([$id],$allowedUnits));if($s->rowCount()!==1)throw new RuntimeException('A matrícula não pode ser excluída porque já possui movimentação financeira ou acadêmica.');
    }

    public function chargeContext(int $id,array $units): ?array
    {
        if($units===[])return null;$marks=implode(',',array_fill(0,count($units),'?'));$s=$this->database->prepare("SELECT e.*,p.name product_name,COALESCE(e.final_value,p.value) value,p.max_installments,c.name campaign_name FROM student_enrollments e INNER JOIN finance_products p ON p.id=e.finance_product_id LEFT JOIN finance_campaigns c ON c.id=e.campaign_id WHERE e.id=? AND e.unit_id IN ($marks) AND e.finance_payment_id IS NULL LIMIT 1");$s->execute(array_merge([$id],$units));$row=$s->fetch();return is_array($row)?$row:null;
    }

    public function attachPayment(int $id,int $paymentId): void
    {
        $s=$this->database->prepare("UPDATE student_enrollments SET finance_payment_id=:payment,status='awaiting_payment' WHERE id=:id AND finance_payment_id IS NULL");$s->execute(['payment'=>$paymentId,'id'=>$id]);if($s->rowCount()===1)$this->recordEvent($id,'payment-linked:'.$id.':'.$paymentId,'charge_created','Cobrança emitida e vinculada à matrícula.');
    }

    public function handlePaymentUpdate(string $asaasPaymentId,string $status): void
    {
        $s=$this->database->prepare('SELECT e.id,e.status FROM student_enrollments e INNER JOIN finance_payments p ON p.id=e.finance_payment_id WHERE p.asaas_payment_id=:payment LIMIT 1');$s->execute(['payment'=>$asaasPaymentId]);$enrollment=$s->fetch();if(!is_array($enrollment))return;
        $id=(int)$enrollment['id'];
        if(in_array($status,['RECEIVED','CONFIRMED','RECEIVED_IN_CASH'],true)){$this->database->prepare("UPDATE student_enrollments SET status='payment_confirmed' WHERE id=:id AND status<>'payment_confirmed'")->execute(['id'=>$id]);$this->recordEvent($id,'payment-confirmed:'.$asaasPaymentId,'payment_confirmed','Pagamento confirmado pelo Asaas. Aguardando liberação no AVA.');return;}
        if(in_array($status,['CANCELED','REFUNDED'],true)){$this->database->prepare("UPDATE student_enrollments SET status='payment_interrupted' WHERE id=:id")->execute(['id'=>$id]);$this->recordEvent($id,'payment-interrupted:'.$asaasPaymentId.':'.$status,'payment_interrupted',$status==='REFUNDED'?'Pagamento estornado no Asaas.':'Cobrança cancelada no Asaas.');}
    }

    public function releaseContext(int$id,array$allowedUnits):?array
    {
        if($allowedUnits===[])return null;$marks=implode(',',array_fill(0,count($allowedUnits),'?'));
        $sql="SELECT e.id,e.finance_customer_id,e.unit_id,e.status,e.moodle_enrolment_status,f.name,f.email,f.cpf_cnpj,mc.moodle_course_id FROM student_enrollments e INNER JOIN finance_customers f ON f.id=e.finance_customer_id INNER JOIN moodle_courses mc ON mc.id=e.moodle_course_id WHERE e.id=? AND e.unit_id IN ($marks) LIMIT 1";
        $s=$this->database->prepare($sql);$s->execute(array_merge([$id],$allowedUnits));$row=$s->fetch();return is_array($row)?$row:null;
    }

    public function markReleased(int$id,int$avaUserId,int$courseId,int$customerId,int$userId,array$avaUser):void
    {
        $this->database->beginTransaction();
        try{$this->database->prepare("UPDATE student_enrollments SET moodle_enrolment_status='released',ava_user_id=:ava_user,ava_released_at=NOW(),ava_released_by=:released_by,ava_last_error=NULL WHERE id=:id AND status IN ('payment_confirmed','payment_waived') AND moodle_enrolment_status<>'released'")->execute(['ava_user'=>$avaUserId,'released_by'=>$userId,'id'=>$id]);
            $this->database->prepare("UPDATE moodle_users SET finance_customer_id=:customer,reconciliation_status='linked',match_method='assisted_release',reviewed_by=:user,matched_at=NOW() WHERE moodle_user_id=:ava_user")->execute(['customer'=>$customerId,'user'=>$userId,'ava_user'=>$avaUserId]);
            $this->database->prepare('INSERT INTO moodle_enrolments(moodle_course_id,moodle_user_id,time_start,is_active) VALUES(:course,:ava_user,NOW(),1) ON DUPLICATE KEY UPDATE is_active=1,time_start=COALESCE(time_start,NOW()),synced_at=NOW()')->execute(['course'=>$courseId,'ava_user'=>$avaUserId]);
            $this->recordEvent($id,'ava-released:'.$id.':'.$avaUserId.':'.$courseId,'ava_released','Acesso ao curso liberado no AVA.',$userId);$this->database->commit();
        }catch(\Throwable$e){$this->database->rollBack();throw$e;}
    }

    public function recordReleaseFailure(int$id,string$message,int$userId):void
    {
        $message=mb_substr(trim($message),0,500);$s=$this->database->prepare('UPDATE student_enrollments SET ava_last_error=:error WHERE id=:id');$s->execute(['error'=>$message,'id'=>$id]);if($s->rowCount()===1)$this->recordEvent($id,'ava-release-failed:'.$id.':'.hash('sha256',$message),'ava_release_failed','Falha ao liberar no AVA: '.$message,$userId);
    }

    public function accessCommunicationContext(int$id,array$allowedUnits):?array
    {
        if($allowedUnits===[])return null;$marks=implode(',',array_fill(0,count($allowedUnits),'?'));
        $sql="SELECT e.id,e.unit_id,e.moodle_enrolment_status,e.ava_user_id,f.name,f.email,f.mobile_phone,f.phone,f.cpf_cnpj,c.fullname course_name,u.name unit_name,mu.username FROM student_enrollments e INNER JOIN finance_customers f ON f.id=e.finance_customer_id INNER JOIN moodle_courses c ON c.id=e.moodle_course_id INNER JOIN units u ON u.id=e.unit_id LEFT JOIN moodle_users mu ON mu.moodle_user_id=e.ava_user_id WHERE e.id=? AND e.unit_id IN ($marks) AND e.moodle_enrolment_status='released' LIMIT 1";
        $s=$this->database->prepare($sql);$s->execute(array_merge([$id],$allowedUnits));$row=$s->fetch();return is_array($row)?$row:null;
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

    private function recordEvent(int $enrollmentId,string $key,string $type,string $description,?int $userId=null): void
    {
        $s=$this->database->prepare('INSERT IGNORE INTO student_enrollment_events(enrollment_id,event_key,event_type,description,created_by) VALUES(:enrollment,:event_key,:event_type,:description,:created_by)');$s->execute(['enrollment'=>$enrollmentId,'event_key'=>$key,'event_type'=>$type,'description'=>$description,'created_by'=>$userId]);
    }
}
