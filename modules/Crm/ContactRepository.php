<?php

declare(strict_types=1);

namespace Interferencia\Modules\Crm;

use PDO;
use RuntimeException;
use Throwable;

final readonly class ContactRepository
{
    public function __construct(private PDO $database) {}

    /** @return list<array<string, mixed>> */
    public function all(int $unitId, string $search = '', int $tagId = 0): array
    {
        $sql = "SELECT c.*, s.name status_name, s.color status_color, u.name responsible_name, (SELECT GROUP_CONCAT(CONCAT(t.name, '|', t.color) SEPARATOR ';;') FROM crm_contact_tags ct INNER JOIN crm_tags t ON t.id=ct.tag_id WHERE ct.contact_id=c.id) tags_data FROM crm_contacts c INNER JOIN crm_statuses s ON s.id=c.status_id LEFT JOIN users u ON u.id=c.responsible_user_id WHERE c.unit_id=:unit";
        $params = ['unit' => $unitId];
        if ($search !== '') { $sql .= ' AND (c.name LIKE :search_name OR c.phone LIKE :search_phone OR c.email LIKE :search_email OR c.course LIKE :search_course)'; $term = '%' . $search . '%'; $params += ['search_name'=>$term,'search_phone'=>$term,'search_email'=>$term,'search_course'=>$term]; }
        if ($tagId > 0) { $sql .= ' AND EXISTS (SELECT 1 FROM crm_contact_tags filter_tags WHERE filter_tags.contact_id=c.id AND filter_tags.tag_id=:tag)'; $params['tag'] = $tagId; }
        $statement = $this->database->prepare($sql . ' ORDER BY c.registered_at DESC, c.id DESC');
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /** @param list<int> $unitIds @return list<array<string, mixed>> */
    public function allForUnits(array $unitIds, string $search='', int $tagId=0): array
    {
        if($unitIds===[])return[]; $marks=implode(',',array_fill(0,count($unitIds),'?'));
        $sql="SELECT c.*, s.name status_name,s.color status_color,u.name responsible_name,un.name unit_name,(SELECT GROUP_CONCAT(CONCAT(t.name, '|', t.color) SEPARATOR ';;') FROM crm_contact_tags ct INNER JOIN crm_tags t ON t.id=ct.tag_id WHERE ct.contact_id=c.id) tags_data FROM crm_contacts c INNER JOIN crm_statuses s ON s.id=c.status_id INNER JOIN units un ON un.id=c.unit_id LEFT JOIN users u ON u.id=c.responsible_user_id WHERE c.unit_id IN ({$marks})"; $params=$unitIds;
        if($search!==''){$sql.=' AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.course LIKE ?)';$term='%'.$search.'%';array_push($params,$term,$term,$term,$term);}
        if($tagId>0){$sql.=' AND EXISTS (SELECT 1 FROM crm_contact_tags filter_tags WHERE filter_tags.contact_id=c.id AND filter_tags.tag_id=?)';$params[]=$tagId;}
        $statement=$this->database->prepare($sql.' ORDER BY c.registered_at DESC,c.id DESC');$statement->execute($params);return $statement->fetchAll();
    }

    /**
     * @param list<int> $unitIds
     * @return array{total:int,internal:int,external_form:int,whatsapp:int,items:list<array<string,mixed>>}
     */
    public function newContactsDashboard(array $unitIds, string $source = '', int $tagId = 0, int $limit = 8): array
    {
        $empty = ['total' => 0, 'internal' => 0, 'external_form' => 0, 'whatsapp' => 0, 'items' => []];
        if ($unitIds === []) return $empty;

        $marks = implode(',', array_fill(0, count($unitIds), '?'));
        $summary = $this->database->prepare("SELECT COUNT(*) total, SUM(c.registration_source='internal') internal, SUM(c.registration_source='external_form') external_form, SUM(c.registration_source='whatsapp') whatsapp FROM crm_contacts c INNER JOIN crm_statuses s ON s.id=c.status_id WHERE s.code='new' AND c.is_active=1 AND c.unit_id IN ({$marks})");
        $summary->execute($unitIds);
        $counts = $summary->fetch() ?: [];

        $sql = "SELECT c.id,c.name,c.phone,c.course,c.registration_source,c.registered_at,un.name unit_name,(SELECT GROUP_CONCAT(CONCAT(t.name,'|',t.color) ORDER BY t.name SEPARATOR ';;') FROM crm_contact_tags ct INNER JOIN crm_tags t ON t.id=ct.tag_id WHERE ct.contact_id=c.id) tags_data FROM crm_contacts c INNER JOIN crm_statuses s ON s.id=c.status_id INNER JOIN units un ON un.id=c.unit_id WHERE s.code='new' AND c.is_active=1 AND c.unit_id IN ({$marks})";
        $params = $unitIds;
        if (in_array($source, ['internal', 'external_form', 'whatsapp'], true)) { $sql .= ' AND c.registration_source=?'; $params[] = $source; }
        if ($tagId > 0) { $sql .= ' AND EXISTS (SELECT 1 FROM crm_contact_tags filter_tags WHERE filter_tags.contact_id=c.id AND filter_tags.tag_id=?)'; $params[] = $tagId; }
        $sql .= ' ORDER BY c.registered_at DESC,c.id DESC LIMIT ' . max(1, min(25, $limit));
        $statement = $this->database->prepare($sql);
        $statement->execute($params);

        return [
            'total' => (int) ($counts['total'] ?? 0),
            'internal' => (int) ($counts['internal'] ?? 0),
            'external_form' => (int) ($counts['external_form'] ?? 0),
            'whatsapp' => (int) ($counts['whatsapp'] ?? 0),
            'items' => $statement->fetchAll(),
        ];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $unitId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM crm_contacts WHERE id=:id AND unit_id=:unit LIMIT 1');
        $statement->execute(['id' => $id, 'unit' => $unitId]); $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function statuses(): array { return $this->database->query('SELECT id, code, name, color FROM crm_statuses WHERE is_active=1 ORDER BY sort_order, name')->fetchAll(); }

    /** @return list<array<string, mixed>> */
    public function users(int $unitId): array
    {
        $statement = $this->database->prepare('SELECT DISTINCT u.id,u.name FROM users u INNER JOIN user_unit_scopes s ON s.user_id=u.id WHERE s.unit_id=:unit AND u.is_active=1 ORDER BY u.name');
        $statement->execute(['unit' => $unitId]); return $statement->fetchAll();
    }

    public function statusExists(int $id): bool { $s=$this->database->prepare('SELECT COUNT(*) FROM crm_statuses WHERE id=:id AND is_active=1'); $s->execute(['id'=>$id]); return (int)$s->fetchColumn()>0; }
    public function setExternalStatus(int $contactId,int $statusId):void{$s=$this->database->prepare('UPDATE crm_contacts SET status_id=:status WHERE id=:contact');$s->execute(['status'=>$statusId,'contact'=>$contactId]);}
    public function userBelongsToUnit(int $userId, int $unitId): bool { $s=$this->database->prepare('SELECT COUNT(*) FROM user_unit_scopes WHERE user_id=:user AND unit_id=:unit'); $s->execute(['user'=>$userId,'unit'=>$unitId]); return (int)$s->fetchColumn()>0; }

    /** @return array<string,mixed>|null */
    public function activeUnitByCode(string $code):?array{$s=$this->database->prepare('SELECT id,code,name FROM units WHERE code=:code AND is_active=1 LIMIT 1');$s->execute(['code'=>$code]);$row=$s->fetch();return is_array($row)?$row:null;}
    public function externalDuplicate(string $submissionId,int $unitId,?string $phone,?string $email):?int{$sql='SELECT id FROM crm_contacts WHERE external_submission_id=:submission OR (unit_id=:unit AND ((:phone_check IS NOT NULL AND phone=:phone_value) OR (:email_check IS NOT NULL AND email=:email_value))) LIMIT 1';$s=$this->database->prepare($sql);$s->execute(['submission'=>$submissionId,'unit'=>$unitId,'phone_check'=>$phone,'phone_value'=>$phone,'email_check'=>$email,'email_value'=>$email]);$id=$s->fetchColumn();return $id===false?null:(int)$id;}
    /** @param array<string,mixed> $data */
    public function createExternal(array $data):int{$sql="INSERT INTO crm_contacts (unit_id,status_id,name,phone,email,course,interest_score,origin_city,registration_source,external_submission_id,consent_at,privacy_notice_version,registered_at,notes,is_active) SELECT :unit_id,id,:name,:phone,:email,:course,:interest_score,:origin_city,'external_form',:external_submission_id,:consent_at,:privacy_notice_version,:registered_at,:notes,1 FROM crm_statuses WHERE code='new'";$s=$this->database->prepare($sql);$s->execute($data);return(int)$this->database->lastInsertId();}
    public function allowExternalRequest(string $fingerprint,int $limit=30):bool{$window=gmdate('Y-m-d H:i:00');$s=$this->database->prepare('INSERT INTO external_form_rate_limits (fingerprint,window_started_at,request_count) VALUES (:fingerprint,:window,1) ON DUPLICATE KEY UPDATE request_count=request_count+1');$s->execute(['fingerprint'=>$fingerprint,'window'=>$window]);$q=$this->database->prepare('SELECT request_count FROM external_form_rate_limits WHERE fingerprint=:fingerprint AND window_started_at=:window');$q->execute(['fingerprint'=>$fingerprint,'window'=>$window]);return(int)$q->fetchColumn()<=$limit;}

    /** @param array<string, mixed> $data */
    public function save(?int $id, int $unitId, int $creatorId, array $data): int
    {
        try {
            if ($id === null) {
                $sql = 'INSERT INTO crm_contacts (unit_id,status_id,responsible_user_id,name,phone,email,document,course,interest_score,origin_city,registration_source,registered_at,notes,is_active,created_by) VALUES (:unit_id,:status_id,:responsible_user_id,:name,:phone,:email,:document,:course,:interest_score,:origin_city,:registration_source,:registered_at,:notes,:is_active,:created_by)';
                $data += ['unit_id' => $unitId, 'created_by' => $creatorId];
            } else {
                $sql = 'UPDATE crm_contacts SET status_id=:status_id,responsible_user_id=:responsible_user_id,name=:name,phone=:phone,email=:email,document=:document,course=:course,interest_score=:interest_score,origin_city=:origin_city,registration_source=:registration_source,registered_at=:registered_at,notes=:notes,is_active=:is_active WHERE id=:id AND unit_id=:unit_id';
                $data += ['id' => $id, 'unit_id' => $unitId];
            }
            $statement = $this->database->prepare($sql); $statement->execute($data);
            return $id ?? (int) $this->database->lastInsertId();
        } catch (Throwable $exception) { throw new RuntimeException('Não foi possível salvar o contato.', 0, $exception); }
    }
}
