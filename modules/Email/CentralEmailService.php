<?php

declare(strict_types=1);

namespace Interferencia\Modules\Email;

use RuntimeException;
use Throwable;

final readonly class CentralEmailService
{
    public function __construct(private CentralEmailRepository $repository,private string$publicBaseUrl='https://mundointer.com.br') {}

    public function test(string$recipient):void
    {
        $html='<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto;padding:32px;border:1px solid #e5e7eb;border-radius:16px"><h1 style="margin:0 0 12px;color:#102a56">E-mail Central validado</h1><p>A conexão SMTP do Mundo Inter foi validada com sucesso.</p></div>';
        $this->deliver(null,$recipient,'Teste do E-mail Central','A conexão SMTP do Mundo Inter foi validada com sucesso.','smtp_test',null,null,$html);
        $this->repository->markTest(null);
    }

    /** @param array<string,mixed> $context */
    public function sendAvaAccess(array$context,string$passwordMode,?int$retryOfId=null):string
    {
        $organizationId=(int)($context['organization_id']??0)?:null;
        $providerAccess=trim((string)($context['academic_provider_code']??''))!=='';
        $login=(string)($context['username']?:preg_replace('/\D/','',(string)($context['cpf_cnpj']??'')));
        $baseUrl=rtrim((string)($context['ava_base_url']??''),'/');
        $accessUrl=$providerAccess?$baseUrl:$baseUrl.'/';
        $password=$providerAccess?'Acesso pessoal pelo botão':($passwordMode==='cpf5'?'Os 5 primeiros dígitos do CPF':'Use “Esqueci minha senha” para definir uma nova senha');
        $variables=[
            'aluno'=>(string)($context['name']??'Aluno'),
            'curso'=>(string)($context['course_name']??'seu curso'),
            'franquia'=>(string)($context['organization_name']??''),
            'unidade'=>(string)($context['unit_name']??''),
            'login'=>$providerAccess?'Acesso pessoal':$login,
            'senha'=>$password,
            'ava_url'=>$accessUrl,
            'suporte_email'=>(string)($context['support_email']??''),
            'suporte_whatsapp'=>(string)($context['support_phone']??''),
            'provider_access'=>$providerAccess,
        ];
        $rendered=$this->renderAvaAccess($organizationId,$variables);
        if(!(bool)($rendered['template']['is_active']??false))throw new RuntimeException('O modelo de acesso ao AVA está inativo no E-mail Central.');
        return$this->deliver($organizationId,(string)$context['email'],$rendered['subject'],$rendered['text'],'ava_access','student_enrollment',(int)$context['id'],$rendered['html'],$retryOfId);
    }

    /** @param array<string,mixed> $overrides @return array{subject:string,text:string,html:string,brand:array<string,mixed>,template:array<string,mixed>} */
    public function previewAvaAccess(?int$organizationId=null,array$overrides=[]):array
    {
        $brand=$this->repository->brand($organizationId);
        $variables=[
            'aluno'=>'Mariana Oliveira','curso'=>'Gestão Administrativa','franquia'=>(string)$brand['display_name'],'unidade'=>'Unidade Centro',
            'login'=>'mariana.oliveira','senha'=>'Os 5 primeiros dígitos do CPF','ava_url'=>'https://avacursos.com.br/','suporte_email'=>(string)($brand['support_email']??''),'suporte_whatsapp'=>(string)($brand['support_phone']??''),'provider_access'=>false,
        ];
        return$this->renderAvaAccess($organizationId,$variables,$overrides);
    }

    /** @param array<string,mixed> $overrides */
    public function testAvaAccess(string$recipient,?int$organizationId,array$overrides=[]):void
    {
        if(filter_var($recipient,FILTER_VALIDATE_EMAIL)===false)throw new RuntimeException('Informe um e-mail válido para o teste do modelo.');
        $rendered=$this->previewAvaAccess($organizationId,$overrides);
        $this->deliver($organizationId,$recipient,'[TESTE] '.$rendered['subject'],$rendered['text'],'ava_access_test',null,null,$rendered['html']);
    }

    public function deliver(?int$organizationId,string$recipient,string$subject,string$text,string$type='transactional',?string$relatedType=null,?int$relatedId=null,?string$html=null,?int$retryOfId=null):string
    {
        $settings=$this->repository->settings(true);if(!(bool)$settings['configured'])throw new RuntimeException('Configure o E-mail Central no ADM Central antes de enviar mensagens.');if(!(bool)$settings['is_active']&&$type!=='smtp_test'&&$type!=='ava_access_test')throw new RuntimeException('A integração E-mail Central está inativa.');
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
        $log=['organization_id'=>$organizationId,'message_type'=>$type,'recipient_email'=>$recipient,'sender_email'=>$fromEmail,'subject'=>$subject,'related_type'=>$relatedType,'related_id'=>$relatedId,'retry_of_id'=>$retryOfId];
        try{$messageId=(new SmtpClient($settings))->send($fromEmail,$fromName,$recipient,$subject,$text,$reply!==''?$reply:null,$html);$this->repository->record($log+['status'=>'sent','provider_message_id'=>$messageId]);return$messageId;}
        catch(Throwable$exception){$this->repository->record($log+['status'=>'failed','error_message'=>$exception->getMessage()]);throw$exception;}
    }

    /** @param array<string,mixed> $variables @param array<string,mixed> $overrides @return array{subject:string,text:string,html:string,brand:array<string,mixed>,template:array<string,mixed>} */
    private function renderAvaAccess(?int$organizationId,array$variables,array$overrides=[]):array
    {
        $template=$this->repository->template();
        foreach(['subject_template','eyebrow','heading','intro','button_label','footer_text']as$field)if(array_key_exists($field,$overrides)){$value=trim((string)$overrides[$field]);if($value!=='')$template[$field]=mb_substr($value,0,$field==='intro'?2000:($field==='footer_text'?500:255));}
        $brand=$this->repository->brand($organizationId);
        if(trim((string)($variables['franquia']??''))==='')$variables['franquia']=(string)$brand['display_name'];
        if(trim((string)($variables['suporte_email']??''))==='')$variables['suporte_email']=(string)($brand['support_email']??'');
        if(trim((string)($variables['suporte_whatsapp']??''))==='')$variables['suporte_whatsapp']=(string)($brand['support_phone']??'');
        $subject=preg_replace('/[\r\n]+/',' ',$this->replace((string)$template['subject_template'],$variables))?:'Seu acesso ao AVA';
        $heading=$this->replace((string)$template['heading'],$variables);$intro=$this->replace((string)$template['intro'],$variables);$footer=$this->replace((string)($template['footer_text']??''),$variables);
        $primary=$this->color((string)($brand['primary_color']??''),'#ed1c24');$secondary=$this->color((string)($brand['secondary_color']??''),'#102a56');
        $logo=$this->assetUrl((string)($brand['logo_path']??''));$accessUrl=$this->safeUrl((string)$variables['ava_url']);
        $fields=[['Login',(string)$variables['login']],['Senha inicial',(string)$variables['senha']],['Curso',(string)$variables['curso']],['Unidade',(string)$variables['unidade']]];
        $fieldHtml='';$textFields=[];foreach($fields as[$label,$value]){if(trim($value)==='')continue;$fieldHtml.='<tr><td style="padding:10px 12px;color:#64748b;border-bottom:1px solid #edf0f3;width:34%">'.$this->e($label).'</td><td style="padding:10px 12px;color:#172033;font-weight:700;border-bottom:1px solid #edf0f3">'.$this->e($value).'</td></tr>';$textFields[]=$label.': '.$value;}
        $support=[];if(trim((string)$variables['suporte_email'])!=='')$support[]=$this->e((string)$variables['suporte_email']);if(trim((string)$variables['suporte_whatsapp'])!=='')$support[]='WhatsApp '.$this->e((string)$variables['suporte_whatsapp']);
        $supportHtml=$support!==[]?'<p style="margin:12px 0 0;font-size:13px;color:#64748b">Suporte: '.implode(' · ',$support).'</p>':'';
        $html='<!doctype html><html><body style="margin:0;background:#f3f6f8;font-family:Arial,sans-serif;color:#172033"><div style="display:none;max-height:0;overflow:hidden">'.$this->e($intro).'</div><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6f8"><tr><td align="center" style="padding:28px 12px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 8px 30px rgba(15,23,42,.08)"><tr><td style="height:7px;background:'.$primary.'"></td></tr><tr><td style="padding:28px 34px 12px">'.($logo!==''?'<img src="'.$this->e($logo).'" alt="'.$this->e((string)$brand['display_name']).'" style="display:block;max-width:190px;max-height:72px;margin-bottom:24px">':'').'<div style="font-size:12px;letter-spacing:1.4px;font-weight:800;color:'.$primary.'">'.$this->e((string)($template['eyebrow']??'')).'</div><h1 style="font-size:28px;line-height:1.2;margin:8px 0 12px;color:'.$secondary.'">'.$this->e($heading).'</h1><p style="font-size:16px;line-height:1.65;margin:0;color:#475569">'.nl2br($this->e($intro)).'</p></td></tr><tr><td style="padding:18px 34px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:12px;border-collapse:separate;overflow:hidden">'.$fieldHtml.'</table></td></tr><tr><td align="center" style="padding:4px 34px 30px"><a href="'.$this->e($accessUrl).'" style="display:inline-block;padding:14px 24px;border-radius:10px;background:'.$primary.';color:#fff;text-decoration:none;font-weight:800">'.$this->e((string)$template['button_label']).'</a><p style="margin:14px 0 0;font-size:12px;color:#94a3b8">Se o botão não abrir, copie: '.$this->e($accessUrl).'</p></td></tr><tr><td style="padding:20px 34px;background:#f8fafc;border-top:1px solid #edf0f3"><p style="margin:0;color:#64748b;font-size:13px;line-height:1.5">'.$this->e($footer).'</p>'.$supportHtml.'<p style="margin:12px 0 0;font-size:11px;color:#94a3b8">Envio seguro pelo E-mail Central Mundo Inter.</p></td></tr></table></td></tr></table></body></html>';
        $text=$heading."\r\n\r\n".$intro."\r\n\r\n".implode("\r\n",$textFields)."\r\nAcesso: ".$accessUrl."\r\n\r\n".$footer;
        return['subject'=>$subject,'text'=>$text,'html'=>$html,'brand'=>$brand,'template'=>$template];
    }

    /** @param array<string,mixed> $variables */
    private function replace(string$text,array$variables):string{return preg_replace_callback('/{{\s*([a-z_]+)\s*}}/i',static fn(array$m):string=>(string)($variables[strtolower($m[1])]??''),$text)??$text;}
    private function e(string$value):string{return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    private function color(string$value,string$fallback):string{return preg_match('/^#[0-9a-f]{6}$/i',trim($value))===1?strtolower(trim($value)):$fallback;}
    private function safeUrl(string$value):string{$value=trim($value);return filter_var($value,FILTER_VALIDATE_URL)!==false&&str_starts_with(strtolower($value),'https://')?$value:'https://avacursos.com.br/';}
    private function assetUrl(string$path):string
    {
        $path=trim($path);if($path===''||str_starts_with($path,'spaces:'))return'';if(filter_var($path,FILTER_VALIDATE_URL)!==false)return$path;return rtrim($this->publicBaseUrl,'/').'/'.ltrim($path,'/');
    }
}
