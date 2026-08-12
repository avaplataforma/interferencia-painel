<?php

declare(strict_types=1);

namespace Interferencia\Modules\Moodle;

use RuntimeException;
use Throwable;
use Interferencia\Modules\Catalog\ContedTechClient;
use Interferencia\Modules\Catalog\CourseProviderRepository;
use Interferencia\Modules\Organization\OrganizationPoleRepository;

final readonly class AvaEnrollmentReleaser
{
    public function __construct(
        private AvaConnectionRepository $connections,
        private IntegrationRepository $integrations,
        private MoodleRepository $moodle,
        private EnrollmentRepository $enrollments,
        private OrganizationPoleRepository $poles,
        private CourseProviderRepository $courseProviders,
        private AcademicOrganizationRepository $academicOrganization,
        private string $automaticFrom,
    ) {}

    /** @return array{status:string,ava_user_id?:int,course_id?:int,connection?:string,academic_organization?:string} */
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

            if ((string)($context['academic_provider_code'] ?? '') !== '') {
                return $this->releaseProvider($enrollmentId, $context, $operatorId);
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
            $academicStatus='pending';
            $placement=null;
            try{
                $placement=$this->academicOrganization->prepareForEnrollment($context,$connection,$identity,$courseId);
                $remoteOrganization=$client->organizeEnrollment($avaUserId,$courseId,$placement['payload']);
                $this->academicOrganization->markSynced($placement['cohort_id'],$placement['group_id'],$remoteOrganization);
                $academicStatus='synced';
            }catch(Throwable $academicException){
                if(is_array($placement))$this->academicOrganization->markFailed($placement['cohort_id'],$placement['group_id'],$academicException->getMessage());
                $this->enrollments->recordAcademicOrganizationFailure($enrollmentId,$academicException->getMessage(),$operatorId);
                $academicStatus='failed';
            }
            $avaUser['customfields']=[
                ['shortname'=>OrganizationPoleRepository::FRANCHISE_FIELD,'name'=>'Franquia Mundo Inter','value'=>$identity['franchise_code']],
                ['shortname'=>OrganizationPoleRepository::POLE_FIELD,'name'=>'Polo Mundo Inter','value'=>$identity['pole_code']],
            ];
            if($unitField!==null)$avaUser['customfields'][]=['shortname'=>$unitField['type'],'name'=>'Polo Presencial (legado)','value'=>$unitField['value']];
            if((string)$connection['connection_type']==='shared')$this->moodle->upsertUser($avaUser);
            $this->enrollments->markReleased($enrollmentId,$avaUserId,$courseId,(int)$context['finance_customer_id'],$operatorId,$avaUser,(int)$connection['id'],(string)$connection['name'],(string)$connection['connection_type']);

            return ['status'=>'released','ava_user_id'=>$avaUserId,'course_id'=>$courseId,'connection'=>(string)$connection['name'],'academic_organization'=>$academicStatus];
        } catch (Throwable $exception) {
            $this->enrollments->recordReleaseFailure($enrollmentId, $exception->getMessage(), $operatorId);
            throw $exception;
        }
    }

    /** @param array<string,mixed> $context @return array{status:string,connection:string,access_url:string} */
    private function releaseProvider(int $enrollmentId, array $context, ?int $operatorId): array
    {
        $providerCode = (string)($context['academic_provider_code'] ?? '');
        if ($providerCode !== 'conted_tech') {
            throw new RuntimeException('O conector acadêmico desta matrícula ainda não está homologado para liberação automática.');
        }

        $document = preg_replace('/\D/', '', (string)($context['cpf_cnpj'] ?? '')) ?? '';
        if (strlen($document) !== 11) {
            throw new RuntimeException('O aluno precisa ter um CPF válido para gerar o acesso no Catálogo EXPERT.');
        }
        $contentType = trim((string)($context['provider_content_type'] ?? ''));
        $batch = trim((string)($context['provider_batch'] ?? ''));
        if ($contentType === '' || $batch === '') {
            throw new RuntimeException('A oferta EXPERT não possui os identificadores acadêmicos exigidos pela CONTED TECH.');
        }

        $settings = $this->courseProviders->settingsForProvider($providerCode, true);
        $client = new ContedTechClient(
            (string)$settings['base_url'],
            (string)$settings['token'],
            (string)$settings['password'],
            (string)$settings['username'],
            (bool)$settings['is_active'],
        );
        $response = $client->contentLink($contentType, $batch, $document);
        $accessUrl = $this->providerAccessUrl($response);
        if ($accessUrl === '') {
            throw new RuntimeException('A CONTED TECH confirmou a solicitação, mas não retornou o link pessoal de acesso esperado.');
        }

        $this->enrollments->markProviderReleased($enrollmentId, $document, $accessUrl, $response, $operatorId);
        return ['status' => 'released', 'connection' => 'Catálogo EXPERT', 'access_url' => $accessUrl];
    }

    /** @param array<string,mixed> $response */
    private function providerAccessUrl(array $response): string
    {
        $priorityKeys = ['url', 'link', 'access_url', 'accessUrl', 'content_url', 'contentUrl'];
        foreach ($priorityKeys as $key) {
            $candidate = trim((string)($response[$key] ?? ''));
            if ($this->isSafeProviderUrl($candidate)) return $candidate;
        }
        foreach ($response as $value) {
            if (is_string($value)) {
                $candidate = trim($value);
                if ($this->isSafeProviderUrl($candidate)) return $candidate;
            }
            if (is_array($value)) {
                $candidate = $this->providerAccessUrl($value);
                if ($candidate !== '') return $candidate;
            }
        }
        return '';
    }

    private function isSafeProviderUrl(string $url): bool
    {
        return $url !== ''
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string)parse_url($url, PHP_URL_SCHEME)) === 'https';
    }
}
