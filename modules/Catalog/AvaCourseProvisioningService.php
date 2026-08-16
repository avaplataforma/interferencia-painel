<?php

declare(strict_types=1);

namespace Interferencia\Modules\Catalog;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Creates a shared-AVA course just in time, before the commercial enrollment is
 * persisted. The database lock makes the operation safe when two franchises
 * request the same provider course at the same time.
 */
final readonly class AvaCourseProvisioningService
{
    public function __construct(
        private PDO $database,
        private CourseProviderRepository $providers,
        private AvaCatalogPublisher $publisher,
        private IesdeLtiRobot $iesdeRobot,
    ) {}

    /** @return array{course_id:int,moodle_course_id:int,ava_connection_id:int,remote_course_id:int,created:bool,job_id:int} */
    public function ensureProviderCourseOffer(int $offerId, int $organizationId, ?int $userId): array
    {
        if ($offerId < 1 || $organizationId < 1) {
            throw new RuntimeException('Oferta ou franquia inválida para a preparação do AVA.');
        }

        $target = $this->providers->courseAccessTargetForOffer($offerId);
        if ((int)($target['organization_id'] ?? 0) !== $organizationId) {
            throw new RuntimeException('Esta oferta não pertence à franquia atual.');
        }

        $courseId = (int)($target['course_id'] ?? 0);
        $providerCode = trim((string)($target['provider_code'] ?? ''));
        if ($courseId < 1) {
            throw new RuntimeException('O Curso individual não possui uma origem acadêmica válida.');
        }

        $current = $this->publishedCourse($courseId, $organizationId);
        $jobId = $this->upsertJob($courseId, $organizationId, $providerCode, $userId, $current !== null ? 'completed' : 'queued');
        if ($current !== null) {
            $this->recordReuse($jobId);
            return $current + ['course_id' => $courseId, 'created' => false, 'job_id' => $jobId];
        }

        if ($providerCode !== 'iesde') {
            $message = 'A criação automática ainda não está habilitada para esta Formação.';
            $this->failJob($jobId, $message);
            throw new RuntimeException($message);
        }

        $lockName = 'mi:ava:provider-course:'.$courseId;
        $lock = $this->database->prepare('SELECT GET_LOCK(:lock_name,30)');
        $lock->execute(['lock_name' => $lockName]);
        if ((int)$lock->fetchColumn() !== 1) {
            $message = 'O AVA já está preparando este curso. Aguarde alguns instantes e tente novamente.';
            $this->failJob($jobId, $message);
            throw new RuntimeException($message);
        }

        try {
            // Another request may have completed while this one waited for the lock.
            $current = $this->publishedCourse($courseId, $organizationId);
            if ($current !== null) {
                $this->completeJob($jobId);
                $this->recordReuse($jobId);
                return $current + ['course_id' => $courseId, 'created' => false, 'job_id' => $jobId];
            }

            $this->startJob($jobId);
            $snapshotId = 0;
            $prepared = $this->iesdeRobot->prepare($courseId, (string)($target['source_name'] ?? $target['name'] ?? ''), $jobId);
            $snapshotId = (int)($prepared['snapshot_id'] ?? 0);
            $this->publisher->publishMasterCourse($courseId, $userId);
            $current = $this->publishedCourse($courseId, $organizationId);
            if ($current === null) {
                throw new RuntimeException('O AVA concluiu a preparação sem devolver o curso publicado.');
            }
            $this->iesdeRobot->finalize($snapshotId, (int)$current['remote_course_id']);
            $this->completeJob($jobId);
            return $current + ['course_id' => $courseId, 'created' => true, 'job_id' => $jobId];
        } catch (Throwable $exception) {
            $message = trim($exception->getMessage()) ?: 'Falha ao preparar automaticamente o curso no AVA.';
            if (isset($snapshotId) && $snapshotId > 0) $this->iesdeRobot->fail($snapshotId, $message);
            $this->failJob($jobId, $message);
            throw new RuntimeException($message, 0, $exception);
        } finally {
            $release = $this->database->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $release->execute(['lock_name' => $lockName]);
        }
    }

    /**
     * @return array{
     *   summary:array{queued:int,working:int,completed:int,failed:int,total:int},
     *   jobs:list<array<string,mixed>>
     * }
     */
    public function dashboard(string $providerCode, int $limit = 30): array
    {
        $providerCode = trim($providerCode);
        $summary = ['queued' => 0, 'working' => 0, 'completed' => 0, 'failed' => 0, 'attention' => 0, 'created' => 0, 'reused' => 0, 'recovered' => 0, 'total' => 0];
        if ($providerCode === '') return ['summary' => $summary, 'jobs' => []];

        $count = $this->database->prepare('SELECT status,COUNT(*) total FROM ava_course_provisioning_jobs WHERE provider_code=:provider GROUP BY status');
        $count->execute(['provider' => $providerCode]);
        foreach ($count->fetchAll() as $row) {
            $status = (string)($row['status'] ?? '');
            $total = (int)($row['total'] ?? 0);
            if (array_key_exists($status, $summary)) $summary[$status] = $total;
            $summary['total'] += $total;
        }

        $attention = $this->database->prepare("SELECT COUNT(*) FROM ava_course_provisioning_jobs WHERE provider_code=:provider AND status='failed' AND attempts>=3");
        $attention->execute(['provider' => $providerCode]);
        $summary['attention'] = (int)$attention->fetchColumn();

        $homologation = $this->database->prepare("SELECT
                SUM(status='completed' AND attempts=1) created_count,
                SUM(reuse_count>0) reused_count,
                SUM(status='completed' AND attempts>1) recovered_count
            FROM ava_course_provisioning_jobs WHERE provider_code=:provider");
        $homologation->execute(['provider' => $providerCode]);
        $homologationRow = $homologation->fetch();
        if (is_array($homologationRow)) {
            $summary['created'] = (int)($homologationRow['created_count'] ?? 0);
            $summary['reused'] = (int)($homologationRow['reused_count'] ?? 0);
            $summary['recovered'] = (int)($homologationRow['recovered_count'] ?? 0);
        }

        $limit = max(5, min(100, $limit));
        $statement = $this->database->prepare("SELECT job.id,job.provider_course_id,job.organization_id,job.provider_code,job.status,job.attempts,job.reuse_count,
                job.started_at,job.completed_at,job.last_reused_at,job.last_error,job.created_at,job.updated_at,
                COALESCE(NULLIF(course.commercial_name,''),course.name) course_name,
                COALESCE(NULLIF(organization.display_name,''),organization.legal_name) organization_name,
                (SELECT snapshot.status FROM lti_selection_snapshots snapshot WHERE snapshot.provisioning_job_id=job.id ORDER BY snapshot.id DESC LIMIT 1) lti_snapshot_status
            FROM ava_course_provisioning_jobs job
            INNER JOIN provider_courses course ON course.id=job.provider_course_id
            INNER JOIN organizations organization ON organization.id=job.organization_id
            WHERE job.provider_code=:provider
            ORDER BY FIELD(job.status,'working','queued','failed','completed'),job.updated_at DESC
            LIMIT {$limit}");
        $statement->execute(['provider' => $providerCode]);
        return ['summary' => $summary, 'jobs' => $statement->fetchAll() ?: []];
    }

    /**
     * Processes the queue without depending on an open browser session. Failed
     * jobs are retried at most three times and with a short cooldown, while a
     * stale worker is safely returned to the queue for the next execution.
     *
     * @return array{discovered:int,processed:int,completed:int,failed:int,exhausted:int}
     */
    public function processBatch(int $limit = 10, int $maxAttempts = 3): array
    {
        $limit = max(1, min(50, $limit));
        $maxAttempts = max(1, min(10, $maxAttempts));

        $this->database->exec("UPDATE ava_course_provisioning_jobs
            SET status='failed',completed_at=NOW(),last_error='Processamento interrompido antes da conclusão; nova tentativa agendada.'
            WHERE status='working' AND updated_at<DATE_SUB(NOW(),INTERVAL 15 MINUTE)");

        // MASTER/IESDE is currently the only Formation with unattended AVA
        // publishing. Other providers keep their explicit/manual workflows.
        $statement = $this->database->prepare("SELECT id
            FROM ava_course_provisioning_jobs
            WHERE provider_code='iesde' AND attempts<:max_attempts
              AND (status='queued' OR (status='failed' AND updated_at<=DATE_SUB(NOW(),INTERVAL 2 MINUTE)))
            ORDER BY FIELD(status,'queued','failed'),attempts ASC,updated_at ASC
            LIMIT {$limit}");
        $statement->execute(['max_attempts' => $maxAttempts]);
        $jobIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN) ?: []);

        $result = ['discovered' => count($jobIds), 'processed' => 0, 'completed' => 0, 'failed' => 0, 'exhausted' => 0];
        foreach ($jobIds as $jobId) {
            ++$result['processed'];
            try {
                $this->retry($jobId, null);
                ++$result['completed'];
            } catch (Throwable) {
                ++$result['failed'];
            }
        }

        $exhausted = $this->database->prepare("SELECT COUNT(*) FROM ava_course_provisioning_jobs WHERE provider_code='iesde' AND status='failed' AND attempts>=:max_attempts");
        $exhausted->execute(['max_attempts' => $maxAttempts]);
        $result['exhausted'] = (int)$exhausted->fetchColumn();
        return $result;
    }

    /** @return array{course_id:int,moodle_course_id:int,ava_connection_id:int,remote_course_id:int,created:bool,job_id:int} */
    public function retry(int $jobId, ?int $userId): array
    {
        if ($jobId < 1) throw new RuntimeException('Item inválido na fila de publicação.');
        $statement = $this->database->prepare("SELECT job.organization_id,offer.id offer_id
            FROM ava_course_provisioning_jobs job
            INNER JOIN organization_provider_course_offers offer
              ON offer.organization_id=job.organization_id AND offer.provider_course_id=job.provider_course_id AND offer.is_active=1
            WHERE job.id=:id LIMIT 1");
        $statement->execute(['id' => $jobId]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            $message = 'A oferta vinculada a esta publicação não está mais ativa.';
            $this->startJob($jobId);
            $this->failJob($jobId, $message);
            throw new RuntimeException($message);
        }
        return $this->ensureProviderCourseOffer((int)$row['offer_id'], (int)$row['organization_id'], $userId);
    }

    /** @return array{moodle_course_id:int,ava_connection_id:int,remote_course_id:int}|null */
    private function publishedCourse(int $courseId, int $organizationId): ?array
    {
        $statement = $this->database->prepare("SELECT publication.moodle_course_id,publication.ava_connection_id,publication.remote_course_id
            FROM catalog_ava_publications publication
            INNER JOIN ava_connections connection ON connection.id=publication.ava_connection_id AND connection.is_active=1
            WHERE publication.entity_type='provider_course' AND publication.entity_id=:course
              AND publication.publication_status='published'
              AND publication.moodle_course_id IS NOT NULL AND publication.remote_course_id IS NOT NULL
              AND (connection.connection_type='shared' OR connection.organization_id=:organization)
            ORDER BY publication.id DESC LIMIT 1");
        $statement->execute(['course' => $courseId, 'organization' => $organizationId]);
        $row = $statement->fetch();
        if (!is_array($row)) return null;
        return [
            'moodle_course_id' => (int)$row['moodle_course_id'],
            'ava_connection_id' => (int)$row['ava_connection_id'],
            'remote_course_id' => (int)$row['remote_course_id'],
        ];
    }

    private function upsertJob(int $courseId, int $organizationId, string $providerCode, ?int $userId, string $status): int
    {
        $requestKey = 'provider-course:'.$courseId;
        $statement = $this->database->prepare("INSERT INTO ava_course_provisioning_jobs(request_key,provider_course_id,organization_id,provider_code,status,requested_by)
            VALUES(:request_key,:course,:organization,:provider,:status,:user)
            ON DUPLICATE KEY UPDATE organization_id=VALUES(organization_id),provider_code=VALUES(provider_code),
              status=IF(VALUES(status)='completed','completed',IF(ava_course_provisioning_jobs.status='working','working','queued')),
              requested_by=VALUES(requested_by),last_error=NULL,updated_at=CURRENT_TIMESTAMP");
        $statement->execute([
            'request_key' => $requestKey,
            'course' => $courseId,
            'organization' => $organizationId,
            'provider' => $providerCode,
            'status' => $status,
            'user' => $userId,
        ]);
        $id = (int)$this->database->lastInsertId();
        if ($id > 0) return $id;
        $lookup = $this->database->prepare('SELECT id FROM ava_course_provisioning_jobs WHERE request_key=:request_key');
        $lookup->execute(['request_key' => $requestKey]);
        return (int)$lookup->fetchColumn();
    }

    private function startJob(int $jobId): void
    {
        $this->database->prepare("UPDATE ava_course_provisioning_jobs SET status='working',attempts=attempts+1,started_at=NOW(),completed_at=NULL,last_error=NULL WHERE id=:id")
            ->execute(['id' => $jobId]);
    }

    private function completeJob(int $jobId): void
    {
        $this->database->prepare("UPDATE ava_course_provisioning_jobs SET status='completed',completed_at=NOW(),last_error=NULL WHERE id=:id")
            ->execute(['id' => $jobId]);
    }

    private function recordReuse(int $jobId): void
    {
        $this->database->prepare("UPDATE ava_course_provisioning_jobs
                SET reuse_count=reuse_count+1,last_reused_at=NOW(),status='completed',completed_at=COALESCE(completed_at,NOW()),last_error=NULL
                WHERE id=:id")
            ->execute(['id' => $jobId]);
    }

    private function failJob(int $jobId, string $message): void
    {
        $this->database->prepare("UPDATE ava_course_provisioning_jobs SET status='failed',completed_at=NOW(),last_error=:error WHERE id=:id")
            ->execute(['id' => $jobId, 'error' => mb_substr($message, 0, 2000)]);
    }
}
