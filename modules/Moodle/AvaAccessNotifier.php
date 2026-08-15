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

    public function notify(int$enrollmentId,?int$userId=null,?int$retryOfId=null):bool
    {
        $context=$this->enrollments->accessCommunicationContextForAutomation($enrollmentId);
        if($context===null)return false;
        $recipient=strtolower(trim((string)$context['email']));
        if(filter_var($recipient,FILTER_VALIDATE_EMAIL)===false){
            $this->enrollments->recordEmailAccessCommunication($enrollmentId,$recipient?:'não informado','failed',$userId,'O aluno não possui um e-mail válido.');
            return false;
        }
        $settings=$this->integrations->settings();
        if(trim((string)($context['ava_base_url']??''))==='')$context['ava_base_url']=(string)($settings['base_url']??'');
        try{
            $this->email->sendAvaAccess($context,(string)($settings['initial_password_mode']??'cpf5'),$retryOfId);
            $this->enrollments->recordEmailAccessCommunication($enrollmentId,$recipient,'sent',$userId,null);
            return true;
        }catch(Throwable$exception){
            $this->enrollments->recordEmailAccessCommunication($enrollmentId,$recipient,'failed',$userId,$exception->getMessage());
            return false;
        }
    }
}
