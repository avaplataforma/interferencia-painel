<?php

declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use Throwable;

final readonly class PedagogicalSynchronizer
{
    public function __construct(private MoodleClient$client,private MoodleRepository$repository){}

    /** @param list<int> $unitIds @return array{updated:int,failed:int,last_error:?string} */
    public function sync(array$unitIds,int$limit=100):array
    {
        $updated=0;$failed=0;$lastError=null;$refreshedUsers=[];
        foreach($this->repository->progressCandidates($unitIds,$limit)as$item){
            $enrolmentId=(int)$item['moodle_enrolment_id'];
            try{$userId=(int)$item['moodle_user_id'];if(!isset($refreshedUsers[$userId])){$users=$this->client->usersByField('username',(string)$item['username']);if(isset($users[0]))$this->repository->upsertUser($users[0]);$refreshedUsers[$userId]=true;}$data=$this->client->courseCompletionStatus($userId,(int)$item['moodle_course_id']);$status=is_array($data['completionstatus']??null)?$data['completionstatus']:[];$completions=array_values(array_filter($status['completions']??[],'is_array'));$total=count($completions);$done=count(array_filter($completions,static fn(array$row):bool=>(bool)($row['complete']??false)));$completed=(bool)($status['completed']??false);$percent=$completed?100.0:($total>0?round(($done/$total)*100,2):0.0);$state=$completed?'completed':($percent>0?'in_progress':'not_started');$this->repository->saveProgress($enrolmentId,$percent,$state,null);$updated++;}
            catch(Throwable$e){
                $message=mb_substr($e->getMessage(),0,500);
                if(str_contains($message,'Código: nocriteriaset')){$this->repository->saveProgress($enrolmentId,null,'not_configured',null);$updated++;continue;}
                $lastError=$message;$this->repository->saveProgress($enrolmentId,null,'unavailable',$lastError);$failed++;
            }
        }
        return['updated'=>$updated,'failed'=>$failed,'last_error'=>$lastError];
    }
}
