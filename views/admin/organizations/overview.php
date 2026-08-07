<?php
$contractLabels=['draft'=>'Rascunho','sent'=>'Enviado','viewed'=>'Visualizado','signed'=>'Assinado','cancelled'=>'Cancelado'];
$billingLabels=['not_issued'=>'Não emitida','issuing'=>'Emitindo','issued'=>'Emitida','paid'=>'Paga','failed'=>'Falhou'];
$walletLabels=['not_configured'=>'Não configurada','pending'=>'Aguardando validação','validated'=>'Validada','invalid'=>'Inválida'];
$models=['fixed_plus_percentage'=>'Mensalidade + percentual','split_only'=>'Somente split'];
$latest=$contracts[0]??null;$site=null;
foreach($domains as$domain){if(($domain['purpose']??'')==='site'&&(int)($domain['is_primary']??0)===1){$site=$domain;break;}}
$organizationActive=($organization['status']??'')==='active';
$contractSigned=$latest!==null&&($latest['status']??'')==='signed';
$centralPercentage=$latest===null?0.0:(float)($latest['sales_fee_percentage']??0);
$franchisePercentage=max(0,100-$centralPercentage);
$splitRequired=$latest!==null&&$centralPercentage>0;
$walletReady=($organization['asaas_wallet_status']??'not_configured')==='validated'&&trim((string)($organization['asaas_wallet_id']??''))!=='';
$splitReady=!$splitRequired||($walletReady&&(int)($organization['split_enabled']??0)===1);
$monthlyRequired=$latest!==null&&($latest['commercial_model']??'')==='fixed_plus_percentage'&&(float)($latest['monthly_fixed_amount']??0)>0;
$recurringReady=!$monthlyRequired||!empty($latest['asaas_payment_link_url']);
$commercialActive=$latest!==null&&($latest['commercial_flow_status']??'pending')==='active';
$canActivate=$organizationActive&&$contractSigned&&$splitReady&&$recurringReady;
$contractStatus=$latest===null?'Sem contrato':($contractLabels[$latest['status']]??$latest['status']);
$billingStatus=$latest===null?'Aguardando contrato':($billingLabels[$latest['billing_issue_state']??'not_issued']??'Não configurada');
$steps=[
 ['label'=>'Cadastro da franquia','done'=>$organizationActive,'detail'=>$organizationActive?'Ativa':'Ative a franquia'],
 ['label'=>'Contrato comercial','done'=>$latest!==null,'detail'=>$latest===null?'Crie o primeiro contrato':'Contrato #'.(int)($latest['contract_number']??1)],
 ['label'=>'Assinatura','done'=>$contractSigned,'detail'=>$contractSigned?'Contrato assinado':'Aguardando assinatura'],
 ['label'=>'Wallet e split','done'=>$splitReady,'detail'=>$splitRequired?($splitReady?'Repasse configurado':'Valide a Wallet e ative o split'):'Contrato sem split'],
 ...($monthlyRequired?[['label'=>'Mensalidade recorrente','done'=>$recurringReady,'detail'=>$recurringReady?'Link pronto para envio':'Gere o link mensal']]:[]),
 ['label'=>'Operação comercial','done'=>$commercialActive,'detail'=>$commercialActive?'Ativa para novas vendas':'Aguardando ativação'],
];
?>
<div class="page-header"><div><p class="eyebrow">ADM Central · Franquia</p><h1><?= $escape((string)$organization['display_name']) ?></h1><p><?= $escape((string)$organization['legal_name']) ?> · <?= $escape((string)$organization['cnpj']) ?></p></div><div class="page-actions"><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/organizations"><i class="fa-solid fa-arrow-left"></i> Voltar</a><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/edit"><i class="fa-solid fa-pen"></i> Editar</a></div></div>
<?php if(!empty($message)):?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif;?><?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>

