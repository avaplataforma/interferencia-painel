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
        $this->syncProfileFields((int)($user['id']??0),is_array($user['customfields']??null)?$user['customfields']:[]);
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

    /** @return array{summary:array<string,int>,students:list<array<string,mixed>>} */
    public function pedagogicalDashboard(array$unitIds,string$search='',string$risk=''):array
    {
        $empty=['students'=>0,'enrolments'=>0,'released'=>0,'pending'=>0,'blocked'=>0,'never_accessed'=>0,'inactive_7'=>0,'inactive_15'=>0,'inactive_30'=>0,'stalled'=>0,'attention'=>0];if($unitIds===[])return['summary'=>$empty,'students'=>[]];$marks=implode(',',array_fill(0,count($unitIds),'?'));$params=$unitIds;$where="f.unit_id IN ($marks) AND f.is_deleted=0";if($search!==''){$where.=' AND (f.name LIKE ? OR f.cpf_cnpj LIKE ? OR c.fullname LIKE ?)';$term='%'.$search.'%';array_push($params,$term,$term,$term);}
        $sql="SELECT e.id enrollment_id,f.id customer_id,f.name,f.cpf_cnpj,u.id unit_id,u.name unit_name,c.fullname course_name,e.status finance_status,e.moodle_enrolment_status,e.ava_user_id,me.id moodle_enrolment_id,me.is_active,me.time_start,me.time_end,me.completion_percent,me.completion_status,me.progress_synced_at,me.progress_changed_at,me.progress_error,mu.suspended,CAST(JSON_UNQUOTE(JSON_EXTRACT(mu.raw_json,'$.lastaccess')) AS UNSIGNED) last_access FROM student_enrollments e INNER JOIN finance_customers f ON f.id=e.finance_customer_id INNER JOIN units u ON u.id=e.unit_id INNER JOIN moodle_courses c ON c.id=e.moodle_course_id LEFT JOIN moodle_users mu ON mu.moodle_user_id=e.ava_user_id LEFT JOIN moodle_enrolments me ON me.moodle_user_id=e.ava_user_id AND me.moodle_course_id=c.moodle_course_id WHERE $where ORDER BY f.name,c.fullname LIMIT 300";$s=$this->database->prepare($sql);$s->execute($params);$all=$s->fetchAll();$summary=$empty;$unique=[];$students=[];$now=time();foreach($all as$row){$unique[(int)$row['customer_id']]=true;$released=$row['moodle_enrolment_status']==='released';$last=(int)($row['last_access']??0);$blocked=(int)($row['suspended']??0)===1||(isset($row['is_active'])&&(int)$row['is_active']===0);$days=$last>0?(int)floor(($now-$last)/86400):null;$changed=$row['progress_changed_at']?strtotime((string)$row['progress_changed_at']):false;$stalled=$row['completion_status']==='in_progress'&&$changed!==false&&$changed<$now-(15*86400);$row['risk_code']=$blocked?'blocked':(!$released?'pending':($last===0?'never_accessed':($stalled?'stalled':($days>=30?'inactive_30':($days>=15?'inactive_15':($days>=7?'inactive_7':'ok'))))));$row['risk_days']=$days;$summary['enrolments']++;$summary[$released?'released':'pending']++;if($blocked)$summary['blocked']++;if($released&&$last===0)$summary['never_accessed']++;if($released&&$days!==null&&$days>=7)$summary['inactive_7']++;if($released&&$days!==null&&$days>=15)$summary['inactive_15']++;if($released&&$days!==null&&$days>=30)$summary['inactive_30']++;if($stalled)$summary['stalled']++;if(in_array($row['risk_code'],['never_accessed','stalled','inactive_7','inactive_15','inactive_30'],true))$summary['attention']++;$matches=$risk===''||$risk==='all'||$row['risk_code']===$risk||($risk==='inactive_15'&&$row['risk_code']==='inactive_30')||($risk==='inactive_7'&&in_array($row['risk_code'],['inactive_15','inactive_30'],true));if($matches)$students[]=$row;}$summary['students']=count($unique);return['summary'=>$summary,'students'=>$students];
    }

    /** @param list<int> $unitIds @return list<array<string,mixed>> */
    public function progressCandidates(array$unitIds,int$limit=100):array
    {
        if($unitIds===[])return[];$marks=implode(',',array_fill(0,count($unitIds),'?'));$sql="SELECT me.id moodle_enrolment_id,me.moodle_user_id,me.moodle_course_id,mu.username FROM moodle_enrolments me INNER JOIN moodle_users mu ON mu.moodle_user_id=me.moodle_user_id INNER JOIN finance_customers f ON f.id=mu.finance_customer_id WHERE me.is_active=1 AND mu.suspended=0 AND f.is_deleted=0 AND f.unit_id IN ($marks) ORDER BY COALESCE(me.progress_synced_at,'2000-01-01') LIMIT ".max(1,min(300,$limit));$s=$this->database->prepare($sql);$s->execute($unitIds);return$s->fetchAll();
    }

    public function saveProgress(int$enrolmentId,?float$percent,string$status,?string$error):void
    {
        $s=$this->database->prepare('UPDATE moodle_enrolments SET progress_changed_at=CASE WHEN NOT(completion_percent <=> :compare_percent) OR completion_status<>:compare_status THEN NOW() ELSE progress_changed_at END,completion_percent=:percent,completion_status=:status,progress_synced_at=NOW(),progress_error=:error WHERE id=:id');$s->execute(['compare_percent'=>$percent,'compare_status'=>$status,'percent'=>$percent,'status'=>$status,'error'=>$error,'id'=>$enrolmentId]);
    }

    /** @param list<int> $unitIds @return array<string,mixed>|null */
    public function avaStatusContext(int$enrollmentId,array$unitIds):?array
    {
        if($unitIds===[])return null;$marks=implode(',',array_fill(0,count($unitIds),'?'));$s=$this->database->prepare("SELECT e.id,e.ava_user_id,e.moodle_enrolment_status,f.name student_name,mu.suspended FROM student_enrollments e INNER JOIN finance_customers f ON f.id=e.finance_customer_id LEFT JOIN moodle_users mu ON mu.moodle_user_id=e.ava_user_id WHERE e.id=? AND e.unit_id IN ($marks) LIMIT 1");$s->execute([$enrollmentId,...$unitIds]);$row=$s->fetch();return is_array($row)?$row:null;
    }

    public function setLocalUserSuspended(int$userId,bool$suspended):void{$s=$this->database->prepare('UPDATE moodle_users SET suspended=:status,synced_at=NOW() WHERE moodle_user_id=:id');$s->execute(['status'=>$suspended?1:0,'id'=>$userId]);}

    /** @return list<array<string,mixed>> */
    public function coursesList():array{return$this->database->query('SELECT * FROM moodle_courses ORDER BY fullname LIMIT 500')->fetchAll();}

    /** @return array<string,mixed>|null */
    public function unitField():?array{$s=$this->database->query("SELECT * FROM moodle_profile_fields WHERE LOWER(source_name)='polo presencial' OR LOWER(shortname) IN ('polo_presencial','polopresencial') ORDER BY id LIMIT 1");$row=$s->fetch();return is_array($row)?$row:null;}

    /** @return list<array<string,mixed>> */
    public function unitFieldMappings():array
    {
        $field=$this->unitField();if($field===null)return[];$s=$this->database->prepare("SELECT values_list.field_value,m.unit_id,u.name unit_name FROM (SELECT DISTINCT TRIM(field_value) field_value FROM moodle_user_profile_values WHERE field_id=:field AND NULLIF(TRIM(field_value),'') IS NOT NULL) values_list LEFT JOIN moodle_unit_mappings m ON m.field_id=:field2 AND m.field_value=values_list.field_value LEFT JOIN units u ON u.id=m.unit_id ORDER BY values_list.field_value");$s->execute(['field'=>(int)$field['id'],'field2'=>(int)$field['id']]);return$s->fetchAll();
    }

    public function saveUnitMapping(string$fieldValue,?int$unitId):void
    {
        $field=$this->unitField();$fieldValue=trim($fieldValue);if($field===null)throw new \RuntimeException('O campo Polo Presencial ainda não foi localizado no AVA. Execute a sincronização.');if($fieldValue==='')throw new \RuntimeException('Valor de polo inválido.');$this->database->beginTransaction();try{$d=$this->database->prepare('DELETE FROM moodle_unit_mappings WHERE field_id=:field AND field_value=:value');$d->execute(['field'=>(int)$field['id'],'value'=>$fieldValue]);if($unitId!==null){$check=$this->database->prepare('SELECT COUNT(*) FROM units WHERE id=:unit AND is_active=1');$check->execute(['unit'=>$unitId]);if((int)$check->fetchColumn()!==1)throw new \RuntimeException('Unidade inválida.');$this->database->prepare('DELETE FROM moodle_unit_mappings WHERE field_id=:field AND unit_id=:unit')->execute(['field'=>(int)$field['id'],'unit'=>$unitId]);$i=$this->database->prepare('INSERT INTO moodle_unit_mappings(field_id,field_value,unit_id) VALUES(:field,:value,:unit)');$i->execute(['field'=>(int)$field['id'],'value'=>$fieldValue,'unit'=>$unitId]);}$this->database->commit();}catch(\Throwable$e){$this->database->rollBack();throw$e;}
    }

    /** @return array{type:string,value:string}|null */
    public function unitCustomFieldForUnit(int$unitId):?array
    {
        $s=$this->database->prepare('SELECT f.shortname type,m.field_value value FROM moodle_unit_mappings m INNER JOIN moodle_profile_fields f ON f.id=m.field_id WHERE m.unit_id=:unit LIMIT 1');$s->execute(['unit'=>$unitId]);$row=$s->fetch();return is_array($row)?['type'=>(string)$row['type'],'value'=>(string)$row['value']]:null;
    }

    /** @return list<array<string,mixed>> */
    public function profileFieldsCatalog():array{return$this->database->query('SELECT f.*,COUNT(v.id) value_count FROM moodle_profile_fields f LEFT JOIN moodle_user_profile_values v ON v.field_id=f.id GROUP BY f.id ORDER BY f.source_name')->fetchAll();}

    public function saveProfileFieldMapping(int$id,string$destination,bool$visible):void
    {
        if(!in_array($destination,['supplemental','document','phone','mobile_phone','birth_date','education','unit','ignore'],true))throw new \RuntimeException('Destino de campo invalido.');
        $s=$this->database->prepare('UPDATE moodle_profile_fields SET destination_key=:destination,is_visible=:visible WHERE id=:id');$s->execute(['destination'=>$destination,'visible'=>(int)$visible,'id'=>$id]);
        $exists=$this->database->prepare('SELECT COUNT(*) FROM moodle_profile_fields WHERE id=:id');$exists->execute(['id'=>$id]);if((int)$exists->fetchColumn()!==1)throw new \RuntimeException('Campo do Moodle nao encontrado.');
    }

    /** @return array{users:int,fields:int} */
    public function rebuildProfileFieldsFromStoredUsers():array
    {
        $users=0;foreach($this->database->query('SELECT moodle_user_id,raw_json FROM moodle_users')->fetchAll()as$row){$data=json_decode((string)($row['raw_json']??''),true);if(!is_array($data))continue;$fields=is_array($data['customfields']??null)?$data['customfields']:[];$this->syncProfileFields((int)$row['moodle_user_id'],$fields);$users++;}
        return['users'=>$users,'fields'=>(int)$this->database->query('SELECT COUNT(*) FROM moodle_profile_fields')->fetchColumn()];
    }

    /** @return array{student:?array,fields:list<array<string,mixed>>} */
    public function academicProfileForCustomer(int$customerId):array
    {
        $s=$this->database->prepare('SELECT id,moodle_user_id,fullname,email,idnumber,suspended,synced_at FROM moodle_users WHERE finance_customer_id=:customer ORDER BY synced_at DESC LIMIT 1');$s->execute(['customer'=>$customerId]);$student=$s->fetch();if(!is_array($student))return['student'=>null,'fields'=>[]];
        $q=$this->database->prepare("SELECT f.source_name,f.shortname,f.destination_key,v.field_value FROM moodle_user_profile_values v INNER JOIN moodle_profile_fields f ON f.id=v.field_id WHERE v.moodle_user_id=:user AND f.is_visible=1 AND f.destination_key<>'ignore' AND NULLIF(TRIM(v.field_value),'') IS NOT NULL ORDER BY f.source_name");$q->execute(['user'=>(int)$student['moodle_user_id']]);return['student'=>$student,'fields'=>$q->fetchAll()];
    }

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

    /** @param list<mixed> $fields */
    private function syncProfileFields(int$userId,array$fields):void
    {
        if($userId<1)return;$fieldSql='INSERT INTO moodle_profile_fields(shortname,source_name,data_type) VALUES(:shortname,:name,:type) ON DUPLICATE KEY UPDATE source_name=VALUES(source_name),data_type=VALUES(data_type)';$valueSql='INSERT INTO moodle_user_profile_values(moodle_user_id,field_id,field_value,raw_json) VALUES(:user,:field,:value,:raw) ON DUPLICATE KEY UPDATE field_value=VALUES(field_value),raw_json=VALUES(raw_json),synced_at=NOW()';
        foreach($fields as$field){if(!is_array($field))continue;$shortname=trim((string)($field['shortname']??''));if($shortname==='')continue;$this->database->prepare($fieldSql)->execute(['shortname'=>$shortname,'name'=>trim((string)($field['name']??$shortname))?:$shortname,'type'=>$this->nullable($field['type']??null)]);$id=$this->database->prepare('SELECT id FROM moodle_profile_fields WHERE shortname=:shortname');$id->execute(['shortname'=>$shortname]);$fieldId=(int)$id->fetchColumn();if($fieldId<1)continue;$value=$field['value']??null;if(is_array($value))$value=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$this->database->prepare($valueSql)->execute(['user'=>$userId,'field'=>$fieldId,'value'=>$this->nullable($value),'raw'=>json_encode($field,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);}
    }

    private function nullable(mixed$value):?string{$value=trim((string)$value);return$value===''?null:$value;}
    private function timestamp(mixed$value):?string{$value=(int)$value;return$value>0?date('Y-m-d H:i:s',$value):null;}
}
