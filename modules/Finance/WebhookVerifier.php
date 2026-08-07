<?php

declare(strict_types=1);

namespace Interferencia\Modules\Finance;

final readonly class WebhookVerifier
{
    /** @param string|list<string> $tokens */
    public function __construct(private string|array $tokens){}
    /** @return list<string> */
    private function usable():array{return array_values(array_filter(is_array($this->tokens)?$this->tokens:[$this->tokens],static fn(string$token):bool=>strlen($token)>=32));}
    public function ready():bool{return$this->usable()!==[];}
    public function valid(?string $received):bool{if(!is_string($received))return false;foreach($this->usable()as$token)if(hash_equals($token,$received))return true;return false;}
}
