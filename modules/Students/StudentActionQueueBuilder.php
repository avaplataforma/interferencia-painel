<?php

declare(strict_types=1);

namespace Interferencia\Modules\Students;

final class StudentActionQueueBuilder
{
    /**
     * @param list<array<string,mixed>> $students
     * @param list<array<string,mixed>> $enrollments
     * @param list<array<string,mixed>> $pedagogicalRows
     * @return array{summary:array<string,int>,items:list<array<string,mixed>>,total:int}
     */
    public function build(array $students,array $enrollments,array $pedagogicalRows,int $limit=500):array
    {
        $enrollmentsByStudent=[];
        foreach($enrollments as$enrollment)$enrollmentsByStudent[(int)($enrollment['finance_customer_id']??0)][]=$enrollment;
        $pedagogicalByStudent=[];
        foreach($pedagogicalRows as$row)$pedagogicalByStudent[(int)($row['customer_id']??0)][]=$row;

        $items=[];
        foreach($students as$student){
            $studentId=(int)($student['id']??0);
            if($studentId<1)continue;
            $item=$this->nextAction($student,$enrollmentsByStudent[$studentId]??[],$pedagogicalByStudent[$studentId]??[]);
            if($item!==null)$items[]=$item;
        }
        usort($items,static fn(array$a,array$b):int=>($b['priority']<=>$a['priority'])?:strcasecmp((string)$a['student_name'],(string)$b['student_name']));

        $summary=['incomplete_registration'=>0,'pending_payment'=>0,'pending_ava'=>0,'inactive'=>0,'certificate_available'=>0];
        foreach($items as$item)$summary[(string)$item['type']]++;
        return['summary'=>$summary,'items'=>array_slice($items,0,max(1,$limit)),'total'=>count($items)];
    }

    /**
     * @param array<string,mixed> $student
     * @param list<array<string,mixed>> $enrollments
     * @param list<array<string,mixed>> $pedagogicalRows
     * @return array<string,mixed>|null
     */
    public function nextAction(array $student,array $enrollments,array $pedagogicalRows):?array
    {
        $missing=[];
        if(trim((string)($student['cpf_cnpj']??''))==='')$missing[]='CPF';
        if(trim((string)($student['email']??''))==='')$missing[]='e-mail';
        if(trim((string)($student['mobile_phone']??$student['phone']??''))==='')$missing[]='telefone';
        if((int)($student['unit_id']??0)<1)$missing[]='unidade';
        if($missing!==[])return$this->item($student,'incomplete_registration','Completar cadastro','Falta '.implode(', ',$missing).'.','fa-user-pen','danger',100,null,'edit_profile');

        foreach($enrollments as$enrollment){
            if(in_array((string)($enrollment['status']??''),['awaiting_charge','awaiting_payment','payment_interrupted'],true)){
                $awaitingCharge=(string)($enrollment['status']??'')==='awaiting_charge';
                return$this->item($student,'pending_payment',$awaitingCharge?'Gerar cobrança':'Revisar pagamento',$awaitingCharge?'A matrícula está pronta para emitir a cobrança.':'A matrícula aguarda confirmação ou correção financeira.','fa-wallet','warning',90,$enrollment,$awaitingCharge?'charge':'finance');
            }
        }
        foreach($enrollments as$enrollment){
            if(in_array((string)($enrollment['status']??''),['payment_confirmed','payment_waived'],true)&&(string)($enrollment['moodle_enrolment_status']??'')!=='released'){
                return$this->item($student,'pending_ava','Liberar acesso no AVA','O financeiro está concluído e o acesso acadêmico ainda está pendente.','fa-key','warning',80,$enrollment,'release_ava');
            }
        }

        $riskPriority=['blocked'=>78,'inactive_30'=>76,'inactive_15'=>74,'inactive_7'=>72,'never_accessed'=>71,'stalled'=>70];
        $riskLabels=['blocked'=>'Acesso bloqueado','inactive_30'=>'Sem acesso há 30 dias','inactive_15'=>'Sem acesso há 15 dias','inactive_7'=>'Sem acesso há 7 dias','never_accessed'=>'Aluno ainda não acessou','stalled'=>'Progresso acadêmico parado'];
        $attention=null;
        foreach($pedagogicalRows as$row){$risk=(string)($row['risk_code']??'');if(isset($riskPriority[$risk])&&($attention===null||$riskPriority[$risk]>$attention['priority']))$attention=['row'=>$row,'risk'=>$risk,'priority'=>$riskPriority[$risk]];}
        if($attention!==null)return$this->item($student,'inactive','Acompanhar aluno',$riskLabels[$attention['risk']].'.','fa-person-circle-exclamation','danger',(int)$attention['priority'],$attention['row'],'pedagogical');

        foreach($pedagogicalRows as$row){
            if((string)($row['academic_certificate_status']??'')==='issued')return$this->item($student,'certificate_available','Certificado disponível','A conclusão já foi registrada e o certificado pode ser consultado ou enviado.','fa-award','success',40,$row,'certificate');
        }
        return null;
    }

    /** @param array<string,mixed> $student @param array<string,mixed>|null $context @return array<string,mixed> */
    private function item(array$student,string$type,string$label,string$description,string$icon,string$tone,int$priority,?array$context,string|null$action):array
    {
        return[
            'type'=>$type,'label'=>$label,'description'=>$description,'icon'=>$icon,'tone'=>$tone,'priority'=>$priority,'action'=>$action,
            'student_id'=>(int)($student['id']??$student['customer_id']??0),'student_name'=>(string)($student['name']??''),'student_document'=>(string)($student['cpf_cnpj']??''),'unit_name'=>(string)($student['unit_name']??''),
            'enrollment_id'=>(int)($context['id']??$context['enrollment_id']??0),'course_name'=>(string)($context['course_name']??$context['product_name']??''),'attendant_user_id'=>(int)($context['attendant_user_id']??0),'attendant_name'=>(string)($context['attendant_name']??''),
        ];
    }
}
