<?php

declare(strict_types=1);

namespace FilamentManager\Services;

use FilamentManager\Core\App;
use RuntimeException;

final class CryptoService
{
    public function __construct(private readonly App $app) {}

    public function encrypt(string $plainText): string
    {
        if($plainText==='')return '';$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plainText,'aes-256-gcm',$this->key(),OPENSSL_RAW_DATA,$iv,$tag,'filamentmanager-smtp');if($cipher===false)throw new RuntimeException('Cannot encrypt the SMTP password.');return 'v1.'.base64_encode($iv.$tag.$cipher);
    }

    public function decrypt(string $encoded): string
    {
        if($encoded==='')return '';if(!str_starts_with($encoded,'v1.'))throw new RuntimeException('Unsupported encrypted setting format.');$raw=base64_decode(substr($encoded,3),true);if($raw===false||strlen($raw)<29)throw new RuntimeException('Invalid encrypted setting.');$plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$this->key(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'filamentmanager-smtp');if($plain===false)throw new RuntimeException('Cannot decrypt the SMTP password.');return $plain;
    }

    private function key(): string
    {
        $key=(string)$this->app->config('app_key');if(strlen($key)<32)throw new RuntimeException('Application key is missing or too short.');return hash('sha256',$key,true);
    }
}
