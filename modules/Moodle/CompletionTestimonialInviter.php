<?php

declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use Interferencia\Modules\Email\CentralEmailService;
use Interferencia\Modules\Site\SiteRepository;
use PDO;
use Throwable;

final readonly class CompletionTestimonialInviter
{
    public function __construct(
        private PDO $database,
        private CentralEmailService $email,
        private SiteRepository $sites,
        private string $publicBaseUrl = 'https://mundointer.com.br',
    ) {}

    /** Convida o aluno a deixar depoimento após concluir o curso no AVA (apenas uma vez por matrícula). */
    public function maybeInvite(array $item, array $snapshot): bool
    {
        if ((float) ($snapshot['progresspercent'] ?? -1) < 100 || (string) ($snapshot['progressstatus'] ?? '') !== 'completed') {
            return false;
        }
        $enrollmentId = (int) ($item['student_enrollment_id'] ?? 0);
        if ($enrollmentId < 1) return false;
        $exists = $this->database->prepare('SELECT 1 FROM site_testimonial_invites WHERE student_enrollment_id=:id');
        $exists->execute(['id' => $enrollmentId]);
        if ($exists->fetchColumn() !== false) return false;
        $organizationId = (int) ($item['organization_id'] ?? 0);
        if ($organizationId < 1) return false;
        $recipient = strtolower(trim((string) ($item['email'] ?? '')));
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) return false;
        $site = $this->sites->publicSite($organizationId);
        $host = $site !== null ? (string) ($site['site_host'] ?? '') : '';
        if ($host === '') return false;
        $name = trim((string) ($item['student_name'] ?? ''));
        $course = trim((string) ($item['course_name'] ?? ''));
        $path = rtrim((string) parse_url($this->publicBaseUrl, PHP_URL_PATH), '/');
        $link = 'https://' . $host . $path . '/site/depoimentos';
        $subject = 'Parabéns pela conclusão! Conte como foi sua experiência';
        $text = ($name !== '' ? 'Olá, ' . $name . "!\n\n" : "Olá!\n\n")
            . 'Parabéns por concluir'
            . ($course !== '' ? ' o curso "' . $course . '"' : ' seu curso')
            . "!\n\nSua opinião ajuda outros alunos a escolherem com confiança. Leva menos de um minuto:\n"
            . $link . "\n\nObrigado e sucesso na sua jornada!";
        $status = 'sent';
        try {
            $this->email->deliver($organizationId, $recipient, $subject, $text, 'transactional', 'enrollment', $enrollmentId);
        } catch (Throwable) {
            $status = 'failed';
        }
        $statement = $this->database->prepare('INSERT INTO site_testimonial_invites(student_enrollment_id,organization_id,student_email,student_name,course_name,status) VALUES(:enrollment,:organization,:email,:name,:course,:status)');
        $statement->execute(['enrollment' => $enrollmentId, 'organization' => $organizationId, 'email' => $recipient, 'name' => $name !== '' ? $name : null, 'course' => $course !== '' ? $course : null, 'status' => $status]);
        return true;
    }
}
