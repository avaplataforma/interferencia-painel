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
    /** @return array{customers:int,payments:int,complete:bool,phase:string,next_offset:int} */
    public function syncBatch():array
    {
        $customers=$this->repository->syncCursor('customers');
        if(!$customers['complete']){
            $page=$this->client->listCustomers($customers['offset'],100);foreach($page['data']as$item)$this->repository->upsertCustomer($item);
            $next=$customers['offset']+count($page['data']);$this->repository->advanceSync('customers',$next,!$page['hasMore']);
            return['customers'=>count($page['data']),'payments'=>0,'complete'=>!$page['hasMore']&&$this->repository->syncCursor('payments')['complete'],'phase'=>'customers','next_offset'=>$next];
        }
        $payments=$this->repository->syncCursor('payments');
        if(!$payments['complete']){
            $page=$this->client->listPayments($payments['offset'],100);foreach($page['data']as$item)$this->repository->upsertPayment($item);
            $next=$payments['offset']+count($page['data']);$complete=!$page['hasMore'];$this->repository->advanceSync('payments',$next,$complete);
            return['customers'=>0,'payments'=>count($page['data']),'complete'=>$complete,'phase'=>'payments','next_offset'=>$next];
        }
        return['customers'=>0,'payments'=>0,'complete'=>true,'phase'=>'complete','next_offset'=>0];
    }
    /** @param callable(int,int):array<string,mixed> $fetch @param callable(array<string,mixed>):void $save */
    private function syncResource(callable$fetch,callable$save):int{$offset=0;$count=0;do{$page=$fetch($offset,100);foreach($page['data']as$item){$save($item);$count++;}$offset+=count($page['data']);$more=(bool)$page['hasMore'];}while($more&&$offset<100000);return$count;}
}
