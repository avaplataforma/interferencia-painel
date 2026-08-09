<?php

declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use RuntimeException;
use Throwable;
use Interferencia\Modules\Organization\OrganizationPoleRepository;

final readonly class AvaEnrollmentReleaser
{
    public function __construct(
        private AvaConnectionRepository $connections,
        private IntegrationRepository $integrations,
        private MoodleRepository $moodle,
        private EnrollmentRepository $enrollments,
        private OrganizationPoleRepository $poles,
        private string $automaticFrom,
    ) {}

    /** @return array{status:string,ava_user_id?:int,course_id?:int,connection?:string} */
    public function release(int $enrollmentId, ?int $operatorId = null): array
    {
        try {
            $context = $this->enrollments->releaseContextForAutomation($enrollmentId);
            if ($context === null) {
                throw new RuntimeException('Matrícula não encontrada.');
            }
            $automaticFrom = strtotime($this->automaticFrom);
            $enrollmentCreatedAt = strtotime((string) $context['created_at']);
            if ($operatorId === null && ($automaticFrom === false || $enrollmentCreatedAt === false || $enrollmentCreatedAt < $automaticFrom)) {
                return ['status' => 'manual_flow'];
            }
            if (!in_array($context['status'], ['payment_confirmed', 'payment_waived'], true)) {
                throw new RuntimeException('O acesso exige pagamento confirmado ou dispensa administrativa registrada.');
            }
            if ($context['moodle_enrolment_status'] === 'released') {
                return ['status' => 'already_released'];
            }

            $connection=$this->connections->find((int)($context['ava_connection_id']??0));
            if($connection===null||!(bool)($connection['configured']??false)||!(bool)($connection['is_active']??false)){
                throw new RuntimeException('O AVA escolhido para esta matrícula não está configurado ou ativo.');
            }
            if((string)$connection['connection_type']==='franchise'&&(int)($connection['organization_id']??0)!==(int)$context['organization_id']){
                throw new RuntimeException('O AVA escolhido não pertence a esta franquia.');
            }
            $courseId=(int)($context['ava_course_id']??0);
            if($courseId<1){
                $destination=$this->connections->resolveDestination((int)$context['organization_id'],(int)$context['moodle_course_id'],(int)$connection['id']);
                $courseId=(int)$destination['remote_course_id'];
            }
            $client=new MoodleClient((string)$connection['base_url'],(string)$connection['token'],true);
            if(!$client->ready())throw new RuntimeException('A conexão com o AVA escolhido não está pronta.');

            $identity=$this->poles->identityForEnrollment((int)$context['unit_id'],isset($context['organization_pole_id'])?(int)$context['organization_pole_id']:null);
            if($identity===null)throw new RuntimeException('A Unidade desta matrícula ainda não possui um polo Mundo Inter ativo. Configure a aba Polos da franquia.');
            $customFields=[
                ['type'=>OrganizationPoleRepository::FRANCHISE_FIELD,'value'=>$identity['franchise_code']],
                ['type'=>OrganizationPoleRepository::POLE_FIELD,'value'=>$identity['pole_code']],
            ];
            $unitField=(string)$connection['connection_type']==='shared'?$this->moodle->unitCustomFieldForUnit((int)$context['unit_id']):null;
            if($unitField!==null)$customFields[]=$unitField;

            $email = strtolower(trim((string) $context['email']));
            $document = preg_replace('/\D/', '', (string) $context['cpf_cnpj']) ?? '';
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException('O aluno precisa ter um e-mail válido antes da liberação.');
            }

            $byDocument = $document === '' ? [] : $client->usersByField('idnumber', $document);
            $byEmail = $client->usersByField('email', $email);
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
                if ($client->usersByField('username', $username) !== []) {
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
                    'customfields' => $customFields,
                ];
                if ($this->integrations->settings()['initial_password_mode'] === 'cpf5') {
                    $payload['password'] = substr($document, 0, 5);
                } else {
                    $payload['createpassword'] = 1;
                }
                $created = $client->createUser($payload);
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
            $client->updateUserCustomFields($avaUserId, $customFields);
            $client->enrolStudent($avaUserId, $courseId);
            $avaUser['customfields']=[
                ['shortname'=>OrganizationPoleRepository::FRANCHISE_FIELD,'name'=>'Franquia Mundo Inter','value'=>$identity['franchise_code']],
                ['shortname'=>OrganizationPoleRepository::POLE_FIELD,'name'=>'Polo Mundo Inter','value'=>$identity['pole_code']],
            ];
            if($unitField!==null)$avaUser['customfields'][]=['shortname'=>$unitField['type'],'name'=>'Polo Presencial (legado)','value'=>$unitField['value']];
            if((string)$connection['connection_type']==='shared')$this->moodle->upsertUser($avaUser);
            $this->enrollments->markReleased($enrollmentId,$avaUserId,$courseId,(int)$context['finance_customer_id'],$operatorId,$avaUser,(int)$connection['id'],(string)$connection['name'],(string)$connection['connection_type']);

            return ['status'=>'released','ava_user_id'=>$avaUserId,'course_id'=>$courseId,'connection'=>(string)$connection['name']];
        } catch (Throwable $exception) {
            $this->enrollments->recordReleaseFailure($enrollmentId, $exception->getMessage(), $operatorId);
            throw $exception;
        }
    }
}
