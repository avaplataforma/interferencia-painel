<?php

declare(strict_types=1);

use Interferencia\Kernel\Database\Migration;

return new class implements Migration
{
    public function id(): string
    {
        return '20260812_000040_consolidate_ava_cohorts_by_franchise';
    }

    public function up(PDO $database): void
    {
        $this->dropConstraint($database,'ava_academic_cohorts','ava_academic_cohort_scope_check');

        $database->exec("ALTER TABLE ava_academic_groups
            ADD class_type VARCHAR(20) NOT NULL DEFAULT 'course' AFTER period_code,
            ADD class_reference VARCHAR(190) NOT NULL DEFAULT '' AFTER class_type,
            ADD class_code VARCHAR(190) NOT NULL DEFAULT '' AFTER class_reference,
            ADD class_name VARCHAR(255) NOT NULL DEFAULT '' AFTER class_code");

        $rows=$database->query("SELECT c.id,c.ava_connection_id,c.organization_id,o.code organization_code,o.display_name organization_name
            FROM ava_academic_cohorts c
            INNER JOIN organizations o ON o.id=c.organization_id
            ORDER BY c.ava_connection_id,c.organization_id,c.id")->fetchAll(PDO::FETCH_ASSOC);
        $cohorts=[];
        foreach($rows as$row)$cohorts[(int)$row['ava_connection_id'].':'.(int)$row['organization_id']][]=$row;

        foreach($cohorts as$records){
            $canonical=$records[0];
            $canonicalId=(int)$canonical['id'];
            $duplicateIds=array_map(static fn(array$row):int=>(int)$row['id'],array_slice($records,1));
            if($duplicateIds!==[]){
                $ids=implode(',',array_map('intval',$duplicateIds));
                $database->exec("UPDATE ava_academic_groups SET ava_academic_cohort_id={$canonicalId} WHERE ava_academic_cohort_id IN ({$ids})");
                $database->exec("UPDATE student_enrollments SET ava_academic_cohort_id={$canonicalId} WHERE ava_academic_cohort_id IN ({$ids})");
                $database->exec("DELETE FROM ava_academic_cohorts WHERE id IN ({$ids})");
            }

            $organizationId=(int)$canonical['organization_id'];
            $code=$this->stableCode((string)$canonical['organization_code'],'franquia-'.$organizationId);
            $statement=$database->prepare("UPDATE ava_academic_cohorts SET catalog_trail_id=NULL,moodle_course_id=NULL,scope_type='organization',scope_reference=:reference,code=:code,name=:name,remote_cohort_id=NULL,sync_status='pending',last_synced_at=NULL,last_error=NULL WHERE id=:id");
            $statement->execute([
                'reference'=>'organization:'.$organizationId,
                'code'=>'mi-franquia-'.$code,
                'name'=>'Franquia '.trim((string)$canonical['organization_name']),
                'id'=>$canonicalId,
            ]);
        }

        $database->exec("UPDATE ava_academic_groups g
            SET g.class_type='course',
                g.class_reference=CONCAT('course:',COALESCE(g.moodle_course_id,g.remote_course_id)),
                g.class_code=g.code,
                g.class_name=g.name");

        $database->exec('ALTER TABLE ava_academic_cohorts ADD UNIQUE KEY ava_academic_cohort_org_unique(ava_connection_id,organization_id)');
        $database->exec('ALTER TABLE ava_academic_groups DROP INDEX ava_academic_group_scope_unique');
        $database->exec('ALTER TABLE ava_academic_groups ADD UNIQUE KEY ava_academic_group_class_scope_unique(ava_connection_id,organization_id,organization_pole_id,class_type,class_reference,remote_course_id,period_code)');
        $database->exec("ALTER TABLE ava_academic_cohorts ADD CONSTRAINT ava_academic_cohort_scope_check CHECK(scope_type IN ('organization'))");
        $database->exec("ALTER TABLE ava_academic_groups ADD CONSTRAINT ava_academic_group_class_type_check CHECK(class_type IN ('course','trail'))");
    }

    public function down(PDO $database): void
    {
        $this->dropConstraint($database,'ava_academic_groups','ava_academic_group_class_type_check');
        $this->dropConstraint($database,'ava_academic_cohorts','ava_academic_cohort_scope_check');
        $database->exec('ALTER TABLE ava_academic_groups DROP INDEX ava_academic_group_class_scope_unique');
        $database->exec('ALTER TABLE ava_academic_groups ADD UNIQUE KEY ava_academic_group_scope_unique(ava_connection_id,organization_id,organization_pole_id,remote_course_id,period_code)');
        $database->exec('ALTER TABLE ava_academic_cohorts DROP INDEX ava_academic_cohort_org_unique');
        $database->exec("UPDATE ava_academic_cohorts SET scope_type='course',scope_reference=CONCAT('legacy:',id)");
        $database->exec("ALTER TABLE ava_academic_cohorts ADD CONSTRAINT ava_academic_cohort_scope_check CHECK(scope_type IN ('course','trail'))");
        $database->exec('ALTER TABLE ava_academic_groups DROP COLUMN class_name,DROP COLUMN class_code,DROP COLUMN class_reference,DROP COLUMN class_type');
    }

    private function stableCode(string $value,string $fallback): string
    {
        $value=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value)?:$value;
        $value=strtolower(trim((string)preg_replace('/[^A-Za-z0-9]+/','-',$value),'-'));
        return$value!==''?$value:$fallback;
    }

    private function dropConstraint(PDO $database,string $table,string $constraint): void
    {
        try{$database->exec("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");}
        catch(Throwable){$database->exec("ALTER TABLE {$table} DROP CHECK {$constraint}");}
    }
};
