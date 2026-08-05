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

    private function nullable(mixed$value):?string{$value=trim((string)$value);return$value===''?null:$value;}
    private function timestamp(mixed$value):?string{$value=(int)$value;return$value>0?date('Y-m-d H:i:s',$value):null;}
}
