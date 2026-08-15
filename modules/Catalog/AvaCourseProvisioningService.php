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
                return $current + ['course_id' => $courseId, 'created' => false, 'job_id' => $jobId];
            }

            $this->startJob($jobId);
            $this->publisher->publishMasterCourse($courseId, $userId);
            $current = $this->publishedCourse($courseId, $organizationId);
            if ($current === null) {
                throw new RuntimeException('O AVA concluiu a preparação sem devolver o curso publicado.');
            }
            $this->completeJob($jobId);
            return $current + ['course_id' => $courseId, 'created' => true, 'job_id' => $jobId];
        } catch (Throwable $exception) {
            $message = trim($exception->getMessage()) ?: 'Falha ao preparar automaticamente o curso no AVA.';
            $this->failJob($jobId, $message);
            throw new RuntimeException($message, 0, $exception);
        } finally {
            $release = $this->database->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $release->execute(['lock_name' => $lockName]);
        }
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

    private function failJob(int $jobId, string $message): void
    {
        $this->database->prepare("UPDATE ava_course_provisioning_jobs SET status='failed',completed_at=NOW(),last_error=:error WHERE id=:id")
            ->execute(['id' => $jobId, 'error' => mb_substr($message, 0, 2000)]);
    }
}
