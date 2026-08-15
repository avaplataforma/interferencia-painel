<?php

declare(strict_types=1);

namespace Interferencia\Modules\Catalog;

use Interferencia\Modules\Moodle\AvaConnectionRepository;
use Interferencia\Modules\Moodle\MoodleClient;
use PDO;
use RuntimeException;

/**
 * Selects one MASTER discipline through LTI Deep Linking in the hidden
 * Migração LTI bridge. The selection is persisted before the final course is
 * materialized, so the bridge can be safely emptied after publication.
 */
final readonly class IesdeLtiRobot
{
    public function __construct(
        private PDO $database,
        private AvaConnectionRepository $connections,
        private CourseProviderRepository $providers,
        private string $rootPath,
    ) {}

    /** @return array{courseid:int,resources:int,snapshot_id:int} */
    public function prepare(int $providerCourseId, string $sourceName, ?int $jobId = null): array
    {
        $connection = $this->connections->shared();
        if (!(bool)($connection['configured'] ?? false) || !(bool)($connection['is_active'] ?? false)) {
            throw new RuntimeException('Configure e ative primeiro a integração AVA Cursos.');
        }
        $client = new MoodleClient((string)$connection['base_url'], (string)$connection['token'], true);
        $staging = $client->prepareLtiRobot(false, false, true);
        $stagingCourseId = (int)($staging['courseid'] ?? 0);
        $stagingIdNumber = trim((string)($staging['courseidnumber'] ?? ''));
        if ($stagingCourseId < 1 || ($stagingIdNumber !== '' && $stagingIdNumber !== 'mi-master-staging')) {
            throw new RuntimeException('A área técnica Migração LTI não foi localizada no AVA Cursos.');
        }

        $snapshotId = $this->createSnapshot($jobId, $providerCourseId, $stagingCourseId, $sourceName);
        $lockName = 'mi:ava:iesde-lti-robot';
        $lock = $this->database->prepare('SELECT GET_LOCK(:lock_name,60)');
        $lock->execute(['lock_name' => $lockName]);
        if ((int)$lock->fetchColumn() !== 1) {
            $message = 'O robô MASTER já está preparando outro curso. A fila tentará novamente.';
            $this->fail($snapshotId, $message);
            throw new RuntimeException($message);
        }

        try {
            // The bridge is intentionally transient. A previous source module
            // is deleted after materialization, so every run recreates the Deep
            // Linking selection before the final course is updated.
            $session = $client->prepareLtiRobot(true, true);
            $loginUrl = trim((string)($session['loginurl'] ?? ''));
            if ($loginUrl === '') throw new RuntimeException('O AVA não gerou a sessão técnica do robô.');
            $this->runBrowser($loginUrl, $sourceName);

            $selection = $client->ltiSelections('iesde', $stagingCourseId);
            $course = $this->matchingCourse((array)($selection['courses'] ?? []), $sourceName);
            $resourceCount = count((array)($course['conteudos'] ?? []));
            $this->recordSelection($snapshotId, $course, $resourceCount, 'selected');
            $result = $this->providers->attachLtiSelectionToCourse($providerCourseId, $course);
            if ((int)$result['received'] < 1) throw new RuntimeException('A seleção foi concluída, mas nenhum recurso acadêmico foi recebido.');
            $this->recordSelection($snapshotId, $course, (int)$result['received'], 'registered');
            return ['courseid' => $stagingCourseId, 'resources' => (int)$result['received'], 'snapshot_id' => $snapshotId];
        } catch (\Throwable $exception) {
            $this->fail($snapshotId, $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Confirms the permanent Moodle course and only then empties the technical
     * bridge. Cleanup errors remain auditable and never undo a valid course.
     */
    public function finalize(int $snapshotId, int $remoteCourseId): bool
    {
        if ($snapshotId < 1 || $remoteCourseId < 1) {
            $this->releaseLock();
            return false;
        }
        try {
            $this->database->prepare("UPDATE lti_selection_snapshots SET status='materialized',final_remote_course_id=:remote,materialized_at=NOW(),last_error=NULL WHERE id=:id")
                ->execute(['remote' => $remoteCourseId, 'id' => $snapshotId]);
            $connection = $this->connections->shared();
            $client = new MoodleClient((string)$connection['base_url'], (string)$connection['token'], true);
            $staging = $client->prepareLtiRobot(false, true, false);
            if ((int)($staging['courseid'] ?? 0) < 1) {
                throw new RuntimeException('O AVA não confirmou a limpeza da área Migração LTI.');
            }
            $this->database->prepare("UPDATE lti_selection_snapshots SET status='purged',purged_at=NOW(),last_error=NULL WHERE id=:id")
                ->execute(['id' => $snapshotId]);
            return true;
        } catch (\Throwable $exception) {
            $this->database->prepare("UPDATE lti_selection_snapshots SET status='cleanup_failed',last_error=:error WHERE id=:id")
                ->execute(['id' => $snapshotId, 'error' => mb_substr(trim($exception->getMessage()), 0, 2000)]);
            return false;
        } finally {
            $this->releaseLock();
        }
    }

    public function fail(int $snapshotId, string $message): void
    {
        if ($snapshotId > 0) {
            $this->database->prepare("UPDATE lti_selection_snapshots SET status='failed',last_error=:error WHERE id=:id AND status<>'purged'")
                ->execute(['id' => $snapshotId, 'error' => mb_substr(trim($message), 0, 2000)]);
        }
        $this->releaseLock();
    }

    private function releaseLock(): void
    {
        $release = $this->database->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $release->execute(['lock_name' => 'mi:ava:iesde-lti-robot']);
    }

    private function createSnapshot(?int $jobId, int $providerCourseId, int $stagingCourseId, string $sourceName): int
    {
        $statement = $this->database->prepare("INSERT INTO lti_selection_snapshots(provisioning_job_id,provider_course_id,provider_code,staging_course_id,source_name,status)
            VALUES(:job,:course,'iesde',:staging,:source,'requested')");
        $statement->execute([
            'job' => $jobId !== null && $jobId > 0 ? $jobId : null,
            'course' => $providerCourseId,
            'staging' => $stagingCourseId,
            'source' => mb_substr(trim($sourceName), 0, 500),
        ]);
        return (int)$this->database->lastInsertId();
    }

    /** @param array<string,mixed> $payload */
    private function recordSelection(int $snapshotId, array $payload, int $resourceCount, string $status): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $statement = $this->database->prepare("UPDATE lti_selection_snapshots
            SET status=:status,selection_payload=:payload,payload_sha256=:hash,resource_count=:resources,selected_at=COALESCE(selected_at,NOW()),last_error=NULL
            WHERE id=:id");
        $statement->execute([
            'status' => $status,
            'payload' => $json,
            'hash' => hash('sha256', $json),
            'resources' => max(0, $resourceCount),
            'id' => $snapshotId,
        ]);
    }

    private function runBrowser(string $loginUrl, string $sourceName): void
    {
        $script = $this->rootPath . '/automation/iesde-lti-robot.mjs';
        if (!is_file($script)) throw new RuntimeException('O executor automático MASTER não está instalado na VPS.');
        $command = ['node', $script];
        $pipes = [];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $this->rootPath);
        if (!is_resource($process)) throw new RuntimeException('Não foi possível iniciar o robô MASTER.');

        fwrite($pipes[0], json_encode(['login_url' => $loginUrl, 'course_name' => $sourceName], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        $observedExitCode = null;
        $deadline = microtime(true) + 210;
        do {
            $stdout .= stream_get_contents($pipes[1]) ?: '';
            $stderr .= stream_get_contents($pipes[2]) ?: '';
            $status = proc_get_status($process);
            if (!$status['running']) {
                $observedExitCode = (int)($status['exitcode'] ?? -1);
                break;
            }
            if (microtime(true) >= $deadline) {
                proc_terminate($process, 9);
                throw new RuntimeException('O robô MASTER excedeu o tempo de preparação do curso.');
            }
            usleep(200000);
        } while (true);
        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $closedExitCode = proc_close($process);
        $exitCode = $observedExitCode !== null && $observedExitCode >= 0
            ? $observedExitCode
            : $closedExitCode;
        $payload = json_decode(trim($stdout), true);
        if ($exitCode !== 0 || !is_array($payload) || !($payload['ok'] ?? false)) {
            $detail = is_array($payload) ? trim((string)($payload['error'] ?? '')) : trim($stderr);
            $detail = preg_replace('~https?://\S+~', '[endereço protegido]', $detail) ?: '';
            throw new RuntimeException('O robô MASTER não conseguiu selecionar o conteúdo' . ($detail !== '' ? ': ' . mb_substr($detail, 0, 500) : '.'));
        }
    }

    /** @param list<array<string,mixed>> $courses @return array<string,mixed> */
    private function matchingCourse(array $courses, string $sourceName): array
    {
        if ($courses === []) throw new RuntimeException('O robô terminou sem criar a seleção na área Migração LTI.');
        $wanted = $this->normalized($sourceName);
        foreach ($courses as $course) {
            if (is_array($course) && $this->normalized((string)($course['nome'] ?? '')) === $wanted) return $course;
        }
        if (count($courses) === 1 && is_array($courses[0])) return $courses[0];
        throw new RuntimeException('A seleção criada no AVA não corresponde ao curso solicitado.');
    }

    private function normalized(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return trim((string)preg_replace('/[^a-z0-9]+/', ' ', is_string($ascii) ? strtolower($ascii) : $value));
    }
}
