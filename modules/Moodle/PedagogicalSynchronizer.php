<?php

declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use Throwable;

final readonly class PedagogicalSynchronizer
{
    public function __construct(
        private MoodleClient $fallbackClient,
        private MoodleRepository $repository,
        private AvaConnectionRepository $connections,
    ) {}

    /** @param list<int> $unitIds @return array{updated:int,failed:int,last_error:?string} */
    public function sync(array $unitIds, int $limit = 100): array
    {
        $updated = 0;
        $failed = 0;
        $lastError = null;
        $refreshedUsers = [];
        /** @var array<string,MoodleClient> $clients */
        $clients = [];

        foreach ($this->repository->progressCandidates($unitIds, $limit) as $item) {
            $studentEnrollmentId = (int)$item['student_enrollment_id'];
            $moodleEnrollmentId = (int)($item['moodle_enrolment_id'] ?? 0) ?: null;
            $userId = (int)$item['moodle_user_id'];
            $courseId = (int)$item['moodle_course_id'];
            try {
                $connectionId = (int)($item['ava_connection_id'] ?? 0);
                $clientKey = $connectionId > 0 ? 'connection:'.$connectionId : 'shared';
                if (!isset($clients[$clientKey])) {
                    $connection = $connectionId > 0 ? $this->connections->find($connectionId) : $this->connections->shared();
                    if (is_array($connection) && (bool)($connection['configured'] ?? false)) {
                        if (!(bool)($connection['is_active'] ?? false)) {
                            throw new \RuntimeException('A conexão AVA da matrícula está inativa.');
                        }
                        $clients[$clientKey] = new MoodleClient((string)$connection['base_url'], (string)$connection['token'], true);
                    } elseif ($connectionId > 0) {
                        throw new \RuntimeException('A conexão AVA da matrícula não está configurada.');
                    } else {
                        $clients[$clientKey] = $this->fallbackClient;
                    }
                }
                $client = $clients[$clientKey];
                $refreshKey = $clientKey.':'.$userId;
                if (!isset($refreshedUsers[$refreshKey])) {
                    $users = $client->usersByField('username', (string)$item['username']);
                    if (isset($users[0])) {
                        $this->repository->upsertUser($users[0]);
                    }
                    $refreshedUsers[$refreshKey] = true;
                }

                try {
                    $snapshot = $client->academicSnapshot($userId, $courseId);
                } catch (Throwable $pluginError) {
                    if (!str_contains($pluginError->getMessage(), 'local_mundointer_academic_snapshot')) {
                        throw $pluginError;
                    }
                    $snapshot = $this->completionFallback($client, $userId, $courseId);
                }

                $this->repository->saveAcademicSnapshot($studentEnrollmentId, $moodleEnrollmentId, $snapshot);
                $updated++;
            } catch (Throwable $error) {
                $message = mb_substr($error->getMessage(), 0, 500);
                if (str_contains($message, 'Código: nocriteriaset')) {
                    $this->repository->saveAcademicSnapshot($studentEnrollmentId, $moodleEnrollmentId, [
                        'provider' => 'ava_cursos',
                        'progresspercent' => -1,
                        'progressstatus' => 'not_configured',
                        'gradepercent' => -1,
                        'gradestatus' => 'not_available',
                        'lastaccess' => 0,
                        'certificatestatus' => 'not_available',
                        'certificateurl' => '',
                    ]);
                    $updated++;
                    continue;
                }
                $lastError = $message;
                $this->repository->saveAcademicSnapshot($studentEnrollmentId, $moodleEnrollmentId, [
                    'provider' => 'ava_cursos',
                    'progresspercent' => -1,
                    'progressstatus' => 'unavailable',
                    'gradepercent' => -1,
                    'gradestatus' => 'not_available',
                    'lastaccess' => 0,
                    'certificatestatus' => 'not_available',
                    'certificateurl' => '',
                ], $lastError);
                $failed++;
            }
        }

        return ['updated' => $updated, 'failed' => $failed, 'last_error' => $lastError];
    }

    /** @return array<string,mixed> */
    private function completionFallback(MoodleClient $client, int $userId, int $courseId): array
    {
        $data = $client->courseCompletionStatus($userId, $courseId);
        $status = is_array($data['completionstatus'] ?? null) ? $data['completionstatus'] : [];
        $completions = array_values(array_filter($status['completions'] ?? [], 'is_array'));
        $total = count($completions);
        $done = count(array_filter($completions, static fn(array $row): bool => (bool)($row['complete'] ?? false)));
        $completed = (bool)($status['completed'] ?? false);
        $percent = $completed ? 100.0 : ($total > 0 ? round(($done / $total) * 100, 2) : 0.0);
        return [
            'provider' => 'ava_cursos',
            'progresspercent' => $percent,
            'progressstatus' => $completed ? 'completed' : ($percent > 0 ? 'in_progress' : 'not_started'),
            'gradepercent' => -1,
            'gradestatus' => 'not_available',
            'lastaccess' => 0,
            'certificatestatus' => 'not_available',
            'certificateurl' => '',
        ];
    }
}
