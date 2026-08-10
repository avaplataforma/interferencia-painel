<?php

declare(strict_types=1);

$orders=$siteOrders??[];
$showActions=(bool)($siteOrdersShowActions??false);
$panelTitle=(string)($siteOrdersTitle??'Pedidos recentes do site');
$panelDescription=(string)($siteOrdersDescription??'Compras iniciadas pelo Site Institucional e sua evolução até a matrícula.');
$fulfillmentLabels=[
    'awaiting_payment'=>'Aguardando pagamento',
    'payment_confirmed'=>'Pagamento confirmado',
    'manual_review'=>'Revisão necessária',
    'releasing'=>'Liberando no AVA',
    'released'=>'Liberado no AVA',
    'failed'=>'Falha na liberação',
];
$paidStatuses=['ACTIVE','PAID','COMPLETED','RECEIVED','CONFIRMED'];
?>
<style>
.site-orders-panel{margin-top:1.25rem;overflow:hidden}.site-orders-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.15rem 1.3rem;border-bottom:1px solid #e3e8ec}.site-orders-head h2,.site-orders-head p{margin:0}.site-orders-head h2{font-size:1.35rem}.site-orders-count{display:grid;place-items:center;min-width:2.25rem;height:2.25rem;border-radius:999px;background:#f1f4f6;font-weight:800}.site-order-name{display:grid;gap:.15rem}.site-order-name small{color:var(--inter-muted)}.site-order-status{display:grid;gap:.35rem;justify-items:start}.site-order-status small{max-width:22rem;color:#b4232e}.site-orders-empty{display:grid;place-items:center;gap:.45rem;min-height:9rem;padding:1.5rem;color:var(--inter-muted);text-align:center}.site-orders-empty i{font-size:1.65rem}.site-orders-footer{display:flex;justify-content:flex-end;padding:.9rem 1.3rem;border-top:1px solid #e3e8ec}@media(max-width:780px){.site-orders-head{align-items:flex-start}.site-orders-panel table{min-width:55rem}}
</style>
<section class="card site-orders-panel" id="site-orders">
 <header class="site-orders-head">
  <div><p class="eyebrow">Site Institucional</p><h2><?= $escape($panelTitle) ?></h2><p class="meta"><?= $escape($panelDescription) ?></p></div>
  <span class="site-orders-count" title="Pedidos exibidos"><?= count($orders) ?></span>
 </header>
 <?php if($orders===[]):?>
  <div class="site-orders-empty"><i class="fa-solid fa-bag-shopping"></i><strong>Nenhum pedido iniciado pelo site.</strong><span>Quando uma compra for criada, ela aparecerá aqui.</span></div>
 <?php else:?>
  <div class="table-responsive"><table><thead><tr><th>Data</th><th>Aluno</th><th>Curso e polo</th><th>Pagamento</th><th>Matrícula / AVA</th><?php if($showActions):?><th>Ações</th><?php endif;?></tr></thead><tbody>
  <?php foreach($orders as$order):$fulfillment=(string)($order['fulfillment_status']??'awaiting_payment');$paid=in_array((string)$order['status'],$paidStatuses,true);?>
   <tr>
    <td><?= $escape(date('d/m/Y H:i',strtotime((string)$order['created_at']))) ?></td>
    <td><span class="site-order-name"><strong><?= $escape($order['contact_name']??'Interessado') ?></strong><small><?= $escape($order['contact_email']??'') ?></small></span></td>
    <td><span class="site-order-name"><strong><?= $escape($order['product_name']) ?></strong><small><?= $escape($order['unit_name']) ?> · R$ <?= number_format((float)$order['value'],2,',','.') ?></small></span></td>
    <td><div class="site-order-status"><span class="badge <?= $paid?'badge-success':((string)$order['status']==='FAILED'?'badge-danger':'badge-warning') ?>"><?= $paid?'Confirmado':$escape((string)$order['status']) ?></span><?php if(!empty($order['error_message'])):?><small><?= $escape($order['error_message']) ?></small><?php endif;?></div></td>
    <td><div class="site-order-status"><span class="badge <?= $fulfillment==='released'?'badge-success':($fulfillment==='failed'?'badge-danger':'badge-warning') ?>"><?= $escape($fulfillmentLabels[$fulfillment]??$fulfillment) ?></span><?php if(!empty($order['fulfillment_error'])):?><small><?= $escape($order['fulfillment_error']) ?></small><?php endif;?></div></td>
    <?php if($showActions):?><td><div class="action-group"><?php if(!empty($order['link'])):?><a class="button button-secondary button-icon" href="<?= $escape($order['link']) ?>" target="_blank" title="Abrir checkout"><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif;?><?php if(in_array($fulfillment,['manual_review','failed'],true)&&!empty($order['student_enrollment_id'])):?><form method="post" action="<?= $escape($basePath) ?>/admin/site/orders/<?= (int)$order['id'] ?>/release" onsubmit="return confirm('Liberar esta matrícula no AVA agora?')"><?= $csrfField ?><button class="button button-primary button-icon" type="submit" title="Liberar no AVA"><i class="fa-solid fa-graduation-cap"></i></button></form><?php endif;?></div></td><?php endif;?>
   </tr>
  <?php endforeach;?>
  </tbody></table></div>
 <?php endif;?>
 <?php if(!empty($siteOrdersViewAllUrl)):?><footer class="site-orders-footer"><a class="button-secondary" href="<?= $escape($siteOrdersViewAllUrl) ?>">Ver pedidos e matrículas <i class="fa-solid fa-arrow-right"></i></a></footer><?php endif;?>
</section>
