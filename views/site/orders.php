<?php

declare(strict_types=1);

$statusLabels=[
    'awaiting_payment'=>'Aguardando pagamento',
    'payment_confirmed'=>'Pagamento confirmado',
    'manual_review'=>'Revisão necessária',
    'releasing'=>'Liberando no AVA',
    'released'=>'Liberado no AVA',
    'failed'=>'Falha no processamento',
];
$paidStatuses=['PAID','COMPLETED','RECEIVED','CONFIRMED','RECEIVED_IN_CASH'];
?>
<style>
.order-ops{max-width:96rem;margin:0 auto}.order-ops-head{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:1rem}.order-ops-head h1,.order-ops-head p{margin:.15rem 0}.order-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem;margin-bottom:1rem}.order-metric{display:flex;align-items:center;gap:.8rem;min-height:6rem;padding:1rem;border:1px solid #dfe5e9;border-radius:.9rem;background:#fff}.order-metric i{display:grid;place-items:center;width:2.6rem;height:2.6rem;flex:0 0 auto;border-radius:.7rem;color:var(--inter-accent);background:#fff0f1}.order-metric span,.order-metric strong{display:block}.order-metric span{color:var(--inter-muted);font-size:.82rem}.order-metric strong{font-size:1.45rem}.order-filter{display:grid!important;grid-template-columns:minmax(18rem,1fr) minmax(13rem,.45fr) auto auto;gap:.65rem;align-items:end;max-width:none!important;margin:0 0 1rem;padding:1rem;border:1px solid #dfe5e9;border-radius:.9rem;background:#fff}.order-filter label{margin:0}.order-filter input,.order-filter select,.order-filter button,.order-filter a{min-height:3rem;margin:0}.order-table-card{overflow:hidden}.order-person,.order-course,.order-state{display:grid;gap:.18rem}.order-person small,.order-course small{color:var(--inter-muted)}.order-state .badge{width:max-content}.order-error{display:block;max-width:24rem;color:#b4232e;font-size:.78rem}.order-actions{display:flex;align-items:center;flex-wrap:wrap;gap:.4rem}.order-actions form{margin:0}.order-details{margin-top:.45rem}.order-details summary{width:max-content;color:var(--inter-muted);font-size:.76rem;cursor:pointer}.order-diagnostics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.25rem .7rem;margin-top:.45rem;padding:.6rem;border-radius:.55rem;background:#f6f8fa;font-size:.75rem}.order-diagnostics span{overflow-wrap:anywhere}.order-empty{display:grid;place-items:center;gap:.4rem;min-height:14rem;color:var(--inter-muted);text-align:center}.order-empty i{font-size:2rem}.order-help{display:flex;align-items:flex-start;gap:.7rem;margin-top:1rem;padding:.9rem;border:1px solid #c9d9e5;border-radius:.75rem;color:#415c70;background:#f3f8fb}.order-help p{margin:0}@media(max-width:900px){.order-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.order-filter{grid-template-columns:1fr 1fr}.order-table-card table{min-width:68rem}}@media(max-width:600px){.order-ops-head{align-items:flex-start;flex-direction:column}.order-metrics,.order-filter{grid-template-columns:1fr}}
</style>
<div class="order-ops">
 <header class="order-ops-head"><div><p class="eyebrow">Alunos · Site Institucional</p><h1>Pedidos do site</h1><p class="meta">Acompanhe pagamento, matrícula e liberação no AVA em um único fluxo.</p></div><a class="button-secondary" href="<?= $escape($basePath) ?>/students/enrollments"><i class="fa-solid fa-graduation-cap"></i> Ver matrículas</a></header>
 <?php if(!empty($message)):?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif;?>
 <?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>
 <section class="order-metrics" aria-label="Resumo dos pedidos">
  <article class="order-metric"><i class="fa-solid fa-bag-shopping"></i><div><span>Total de pedidos</span><strong><?= number_format((int)$summary['total'],0,',','.') ?></strong></div></article>
  <article class="order-metric"><i class="fa-regular fa-clock"></i><div><span>Aguardando pagamento</span><strong><?= number_format((int)$summary['awaiting_payment'],0,',','.') ?></strong></div></article>
  <article class="order-metric"><i class="fa-solid fa-triangle-exclamation"></i><div><span>Exigem atenção</span><strong><?= number_format((int)$summary['attention'],0,',','.') ?></strong></div></article>
  <article class="order-metric"><i class="fa-solid fa-circle-check"></i><div><span>Liberados no AVA</span><strong><?= number_format((int)$summary['released'],0,',','.') ?></strong></div></article>
 </section>
 <form class="order-filter" method="get" action="<?= $escape($basePath) ?>/students/site-orders">
  <label>Buscar pedido<input name="q" value="<?= $escape($filters['q']) ?>" placeholder="Aluno, e-mail, curso, polo ou referência"></label>
  <label>Situação<select name="status"><option value="">Todas as situações</option><option value="awaiting_payment" <?= $filters['status']==='awaiting_payment'?'selected':'' ?>>Aguardando pagamento</option><option value="attention" <?= $filters['status']==='attention'?'selected':'' ?>>Exigem atenção</option><option value="failed" <?= $filters['status']==='failed'?'selected':'' ?>>Somente falhas</option><option value="released" <?= $filters['status']==='released'?'selected':'' ?>>Liberados no AVA</option></select></label>
  <button class="button-primary" type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button><a class="button-secondary" href="<?= $escape($basePath) ?>/students/site-orders">Limpar</a>
 </form>
 <section class="card order-table-card">
 <?php if($orders===[]):?><div class="order-empty"><i class="fa-solid fa-inbox"></i><strong>Nenhum pedido encontrado.</strong><span>Ajuste os filtros ou aguarde a primeira compra pelo site.</span></div><?php else:?><div class="table-responsive"><table><thead><tr><th>Pedido</th><th>Aluno</th><th>Curso e polo</th><th>Pagamento</th><th>Matrícula / AVA</th><th>Ações</th></tr></thead><tbody>
 <?php foreach($orders as$order):$fulfillment=(string)$order['fulfillment_status'];$paid=in_array(strtoupper((string)$order['status']),$paidStatuses,true);$attention=in_array($fulfillment,['manual_review','failed'],true);?>
  <tr>
   <td><strong>#<?= (int)$order['id'] ?></strong><br><small><?= $escape(date('d/m/Y H:i',strtotime((string)$order['created_at']))) ?></small></td>
   <td><span class="order-person"><strong><?= $escape($order['contact_name']?:($order['finance_customer_name']?:'Interessado')) ?></strong><small><?= $escape($order['contact_email']?:'Sem e-mail informado') ?></small></span></td>
   <td><span class="order-course"><strong><?= $escape($order['product_name']) ?></strong><small><?= $escape($order['unit_name']) ?> · R$ <?= number_format((float)$order['value'],2,',','.') ?></small></span></td>
   <td><span class="order-state"><span class="badge <?= $paid?'badge-success':((string)$order['status']==='FAILED'?'badge-danger':'badge-warning') ?>"><?= $paid?'Confirmado':$escape((string)$order['status']) ?></span><?php if(!empty($order['error_message'])):?><small class="order-error"><?= $escape($order['error_message']) ?></small><?php endif;?></span></td>
   <td><span class="order-state"><span class="badge <?= $fulfillment==='released'?'badge-success':($fulfillment==='failed'?'badge-danger':'badge-warning') ?>"><?= $escape($statusLabels[$fulfillment]??$fulfillment) ?></span><?php if(!empty($order['ava_connection_name'])):?><small><?= $escape($order['ava_connection_name']) ?></small><?php endif;?><?php if(!empty($order['fulfillment_error'])):?><small class="order-error"><?= $escape($order['fulfillment_error']) ?></small><?php endif;?></span></td>
   <td><div class="order-actions"><?php if(!empty($order['link'])):?><a class="button-secondary button-small button-icon" href="<?= $escape($order['link']) ?>" target="_blank" title="Abrir checkout"><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif;?><?php if(!empty($order['finance_customer_id'])):?><a class="button-secondary button-small button-icon" href="<?= $escape($basePath) ?>/finance/customers/<?= (int)$order['finance_customer_id'] ?>" title="Abrir cadastro do aluno"><i class="fa-solid fa-user-graduate"></i></a><?php endif;?><?php if($attention):?><form method="post" action="<?= $escape($basePath) ?>/students/site-orders/<?= (int)$order['id'] ?>/retry" data-confirm-submit="Reprocessar este pedido agora? O pagamento será apenas consultado, nunca recriado."><?= $csrfField ?><button class="button-primary button-small" type="submit"><i class="fa-solid fa-rotate"></i> Reprocessar</button></form><?php endif;?></div>
    <details class="order-details"><summary>Ver diagnóstico</summary><div class="order-diagnostics"><span><strong>Checkout</strong><br><?= $escape($order['asaas_checkout_id']?:'não criado') ?></span><span><strong>Referência</strong><br><?= $escape($order['external_reference']) ?></span><span><strong>Aluno financeiro</strong><br><?= (int)($order['finance_customer_id']??0)>0?'vinculado':'pendente' ?></span><span><strong>Matrícula</strong><br><?= (int)($order['student_enrollment_id']??0)>0?'#'.(int)$order['student_enrollment_id']:'pendente' ?></span></div></details>
   </td>
  </tr>
 <?php endforeach;?>
 </tbody></table></div><?php endif;?>
 </section>
 <aside class="order-help"><i class="fa-solid fa-shield-halved"></i><p><strong>Reprocessamento seguro:</strong> o Painel consulta o checkout existente no Asaas e continua somente quando o pagamento já estiver confirmado. Nenhuma cobrança nova é criada por esta ação.</p></aside>
</div>
