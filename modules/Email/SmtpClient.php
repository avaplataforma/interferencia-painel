<?php

declare(strict_types=1);

namespace Interferencia\Modules\Email;

use RuntimeException;

final class SmtpClient
{
    /** @param array<string,mixed> $settings */
    public function __construct(private readonly array $settings) {}

    public function send(string$fromEmail,string$fromName,string$toEmail,string$subject,string$text,?string$replyTo=null,?string$html=null):string
    {
        foreach([$fromEmail,$toEmail]as$email)if(filter_var($email,FILTER_VALIDATE_EMAIL)===false)throw new RuntimeException('O envio contém um endereço de e-mail inválido.');
        $host=(string)$this->settings['smtp_host'];$port=(int)$this->settings['smtp_port'];$encryption=(string)$this->settings['encryption'];
        $target=($encryption==='ssl'?'ssl://':'').$host.':'.$port;$errorNumber=0;$errorMessage='';
        $stream=@stream_socket_client($target,$errorNumber,$errorMessage,15,STREAM_CLIENT_CONNECT);
        if(!is_resource($stream))throw new RuntimeException('Não foi possível conectar ao SMTP: '.($errorMessage?:'conexão recusada').'.');
        stream_set_timeout($stream,15);
        try{
            $this->expect($stream,[220]);$this->command($stream,'EHLO mundointer.com.br',[250]);
            if($encryption==='tls'){$this->command($stream,'STARTTLS',[220]);if(!stream_socket_enable_crypto($stream,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('O servidor SMTP recusou a conexão segura.');$this->command($stream,'EHLO mundointer.com.br',[250]);}
            $username=(string)$this->settings['username'];$password=(string)$this->settings['password'];
            $this->command($stream,'AUTH LOGIN',[334]);$this->command($stream,base64_encode($username),[334]);$this->command($stream,base64_encode($password),[235]);
            $this->command($stream,'MAIL FROM:<'.$fromEmail.'>',[250]);$this->command($stream,'RCPT TO:<'.$toEmail.'>',[250,251]);$this->command($stream,'DATA',[354]);
            $boundary='mi_'.bin2hex(random_bytes(12));$headers=[
                'Date: '.date(DATE_RFC2822),'From: '.$this->address($fromEmail,$fromName),'To: <'.$toEmail.'>','Subject: '.$this->encoded($subject),
                'Message-ID: <'.bin2hex(random_bytes(16)).'@'.substr(strrchr($fromEmail,'@')?:'@mundointer.com.br',1).'>','MIME-Version: 1.0',
            ];
            if($replyTo!==null&&filter_var($replyTo,FILTER_VALIDATE_EMAIL)!==false)$headers[]='Reply-To: <'.$replyTo.'>';
            if($html===null){$headers[]='Content-Type: text/plain; charset=UTF-8';$headers[]='Content-Transfer-Encoding: base64';$body=chunk_split(base64_encode($text));}
            else{$headers[]='Content-Type: multipart/alternative; boundary="'.$boundary.'"';$body="--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($text))."\r\n--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($html))."\r\n--{$boundary}--\r\n";}
            $payload=implode("\r\n",$headers)."\r\n\r\n".$body;$payload=preg_replace('/(?m)^\./','..',$payload)??$payload;
            fwrite($stream,$payload."\r\n.\r\n");$reply=$this->expect($stream,[250]);$this->command($stream,'QUIT',[221]);
            return trim($reply);
        }finally{fclose($stream);}
    }

    /** @param resource $stream @param list<int> $codes */
    private function command($stream,string$command,array$codes):string{fwrite($stream,$command."\r\n");return$this->expect($stream,$codes);}
    /** @param resource $stream @param list<int> $codes */
    private function expect($stream,array$codes):string
    {
        $reply='';do{$line=fgets($stream,4096);if($line===false){$meta=stream_get_meta_data($stream);throw new RuntimeException(($meta['timed_out']??false)?'O servidor SMTP excedeu o tempo de resposta.':'A conexão SMTP foi encerrada inesperadamente.');}$reply.=$line;}while(isset($line[3])&&$line[3]==='-');
        $code=(int)substr($reply,0,3);if(!in_array($code,$codes,true))throw new RuntimeException('O SMTP recusou a operação ('.$code.').');return$reply;
    }
    private function encoded(string$value):string{$value=preg_replace('/[\r\n]+/',' ',trim($value))??'';return'=?UTF-8?B?'.base64_encode($value).'?=';}
    private function address(string$email,string$name):string{return$this->encoded($name).' <'.$email.'>';}
}
