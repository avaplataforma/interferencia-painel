<?php

declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use PDO;
use RuntimeException;

final readonly class EnrollmentRepository
{
    public function __construct(private PDO$database){}
    /** @param list<int> $unitIds @return list<array<string,mixed>> */
    public function all(array$unitIds):array{if($unitIds===[])return[];$marks=implode(',',array_fill(0,count($unitIds),'?'));$s=$this->database->prepare("SELECT e.*,f.name student_name,f.cpf_cnpj,m.fullname course_name,m.shortname,p.name product_name,p.value,u.name unit_name FROM student_enrollments e INNER JOIN finance_customers f ON f.id=e.finance_customer_id INNER JOIN moodle_courses m ON m.id=e.moodle_course_id LEFT JOIN finance_products p ON p.id=e.finance_product_id INNER JOIN units u ON u.id=e.unit_id WHERE e.unit_id IN ($marks) ORDER BY e.created_at DESC,e.id DESC LIMIT 500");$s->execute($unitIds);return$s->fetchAll();}
    /** @return list<array<string,mixed>> */
    public function courses():array{return$this->database->query('SELECT id,moodle_course_id,fullname,shortname,visible FROM moodle_courses WHERE visible=1 ORDER BY fullname')->fetchAll();}
    public function create(int$customerId,int$courseId,?int$productId,int$unitId,int$userId,array$allowedUnits):int
    {
        if(!in_array($unitId,$allowedUnits,true))throw new RuntimeException('Selecione uma unidade permitida.');if($productId===null)throw new RuntimeException('Selecione o curso e preço da cobrança.');$s=$this->database->prepare('SELECT COUNT(*) FROM finance_customers WHERE id=:id AND unit_id=:unit AND is_deleted=0');$s->execute(['id'=>$customerId,'unit'=>$unitId]);if((int)$s->fetchColumn()!==1)throw new RuntimeException('Aluno não encontrado nesta unidade.');$s=$this->database->prepare('SELECT COUNT(*) FROM moodle_courses WHERE id=:id AND visible=1');$s->execute(['id'=>$courseId]);if((int)$s->fetchColumn()!==1)throw new RuntimeException('Curso do Moodle não encontrado.');$s=$this->database->prepare('SELECT COUNT(*) FROM finance_products WHERE id=:id AND is_active=1 AND (unit_id=:unit OR unit_id IS NULL)');$s->execute(['id'=>$productId,'unit'=>$unitId]);if((int)$s->fetchColumn()!==1)throw new RuntimeException('Curso e preço financeiro inválido.');$s=$this->database->prepare('INSERT INTO student_enrollments(finance_customer_id,moodle_course_id,finance_product_id,unit_id,created_by) VALUES(:customer,:course,:product,:unit,:user)');$s->execute(['customer'=>$customerId,'course'=>$courseId,'product'=>$productId,'unit'=>$unitId,'user'=>$userId]);return(int)$this->database->lastInsertId();
    }
    /** @return array<string,mixed>|null */ public function chargeContext(int$id,array$units):?array{if($units===[])return null;$marks=implode(',',array_fill(0,count($units),'?'));$s=$this->database->prepare("SELECT e.*,p.name product_name,p.value FROM student_enrollments e INNER JOIN finance_products p ON p.id=e.finance_product_id WHERE e.id=? AND e.unit_id IN ($marks) AND e.finance_payment_id IS NULL LIMIT 1");$s->execute(array_merge([$id],$units));$row=$s->fetch();return is_array($row)?$row:null;}
    public function attachPayment(int$id,int$paymentId):void{$s=$this->database->prepare("UPDATE student_enrollments SET finance_payment_id=:payment,status='awaiting_payment' WHERE id=:id AND finance_payment_id IS NULL");$s->execute(['payment'=>$paymentId,'id'=>$id]);}
}
