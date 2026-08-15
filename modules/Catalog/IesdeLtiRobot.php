<?php

declare(strict_types=1);

namespace Interferencia\Modules\Catalog;

use Interferencia\Modules\Moodle\AvaConnectionRepository;
use Interferencia\Modules\Moodle\MoodleClient;
use PDO;
use RuntimeException;

/**
 * Selects one MASTER discipline through LTI Deep Linking in the dedicated
 * TESTES - Funções course. Moodle grants a short-lived one-time session, so
 * neither a human login nor a stored Moodle password is required.
 */
final readonly class IesdeLtiRobot
{
    public function __construct(
        private PDO $database,
        private AvaConnectionRepository $connections,
        private CourseProviderRepository $providers,
        private string $rootPath,
    ) {}

    /** @return array{courseid:int,resources:int} */
    public function prepare(int $providerCourseId, string $sourceName): array
    {
        $connection = $this->connections->shared();
        if (!(bool)($connection['configured'] ?? false) || !(bool)($connection['is_active'] ?? false)) {
            throw new RuntimeException('Configure e ative primeiro a integração AVA Cursos.');
        }
        $client = new MoodleClient((string)$connection['base_url'], (string)$connection['token'], true);
        $staging = $client->prepareLtiRobot(false, false, true);
        $stagingCourseId = (int)($staging['courseid'] ?? 0);
        if ($stagingCourseId < 1 || (string)($staging['coursename'] ?? '') !== 'TESTES - Funções') {
            throw new RuntimeException('O curso técnico TESTES - Funções não foi localizado no AVA Cursos.');
        }

        $context = $this->providers->coursePublicationContext($providerCourseId);
        $currentSourceId = (int)($context['source_raw']['moodle_course_id'] ?? 0);
        if ((int)($context['resource_count'] ?? 0) > 0 && $currentSourceId === $stagingCourseId) {
            return ['courseid' => $stagingCourseId, 'resources' => (int)$context['resource_count']];
        }

        $lockName = 'mi:ava:iesde-lti-robot';
        $lock = $this->database->prepare('SELECT GET_LOCK(:lock_name,60)');
        $lock->execute(['lock_name' => $lockName]);
        if ((int)$lock->fetchColumn() !== 1) {
            throw new RuntimeException('O robô MASTER já está preparando outro curso. A fila tentará novamente.');
        }

        try {
            $session = $client->prepareLtiRobot(true, true);
            $loginUrl = trim((string)($session['loginurl'] ?? ''));
            if ($loginUrl === '') throw new RuntimeException('O AVA não gerou a sessão técnica do robô.');
            $this->runBrowser($loginUrl, $sourceName);

            $selection = $client->ltiSelections('iesde', $stagingCourseId);
            $course = $this->matchingCourse((array)($selection['courses'] ?? []), $sourceName);
            $result = $this->providers->attachLtiSelectionToCourse($providerCourseId, $course);
            if ((int)$result['received'] < 1) throw new RuntimeException('A seleção foi concluída, mas nenhum recurso acadêmico foi recebido.');
            return ['courseid' => $stagingCourseId, 'resources' => (int)$result['received']];
        } finally {
            $release = $this->database->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $release->execute(['lock_name' => $lockName]);
        }
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
        $deadline = microtime(true) + 210;
        do {
            $stdout .= stream_get_contents($pipes[1]) ?: '';
            $stderr .= stream_get_contents($pipes[2]) ?: '';
            $status = proc_get_status($process);
            if (!$status['running']) break;
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
        $exitCode = proc_close($process);
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
        if ($courses === []) throw new RuntimeException('O robô terminou sem criar a seleção no curso TESTES - Funções.');
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
