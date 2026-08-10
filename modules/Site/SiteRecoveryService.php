<?php

declare(strict_types=1);

namespace Interferencia\Modules\Site;

use DateTimeImmutable;
use Interferencia\Modules\Crm\FollowUpRepository;
use PDO;

final readonly class SiteRecoveryService
{
    public function __construct(
        private PDO $database,
        private FollowUpRepository $followUps,
    ) {}

    /** @return array{created:int,advanced:int,follow_ups:int,cancelled:int} */
    public function sync(?int $organizationId = null, int $limit = 250): array
    {
        $limit = max(1, min(1000, $limit));
        $scope = $organizationId !== null && $organizationId > 0 ? ' AND o.organization_id=:organization' : '';
        $params = $scope === '' ? [] : ['organization' => $organizationId];
        $insert = $this->database->prepare("INSERT IGNORE INTO organization_site_recoveries(organization_id,site_order_id,unit_id,crm_contact_id,status,alert_stage,next_alert_at)
            SELECT o.organization_id,o.id,o.unit_id,o.crm_contact_id,'pending',0,DATE_ADD(o.created_at,INTERVAL 30 MINUTE)
            FROM organization_site_orders o
            WHERE o.fulfillment_status='awaiting_payment' AND o.asaas_checkout_id IS NOT NULL
              AND o.created_at<=DATE_SUB(NOW(),INTERVAL 30 MINUTE){$scope}");
        $insert->execute($params);
        $created = $insert->rowCount();

        $cancel = $this->database->prepare("UPDATE organization_site_recoveries r
            INNER JOIN organization_site_orders o ON o.id=r.site_order_id
            SET r.status='cancelled',r.next_alert_at=NULL
            WHERE r.status='pending' AND o.fulfillment_status<>'awaiting_payment' AND o.paid_at IS NULL{$scope}");
        $cancel->execute($params);

        $select = $this->database->prepare("SELECT r.*,o.created_at order_created_at,o.link,c.responsible_user_id contact_responsible_id,
                c.created_by contact_creator_id,c.name contact_name,p.name product_name
            FROM organization_site_recoveries r
            INNER JOIN organization_site_orders o ON o.id=r.site_order_id
            LEFT JOIN crm_contacts c ON c.id=r.crm_contact_id
            INNER JOIN finance_products p ON p.id=o.finance_product_id
            WHERE r.status='pending' AND o.fulfillment_status='awaiting_payment'
              AND r.next_alert_at IS NOT NULL AND r.next_alert_at<=NOW()".
              ($organizationId !== null && $organizationId > 0 ? ' AND r.organization_id=:organization' : '').
            " ORDER BY r.next_alert_at,r.id LIMIT {$limit}");
        $select->execute($params);
        $advanced = 0;
        $followUps = 0;

        foreach ($select->fetchAll() as $row) {
            $createdAt = new DateTimeImmutable((string) $row['order_created_at']);
            $age = time() - $createdAt->getTimestamp();
            $stage = $age >= 259200 ? 3 : ($age >= 86400 ? 2 : ($age >= 1800 ? 1 : 0));
            if ($stage < 1 || $stage <= (int) $row['alert_stage']) {
                continue;
            }

            $responsibleId = (int) ($row['responsible_user_id'] ?? 0);
            if ($responsibleId < 1) {
                $preferred = (int) ($row['contact_responsible_id'] ?? 0);
                if ($preferred < 1) {
                    $preferred = (int) ($row['contact_creator_id'] ?? 0);
                }
                $responsibleId = $this->responsibleForUnit((int) $row['organization_id'], (int) $row['unit_id'], $preferred);
            }

            $followUpId = (int) ($row['follow_up_id'] ?? 0);
            if ($followUpId < 1 && (int) ($row['crm_contact_id'] ?? 0) > 0 && $responsibleId > 0) {
                $contactName = trim((string) ($row['contact_name'] ?? '')) ?: 'Lead';
                $notes = sprintf(
                    'Checkout do site aguardando pagamento. Lead: %s. Curso: %s.%s',
                    $contactName,
                    (string) $row['product_name'],
                    trim((string) ($row['link'] ?? '')) !== '' ? ' Link: '.trim((string) $row['link']) : '',
                );
                $followUpId = $this->followUps->createAutomatedRecovery(
                    (int) $row['id'],
                    (int) $row['crm_contact_id'],
                    $responsibleId,
                    $createdAt->modify('+30 minutes')->format('Y-m-d H:i:s'),
                    $notes,
                );
                if ($followUpId > 0) {
                    ++$followUps;
                    $this->database->prepare('UPDATE crm_contacts SET responsible_user_id=COALESCE(responsible_user_id,:responsible) WHERE id=:id')->execute(['responsible'=>$responsibleId,'id'=>(int)$row['crm_contact_id']]);
                    $this->recordContactEvent((int) $row['crm_contact_id'], $responsibleId, 'Recuperação comercial criada automaticamente para um checkout não concluído.');
                }
            }

            $next = $stage === 1 ? $createdAt->modify('+24 hours')->format('Y-m-d H:i:s') : ($stage === 2 ? $createdAt->modify('+3 days')->format('Y-m-d H:i:s') : null);
            $update = $this->database->prepare('UPDATE organization_site_recoveries SET responsible_user_id=:responsible,follow_up_id=:follow_up,alert_stage=:stage,first_alert_at=COALESCE(first_alert_at,NOW()),last_alert_at=NOW(),next_alert_at=:next WHERE id=:id AND status=\'pending\' AND alert_stage<:stage_check');
            $update->execute(['responsible'=>$responsibleId > 0 ? $responsibleId : null,'follow_up'=>$followUpId > 0 ? $followUpId : null,'stage'=>$stage,'next'=>$next,'id'=>(int)$row['id'],'stage_check'=>$stage]);
            $advanced += $update->rowCount();
        }

        return ['created'=>$created,'advanced'=>$advanced,'follow_ups'=>$followUps,'cancelled'=>$cancel->rowCount()];
    }

    public function markRecovered(int $orderId): bool
    {
        $previousStatement=$this->database->prepare('SELECT status FROM organization_site_recoveries WHERE site_order_id=:order LIMIT 1');
        $previousStatement->execute(['order'=>$orderId]);
        $alreadyRecovered=$previousStatement->fetchColumn()==='recovered';
        $statement = $this->database->prepare("SELECT o.*,p.value product_value,TIMESTAMPDIFF(SECOND,o.created_at,NOW()) age_seconds
            FROM organization_site_orders o INNER JOIN finance_products p ON p.id=o.finance_product_id WHERE o.id=:id LIMIT 1");
        $statement->execute(['id'=>$orderId]);
        $order = $statement->fetch();
        if (!is_array($order)) {
            return false;
        }
        if ((int) $order['age_seconds'] < 1800) {
            $this->database->prepare("UPDATE organization_site_recoveries SET status='cancelled',next_alert_at=NULL WHERE site_order_id=:order AND alert_stage=0")->execute(['order'=>$orderId]);
            return false;
        }

        $age = (int) $order['age_seconds'];
        $stage = $age >= 259200 ? 3 : ($age >= 86400 ? 2 : 1);
        $responsible = $this->responsibleForUnit((int)$order['organization_id'],(int)$order['unit_id'],0);
        $insert = $this->database->prepare("INSERT INTO organization_site_recoveries(organization_id,site_order_id,unit_id,crm_contact_id,responsible_user_id,status,alert_stage,first_alert_at,last_alert_at,recovered_at,recovered_amount)
            VALUES(:organization,:site_order,:unit,:contact,:responsible,'recovered',:stage,NOW(),NOW(),NOW(),:amount)
            ON DUPLICATE KEY UPDATE status='recovered',alert_stage=GREATEST(alert_stage,VALUES(alert_stage)),next_alert_at=NULL,recovered_at=COALESCE(recovered_at,NOW()),recovered_amount=VALUES(recovered_amount)");
        $insert->execute(['organization'=>(int)$order['organization_id'],'site_order'=>$orderId,'unit'=>(int)$order['unit_id'],'contact'=>(int)($order['crm_contact_id']??0)>0?(int)$order['crm_contact_id']:null,'responsible'=>$responsible>0?$responsible:null,'stage'=>$stage,'amount'=>(float)$order['product_value']]);

        $recovery = $this->database->prepare('SELECT id,follow_up_id,crm_contact_id,responsible_user_id FROM organization_site_recoveries WHERE site_order_id=:order LIMIT 1');
        $recovery->execute(['order'=>$orderId]);$row=$recovery->fetch();
        if (is_array($row)) {
            if ((int)($row['follow_up_id']??0)>0) {
                $this->followUps->completeAutomated((int)$row['follow_up_id']);
            }
            if (!$alreadyRecovered && (int)($row['crm_contact_id']??0)>0) {
                $this->recordContactEvent((int)$row['crm_contact_id'],(int)($row['responsible_user_id']??0)?:null,'Checkout recuperado: pagamento confirmado automaticamente.');
            }
        }
        return true;
    }

    /** @param list<int> $unitIds @return array{total:int,initial:int,day:int,critical:int} */
    public function notificationSummary(int $organizationId,array $unitIds,int $userId):array
    {
        if($organizationId<1||$unitIds===[])return['total'=>0,'initial'=>0,'day'=>0,'critical'=>0];
        $marks=implode(',',array_fill(0,count($unitIds),'?'));
        $statement=$this->database->prepare("SELECT COUNT(*) total,SUM(alert_stage=1) initial,SUM(alert_stage=2) day,SUM(alert_stage>=3) critical
            FROM organization_site_recoveries WHERE organization_id=? AND unit_id IN ({$marks}) AND status='pending' AND alert_stage>0 AND (responsible_user_id=? OR responsible_user_id IS NULL)");
        $statement->execute(array_merge([$organizationId],$unitIds,[$userId]));$row=$statement->fetch()?:[];
        return['total'=>(int)($row['total']??0),'initial'=>(int)($row['initial']??0),'day'=>(int)($row['day']??0),'critical'=>(int)($row['critical']??0)];
    }

    private function responsibleForUnit(int $organizationId,int $unitId,int $preferred):int
    {
        $statement=$this->database->prepare("SELECT u.id FROM users u
            INNER JOIN organization_users ou ON ou.user_id=u.id AND ou.organization_id=:organization AND ou.status='active'
            INNER JOIN user_unit_scopes scope ON scope.user_id=u.id AND scope.unit_id=:unit
            WHERE u.is_active=1 ORDER BY (u.id=:preferred) DESC,ou.is_owner DESC,u.id LIMIT 1");
        $statement->execute(['organization'=>$organizationId,'unit'=>$unitId,'preferred'=>$preferred]);return(int)($statement->fetchColumn()?:0);
    }

    private function recordContactEvent(int $contactId,?int $actorId,string $description):void
    {
        $statement=$this->database->prepare("INSERT INTO crm_contact_events(contact_id,actor_user_id,event_type,description) VALUES(:contact,:actor,'site_recovery',:description)");
        $statement->execute(['contact'=>$contactId,'actor'=>$actorId&&$actorId>0?$actorId:null,'description'=>$description]);
    }
}
