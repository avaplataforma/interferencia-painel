<?php

declare(strict_types=1);

namespace Interferencia\Modules\Students;

final class StudentJourneyBuilder
{
    /**
     * @param array<string,mixed> $student
     * @param list<array<string,mixed>> $enrollments
     * @param array<int,list<array<string,mixed>>> $eventMap
     * @param list<array<string,mixed>> $pedagogicalRows
     * @param list<array<string,mixed>> $payments
     * @param list<array<string,mixed>> $tickets
     * @param list<array<string,mixed>> $documents
     * @param array<string,mixed> $academicProfile
     * @return array<string,mixed>
     */
    public function build(array $student,array $enrollments,array $eventMap,array $pedagogicalRows,array $payments,array $tickets,array $documents,array $academicProfile):array
    {
        $missing=[];
        if(trim((string)($student['cpf_cnpj']??''))==='')$missing[]='CPF';
        if(trim((string)($student['email']??''))==='')$missing[]='e-mail';
        if(trim((string)($student['mobile_phone']??$student['phone']??''))==='')$missing[]='telefone';
        if((int)($student['unit_id']??0)<1)$missing[]='unidade';

        $waitingPayment=false;$waitingAccess=false;$released=0;
        foreach($enrollments as$enrollment){
            $status=(string)($enrollment['status']??'');
            if(in_array($status,['awaiting_charge','awaiting_payment','payment_interrupted'],true))$waitingPayment=true;
            if(in_array($status,['payment_confirmed','payment_waived'],true)&&(string)($enrollment['moodle_enrolment_status']??'')!=='released')$waitingAccess=true;
            if((string)($enrollment['moodle_enrolment_status']??'')==='released')$released++;
        }

        $attentionRisks=['blocked','never_accessed','inactive_7','inactive_15','inactive_30','stalled'];
        $needsAttention=false;$completed=0;$started=0;
        foreach($pedagogicalRows as$row){
            if(in_array((string)($row['risk_code']??''),$attentionRisks,true))$needsAttention=true;
            $progress=(float)($row['completion_percent']??0);
            if($progress>0||(int)($row['last_access']??0)>0)$started++;
            if($progress>=100||(string)($row['academic_certificate_status']??'')==='issued')$completed++;
        }

        $state='in_progress';
        if($missing!==[])$state='incomplete';
        elseif($enrollments===[])$state='ready';
        elseif($waitingPayment)$state='awaiting_payment';
        elseif($waitingAccess)$state='awaiting_access';
        elseif($needsAttention)$state='attention';
        elseif($completed>0&&$completed===count($enrollments))$state='completed';

        $states=[
            'incomplete'=>['label'=>'Cadastro incompleto','description'=>'Complete os dados obrigatórios para seguir com segurança.','tone'=>'danger','icon'=>'fa-user-pen'],
            'ready'=>['label'=>'Pronto para matrícula','description'=>'Cadastro completo e disponível para uma nova matrícula.','tone'=>'neutral','icon'=>'fa-user-check'],
            'awaiting_payment'=>['label'=>'Aguardando pagamento','description'=>'Existe matrícula aguardando cobrança ou confirmação financeira.','tone'=>'warning','icon'=>'fa-wallet'],
            'awaiting_access'=>['label'=>'Aguardando acesso','description'=>'O financeiro está concluído, mas ainda falta liberar o AVA.','tone'=>'warning','icon'=>'fa-key'],
            'in_progress'=>['label'=>'Em andamento','description'=>'O aluno está com acesso liberado e jornada acadêmica ativa.','tone'=>'success','icon'=>'fa-graduation-cap'],
            'attention'=>['label'=>'Atenção pedagógica','description'=>'Há inatividade, bloqueio ou outro sinal que exige acompanhamento.','tone'=>'danger','icon'=>'fa-triangle-exclamation'],
            'completed'=>['label'=>'Concluído','description'=>'Todas as matrículas acompanhadas chegaram à conclusão.','tone'=>'success','icon'=>'fa-award'],
        ];

        $hasPaid=count(array_filter($enrollments,static fn(array$item):bool=>in_array((string)($item['status']??''),['payment_confirmed','payment_waived'],true)))>0;
        $steps=[
            ['label'=>'Cadastro','done'=>$missing===[],'icon'=>'fa-address-card'],
            ['label'=>'Matrícula','done'=>$enrollments!==[],'icon'=>'fa-graduation-cap'],
            ['label'=>'Pagamento','done'=>$hasPaid&&!$waitingPayment,'icon'=>'fa-wallet'],
            ['label'=>'Acesso AVA','done'=>$released>0&&!$waitingAccess,'icon'=>'fa-key'],
            ['label'=>'Aprendizado','done'=>$started>0,'icon'=>'fa-chart-line'],
            ['label'=>'Conclusão','done'=>$enrollments!==[]&&$completed===count($enrollments),'icon'=>'fa-award'],
        ];
        $currentIndex=['incomplete'=>0,'ready'=>1,'awaiting_payment'=>2,'awaiting_access'=>3,'in_progress'=>4,'attention'=>4,'completed'=>5][$state];
        foreach($steps as$index=>&$step)$step['status']=$step['done']?'done':($index===$currentIndex?'current':'pending');
        unset($step);

        $events=[];
        $this->addEvent($events,$student['created_at']??null,'Cadastro realizado','O aluno entrou na base unificada.','registration','fa-user-plus');
        foreach($enrollments as$enrollment){
            $course=(string)($enrollment['course_name']??$enrollment['product_name']??'Curso');
            $this->addEvent($events,$enrollment['created_at']??null,'Matrícula criada',$course,'enrollment','fa-graduation-cap');
            foreach($eventMap[(int)($enrollment['id']??0)]??[]as$event){
                $type=(string)($event['event_type']??'enrollment');
                $icon=match($type){'charge_created','payment_confirmed','payment_waived'=>'fa-wallet','ava_released','access_sent','access_opened'=>'fa-key','access_failed'=>'fa-triangle-exclamation',default=>'fa-clock-rotate-left'};
                $this->addEvent($events,$event['created_at']??null,(string)($event['description']??'Movimentação da matrícula'),$course,$type,$icon);
            }
        }
        foreach($payments as$payment){
            $status=(string)($payment['status']??'');
            $label=match($status){'RECEIVED','CONFIRMED','RECEIVED_IN_CASH'=>'Pagamento confirmado','OVERDUE'=>'Cobrança vencida','CANCELED'=>'Cobrança cancelada',default=>'Cobrança emitida'};
            $this->addEvent($events,$payment['created_at']??$payment['synced_at']??null,$label,(string)($payment['description']??'Financeiro'),'finance',$status==='OVERDUE'?'fa-triangle-exclamation':'fa-file-invoice-dollar');
        }
        foreach($tickets as$ticket)$this->addEvent($events,$ticket['created_at']??null,'Ticket aberto',(string)($ticket['subject']??'Atendimento interno'),'ticket','fa-ticket');
        foreach($documents as$document)$this->addEvent($events,$document['created_at']??null,'Documento anexado',(string)($document['original_name']??$document['title']??'Arquivo'),'document','fa-paperclip');
        foreach($pedagogicalRows as$row){
            $last=(int)($row['last_access']??0);$course=(string)($row['course_name']??'Curso');
            if($last>0)$this->addEvent($events,date('Y-m-d H:i:s',$last),'Último acesso ao AVA',$course,'ava','fa-laptop');
            if((string)($row['academic_certificate_status']??'')==='issued')$this->addEvent($events,$row['academic_synced_at']??null,'Certificado emitido',$course,'certificate','fa-award');
        }
        if(is_array($academicProfile['student']??null)){
            $profile=$academicProfile['student'];
            $this->addEvent($events,$profile['synced_at']??$profile['updated_at']??null,'Cadastro vinculado ao AVA',(string)($profile['fullname']??'Identidade acadêmica'),'ava','fa-link');
        }
        usort($events,static fn(array$a,array$b):int=>strcmp((string)$b['occurred_at'],(string)$a['occurred_at']));

        return[
            'state'=>$state,
            'status'=>$states[$state],
            'missing'=>$missing,
            'steps'=>$steps,
            'events'=>array_slice($events,0,80),
            'summary'=>['enrollments'=>count($enrollments),'released'=>$released,'completed'=>$completed,'open_tickets'=>count(array_filter($tickets,static fn(array$item):bool=>!in_array((string)($item['status']??''),['resolved','closed'],true))),'documents'=>count($documents)],
        ];
    }

    /** @param list<array<string,string>> $events */
    private function addEvent(array&$events,mixed$date,string$title,string$description,string$type,string$icon):void
    {
        if($date===null||trim((string)$date)==='')return;
        $timestamp=strtotime((string)$date);if($timestamp===false)return;
        $events[]=['occurred_at'=>date('Y-m-d H:i:s',$timestamp),'title'=>$title,'description'=>$description,'type'=>$type,'icon'=>$icon];
    }
}
