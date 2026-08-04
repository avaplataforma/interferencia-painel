<?php

declare(strict_types=1);

namespace Interferencia\Modules\Finance;

final readonly class AsaasSynchronizer
{
    public function __construct(private AsaasClient $client,private FinanceRepository $repository){}
    /** @return array{customers:int,payments:int} */
    public function sync():array
    {
        $customers=$this->syncResource(fn(int$o,int$l):array=>$this->client->listCustomers($o,$l),fn(array$i)=>$this->repository->upsertCustomer($i));
        $payments=$this->syncResource(fn(int$o,int$l):array=>$this->client->listPayments($o,$l),fn(array$i)=>$this->repository->upsertPayment($i));
        return['customers'=>$customers,'payments'=>$payments];
    }
    /** @param callable(int,int):array<string,mixed> $fetch @param callable(array<string,mixed>):void $save */
    private function syncResource(callable$fetch,callable$save):int{$offset=0;$count=0;do{$page=$fetch($offset,100);foreach($page['data']as$item){$save($item);$count++;}$offset+=count($page['data']);$more=(bool)$page['hasMore'];}while($more&&$offset<100000);return$count;}
}
