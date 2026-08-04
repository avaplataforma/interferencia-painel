<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Security;

use RuntimeException;

final readonly class SecretCipher
{
    private string $key;
    public function __construct(string $encodedKey)
    {
        $key=base64_decode($encodedKey,true);
        $this->key=is_string($key)?$key:'';
    }
    public function ready():bool{return function_exists('sodium_crypto_secretbox')&&defined('SODIUM_CRYPTO_SECRETBOX_KEYBYTES')&&strlen($this->key)===SODIUM_CRYPTO_SECRETBOX_KEYBYTES;}
    public function encrypt(string$value):string
    {
        if(!$this->ready())throw new RuntimeException('A chave-mestra de criptografia ainda não foi configurada.');
        $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce.sodium_crypto_secretbox($value,$nonce,$this->key));
    }
    public function decrypt(?string$value):string
    {
        if(!$this->ready()||$value===null||$value==='')return'';
        $payload=base64_decode($value,true);if(!is_string($payload)||strlen($payload)<=SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)return'';
        $plain=sodium_crypto_secretbox_open(substr($payload,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),substr($payload,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),$this->key);
        return is_string($plain)?$plain:'';
    }
}
