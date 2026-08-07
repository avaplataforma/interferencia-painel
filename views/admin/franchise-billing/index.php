<?php
$models=['fixed_plus_percentage'=>'Mensal + percentual','split_only'=>'Somente split'];
$states=['not_issued'=>'Não emitida','issuing'=>'Processando','issued'=>'Emitida','paid'=>'Paga','failed'=>'Falhou'];
$splitStates=['prepared'=>'Preparando','submitted'=>'Enviado','failed'=>'Falhou'];
$money=static fn(mixed$value):string=>'R$ '.number_format((float)$value,2,',','.');
$pendingTotal=(int)($summary['pending_activation']??0)+(int)($summary['split_pending']??0)+(int)($summary['failures']??0)+(int)($summary['split_failures']??0);
$hasFilters=(int)$filters['organization_id']>0||$filters['status']!==''||$filters['from']!==''||$filters['to']!=='';
?>
<style>
.franchise-finance-page{width:min(96rem,calc(100vw - 19rem));max-width:none;position:relative;left:50%;transform:translateX(-50%)}
.franchise-finance-header{align-items:center;margin-bottom:1.25rem}.franchise-finance-header h1{margin-bottom:.25rem}
.franchise-finance-metrics{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.85rem;margin-bottom:1rem}.franchise-finance-metrics .metric-card{min-height:7.25rem}
.franchise-finance-metrics .metric-card>span{display:flex;align-items:center;gap:.4rem}.franchise-finance-metrics .metric-card>span i{color:var(--inter-accent)}
.franchise-finance-toolbar{display:grid;grid-template-columns:minmax(15rem,2fr) minmax(12rem,1.25fr) repeat(2,minmax(10rem,1fr)) auto;gap:.85rem;align-items:end;padding:1rem;margin-bottom:1rem}
.franchise-finance-toolbar label{margin:0}.franchise-finance-toolbar select,.franchise-finance-toolbar input{width:100%}
.franchise-finance-toolbar-actions{display:flex;gap:.5rem;align-items:center}.franchise-finance-toolbar-actions .button{height:2.85rem;white-space:nowrap}.franchise-finance-toolbar-actions .button-icon{width:2.85rem}
.franchise-finance-notice{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem}.franchise-finance-notice p{margin:0}
.franchise-finance-section .card-header{align-items:center}.franchise-finance-section .card-header h2{margin-bottom:.2rem}
.franchise-finance-table th{white-space:nowrap}.franchise-finance-table td{vertical-align:middle}.franchise-finance-table .billing-main{min-width:11rem}.franchise-finance-table .billing-contract{min-width:13rem}.franchise-finance-table .billing-actions{min-width:8.5rem}
.franchise-finance-table .page-actions{justify-content:flex-start;flex-wrap:nowrap}.franchise-finance-table .button-icon{flex:0 0 2.65rem}
@media(max-width:1399.98px){.franchise-finance-page{width:100%;left:auto;transform:none}.franchise-finance-metrics{grid-template-columns:repeat(3,minmax(0,1fr))}.franchise-finance-toolbar{grid-template-columns:repeat(2,minmax(0,1fr))}.franchise-finance-toolbar-actions{grid-column:1/-1;justify-content:flex-end}}
@media(max-width:767.98px){.franchise-finance-metrics{grid-template-columns:1fr 1fr}.franchise-finance-toolbar{grid-template-columns:1fr}.franchise-finance-toolbar-actions{grid-column:auto;justify-content:stretch}.franchise-finance-toolbar-actions .button-primary{flex:1}.franchise-finance-notice{align-items:flex-start;flex-direction:column}}
@media(max-width:479.98px){.franchise-finance-metrics{grid-template-columns:1fr}}
</style>

