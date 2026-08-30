<?php

declare(strict_types=1);

namespace FilamentManager\Services;

use RuntimeException;

final class SmtpMailer
{
    public function send(array $config,string $recipient,string $subject,string $body): void
    {
        $host=trim((string)($config['smtp_host']??''));$port=(int)($config['smtp_port']??587);$encryption=(string)($config['smtp_encryption']??'tls');$from=trim((string)($config['smtp_from_email']??''));if($host===''||$port<1||$port>65535||!filter_var($recipient,FILTER_VALIDATE_EMAIL)||!filter_var($from,FILTER_VALIDATE_EMAIL))throw new RuntimeException('SMTP configuration or recipient is invalid.');$target=($encryption==='ssl'?'ssl://':'').$host;$socket=@stream_socket_client($target.':'.$port,$errno,$error,15,STREAM_CLIENT_CONNECT);if(!$socket)throw new RuntimeException('SMTP connection failed: '.($error?:$errno));stream_set_timeout($socket,20);
        try{$this->expect($socket,[220]);$this->command($socket,'EHLO filamentmanager',[250]);if($encryption==='tls'){$this->command($socket,'STARTTLS',[220]);if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('SMTP TLS negotiation failed.');$this->command($socket,'EHLO filamentmanager',[250]);}$username=(string)($config['smtp_username']??'');if($username!==''){$this->command($socket,'AUTH LOGIN',[334]);$this->command($socket,base64_encode($username),[334]);$this->command($socket,base64_encode((string)($config['smtp_password']??'')),[235]);}$this->command($socket,'MAIL FROM:<'.$from.'>',[250]);$this->command($socket,'RCPT TO:<'.$recipient.'>',[250,251]);$this->command($socket,'DATA',[354]);$fromName=$this->encoded((string)($config['smtp_from_name']??'FilamentManager'));$headers=['Date: '.date(DATE_RFC2822),'From: '.$fromName.' <'.$from.'>','To: <'.$recipient.'>','Subject: '.$this->encoded($subject),'Message-ID: <'.bin2hex(random_bytes(12)).'@filamentmanager>','MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8','Content-Transfer-Encoding: base64'];$payload=implode("\r\n",$headers)."\r\n\r\n".chunk_split(base64_encode($body),76,"\r\n");$payload=preg_replace('/(?m)^\./','..',$payload)."\r\n.\r\n";fwrite($socket,$payload);$this->expect($socket,[250]);$this->command($socket,'QUIT',[221]);}finally{fclose($socket);}
    }

    private function command($socket,string $command,array $codes): void{fwrite($socket,$command."\r\n");$this->expect($socket,$codes);}
    private function expect($socket,array $codes): void{$response='';do{$line=fgets($socket,4096);if($line===false)throw new RuntimeException('SMTP server closed the connection.');$response.=$line;}while(strlen($line)>=4&&$line[3]==='-');$code=(int)substr($response,0,3);if(!in_array($code,$codes,true))throw new RuntimeException('SMTP error '.$code.': '.trim(preg_replace('/\s+/',' ',$response)));}
    private function encoded(string $value): string{return '=?UTF-8?B?'.base64_encode(str_replace(["\r","\n"],' ',$value)).'?=';}
}
