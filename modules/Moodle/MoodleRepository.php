<?php

declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use PDO;

final readonly class MoodleRepository
{
    public function __construct(private PDO$database){}

    /** @param array<string,mixed> $course */
    public function upsertCourse(array$course):void
    {
        $sql='INSERT INTO moodle_courses(moodle_course_id,shortname,fullname,idnumber,category_id,visible,start_at,end_at,raw_json) VALUES(:id,:short,:full,:number,:category,:visible,:start,:end,:raw) ON DUPLICATE KEY UPDATE shortname=VALUES(shortname),fullname=VALUES(fullname),idnumber=VALUES(idnumber),category_id=VALUES(category_id),visible=VALUES(visible),start_at=VALUES(start_at),end_at=VALUES(end_at),raw_json=VALUES(raw_json),synced_at=NOW()';
        $this->database->prepare($sql)->execute(['id'=>(int)($course['id']??0),'short'=>(string)($course['shortname']??''),'full'=>(string)($course['fullname']??''),'number'=>$this->nullable($course['idnumber']??null),'category'=>(int)($course['categoryid']??0),'visible'=>(int)(bool)($course['visible']??true),'start'=>$this->timestamp($course['startdate']??null),'end'=>$this->timestamp($course['enddate']??null),'raw'=>json_encode($course,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
    }

    /** @param array<string,mixed> $user */
    public function upsertUser(array$user):void
    {
        $sql='INSERT INTO moodle_users(moodle_user_id,username,firstname,lastname,fullname,email,idnumber,suspended,raw_json) VALUES(:id,:username,:first,:last,:full,:email,:number,:suspended,:raw) ON DUPLICATE KEY UPDATE username=VALUES(username),firstname=VALUES(firstname),lastname=VALUES(lastname),fullname=VALUES(fullname),email=VALUES(email),idnumber=VALUES(idnumber),suspended=VALUES(suspended),raw_json=VALUES(raw_json),synced_at=NOW()';
        $this->database->prepare($sql)->execute(['id'=>(int)($user['id']??0),'username'=>(string)($user['username']??''),'first'=>(string)($user['firstname']??''),'last'=>(string)($user['lastname']??''),'full'=>(string)($user['fullname']??trim((string)($user['firstname']??'').' '.(string)($user['lastname']??''))),'email'=>$this->nullable($user['email']??null),'number'=>$this->nullable($user['idnumber']??null),'suspended'=>(int)(bool)($user['suspended']??false),'raw'=>json_encode($user,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
    }

    public function upsertEnrolment(int$courseId,int$userId,int$timeStart,int$timeEnd):void
    {
        $sql='INSERT INTO moodle_enrolments(moodle_course_id,moodle_user_id,time_start,time_end,is_active) VALUES(:course,:user,:start,:end,1) ON DUPLICATE KEY UPDATE time_start=VALUES(time_start),time_end=VALUES(time_end),is_active=1,synced_at=NOW()';
        $this->database->prepare($sql)->execute(['course'=>$courseId,'user'=>$userId,'start'=>$this->timestamp($timeStart),'end'=>$this->timestamp($timeEnd)]);
    }

    /** @return array{courses:int,users:int,enrolments:int,linked:int,review:int} */
    public function summary():array
    {
        return['courses'=>(int)$this->database->query('SELECT COUNT(*) FROM moodle_courses')->fetchColumn(),'users'=>(int)$this->database->query('SELECT COUNT(*) FROM moodle_users')->fetchColumn(),'enrolments'=>(int)$this->database->query('SELECT COUNT(*) FROM moodle_enrolments WHERE is_active=1')->fetchColumn(),'linked'=>(int)$this->database->query('SELECT COUNT(*) FROM moodle_users WHERE finance_customer_id IS NOT NULL')->fetchColumn(),'review'=>(int)$this->database->query('SELECT COUNT(*) FROM moodle_users WHERE finance_customer_id IS NULL')->fetchColumn()];
    }

    /** @return list<array<string,mixed>> */
    public function coursesList():array{return$this->database->query('SELECT * FROM moodle_courses ORDER BY fullname LIMIT 100')->fetchAll();}

    /** Vincula apenas correspondencias unicas. CPF tem prioridade; divergencia entre CPF e e-mail vira conflito. */
    public function reconcileAutomatically():array
    {
        $users=$this->database->query("SELECT id,email,idnumber FROM moodle_users WHERE finance_customer_id IS NULL AND reconciliation_status IN ('pending','conflict')")->fetchAll();
        $linked=0;$conflicts=0;
        foreach($users as$user){$document=preg_replace('/\D/','',(string)($user['idnumber']??''))??'';$email=strtolower(trim((string)($user['email']??'')));$byDocument=$document===''?[]:$this->customerIdsByDocument($document);$byEmail=$email===''?[]:$this->customerIdsByEmail($email);$candidate=null;$method=null;$conflict=false;
            if(count($byDocument)===1){$candidate=$byDocument[0];$method='cpf';if(count($byEmail)===1&&$byEmail[0]!==$candidate)$conflict=true;elseif(count($byEmail)>1)$conflict=true;}
            elseif(count($byDocument)>1)$conflict=true;
            elseif(count($byEmail)===1){$candidate=$byEmail[0];$method='email';}
            elseif(count($byEmail)>1)$conflict=true;
            if($conflict){$this->setReconciliation((int)$user['id'],null,'conflict',null,null);$conflicts++;continue;}
            if($candidate!==null){$this->setReconciliation((int)$user['id'],$candidate,'linked',$method,null);$linked++;}
            else $this->setReconciliation((int)$user['id'],null,'pending',null,null);
        }
        return['linked'=>$linked,'conflicts'=>$conflicts];
    }

    /** @return list<array<string,mixed>> */
    public function reconciliationList(string$search='',string$status='',int$limit=200):array
    {
        $where=[];$params=[];if($search!==''){$where[]='(m.fullname LIKE :term OR m.email LIKE :term OR m.idnumber LIKE :term OR f.name LIKE :term)';$params['term']='%'.$search.'%';}if(in_array($status,['pending','linked','conflict'],true)){$where[]='m.reconciliation_status=:status';$params['status']=$status;}$sql='SELECT m.*,f.name finance_name,f.cpf_cnpj finance_document,f.email finance_email,u.name unit_name,(SELECT COUNT(*) FROM moodle_enrolments e WHERE e.moodle_user_id=m.moodle_user_id AND e.is_active=1) enrolment_count FROM moodle_users m LEFT JOIN finance_customers f ON f.id=m.finance_customer_id LEFT JOIN units u ON u.id=f.unit_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY FIELD(m.reconciliation_status,\'conflict\',\'pending\',\'linked\'),m.fullname LIMIT '.max(1,min(500,$limit));$s=$this->database->prepare($sql);$s->execute($params);return$s->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function candidatesFor(int$moodleUserId):array
    {
        $s=$this->database->prepare('SELECT email,idnumber FROM moodle_users WHERE id=:id');$s->execute(['id'=>$moodleUserId]);$user=$s->fetch();if(!is_array($user))return[];$document=preg_replace('/\D/','',(string)($user['idnumber']??''))??'';$email=strtolower(trim((string)($user['email']??'')));$conditions=[];$params=[];if($document!==''){$conditions[]="REPLACE(REPLACE(REPLACE(f.cpf_cnpj,'.',''),'-',''),'/','')=:document";$params['document']=$document;}if($email!==''){$conditions[]='LOWER(f.email)=:email';$params['email']=$email;}if($conditions===[])return[];$q=$this->database->prepare('SELECT f.id,f.name,f.cpf_cnpj,f.email,u.name unit_name FROM finance_customers f LEFT JOIN units u ON u.id=f.unit_id WHERE f.is_deleted=0 AND ('.implode(' OR ',$conditions).') ORDER BY f.name LIMIT 20');$q->execute($params);return$q->fetchAll();
    }

    public function reconcileManually(int$moodleUserId,?int$financeCustomerId,int$reviewerId):void
    {
        if($financeCustomerId===null){$this->setReconciliation($moodleUserId,null,'pending',null,$reviewerId);return;}$s=$this->database->prepare('SELECT COUNT(*) FROM finance_customers WHERE id=:id AND is_deleted=0');$s->execute(['id'=>$financeCustomerId]);if((int)$s->fetchColumn()!==1)throw new \RuntimeException('Aluno financeiro nao encontrado.');$this->setReconciliation($moodleUserId,$financeCustomerId,'linked','manual',$reviewerId);
    }

    private function customerIdsByDocument(string$document):array{$s=$this->database->prepare("SELECT id FROM finance_customers WHERE REPLACE(REPLACE(REPLACE(cpf_cnpj,'.',''),'-',''),'/','')=:value AND is_deleted=0");$s->execute(['value'=>$document]);return array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));}
    private function customerIdsByEmail(string$email):array{$s=$this->database->prepare('SELECT id FROM finance_customers WHERE LOWER(email)=:value AND is_deleted=0');$s->execute(['value'=>$email]);return array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));}
    private function setReconciliation(int$id,?int$customerId,string$status,?string$method,?int$reviewerId):void{$s=$this->database->prepare('UPDATE moodle_users SET finance_customer_id=:customer,reconciliation_status=:status,match_method=:method,matched_at=CASE WHEN :customer2 IS NULL THEN NULL ELSE NOW() END,reviewed_by=:reviewer WHERE id=:id');$s->execute(['customer'=>$customerId,'status'=>$status,'method'=>$method,'customer2'=>$customerId,'reviewer'=>$reviewerId,'id'=>$id]);}

    private function nullable(mixed$value):?string{$value=trim((string)$value);return$value===''?null:$value;}
    private function timestamp(mixed$value):?string{$value=(int)$value;return$value>0?date('Y-m-d H:i:s',$value):null;}
}
