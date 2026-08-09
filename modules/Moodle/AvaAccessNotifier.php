<?php

declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use RuntimeException;
use Throwable;

final readonly class AvaAccessNotifier
{
    public function __construct(
        private EnrollmentRepository $enrollments,
        private IntegrationRepository $integrations,
    ) {}

    public function notify(int $enrollmentId): bool
    {
        $context = $this->enrollments->accessCommunicationContextForAutomation($enrollmentId);
        if ($context === null) {
            return false;
        }
        $email = strtolower(trim((string) $context['email']));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->enrollments->recordAutomaticAccessCommunication($enrollmentId, 'email', $email ?: 'não informado', 'failed', 'O aluno não possui um e-mail válido.');
            return false;
        }
        $settings = $this->integrations->settings();
        $login = (string) ($context['username'] ?: preg_replace('/\D/', '', (string) $context['cpf_cnpj']));
        $lines = [
            'Olá, ' . $context['name'] . '!',
            '',
            'Seu acesso ao curso ' . $context['course_name'] . ' foi liberado no AVA.',
            'Endereço: ' . rtrim((string) ($context['ava_base_url'] ?: $settings['base_url']), '/') . '/',
            'Login: ' . $login,
            $settings['initial_password_mode'] === 'cpf5'
                ? 'Senha inicial: os 5 primeiros dígitos do seu CPF.'
                : 'A senha foi gerada pelo AVA. Consulte o e-mail enviado pelo AVA ou use a opção “Esqueci minha senha”.',
            'Unidade: ' . $context['unit_name'],
            '',
            'PAINEL INTER',
        ];
        $subject = preg_replace('/[\r\n]+/', ' ', 'Seu acesso ao AVA — ' . $context['course_name']) ?: 'Seu acesso ao AVA';
        try {
            $sent = mail($email, mb_encode_mimeheader($subject, 'UTF-8'), implode("\r\n", $lines), "Content-Type: text/plain; charset=UTF-8\r\nMIME-Version: 1.0");
            if (!$sent) {
                throw new RuntimeException('O servidor de e-mail não aceitou a mensagem.');
            }
            $this->enrollments->recordAutomaticAccessCommunication($enrollmentId, 'email', $email, 'opened', null);
            return true;
        } catch (Throwable $exception) {
            $this->enrollments->recordAutomaticAccessCommunication($enrollmentId, 'email', $email, 'failed', $exception->getMessage());
            return false;
        }
    }
}
