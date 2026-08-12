<?php

declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use PDO;
use RuntimeException;
use Throwable;

final readonly class AcademicOrganizationRepository
{
    public function __construct(private PDO $database) {}

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $connection
     * @param array{pole_id:int,organization_id:int,franchise_code:string,franchise_name:string,pole_code:string,pole_name:string,legacy_value:string} $identity
     * @return array{cohort_id:int,group_id:int,period_code:string,payload:array<string,mixed>}
     */
    public function prepareForEnrollment(array $context,array $connection,array $identity,int $remoteCourseId): array
    {
        $enrollmentId=(int)($context['id']??0);
        $organizationId=(int)($context['organization_id']??0);
        $connectionId=(int)($connection['id']??0);
        $localCourseId=(int)($context['moodle_course_local_id']??0);
        $trailId=(int)($context['catalog_trail_id']??0);
        if($enrollmentId<1||$organizationId<1||$connectionId<1||$remoteCourseId<1||$identity['pole_id']<1){
            throw new RuntimeException('Dados insuficientes para organizar a turma no AVA.');
        }

        $period=$this->periodCode((string)($context['created_at']??''));
        $franchiseCode=$this->code((string)$identity['franchise_code'],'franquia');
        $poleCode=$this->code((string)$identity['pole_code'],'polo');
        $courseLabel=trim((string)($context['course_shortname']??$context['course_fullname']??('Curso '.$remoteCourseId)));
        $franchiseName=trim((string)($identity['franchise_name']??$identity['franchise_code']));
        $classType=$trailId>0?'trail':'course';
        $classId=$trailId>0?$trailId:($localCourseId>0?$localCourseId:$remoteCourseId);
        $classReference=$classType.':'.$classId;
        $classLabel=$trailId>0?trim((string)($context['trail_name']??('Trilha #'.$trailId))):$courseLabel;
        $classCode=$trailId>0
            ?$this->limited('mi-turma-'.$franchiseCode.'-'.$poleCode.'-trail-'.$trailId.'-'.$period)
            :$this->limited('mi-turma-'.$franchiseCode.'-'.$poleCode.'-'.$remoteCourseId.'-'.$period);
        $className=$franchiseName.' · '.$identity['pole_name'].' · '.$classLabel.' · '.$period;
        $cohortCode=$this->limited('mi-franquia-'.$franchiseCode);
        $cohortName='Franquia '.$franchiseName;
        $groupCode=$trailId>0?$this->limited($classCode.'-curso-'.$remoteCourseId):$classCode;
        $groupName=$className;

        $this->database->beginTransaction();
        try{
            $cohort=$this->upsertCohort($connectionId,$organizationId,$cohortCode,$cohortName);
            $group=$this->upsertGroup($cohort,$connectionId,$organizationId,$identity['pole_id'],$localCourseId,$remoteCourseId,$period,$classType,$classReference,$classCode,$className,$groupCode,$groupName);
            $statement=$this->database->prepare('UPDATE student_enrollments SET catalog_trail_id=:trail,ava_academic_cohort_id=:cohort,ava_academic_group_id=:group,academic_period_code=:period WHERE id=:id');
            $statement->execute(['trail'=>$trailId>0?$trailId:null,'cohort'=>$cohort,'group'=>$group,'period'=>$period,'id'=>$enrollmentId]);
            $this->database->commit();
        }catch(Throwable $exception){
            if($this->database->inTransaction())$this->database->rollBack();
            throw $exception;
        }

        return[
            'cohort_id'=>$cohort,
            'group_id'=>$group,
            'period_code'=>$period,
            'payload'=>[
                'organizationcode'=>$franchiseCode,
                'organizationname'=>$franchiseName,
                'polecode'=>$poleCode,
                'polename'=>(string)$identity['pole_name'],
                'periodcode'=>$period,
                'cohortcode'=>$cohortCode,
                'cohortname'=>$cohortName,
                'groupcode'=>$groupCode,
                'groupname'=>$groupName,
            ],
        ];
    }

    /** @param array<string,mixed> $remote */
    public function markSynced(int $cohortId,int $groupId,array $remote): void
    {
        $cohortRemote=(int)($remote['cohortid']??0);
        $groupRemote=(int)($remote['groupid']??0);
        if($cohortRemote<1||$groupRemote<1)throw new RuntimeException('O AVA não confirmou a coorte e o grupo da matrícula.');
        $this->database->prepare("UPDATE ava_academic_cohorts SET remote_cohort_id=:remote,sync_status='synced',last_synced_at=NOW(),last_error=NULL WHERE id=:id")->execute(['remote'=>$cohortRemote,'id'=>$cohortId]);
        $this->database->prepare("UPDATE ava_academic_groups SET remote_group_id=:remote,sync_status='synced',last_synced_at=NOW(),last_error=NULL WHERE id=:id")->execute(['remote'=>$groupRemote,'id'=>$groupId]);
    }

    public function markFailed(int $cohortId,int $groupId,string $message): void
    {
        $message=mb_substr(trim($message),0,500);
        $this->database->prepare("UPDATE ava_academic_cohorts SET sync_status='failed',last_error=:error WHERE id=:id")->execute(['error'=>$message,'id'=>$cohortId]);
        $this->database->prepare("UPDATE ava_academic_groups SET sync_status='failed',last_error=:error WHERE id=:id")->execute(['error'=>$message,'id'=>$groupId]);
    }

    /** @return array{cohorts:int,classes:int,groups:int,synced:int,pending:int,failed:int} */
    public function summary(): array
    {
        $row=$this->database->query("SELECT (SELECT COUNT(*) FROM ava_academic_cohorts) cohorts,(SELECT COUNT(DISTINCT organization_id,organization_pole_id,class_type,class_reference,period_code) FROM ava_academic_groups) classes,(SELECT COUNT(*) FROM ava_academic_groups) groups,(SELECT COUNT(*) FROM ava_academic_groups WHERE sync_status='synced') synced,(SELECT COUNT(*) FROM ava_academic_groups WHERE sync_status='pending') pending,(SELECT COUNT(*) FROM ava_academic_groups WHERE sync_status='failed') failed")->fetch()?:[];
        return['cohorts'=>(int)($row['cohorts']??0),'classes'=>(int)($row['classes']??0),'groups'=>(int)($row['groups']??0),'synced'=>(int)($row['synced']??0),'pending'=>(int)($row['pending']??0),'failed'=>(int)($row['failed']??0)];
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $limit=12): array
    {
        $limit=max(1,min(50,$limit));
        return$this->database->query("SELECT g.id,g.name,g.code,g.class_type,g.class_reference,g.class_name,g.period_code,g.sync_status,g.remote_group_id,g.last_error,g.updated_at,c.name cohort_name,c.remote_cohort_id,o.display_name organization_name,p.name pole_name,mc.fullname course_name FROM ava_academic_groups g INNER JOIN ava_academic_cohorts c ON c.id=g.ava_academic_cohort_id INNER JOIN organizations o ON o.id=g.organization_id INNER JOIN organization_poles p ON p.id=g.organization_pole_id LEFT JOIN moodle_courses mc ON mc.id=g.moodle_course_id ORDER BY g.updated_at DESC,g.id DESC LIMIT $limit")->fetchAll();
    }

    private function upsertCohort(int $connectionId,int $organizationId,string $code,string $name): int
    {
        $sql="INSERT INTO ava_academic_cohorts(ava_connection_id,organization_id,catalog_trail_id,moodle_course_id,scope_type,scope_reference,code,name) VALUES(:connection,:organization,NULL,NULL,'organization',:scope_reference,:code,:name) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),catalog_trail_id=NULL,moodle_course_id=NULL,scope_type='organization',scope_reference=VALUES(scope_reference),code=VALUES(code),name=VALUES(name)";
        $this->database->prepare($sql)->execute(['connection'=>$connectionId,'organization'=>$organizationId,'scope_reference'=>'organization:'.$organizationId,'code'=>$code,'name'=>$name]);
        return(int)$this->database->lastInsertId();
    }

    private function upsertGroup(int $cohortId,int $connectionId,int $organizationId,int $poleId,int $courseId,int $remoteCourseId,string $period,string $classType,string $classReference,string $classCode,string $className,string $code,string $name): int
    {
        $sql="INSERT INTO ava_academic_groups(ava_academic_cohort_id,ava_connection_id,organization_id,organization_pole_id,moodle_course_id,remote_course_id,period_code,class_type,class_reference,class_code,class_name,code,name) VALUES(:cohort,:connection,:organization,:pole,:course,:remote_course,:period,:class_type,:class_reference,:class_code,:class_name,:code,:name) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),ava_academic_cohort_id=VALUES(ava_academic_cohort_id),moodle_course_id=VALUES(moodle_course_id),class_code=VALUES(class_code),class_name=VALUES(class_name),code=VALUES(code),name=VALUES(name)";
        $this->database->prepare($sql)->execute(['cohort'=>$cohortId,'connection'=>$connectionId,'organization'=>$organizationId,'pole'=>$poleId,'course'=>$courseId>0?$courseId:null,'remote_course'=>$remoteCourseId,'period'=>$period,'class_type'=>$classType,'class_reference'=>$classReference,'class_code'=>$classCode,'class_name'=>$className,'code'=>$code,'name'=>$name]);
        return(int)$this->database->lastInsertId();
    }

    private function periodCode(string $createdAt): string
    {
        $time=strtotime($createdAt);
        if($time===false)$time=time();
        return date('Y',$time).'-'.((int)date('n',$time)<=6?'1':'2');
    }

    private function code(string $value,string $fallback): string
    {
        $value=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value)?:$value;
        $value=strtolower(trim((string)preg_replace('/[^A-Za-z0-9]+/','-',$value),'-'));
        return$value!==''?$value:$fallback;
    }

    private function limited(string $value): string
    {
        if(strlen($value)<=180)return$value;
        return substr($value,0,165).'-'.substr(hash('sha256',$value),0,12);
    }
}
