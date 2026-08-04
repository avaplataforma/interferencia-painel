<?php

declare(strict_types=1);

namespace Interferencia\Modules\Finance;

final readonly class WebhookVerifier
{
    public function __construct(private string $token){}
    public function ready():bool{return strlen($this->token)>=32;}
    public function valid(?string $received):bool{return$this->ready()&&is_string($received)&&hash_equals($this->token,$received);}
}
