<?php
declare(strict_types=1);
namespace Interferencia\Modules\WhatsApp;

final readonly class WebhookVerifier
{
    public function __construct(private string $verifyToken, private string $appSecret) {}

    public function challenge(?string $mode, ?string $token, ?string $challenge): ?string
    {
        if ($this->verifyToken === '' || $mode !== 'subscribe' || $token === null || !hash_equals($this->verifyToken, $token)) return null;
        return $challenge;
    }

    public function validSignature(string $body, ?string $signature): bool
    {
        if ($this->appSecret === '' || $signature === null || !str_starts_with($signature, 'sha256=')) return false;
        return hash_equals('sha256=' . hash_hmac('sha256', $body, $this->appSecret), $signature);
    }
}
