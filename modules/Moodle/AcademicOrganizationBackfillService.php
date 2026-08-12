<?php

declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use Interferencia\Modules\Organization\OrganizationPoleRepository;
use PDO;
use RuntimeException;
use Throwable;

final readonly class AcademicOrganizationBackfillService
{
    public function __construct(
        private PDO $database,
        private AvaConnectionRepository $connections,
        private EnrollmentRepository $enrollments,
        private OrganizationPoleRepository $poles,
        private AcademicOrganizationRepository $academicOrganization,
    ) {}

    /** @return array{preview:array<string,int>,active_run:?array<string,mixed>,runs:list<array<string,mixed>>,errors:list<array<string,mixed>>} */
    public function dashboard(): array
    {
        try {
            return [
                'preview' => $this->preview(),
                'active_run' => $this->activeRun(),
                'runs' => $this->recentRuns(),
                'errors' => $this->recentErrors(),
            ];
        } catch (Throwable) {
            return [
                'preview' => ['panel'=>0,'moodle'=>0,'eligible'=>0,'organized'=>0,'incomplete'=>0],
                'active_run' => null,
                'runs' => [],
                'errors' => [],
            ];
        }
    }

    /** @return array{panel:int,moodle:int,eligible:int,organized:int,incomplete:int} */
    public function preview(): array
    {
        $panel = (int)$this->database->query($this->panelEligibleSql('COUNT(*)'))->fetchColumn();
        $moodle = (int)$this->database->query($this->moodleEligibleSql('COUNT(*)'))->fetchColumn();
        $organized = (int)$this->database->query("SELECT COUNT(*) FROM student_enrollments e INNER JOIN ava_academic_groups g ON g.id=e.ava_academic_group_id WHERE e.moodle_enrolment_status='released' AND COALESCE(e.academic_provider_code,'')='' AND e.ava_academic_cohort_id IS NOT NULL AND g.sync_status='synced'")->fetchColumn();
        $organized += (int)$this->database->query("SELECT COUNT(*) FROM ava_academic_backfill_items WHERE source_type='moodle_mirror' AND status='synced'")->fetchColumn();
        $incomplete = (int)$this->database->query("SELECT COUNT(*) FROM student_enrollments e WHERE e.moodle_enrolment_status='released' AND COALESCE(e.academic_provider_code,'')='' AND (e.ava_user_id IS NULL OR e.ava_connection_id IS NULL OR e.ava_course_id IS NULL OR e.unit_id IS NULL)")->fetchColumn();
        $incomplete += (int)$this->database->query("SELECT COUNT(*) FROM moodle_enrolments me INNER JOIN moodle_users mu ON mu.organization_id=me.organization_id AND mu.moodle_user_id=me.moodle_user_id INNER JOIN finance_customers fc ON fc.organization_id=me.organization_id AND fc.id=mu.finance_customer_id WHERE me.is_active=1 AND mu.suspended=0 AND fc.unit_id IS NULL")->fetchColumn();
        return ['panel'=>$panel,'moodle'=>$moodle,'eligible'=>$panel+$moodle,'organized'=>$organized,'incomplete'=>$incomplete];
    }

    public function start(?int $userId, int $batchSize = 10): int
    {
        $active = $this->activeRun();
        if ($active !== null) return (int)$active['id'];
        $batchSize = max(1, min(25, $batchSize));
        $this->database->beginTransaction();
        try {
            $statement = $this->database->prepare("INSERT INTO ava_academic_backfill_runs(status,batch_size,created_by,started_at) VALUES('running',:batch,:user,NOW())");
            $statement->execute(['batch'=>$batchSize,'user'=>$userId]);
            $runId = (int)$this->database->lastInsertId();

            $this->database->exec("INSERT INTO ava_academic_backfill_items(run_id,source_type,source_id,student_enrollment_id)
                SELECT {$runId},'panel_enrollment',e.id,e.id FROM student_enrollments e
                LEFT JOIN ava_academic_groups g ON g.id=e.ava_academic_group_id
                WHERE e.moodle_enrolment_status='released' AND COALESCE(e.academic_provider_code,'')=''
                  AND e.ava_user_id IS NOT NULL AND e.ava_connection_id IS NOT NULL AND e.ava_course_id IS NOT NULL AND e.unit_id IS NOT NULL
                  AND (e.ava_academic_cohort_id IS NULL OR e.ava_academic_group_id IS NULL OR COALESCE(g.sync_status,'pending')<>'synced')
                  AND NOT EXISTS(SELECT 1 FROM ava_academic_backfill_items done WHERE done.source_type='panel_enrollment' AND done.source_id=e.id AND done.status='synced')");

            $this->database->exec("INSERT INTO ava_academic_backfill_items(run_id,source_type,source_id,student_enrollment_id)
                SELECT {$runId},'moodle_mirror',me.id,NULL FROM moodle_enrolments me
                INNER JOIN moodle_users mu ON mu.organization_id=me.organization_id AND mu.moodle_user_id=me.moodle_user_id
                INNER JOIN finance_customers fc ON fc.organization_id=me.organization_id AND fc.id=mu.finance_customer_id
                INNER JOIN moodle_courses mc ON mc.organization_id=me.organization_id AND mc.moodle_course_id=me.moodle_course_id
                WHERE me.is_active=1 AND mu.suspended=0 AND fc.unit_id IS NOT NULL
                  AND NOT EXISTS(SELECT 1 FROM student_enrollments e WHERE e.organization_id=me.organization_id AND e.finance_customer_id=fc.id AND e.moodle_course_id=mc.id AND e.moodle_enrolment_status='released' AND e.ava_user_id IS NOT NULL AND e.ava_connection_id IS NOT NULL AND e.ava_course_id IS NOT NULL AND e.unit_id IS NOT NULL)
                  AND NOT EXISTS(SELECT 1 FROM ava_academic_backfill_items done WHERE done.source_type='moodle_mirror' AND done.source_id=me.id AND done.status='synced')");

            $count = (int)$this->database->query("SELECT COUNT(*) FROM ava_academic_backfill_items WHERE run_id={$runId}")->fetchColumn();
            $status = $count > 0 ? 'running' : 'completed';
            $completed = $count > 0 ? 'NULL' : 'NOW()';
            $this->database->exec("UPDATE ava_academic_backfill_runs SET discovered_count={$count},status='{$status}',completed_at={$completed} WHERE id={$runId}");
            $this->database->commit();
            return $runId;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw $exception;
        }
    }

    /** @return array<string,mixed> */
    public function processNextBatch(int $runId): array
    {
        $run = $this->run($runId);
        if ($run === null) throw new RuntimeException('Sincronização acadêmica não encontrada.');
        if ((string)$run['status'] !== 'running') return $run;
        $this->database->prepare("UPDATE ava_academic_backfill_items SET status='pending',message='Retomado após interrupção do lote.',started_at=NULL WHERE run_id=:run AND status='processing' AND updated_at<DATE_SUB(NOW(),INTERVAL 10 MINUTE)")->execute(['run'=>$runId]);
        $limit = max(1, min(25, (int)$run['batch_size']));
        $items = $this->database->query("SELECT * FROM ava_academic_backfill_items WHERE run_id={$runId} AND status='pending' ORDER BY id LIMIT {$limit}")->fetchAll() ?: [];
        foreach ($items as $item) $this->processItem($item);
        $this->refreshRun($runId);
        return $this->run($runId) ?? $run;
    }

    public function retryFailures(int $runId): void
    {
        $active = $this->activeRun();
        if ($active !== null && (int)$active['id'] !== $runId) throw new RuntimeException('Conclua a sincronização em andamento antes de reprocessar outro lote.');
        $statement = $this->database->prepare("UPDATE ava_academic_backfill_items SET status='pending',message=NULL,started_at=NULL,completed_at=NULL WHERE run_id=:run AND status='failed'");
        $statement->execute(['run'=>$runId]);
        if ($statement->rowCount() > 0) {
            $this->database->prepare("UPDATE ava_academic_backfill_runs SET status='running',completed_at=NULL WHERE id=:run")->execute(['run'=>$runId]);
            $this->refreshRun($runId);
        }
    }

    /** @return array<string,mixed>|null */
    public function activeRun(): ?array
    {
        $row = $this->database->query("SELECT * FROM ava_academic_backfill_runs WHERE status='running' ORDER BY id DESC LIMIT 1")->fetch();
        return is_array($row) ? $this->hydrateRun($row) : null;
    }

    /** @return list<array<string,mixed>> */
    public function recentRuns(int $limit = 8): array
    {
        $limit = max(1, min(20, $limit));
        $rows = $this->database->query("SELECT r.*,u.name created_by_name FROM ava_academic_backfill_runs r LEFT JOIN platform_users u ON u.id=r.created_by ORDER BY r.id DESC LIMIT {$limit}")->fetchAll() ?: [];
        return array_map(fn(array $row): array => $this->hydrateRun($row), $rows);
    }

    /** @return list<array<string,mixed>> */
    public function recentErrors(int $limit = 12): array
    {
        $limit = max(1, min(30, $limit));
        return $this->database->query("SELECT i.id,i.run_id,i.source_type,i.source_id,i.message,i.attempts,i.updated_at,COALESCE(fc.name,fc2.name) student_name,COALESCE(mc.fullname,mc2.fullname) course_name
            FROM ava_academic_backfill_items i
            LEFT JOIN student_enrollments e ON e.id=i.student_enrollment_id
            LEFT JOIN finance_customers fc ON fc.id=e.finance_customer_id
            LEFT JOIN moodle_courses mc ON mc.id=e.moodle_course_id
            LEFT JOIN moodle_enrolments me ON me.id=i.source_id AND i.source_type='moodle_mirror'
            LEFT JOIN moodle_users mu ON mu.organization_id=me.organization_id AND mu.moodle_user_id=me.moodle_user_id
            LEFT JOIN finance_customers fc2 ON fc2.organization_id=me.organization_id AND fc2.id=mu.finance_customer_id
            LEFT JOIN moodle_courses mc2 ON mc2.organization_id=me.organization_id AND mc2.moodle_course_id=me.moodle_course_id
            WHERE i.status='failed' ORDER BY i.updated_at DESC,i.id DESC LIMIT {$limit}")->fetchAll() ?: [];
    }

    /** @param array<string,mixed> $item */
    private function processItem(array $item): void
    {
        $id = (int)$item['id'];
        $claim = $this->database->prepare("UPDATE ava_academic_backfill_items SET status='processing',attempts=attempts+1,started_at=NOW(),message=NULL WHERE id=:id AND status='pending'");
        $claim->execute(['id'=>$id]);
        if ($claim->rowCount() !== 1) return;
        try {
            if ((string)$item['source_type'] === 'panel_enrollment') $this->processPanelEnrollment((int)$item['source_id']);
            else $this->processMoodleMirror((int)$item['source_id']);
            $this->database->prepare("UPDATE ava_academic_backfill_items SET status='synced',message='Organização acadêmica confirmada no AVA.',completed_at=NOW() WHERE id=:id")->execute(['id'=>$id]);
        } catch (Throwable $exception) {
            $message = mb_substr(trim($exception->getMessage()), 0, 500);
            $this->database->prepare("UPDATE ava_academic_backfill_items SET status='failed',message=:message,completed_at=NOW() WHERE id=:id")->execute(['id'=>$id,'message'=>$message]);
        }
    }

    private function processPanelEnrollment(int $enrollmentId): void
    {
        $context = $this->enrollments->releaseContextForAutomation($enrollmentId);
        if ($context === null || (string)$context['moodle_enrolment_status'] !== 'released') throw new RuntimeException('Matrícula não está mais liberada no AVA.');
        $this->organize($context, (int)$context['ava_user_id'], (int)$context['ava_course_id']);
    }

    private function processMoodleMirror(int $moodleEnrollmentId): void
    {
        $statement = $this->database->prepare("SELECT me.id,me.moodle_user_id ava_user_id,me.moodle_course_id ava_course_id,COALESCE(me.time_start,me.synced_at) created_at,fc.id finance_customer_id,fc.unit_id,u.organization_id,mc.id moodle_course_local_id,mc.moodle_course_id,mc.shortname course_shortname,mc.fullname course_fullname,NULL organization_pole_id,NULL catalog_trail_id,NULL trail_name
            FROM moodle_enrolments me
            INNER JOIN moodle_users mu ON mu.organization_id=me.organization_id AND mu.moodle_user_id=me.moodle_user_id
            INNER JOIN finance_customers fc ON fc.organization_id=me.organization_id AND fc.id=mu.finance_customer_id
            INNER JOIN units u ON u.organization_id=me.organization_id AND u.id=fc.unit_id
            INNER JOIN moodle_courses mc ON mc.organization_id=me.organization_id AND mc.moodle_course_id=me.moodle_course_id
            WHERE me.id=:id AND me.is_active=1 AND mu.suspended=0 LIMIT 1");
        $statement->execute(['id'=>$moodleEnrollmentId]);
        $context = $statement->fetch();
        if (!is_array($context)) throw new RuntimeException('Vínculo acadêmico antigo não está mais ativo ou conciliado.');
        $settings = $this->connections->organizationSettings((int)$context['organization_id']);
        $primary = (string)($settings['primary_ava'] ?? 'shared');
        $connection = is_array($settings[$primary] ?? null) ? $settings[$primary] : $this->connections->shared();
        $context['id'] = 0;
        $context['ava_connection_id'] = (int)($connection['id'] ?? 0);
        $this->organize($context, (int)$context['ava_user_id'], (int)$context['ava_course_id'], $connection);
    }

    /** @param array<string,mixed> $context @param array<string,mixed>|null $knownConnection */
    private function organize(array $context, int $avaUserId, int $remoteCourseId, ?array $knownConnection = null): void
    {
        if ($avaUserId < 1 || $remoteCourseId < 1) throw new RuntimeException('Aluno ou curso sem identificação válida no AVA.');
        $connection = $knownConnection ?? $this->connections->find((int)($context['ava_connection_id'] ?? 0));
        if ($connection === null || !(bool)($connection['configured'] ?? false) || !(bool)($connection['is_active'] ?? false)) throw new RuntimeException('A conexão do AVA não está configurada e ativa.');
        $identity = $this->poles->identityForEnrollment((int)($context['unit_id'] ?? 0), isset($context['organization_pole_id']) && (int)$context['organization_pole_id'] > 0 ? (int)$context['organization_pole_id'] : null);
        if ($identity === null) throw new RuntimeException('O aluno não possui um polo Mundo Inter ativo para esta franquia.');
        $client = new MoodleClient((string)$connection['base_url'], (string)$connection['token'], true);
        if (!$client->ready()) throw new RuntimeException('A conexão com o AVA não está pronta.');
        $customFields = [
            ['type'=>OrganizationPoleRepository::FRANCHISE_FIELD,'value'=>$identity['franchise_code']],
            ['type'=>OrganizationPoleRepository::POLE_FIELD,'value'=>$identity['pole_code']],
        ];
        $client->updateUserCustomFields($avaUserId, $customFields);
        $placement = null;
        try {
            $placement = $this->academicOrganization->prepareForEnrollment($context, $connection, $identity, $remoteCourseId);
            $remote = $client->organizeEnrollment($avaUserId, $remoteCourseId, $placement['payload']);
            $this->academicOrganization->markSynced($placement['cohort_id'], $placement['group_id'], $remote);
        } catch (Throwable $exception) {
            if (is_array($placement)) $this->academicOrganization->markFailed($placement['cohort_id'], $placement['group_id'], $exception->getMessage());
            throw $exception;
        }
    }

    private function refreshRun(int $runId): void
    {
        $statement = $this->database->prepare("SELECT COUNT(*) total,SUM(status IN ('synced','skipped','failed')) processed,SUM(status='synced') synced,SUM(status='skipped') skipped,SUM(status='failed') failed,SUM(status IN ('pending','processing')) remaining FROM ava_academic_backfill_items WHERE run_id=:run");
        $statement->execute(['run'=>$runId]);
        $counts = $statement->fetch() ?: [];
        $remaining = (int)($counts['remaining'] ?? 0);
        $failed = (int)($counts['failed'] ?? 0);
        $status = $remaining > 0 ? 'running' : ($failed > 0 ? 'completed_with_errors' : 'completed');
        $sql = "UPDATE ava_academic_backfill_runs SET discovered_count=:total,processed_count=:processed,synced_count=:synced,skipped_count=:skipped,failed_count=:failed,status=:status,completed_at=".($remaining > 0 ? 'NULL' : 'NOW()')." WHERE id=:run";
        $this->database->prepare($sql)->execute(['total'=>(int)($counts['total']??0),'processed'=>(int)($counts['processed']??0),'synced'=>(int)($counts['synced']??0),'skipped'=>(int)($counts['skipped']??0),'failed'=>$failed,'status'=>$status,'run'=>$runId]);
    }

    /** @return array<string,mixed>|null */
    private function run(int $runId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM ava_academic_backfill_runs WHERE id=:id LIMIT 1');
        $statement->execute(['id'=>$runId]);
        $row = $statement->fetch();
        return is_array($row) ? $this->hydrateRun($row) : null;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function hydrateRun(array $row): array
    {
        foreach (['id','batch_size','discovered_count','processed_count','synced_count','skipped_count','failed_count'] as $key) $row[$key] = (int)($row[$key] ?? 0);
        $row['progress_percent'] = $row['discovered_count'] > 0 ? min(100, (int)round(($row['processed_count'] / $row['discovered_count']) * 100)) : 100;
        return $row;
    }

    private function panelEligibleSql(string $select): string
    {
        return "SELECT {$select} FROM student_enrollments e LEFT JOIN ava_academic_groups g ON g.id=e.ava_academic_group_id WHERE e.moodle_enrolment_status='released' AND COALESCE(e.academic_provider_code,'')='' AND e.ava_user_id IS NOT NULL AND e.ava_connection_id IS NOT NULL AND e.ava_course_id IS NOT NULL AND e.unit_id IS NOT NULL AND (e.ava_academic_cohort_id IS NULL OR e.ava_academic_group_id IS NULL OR COALESCE(g.sync_status,'pending')<>'synced') AND NOT EXISTS(SELECT 1 FROM ava_academic_backfill_items done WHERE done.source_type='panel_enrollment' AND done.source_id=e.id AND done.status='synced')";
    }

    private function moodleEligibleSql(string $select): string
    {
        return "SELECT {$select} FROM moodle_enrolments me INNER JOIN moodle_users mu ON mu.organization_id=me.organization_id AND mu.moodle_user_id=me.moodle_user_id INNER JOIN finance_customers fc ON fc.organization_id=me.organization_id AND fc.id=mu.finance_customer_id INNER JOIN moodle_courses mc ON mc.organization_id=me.organization_id AND mc.moodle_course_id=me.moodle_course_id WHERE me.is_active=1 AND mu.suspended=0 AND fc.unit_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM student_enrollments e WHERE e.organization_id=me.organization_id AND e.finance_customer_id=fc.id AND e.moodle_course_id=mc.id AND e.moodle_enrolment_status='released' AND e.ava_user_id IS NOT NULL AND e.ava_connection_id IS NOT NULL AND e.ava_course_id IS NOT NULL AND e.unit_id IS NOT NULL) AND NOT EXISTS(SELECT 1 FROM ava_academic_backfill_items done WHERE done.source_type='moodle_mirror' AND done.source_id=me.id AND done.status='synced')";
    }
}
