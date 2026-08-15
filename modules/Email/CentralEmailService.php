<?php

declare(strict_types=1);

namespace Interferencia\Modules\Email;

use RuntimeException;
use Throwable;

final readonly class CentralEmailService
{
    public function __construct(private CentralEmailRepository $repository) {}

    public function test(string$recipient):void
    {
        $this->deliver(null,$recipient,'Teste do E-mail Central','A conexão SMTP do Mundo Inter foi validada com sucesso.','smtp_test',null,null);
        $this->repository->markTest(null);
    }

    public function deliver(?int$organizationId,string$recipient,string$subject,string$text,string$type='transactional',?string$relatedType=null,?int$relatedId=null,?string$html=null):string
    {
        $settings=$this->repository->settings(true);if(!(bool)$settings['configured'])throw new RuntimeException('Configure o E-mail Central no ADM Central antes de enviar mensagens.');if(!(bool)$settings['is_active']&&$type!=='smtp_test')throw new RuntimeException('A integração E-mail Central está inativa.');
        $fromName=(string)$settings['from_name'];$fromEmail=(string)$settings['from_email'];$reply=(string)$settings['reply_to_email'];
        if(($organizationId??0)>0){
            $sender=$this->repository->senderForOrganization((int)$organizationId);
            if($sender!==null){
                $fromName=trim((string)($sender['from_name']??''))?:trim((string)$sender['display_name']);
                $organizationReply=trim((string)($sender['reply_to_email']??''));
                if($organizationReply==='')$organizationReply=trim((string)($sender['support_email']??''));
                if($organizationReply==='')$organizationReply=trim((string)($sender['manager_email']??''));
                if($organizationReply!=='')$reply=$organizationReply;
                if((int)($sender['is_active']??0)===1&&($sender['domain_status']??'')==='verified')$fromEmail=(string)$sender['from_email'];
            }
        }
        $log=['organization_id'=>$organizationId,'message_type'=>$type,'recipient_email'=>$recipient,'sender_email'=>$fromEmail,'subject'=>$subject,'related_type'=>$relatedType,'related_id'=>$relatedId];
        try{$messageId=(new SmtpClient($settings))->send($fromEmail,$fromName,$recipient,$subject,$text,$reply!==''?$reply:null,$html);$this->repository->record($log+['status'=>'sent','provider_message_id'=>$messageId]);return$messageId;}
        catch(Throwable$exception){$this->repository->record($log+['status'=>'failed','error_message'=>$exception->getMessage()]);throw$exception;}
    }
}