<div class="franchise-finance-page">
 <div class="page-header franchise-finance-header">
  <div><p class="eyebrow">ADM Central · Rede</p><h1>Financeiro das franquias</h1><p>Mensalidades, comissões, repasses e pendências de toda a rede em uma única visão.</p></div>
  <div class="page-actions"><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/platform/integrations/asaas"><i class="fa-solid fa-plug"></i> Integração Asaas</a></div>
 </div>
 <?php if(!empty($message)):?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif;?>
 <?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>

 <section class="franchise-finance-metrics" aria-label="Resumo financeiro">
  <article class="metric-card"><span><i class="fa-solid fa-sack-dollar"></i> Recebido</span><strong><?= $money($summary['paid_amount']??0) ?></strong><small><?= (int)($summary['paid']??0) ?> cobrança(s) paga(s)</small></article>
  <article class="metric-card"><span><i class="fa-solid fa-hourglass-half"></i> Em aberto</span><strong><?= $money($summary['open_amount']??0) ?></strong><small>Cobranças emitidas ou pendentes</small></article>
  <article class="metric-card"><span><i class="fa-solid fa-triangle-exclamation"></i> Vencido</span><strong><?= $money($summary['overdue_amount']??0) ?></strong><small><?= (int)($summary['overdue']??0) ?> mensalidade(s)</small></article>
  <article class="metric-card"><span><i class="fa-solid fa-percent"></i> Comissão Mundo Inter</span><strong><?= $money($summary['central_commission_amount']??0) ?></strong><small>Splits enviados no período</small></article>
  <article class="metric-card"><span><i class="fa-solid fa-money-bill-transfer"></i> Repasses</span><strong><?= $money($summary['franchise_transfer_amount']??0) ?></strong><small><?= (int)($summary['split_submitted']??0) ?> split(s) enviado(s)</small></article>
 </section>

 <form class="card franchise-finance-toolbar" method="get" action="<?= $escape($basePath) ?>/admin/franchise-billing">
  <label>Franquia<select name="organization"><option value="0">Todas as franquias</option><?php foreach($organizations as$organization):?><option value="<?= (int)$organization['id'] ?>" <?= (int)$filters['organization_id']===(int)$organization['id']?'selected':'' ?>><?= $escape((string)$organization['display_name']) ?></option><?php endforeach;?></select></label>
  <label>Situação<select name="status"><option value="">Todas as situações</option><option value="pending" <?= $filters['status']==='pending'?'selected':'' ?>>Pendências</option><option value="active" <?= $filters['status']==='active'?'selected':'' ?>>Operação ativa</option><option value="paid" <?= $filters['status']==='paid'?'selected':'' ?>>Pagas</option><option value="overdue" <?= $filters['status']==='overdue'?'selected':'' ?>>Vencidas</option><option value="failed" <?= $filters['status']==='failed'?'selected':'' ?>>Com falha</option></select></label>
  <label>Período inicial<input type="date" name="from" value="<?= $escape((string)$filters['from']) ?>"></label>
  <label>Período final<input type="date" name="to" value="<?= $escape((string)$filters['to']) ?>"></label>
  <div class="franchise-finance-toolbar-actions"><button class="button button-primary" type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button><?php if($hasFilters):?><a class="button button-secondary button-icon" title="Limpar filtros" aria-label="Limpar filtros" href="<?= $escape($basePath) ?>/admin/franchise-billing"><i class="fa-solid fa-rotate-left"></i></a><?php endif;?></div>
 </form>

 <div class="alert <?= $pendingTotal>0?'alert-warning':'alert-success' ?> franchise-finance-notice"><p><strong><i class="fa-solid <?= $pendingTotal>0?'fa-bell':'fa-circle-check' ?>"></i> <?= $pendingTotal>0?$pendingTotal.' pendência(s) operacional(is).':'Operação financeira regular.' ?></strong> <?= $pendingTotal>0?'Revise contratos, Wallets e falhas antes das próximas vendas.':'Nenhuma pendência encontrada para o filtro atual.' ?></p><?php if($pendingTotal>0):?><a href="<?= $escape($basePath) ?>/admin/franchise-billing?status=pending">Ver somente pendências</a><?php endif;?></div>

 <section class="card mb-4 franchise-finance-section"><div class="card-header"><div><p class="eyebrow">Operação recorrente</p><h2>Contratos e mensalidades</h2><p class="meta">Situação financeira e comercial por franquia.</p></div><span class="badge"><?= count($contracts) ?> contrato(s)</span></div>
  <div class="table-responsive"><table class="franchise-finance-table"><thead><tr><th>Franquia</th><th>Contrato</th><th>Modelo comercial</th><th>Mensalidade</th><th>Comissão</th><th>Wallet</th><th>Cobrança</th><th>Operação</th><th>Ações</th></tr></thead><tbody><?php foreach($contracts as$item):$walletRequired=(float)($item['sales_fee_percentage']??0)>0;$walletReady=!$walletRequired||(($item['asaas_wallet_status']??'')==='validated'&&!empty($item['asaas_wallet_id'])&&(int)($item['split_enabled']??0)===1);?><tr>
   <td class="billing-main"><strong><?= $escape((string)$item['franchise_name']) ?></strong><small class="d-block meta">Franquia #<?= (int)$item['organization_id'] ?></small></td>
   <td class="billing-contract"><strong>#<?= (int)$item['contract_number'] ?> · <?= $escape((string)$item['title']) ?></strong></td>
   <td><?= $escape($models[$item['commercial_model']]??'Legado') ?></td>
   <td><strong><?= (float)$item['monthly_fixed_amount']>0?$money($item['monthly_fixed_amount']):'—' ?></strong><?php if(!empty($item['billing_due_date'])):?><small class="d-block meta">Vence <?= $escape(date('d/m/Y',strtotime((string)$item['billing_due_date']))) ?></small><?php endif;?></td>
   <td><strong><?= number_format((float)$item['sales_fee_percentage'],2,',','.') ?>%</strong></td>
   <td><span class="badge <?= $walletReady?'badge-success':'badge-warning' ?>"><?= $walletReady?'Pronta':'Pendente' ?></span></td>
   <td><span class="badge <?= $item['billing_issue_state']==='paid'?'badge-success':($item['billing_issue_state']==='failed'?'badge-danger':'badge-warning') ?>"><?= $escape($states[$item['billing_issue_state']]??$item['billing_issue_state']) ?></span></td>
   <td><span class="badge <?= $item['commercial_flow_status']==='active'?'badge-success':'badge-warning' ?>"><?= $item['commercial_flow_status']==='active'?'Ativa':'Pendente' ?></span></td>
   <td class="billing-actions"><div class="page-actions"><a class="button button-secondary button-icon" title="Abrir ficha da franquia" href="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$item['organization_id'] ?>"><i class="fa-solid fa-building"></i></a><a class="button button-secondary button-icon" title="Abrir contrato" href="<?= $escape($basePath) ?>/admin/franchise-contracts/<?= (int)$item['id'] ?>"><i class="fa-solid fa-file-signature"></i></a><?php if(!empty($item['asaas_invoice_url'])):?><a class="button button-secondary button-icon" title="Abrir cobrança" href="<?= $escape((string)$item['asaas_invoice_url']) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif;?></div></td>
  </tr><?php endforeach;?><?php if($contracts===[]):?><tr><td colspan="9"><div class="empty-state"><i class="fa-solid fa-magnifying-glass"></i><strong>Nenhum contrato encontrado</strong><span>Ajuste os filtros para ampliar a consulta.</span></div></td></tr><?php endif;?></tbody></table></div>
 </section>

 <section class="card franchise-finance-section" id="repasses"><div class="card-header"><div><p class="eyebrow">Movimentações</p><h2>Histórico de splits e repasses</h2><p class="meta">Últimas divisões de pagamento processadas pelas novas vendas.</p></div><span class="badge"><?= count($splits) ?> registro(s)</span></div>
  <div class="table-responsive"><table class="franchise-finance-table"><thead><tr><th>Data</th><th>Franquia</th><th>Venda</th><th>Mundo Inter</th><th>Franquia</th><th>Situação</th><th>Referência</th></tr></thead><tbody><?php foreach($splits as$split):?><tr><td><?= $escape(date('d/m/Y H:i',strtotime((string)$split['created_at']))) ?></td><td class="billing-main"><strong><?= $escape((string)$split['franchise_name']) ?></strong><small class="d-block meta">Contrato #<?= (int)$split['contract_number'] ?></small></td><td><strong><?= $money($split['gross_value']) ?></strong></td><td><strong><?= $money((float)$split['gross_value']*(float)$split['central_percentage']/100) ?></strong><small class="d-block meta"><?= number_format((float)$split['central_percentage'],2,',','.') ?>%</small></td><td><strong><?= $money((float)$split['gross_value']*(float)$split['franchise_percentage']/100) ?></strong><small class="d-block meta"><?= number_format((float)$split['franchise_percentage'],2,',','.') ?>%</small></td><td><span class="badge <?= $split['status']==='submitted'?'badge-success':($split['status']==='failed'?'badge-danger':'badge-warning') ?>"><?= $escape($splitStates[$split['status']]??(string)$split['status']) ?></span><?php if(!empty($split['error_message'])):?><small class="d-block text-danger"><?= $escape((string)$split['error_message']) ?></small><?php endif;?></td><td><small><?= $escape((string)$split['external_reference']) ?></small></td></tr><?php endforeach;?><?php if($splits===[]):?><tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-money-bill-transfer"></i><strong>Nenhum split processado neste período</strong></div></td></tr><?php endif;?></tbody></table></div>
 </section>
</div>