<div class="row g-3 mb-4">
  <div class="col-md-6 col-xl-3"><section class="metric-card h-100"><span><i class="fa-solid fa-building"></i> Cadastro</span><strong><?= $organizationActive?'Franquia ativa':'Franquia suspensa' ?></strong><small><?= $escape((string)($organization['manager_name']?:'Gestor não informado')) ?></small><a href="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/edit">Editar cadastro e marca</a></section></div>
  <div class="col-md-6 col-xl-3"><section class="metric-card h-100"><span><i class="fa-solid fa-right-to-bracket"></i> Acesso</span><strong>mundointer.com.br/<?= $escape((string)$organization['panel_slug']) ?></strong><small><?= $site?$escape((string)$site['host']):'Site público não configurado' ?></small><a href="<?= $escape($basePath) ?>/<?= $escape((string)$organization['panel_slug']) ?>" target="_blank" rel="noopener">Abrir login exclusivo</a></section></div>
  <div class="col-md-6 col-xl-3"><section class="metric-card h-100"><span><i class="fa-solid fa-file-signature"></i> Contrato</span><strong><?= $escape($contractStatus) ?></strong><small><?= $latest?'#'.(int)($latest['contract_number']??1).' · '.($models[$latest['commercial_model']]??'Modelo legado'):'Nenhum contrato vinculado' ?></small><?php if($latest):?><a href="<?= $escape($basePath) ?>/admin/franchise-contracts/<?= (int)$latest['id'] ?>">Abrir contrato atual</a><?php elseif($application):?><a href="<?= $escape($basePath) ?>/admin/franchise-applications/<?= (int)$application['id'] ?>/contracts/create">Criar primeiro contrato</a><?php endif;?></section></div>
  <div class="col-md-6 col-xl-3"><section class="metric-card h-100"><span><i class="fa-solid fa-wallet"></i> Financeiro</span><strong><?= $escape($billingStatus) ?></strong><small><?= $escape($walletLabels[$organization['asaas_wallet_status']??'not_configured']??'Pendente') ?> · Split <?= (int)($organization['split_enabled']??0)===1?'ativo':'inativo' ?></small><a href="#operacao-comercial">Configurar operação</a></section></div>
</div>

<section class="card mb-4" id="operacao-comercial">
 <div class="card-header"><div><p class="eyebrow">Implantação</p><h2>Checklist operacional</h2><p class="meta">Conclua os itens para liberar cobranças e repasses das novas vendas.</p></div><span class="badge"><?= count(array_filter($steps,static fn(array$s):bool=>$s['done'])) ?>/<?= count($steps) ?></span></div>
 <div class="p-4">
  <div class="row g-3 mb-4"><?php foreach($steps as$step):?><div class="col-md-6 col-xl"><div class="border rounded-3 p-3 h-100"><i class="fa-solid <?= $step['done']?'fa-circle-check text-success':'fa-clock text-warning' ?>"></i><strong class="d-block mt-2"><?= $escape($step['label']) ?></strong><small class="meta"><?= $escape($step['detail']) ?></small></div></div><?php endforeach;?></div>
  <?php if($latest!==null):?>
  <div class="row g-3 mb-4">
   <div class="col-md-4"><div class="metric-card h-100"><span>Comissão Mundo Inter</span><strong><?= number_format($centralPercentage,2,',','.') ?>%</strong><small>Percentual definido no contrato</small></div></div>
   <div class="col-md-4"><div class="metric-card h-100"><span>Repasse da franquia</span><strong><?= number_format($franchisePercentage,2,',','.') ?>%</strong><small>Aplicado às novas vendas com split</small></div></div>
   <div class="col-md-4"><div class="metric-card h-100"><span>Mensalidade</span><strong><?= $monthlyRequired?'R$ '.number_format((float)$latest['monthly_fixed_amount'],2,',','.'):'Sem mensalidade' ?></strong><small><?= $monthlyRequired?($recurringReady?'Link recorrente pronto':'Link ainda não gerado'):'Modelo somente percentual' ?></small></div></div>
  </div>
  <?php endif;?>

  <details class="border rounded-3 p-3 mb-3" <?= !$splitReady?'open':'' ?>><summary><strong><i class="fa-solid fa-wallet"></i> Wallet Asaas e split</strong> <span class="badge <?= $splitReady?'badge-success':'badge-warning' ?>"><?= $splitReady?'Pronto':'Pendente' ?></span></summary>
   <form class="form-grid mt-3" method="post" action="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/finance-inline"><?= $csrfField ?>
    <label class="form-span-2">Wallet ID da franquia<input maxlength="80" name="asaas_wallet_id" value="<?= $escape($organization['asaas_wallet_id']??'') ?>" placeholder="00000000-0000-0000-0000-000000000000"><small>Copie o identificador da conta no Asaas e confirme a situação após a conferência.</small></label>
    <label>Situação da validação<select name="asaas_wallet_status"><?php foreach($walletLabels as$value=>$label):?><option value="<?= $escape($value) ?>" <?= ($organization['asaas_wallet_status']??'not_configured')===$value?'selected':'' ?>><?= $escape($label) ?></option><?php endforeach;?></select></label>
    <label class="checkbox-row"><input type="checkbox" name="split_enabled" value="1" <?= !empty($organization['split_enabled'])?'checked':'' ?>> Ativar split nas novas vendas</label>
    <label class="form-span-2">Observações financeiras<textarea maxlength="500" rows="2" name="asaas_finance_notes"><?= $escape($organization['asaas_finance_notes']??'') ?></textarea></label>
    <div class="form-actions form-span-2"><button class="button button-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar e validar configuração</button><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/finance">Configuração avançada</a></div>
   </form>
  </details>

  <?php if($latest!==null):?><div class="border rounded-3 p-3"><div class="d-flex flex-wrap justify-content-between gap-3 align-items-center"><div><strong><i class="fa-solid fa-bolt"></i> Ações comerciais</strong><p class="meta mb-0"><?= $commercialActive?'A regra contratual já está ativa para as novas vendas.':'Ative somente depois de conferir assinatura, Wallet e percentuais.' ?></p></div><div class="page-actions">
   <?php if($monthlyRequired&&empty($latest['asaas_payment_link_url'])):?><form method="post" action="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/contracts/<?= (int)$latest['id'] ?>/recurring-link"><?= $csrfField ?><button class="button button-secondary" type="submit" <?= (!$contractSigned||!$asaasReady)?'disabled':'' ?>><i class="fa-solid fa-link"></i> Gerar link mensal</button></form><?php elseif($monthlyRequired):?><a class="button button-secondary" href="<?= $escape((string)$latest['asaas_payment_link_url']) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir link mensal</a><?php endif;?>
   <?php if(!$commercialActive):?><form method="post" action="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/contracts/<?= (int)$latest['id'] ?>/activate"><?= $csrfField ?><button class="button button-primary" type="submit" <?= !$canActivate?'disabled':'' ?>><i class="fa-solid fa-circle-play"></i> Ativar operação comercial</button></form><?php else:?><span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Operação ativa</span><?php endif;?>
  </div></div><?php if(!$canActivate&&!$commercialActive):?><div class="alert alert-warning mt-3 mb-0">Antes de ativar: <?= !$organizationActive?'ative a franquia; ':'' ?><?= !$contractSigned?'obtenha a assinatura do contrato; ':'' ?><?= !$splitReady?'valide a Wallet e habilite o split; ':'' ?><?= !$recurringReady?'gere o link da mensalidade; ':'' ?></div><?php endif;?></div><?php endif;?>
 </div>
