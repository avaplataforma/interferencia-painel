<?php

declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use RuntimeException;
use Throwable;

final readonly class AvaEnrollmentReleaser
{
    public function __construct(
        private MoodleClient $client,
        private IntegrationRepository $integrations,
        private MoodleRepository $moodle,
        private EnrollmentRepository $enrollments,
    ) {}

    /** @return array{status:string,ava_user_id?:int,course_id?:int} */
    public function release(int $enrollmentId, ?int $operatorId = null): array
    {
        try {
            $context = $this->enrollments->releaseContextForAutomation($enrollmentId);
            if ($context === null) {
                throw new RuntimeException('Matrícula não encontrada.');
            }
            if (!in_array($context['status'], ['payment_confirmed', 'payment_waived'], true)) {
                throw new RuntimeException('O acesso exige pagamento confirmado ou dispensa administrativa registrada.');
            }
            if ($context['moodle_enrolment_status'] === 'released') {
                return ['status' => 'already_released'];
            }

            $unitField = $this->moodle->unitCustomFieldForUnit((int) $context['unit_id']);
            if ($unitField === null) {
                throw new RuntimeException('A Unidade desta matrícula ainda não está vinculada ao campo Polo Presencial do AVA. Configure em ADM → AVA.');
            }

            $email = strtolower(trim((string) $context['email']));
            $document = preg_replace('/\D/', '', (string) $context['cpf_cnpj']) ?? '';
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException('O aluno precisa ter um e-mail válido antes da liberação.');
            }

            $byDocument = $document === '' ? [] : $this->client->usersByField('idnumber', $document);
            $byEmail = $this->client->usersByField('email', $email);
            $documentId = isset($byDocument[0]['id']) ? (int) $byDocument[0]['id'] : null;
            $emailId = isset($byEmail[0]['id']) ? (int) $byEmail[0]['id'] : null;
            if ($documentId !== null && $emailId !== null && $documentId !== $emailId) {
                throw new RuntimeException('CPF e e-mail pertencem a usuários diferentes no AVA. Revise os cadastros antes de continuar.');
            }

            $avaUser = $byDocument[0] ?? $byEmail[0] ?? null;
            if (!is_array($avaUser)) {
                if (strlen($document) !== 11) {
                    throw new RuntimeException('O aluno precisa ter um CPF válido para criar o login no AVA.');
                }
                $parts = preg_split('/\s+/', trim((string) $context['name'])) ?: [];
                $first = array_shift($parts) ?: 'Aluno';
                $last = trim(implode(' ', $parts)) ?: 'Interferência';
                $username = $document;
                if ($this->client->usersByField('username', $username) !== []) {
                    throw new RuntimeException('Já existe outro usuário com este CPF como login no AVA. Revise o cadastro antes de continuar.');
                }
                $payload = [
                    'username' => $username,
                    'firstname' => $first,
                    'lastname' => $last,
                    'email' => $email,
                    'idnumber' => $document,
                    'auth' => 'manual',
                    'lang' => 'pt_br',
                    'customfields' => [$unitField],
                ];
                if ($this->integrations->settings()['initial_password_mode'] === 'cpf5') {
                    $payload['password'] = substr($document, 0, 5);
                } else {
                    $payload['createpassword'] = 1;
                }
                $created = $this->client->createUser($payload);
                $avaUser = [
                    'id' => (int) $created['id'],
                    'username' => (string) ($created['username'] ?? $username),
                    'firstname' => $first,
                    'lastname' => $last,
                    'fullname' => trim($first . ' ' . $last),
                    'email' => $email,
                    'idnumber' => $document,
                    'suspended' => 0,
                ];
            }

            $avaUserId = (int) $avaUser['id'];
            $courseId = (int) $context['moodle_course_id'];
            $this->client->updateUserCustomFields($avaUserId, [$unitField]);
            $this->client->enrolStudent($avaUserId, $courseId);
            $avaUser['customfields'] = [[
                'shortname' => $unitField['type'],
                'name' => 'Polo Presencial',
                'value' => $unitField['value'],
            ]];
            $this->moodle->upsertUser($avaUser);
            $this->enrollments->markReleased($enrollmentId, $avaUserId, $courseId, (int) $context['finance_customer_id'], $operatorId, $avaUser);

            return ['status' => 'released', 'ava_user_id' => $avaUserId, 'course_id' => $courseId];
        } catch (Throwable $exception) {
            $this->enrollments->recordReleaseFailure($enrollmentId, $exception->getMessage(), $operatorId);
            throw $exception;
        }
    }
}
