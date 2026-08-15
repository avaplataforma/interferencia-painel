<?php

declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use Interferencia\Modules\Email\CentralEmailService;
use Throwable;

final readonly class AvaAccessNotifier
{
    public function __construct(
        private EnrollmentRepository $enrollments,
        private IntegrationRepository $integrations,
        private CentralEmailService $email,
    ) {}

    public function notify(int $enrollmentId, ?int $userId = null): bool
    {
        $context = $this->enrollments->accessCommunicationContextForAutomation($enrollmentId);
        if ($context === null) {
            return false;
        }
        $email = strtolower(trim((string) $context['email']));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->enrollments->recordEmailAccessCommunication($enrollmentId, $email ?: 'não informado', 'failed', $userId, 'O aluno não possui um e-mail válido.');
            return false;
        }
        $settings = $this->integrations->settings();
        $login = (string) ($context['username'] ?: preg_replace('/\D/', '', (string) $context['cpf_cnpj']));
        $providerAccess = (string)($context['academic_provider_code'] ?? '') !== '';
        $lines = ['Olá, ' . $context['name'] . '!', ''];
        if ($providerAccess) {
            $lines[] = 'Seu acesso ao curso ' . $context['course_name'] . ' foi liberado no Catálogo EXPERT.';
            $lines[] = 'Link pessoal de acesso: ' . (string)$context['ava_base_url'];
            $lines[] = 'Use este endereço individual para entrar diretamente no conteúdo contratado.';
        } else {
            $lines[] = 'Seu acesso ao curso ' . $context['course_name'] . ' foi liberado no AVA.';
            $lines[] = 'Endereço: ' . rtrim((string) ($context['ava_base_url'] ?: $settings['base_url']), '/') . '/';
            $lines[] = 'Login: ' . $login;
            $lines[] = $settings['initial_password_mode'] === 'cpf5'
                ? 'Senha inicial: os 5 primeiros dígitos do seu CPF.'
                : 'A senha foi gerada pelo AVA. Consulte o e-mail enviado pelo AVA ou use a opção “Esqueci minha senha”.';
        }
        $lines[] = 'Unidade: ' . $context['unit_name'];
        $lines[] = '';
        $lines[] = 'PAINEL INTER';
        $subject = preg_replace('/[\r\n]+/', ' ', 'Seu acesso ao AVA — ' . $context['course_name']) ?: 'Seu acesso ao AVA';
        try {
            $this->email->deliver(
                (int) ($context['organization_id'] ?? 0) ?: null,
                $email,
                $subject,
                implode("\r\n", $lines),
                'ava_access',
                'student_enrollment',
                $enrollmentId,
            );
            $this->enrollments->recordEmailAccessCommunication($enrollmentId, $email, 'sent', $userId, null);
            return true;
        } catch (Throwable $exception) {
            $this->enrollments->recordEmailAccessCommunication($enrollmentId, $email, 'failed', $userId, $exception->getMessage());
            return false;
        }
    }
}