</section>

<?php if($latest!==null&&$billingEvents!==[]):?><section class="card mb-4"><div class="card-header"><div><h2>Histórico operacional</h2><p class="meta">Últimos registros financeiros e comerciais do contrato atual.</p></div></div><div class="table-responsive"><table><thead><tr><th>Data</th><th>Evento</th><th>Responsável</th></tr></thead><tbody><?php foreach(array_slice($billingEvents,0,5)as$event):?><tr><td><?= $escape(date('d/m/Y H:i',strtotime((string)$event['created_at']))) ?></td><td><strong><?= $escape((string)$event['description']) ?></strong></td><td><?= $escape((string)($event['user_name']?:'Sistema')) ?></td></tr><?php endforeach;?></tbody></table></div></section><?php endif;?>

<section class="card"><div class="card-header"><div><h2>Histórico contratual</h2><p class="meta">Contratos, renovações, assinatura e situação financeira em uma única linha do tempo.</p></div><span class="badge"><?= count($contracts) ?></span></div>
<?php if($contracts===[]):?><div class="empty-state"><i class="fa-solid fa-file-signature"></i><strong>Nenhum contrato vinculado</strong></div><?php else:?><div class="table-responsive"><table><thead><tr><th>Contrato</th><th>Modelo</th><th>Vigência</th><th>Assinatura</th><th>Financeiro</th><th>Ação</th></tr></thead><tbody><?php foreach($contracts as$c):?><tr><td><strong>#<?= (int)($c['contract_number']??1) ?> · <?= $escape((string)$c['title']) ?></strong><small class="d-block meta"><?= ($c['contract_type']??'new')==='renewal'?'Renovação':'Novo contrato' ?></small></td><td><?= $escape($models[$c['commercial_model']]??'Legado') ?></td><td><?= $c['valid_from']?$escape(date('d/m/Y',strtotime((string)$c['valid_from']))):'Sem início' ?> — <?= $c['valid_until']?$escape(date('d/m/Y',strtotime((string)$c['valid_until']))):'indeterminada' ?></td><td><span class="badge <?= $c['status']==='signed'?'badge-success':'badge-warning' ?>"><?= $escape($contractLabels[$c['status']]??(string)$c['status']) ?></span></td><td><?= $escape($billingLabels[$c['billing_issue_state']??'not_issued']??'Não configurada') ?></td><td><a class="button button-secondary button-icon" title="Abrir contrato" href="<?= $escape($basePath) ?>/admin/franchise-contracts/<?= (int)$c['id'] ?>"><i class="fa-solid fa-eye"></i></a></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
