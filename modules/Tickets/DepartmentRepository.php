<?php

declare(strict_types=1);

namespace Interferencia\Modules\Tickets;

use PDO;
use RuntimeException;

final readonly class DepartmentRepository
{
    public function __construct(private PDO $db) {}
    public function all(bool $activeOnly=false):array{return$this->db->query('SELECT d.*,COUNT(du.user_id) user_count FROM ticket_departments d LEFT JOIN ticket_department_users du ON du.department_id=d.id'.($activeOnly?' WHERE d.is_active=1':'').' GROUP BY d.id ORDER BY d.is_active DESC,d.name')->fetchAll();}

    /** Primeiro setor ativo, usado para chamados abertos pelo aluno no AVA. */
    public function firstActiveId(): ?int
    {
        $id=$this->db->query('SELECT id FROM ticket_departments WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();
        return $id!==false?(int)$id:null;
    }
    public function find(int$id):?array{$s=$this->db->prepare('SELECT * FROM ticket_departments WHERE id=:id');$s->execute(['id'=>$id]);$row=$s->fetch();return is_array($row)?$row:null;}
    public function users():array{return$this->db->query('SELECT id,name,email FROM users WHERE is_active=1 ORDER BY name')->fetchAll();}
    public function selectedUsers(int$id):array{$s=$this->db->prepare('SELECT user_id FROM ticket_department_users WHERE department_id=:id');$s->execute(['id'=>$id]);return array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));}
    public function existsActive(int$id):bool{$s=$this->db->prepare('SELECT COUNT(*) FROM ticket_departments WHERE id=:id AND is_active=1');$s->execute(['id'=>$id]);return(int)$s->fetchColumn()>0;}
    public function save(?int$id,string$name,bool$active,array$userIds):int{$name=trim($name);if(mb_strlen($name)<2||mb_strlen($name)>120)throw new RuntimeException('Informe um nome de setor entre 2 e 120 caracteres.');$this->db->beginTransaction();try{if($id===null){$s=$this->db->prepare('INSERT INTO ticket_departments(name,is_active) VALUES(:name,:active)');$s->execute(['name'=>$name,'active'=>(int)$active]);$id=(int)$this->db->lastInsertId();}else{$s=$this->db->prepare('UPDATE ticket_departments SET name=:name,is_active=:active WHERE id=:id');$s->execute(['name'=>$name,'active'=>(int)$active,'id'=>$id]);$this->db->prepare('DELETE FROM ticket_department_users WHERE department_id=:id')->execute(['id'=>$id]);}$insert=$this->db->prepare('INSERT IGNORE INTO ticket_department_users(department_id,user_id) SELECT :department,id FROM users WHERE id=:user AND is_active=1');foreach(array_unique(array_map('intval',$userIds))as$userId)if($userId>0)$insert->execute(['department'=>$id,'user'=>$userId]);$this->db->commit();return$id;}catch(\Throwable$e){if($this->db->inTransaction())$this->db->rollBack();throw new RuntimeException('Não foi possível salvar o setor.',0,$e);}}
}
