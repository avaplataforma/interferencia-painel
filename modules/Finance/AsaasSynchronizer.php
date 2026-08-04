<?php

declare(strict_types=1);

namespace Interferencia\Modules\Finance;

final readonly class AsaasSynchronizer
{
    public function __construct(private AsaasClient $client,private FinanceRepository $repository){}
    /** @return array{customers:int,payments:int,subscriptions:int} */
    public function sync():array
    {
        $customers=$this->syncResource(fn(int$o,int$l):array=>$this->client->listCustomers($o,$l),fn(array$i)=>$this->repository->upsertCustomer($i));
        $payments=$this->syncResource(fn(int$o,int$l):array=>$this->client->listPayments($o,$l),fn(array$i)=>$this->repository->upsertPayment($i));
        $subscriptions=$this->syncResource(fn(int$o,int$l):array=>$this->client->listSubscriptions($o,$l),fn(array$i)=>$this->repository->upsertSubscription($i));
        return['customers'=>$customers,'payments'=>$payments,'subscriptions'=>$subscriptions];
    }
    /** @return array{customers:int,payments:int,subscriptions:int,complete:bool,phase:string,next_offset:int} */
    public function syncBatch():array
    {
        $customers=$this->repository->syncCursor('customers');
        if(!$customers['complete']){
            $probe=$this->client->listCustomers(0,1);if($probe['totalCount']>0&&$this->repository->localResourceCount('customers')>=$probe['totalCount']){$this->repository->advanceSync('customers',$probe['totalCount'],true);$customers=['offset'=>$probe['totalCount'],'complete'=>true];}
        }
        if(!$customers['complete']){
            $page=$this->client->listCustomers($customers['offset'],100);foreach($page['data']as$item)$this->repository->upsertCustomer($item);
            $next=$customers['offset']+count($page['data']);$this->repository->advanceSync('customers',$next,!$page['hasMore']);
            return['customers'=>count($page['data']),'payments'=>0,'subscriptions'=>0,'complete'=>false,'phase'=>'customers','next_offset'=>$next];
        }
        $payments=$this->repository->syncCursor('payments');
        if(!$payments['complete']){
            $probe=$this->client->listPayments(0,1);if($probe['totalCount']>0&&$this->repository->localResourceCount('payments')>=$probe['totalCount']){$this->repository->advanceSync('payments',$probe['totalCount'],true);$payments=['offset'=>$probe['totalCount'],'complete'=>true];}
        }
        if(!$payments['complete']){
            $page=$this->client->listPayments($payments['offset'],100);foreach($page['data']as$item)$this->repository->upsertPayment($item);
            $next=$payments['offset']+count($page['data']);$complete=!$page['hasMore'];$this->repository->advanceSync('payments',$next,$complete);
            return['customers'=>0,'payments'=>count($page['data']),'subscriptions'=>0,'complete'=>false,'phase'=>'payments','next_offset'=>$next];
        }
        $subscriptions=$this->repository->syncCursor('subscriptions');
        if(!$subscriptions['complete']){
            $page=$this->client->listSubscriptions($subscriptions['offset'],100);foreach($page['data']as$item)$this->repository->upsertSubscription($item);
            $next=$subscriptions['offset']+count($page['data']);$complete=!$page['hasMore'];$this->repository->advanceSync('subscriptions',$next,$complete);
            return['customers'=>0,'payments'=>0,'subscriptions'=>count($page['data']),'complete'=>$complete,'phase'=>'subscriptions','next_offset'=>$next];
        }
        return['customers'=>0,'payments'=>0,'subscriptions'=>0,'complete'=>true,'phase'=>'complete','next_offset'=>0];
    }
    /** @param callable(int,int):array<string,mixed> $fetch @param callable(array<string,mixed>):void $save */
    private function syncResource(callable$fetch,callable$save):int{$offset=0;$count=0;do{$page=$fetch($offset,100);foreach($page['data']as$item){$save($item);$count++;}$offset+=count($page['data']);$more=(bool)$page['hasMore'];}while($more&&$offset<100000);return$count;}
}
