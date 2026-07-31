<?php
declare(strict_types=1);
namespace Interferencia\Modules\Crm;
use DateTimeImmutable;
use RuntimeException;
final readonly class ExternalContactIntake{
 public function __construct(private ContactRepository $contacts,private string $key){}
 /** @param array<string,mixed> $data @return array{id:int,duplicate:bool} */
 public function receive(string $providedKey,array $data,string $source='unknown'):array{
  if($this->key===''||!hash_equals($this->key,$providedKey))throw new RuntimeException('Credencial inválida.',401);
  if(!$this->contacts->allowExternalRequest(hash('sha256',$providedKey.'|'.$source)))throw new RuntimeException('Limite temporário de envios excedido.',429);
  $unit=$this->contacts->activeUnitByCode(trim((string)($data['polo']??'')));if($unit===null)throw new RuntimeException('Polo inválido ou inativo.',422);
  $name=trim((string)($data['nome']??''));$submission=trim((string)($data['submission_id']??''));if($name===''||$submission==='')throw new RuntimeException('Nome e submission_id são obrigatórios.',422);
  $phone=$this->nullable($data['telefone']??null);$email=$this->nullable($data['email']??null);if($phone===null&&$email===null)throw new RuntimeException('Informe telefone ou e-mail.',422);
  $existing=$this->contacts->externalDuplicate($submission,(int)$unit['id'],$phone,$email);if($existing!==null)return['id'=>$existing,'duplicate'=>true];
  $score=($data['interesse']??'')===''?null:(int)$data['interesse'];if($score!==null&&($score<0||$score>10))throw new RuntimeException('Interesse deve estar entre 0 e 10.',422);
  try{$registered=new DateTimeImmutable((string)($data['data_hora']??'now'));$consent=isset($data['consentimento_em'])?new DateTimeImmutable((string)$data['consentimento_em']):null;}catch(\Throwable){throw new RuntimeException('Data e hora inválidas.',422);}
  $id=$this->contacts->createExternal(['unit_id'=>(int)$unit['id'],'name'=>$name,'phone'=>$phone,'email'=>$email,'course'=>$this->nullable($data['curso']??null),'interest_score'=>$score,'origin_city'=>$this->nullable($data['cidade_origem']??null),'external_submission_id'=>$submission,'consent_at'=>$consent?->format('Y-m-d H:i:s'),'privacy_notice_version'=>$this->nullable($data['versao_privacidade']??null),'registered_at'=>$registered->format('Y-m-d H:i:s'),'notes'=>$this->nullable($data['observacoes']??null)]);return['id'=>$id,'duplicate'=>false];
 }
 private function nullable(mixed $v):?string{$v=trim((string)$v);return$v===''?null:$v;}
}
