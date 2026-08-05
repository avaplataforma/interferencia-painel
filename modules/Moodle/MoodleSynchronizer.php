<?php

declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

final readonly class MoodleSynchronizer
{
    public function __construct(private MoodleClient$client,private MoodleRepository$repository){}

    /** @return array{courses:int,users:int,enrolments:int,cursor:int,complete:bool} */
    public function syncBatch(int$cursor,int$limit=5):array
    {
        $available=array_values(array_filter($this->client->courses(),static fn(array$course):bool=>(int)($course['id']??0)>max(1,$cursor)));
        usort($available,static fn(array$a,array$b):int=>(int)$a['id']<=>(int)$b['id']);$batch=array_slice($available,0,max(1,min(20,$limit)));
        $courses=0;$users=[];$enrolments=0;$next=$cursor;
        foreach($batch as$course){$courseId=(int)($course['id']??0);$this->repository->upsertCourse($course);$courses++;$next=max($next,$courseId);
            foreach($this->client->enrolledUsers($courseId)as$user){$userId=(int)($user['id']??0);if($userId<1)continue;$this->repository->upsertUser($user);$users[$userId]=true;$this->repository->upsertEnrolment($courseId,$userId,(int)($user['enrolledcourses'][0]['timestart']??0),(int)($user['enrolledcourses'][0]['timeend']??0));$enrolments++;}
        }
        $reconciliation=$this->repository->reconcileAutomatically();
        return['courses'=>$courses,'users'=>count($users),'enrolments'=>$enrolments,'cursor'=>$next,'complete'=>count($available)<=count($batch),'linked'=>$reconciliation['linked'],'conflicts'=>$reconciliation['conflicts']];
    }
}
