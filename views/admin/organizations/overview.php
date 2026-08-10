<?php
$contractLabels=['draft'=>'Rascunho','sent'=>'Enviado','viewed'=>'Visualizado','signed'=>'Assinado','cancelled'=>'Cancelado'];
$billingLabels=['not_issued'=>'Não emitida','issuing'=>'Emitindo','issued'=>'Emitida','paid'=>'Paga','failed'=>'Falhou'];
$walletLabels=['not_configured'=>'Não configurada','pending'=>'Aguardando validação','validated'=>'Validada','invalid'=>'Inválida'];
$rules=['percentage_commission'=>'Comissão percentual','fixed_monthly'=>'Mensalidade fixa','hybrid'=>'Mensalidade + comissão','per_enrollment'=>'Valor por matrícula'];
$processingLabels=['central_monthly_settlement'=>'Repasse mensal','central_automatic_split'=>'Split automático','franchise_asaas'=>'Asaas da franquia'];
$latest=$contracts[0]??null;$site=null;
foreach($domains as$domain){if(($domain['purpose']??'')==='site'&&(int)($domain['is_primary']??0)===1){$site=$domain;break;}}
$organizationActive=($organization['status']??'')==='active';
$contractSigned=$latest!==null&&($latest['status']??'')==='signed';
$commercialRule=$latest===null?'':trim((string)($latest['commercial_rule']??''));
if($latest!==null&&$commercialRule==='')$commercialRule=($latest['commercial_model']??'')==='fixed_plus_percentage'?((float)($latest['sales_fee_percentage']??0)>0?'hybrid':'fixed_monthly'):'percentage_commission';
$financialProcessing=$latest===null?'':trim((string)($latest['financial_processing']??''));
if($latest!==null&&$financialProcessing==='')$financialProcessing=(float)($latest['sales_fee_percentage']??0)>0?'central_automatic_split':'central_monthly_settlement';
$centralPercentage=$latest===null?0.0:(float)($latest['sales_fee_percentage']??0);
$franchisePercentage=$latest===null?0.0:(float)($latest['franchise_fee_percentage']??0);if($centralPercentage>0&&$franchisePercentage<=0)$franchisePercentage=max(0,100-$centralPercentage);
$splitRequired=$latest!==null&&$financialProcessing==='central_automatic_split';
$walletReady=($organization['asaas_wallet_status']??'not_configured')==='validated'&&trim((string)($organization['asaas_wallet_id']??''))!=='';
$splitReady=!$splitRequired||($walletReady&&(int)($organization['split_enabled']??0)===1);
$monthlyRequired=$latest!==null&&(float)($latest['monthly_fixed_amount']??0)>0;
$recurringReady=!$monthlyRequired||!empty($latest['asaas_payment_link_url']);
$commercialActive=$latest!==null&&($latest['commercial_flow_status']??'pending')==='active';
$financeStep=null;foreach(($implementation['required_steps']??[])as$step){if(($step['id']??'')==='financeiro'){$financeStep=$step;break;}}
$financeReady=(bool)($financeStep['done']??false);
$canActivateCommercial=$organizationActive&&$contractSigned&&$financeReady;
$contractStatus=$latest===null?'Sem contrato':($contractLabels[$latest['status']]??$latest['status']);
$billingStatus=$latest===null?'Aguardando contrato':($billingLabels[$latest['billing_issue_state']??'not_issued']??'Não configurada');
?>
<div class="page-header"><div><p class="eyebrow">ADM Central · Franquia</p><h1><?= $escape((string)$organization['display_name']) ?></h1><p><?= $escape((string)$organization['legal_name']) ?> · <?= $escape((string)$organization['cnpj']) ?></p></div><div class="page-actions"><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/organizations"><i class="fa-solid fa-arrow-left"></i> Voltar</a><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/edit#documentos"><i class="fa-solid fa-folder-open"></i> Documentos</a><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/edit"><i class="fa-solid fa-pen"></i> Editar</a></div></div>
<?php if(!empty($message)):?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif;?><?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>

<div class="row g-3 mb-4">
  <div class="col-md-6 col-xl-3"><section class="metric-card h-100"><span><i class="fa-solid fa-building"></i> Cadastro</span><strong><?= $organizationActive?'Franquia ativa':'Franquia suspensa' ?></strong><small><?= $escape((string)($organization['manager_name']?:'Gestor não informado')) ?></small><a href="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/edit">Editar cadastro e marca</a></section></div>
  <div class="col-md-6 col-xl-3"><section class="metric-card h-100"><span><i class="fa-solid fa-right-to-bracket"></i> Acesso</span><strong>mundointer.com.br/<?= $escape((string)$organization['panel_slug']) ?></strong><small><?= $site?$escape((string)$site['host']):'Site público não configurado' ?></small><a href="<?= $escape($basePath) ?>/<?= $escape((string)$organization['panel_slug']) ?>" target="_blank" rel="noopener">Abrir login exclusivo</a></section></div>
  <div class="col-md-6 col-xl-3"><section class="metric-card h-100"><span><i class="fa-solid fa-file-signature"></i> Contrato</span><strong><?= $escape($contractStatus) ?></strong><small><?= $latest?'#'.(int)($latest['contract_number']??1).' · '.($rules[$commercialRule]??'Modelo legado'):'Nenhum contrato vinculado' ?></small><?php if($latest):?><a href="<?= $escape($basePath) ?>/admin/franchise-contracts/<?= (int)$latest['id'] ?>">Abrir contrato atual</a><?php elseif($application):?><a href="<?= $escape($basePath) ?>/admin/franchise-applications/<?= (int)$application['id'] ?>/contracts/create">Criar primeiro contrato</a><?php endif;?></section></div>
  <div class="col-md-6 col-xl-3"><section class="metric-card h-100"><span><i class="fa-solid fa-wallet"></i> Financeiro</span><strong><?= $escape($billingStatus) ?></strong><small><?= $escape($processingLabels[$financialProcessing]??'Aguardando contrato') ?></small><a href="#operacao-comercial">Configurar operação</a></section></div>
</div>

<section class="card mb-4" id="implantacao">
 <div class="card-header"><div><p class="eyebrow">Fluxo guiado</p><h2>Implantação da franquia</h2><p class="meta">Uma visão única do cadastro, contrato, financeiro, acessos, AVA e identidade.</p></div><div class="implementation-score"><strong><?= (int)$implementation['progress'] ?>%</strong><span class="badge <?= $implementation['ready_to_activate']?'badge-success':'badge-warning' ?>"><?= (int)$implementation['required_done'] ?>/<?= (int)$implementation['required_total'] ?> obrigatórios</span></div></div>
 <div class="p-4">
  <div class="implementation-progress mb-4" role="progressbar" aria-valuenow="<?= (int)$implementation['progress'] ?>" aria-valuemin="0" aria-valuemax="100"><span style="width:<?= (int)$implementation['progress'] ?>%"></span></div>
  <?php $implementationSections=[
   ['title'=>'Essencial para ativar','description'=>'Estes itens protegem a operação e bloqueiam a ativação enquanto estiverem pendentes.','steps'=>$implementation['required_steps'],'recommended'=>false],
   ['title'=>'Preparação recomendada','description'=>'Complete quando possível. Estes itens melhoram a operação, mas não bloqueiam a ativação.','steps'=>$implementation['recommended_steps'],'recommended'=>true],
  ]; ?>
  <?php foreach($implementationSections as$section):?>
   <div class="implementation-section <?= $section['recommended']?'is-recommended':'' ?>">
    <div class="implementation-section-heading"><div><h3><?= $escape($section['title']) ?></h3><p class="meta"><?= $escape($section['description']) ?></p></div><?php if($section['recommended']):?><span class="badge"><?= (int)$implementation['recommended_done'] ?>/<?= (int)$implementation['recommended_total'] ?> concluídos</span><?php endif;?></div>
    <div class="row g-3"><?php foreach($section['steps'] as$step):?><?php
     $action=(string)$step['action'];$href='';
     if($action!==''){
      if(str_starts_with($action,'#')){$href=$action;}
      elseif(str_starts_with($action,'/edit')||str_starts_with($action,'/contracts')){$href=$basePath.'/admin/organizations/'.(int)$organization['id'].$action;}
      else{$href=$basePath.$action;}
     }
    ?><div class="col-md-6 col-xl-4"><article class="implementation-step <?= $step['done']?'is-done':($section['recommended']?'is-recommended':'is-pending') ?> h-100">
     <div class="implementation-step-icon"><i class="fa-solid <?= $escape((string)$step['icon']) ?>"></i></div>
     <div class="implementation-step-content"><div class="d-flex justify-content-between gap-2"><small><?= $escape((string)$step['group']) ?></small><span class="badge <?= $step['done']?'badge-success':($section['recommended']?'':'badge-warning') ?>"><?= $step['done']?'Concluído':($section['recommended']?'Recomendado':'Pendente') ?></span></div><strong><?= $escape((string)$step['label']) ?></strong><p><?= $escape((string)$step['detail']) ?></p><?php if(!$step['done']):?><div class="implementation-step-actions"><?php if($href!==''):?><a href="<?= $escape($href) ?>">Resolver agora <i class="fa-solid fa-arrow-right"></i></a><?php endif;?><?php if(!$section['recommended']&&$application!==null):?><?php if(in_array($step['id'],$implementationTickets,true)):?><a class="implementation-ticket-open" href="<?= $escape($basePath) ?>/admin/tickets?q=<?= rawurlencode((string)$step['label']) ?>"><i class="fa-solid fa-ticket"></i> Ticket aberto</a><?php else:?><form method="post" action="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/implementation/<?= $escape((string)$step['id']) ?>/ticket"><?= $csrfField ?><button type="submit"><i class="fa-regular fa-bell"></i> Criar ticket</button></form><?php endif;?><?php endif;?></div><?php endif;?></div>
    </article></div><?php endforeach;?></div>
   </div>
  <?php endforeach;?>
  <div class="implementation-activation mt-4"><div><strong><?= $organizationActive?'Franquia ativa':'Ativação final' ?></strong><p class="meta mb-0"><?= $organizationActive?'O login exclusivo e a operação da franquia estão liberados.':($implementation['ready_to_activate']?'Todos os requisitos obrigatórios foram concluídos.':'Ainda faltam '.count($implementation['missing']).' requisito(s) obrigatório(s).') ?></p></div><?php if($organizationActive):?><span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Ativa</span><?php else:?><form method="post" action="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/activate"><?= $csrfField ?><button class="button button-primary" type="submit" <?= !$implementation['ready_to_activate']?'disabled':'' ?>><i class="fa-solid fa-power-off"></i> Ativar franquia</button></form><?php endif;?></div>
  <?php if(!$organizationActive&&!$implementation['ready_to_activate']):?><div class="alert alert-warning mt-3 mb-0"><strong>Ativação protegida.</strong> Conclua: <?= $escape(implode(', ',$implementation['missing'])) ?>.</div><?php endif;?>
 </div>
</section>

<section class="card mb-4" id="operacao-comercial">
 <div class="card-header"><div><p class="eyebrow">Financeiro</p><h2>Operação comercial</h2><p class="meta">Configure cobranças, repasses e as regras aplicadas às novas vendas.</p></div></div>
 <div class="p-4">
  <?php if($latest!==null):?>
  <div class="row g-3 mb-4">
   <div class="col-md-4"><div class="metric-card h-100"><span>Comissão Mundo Inter</span><strong><?= number_format($centralPercentage,2,',','.') ?>%</strong><small>Percentual definido no contrato</small></div></div>
   <div class="col-md-4"><div class="metric-card h-100"><span>Repasse da franquia</span><strong><?= $franchisePercentage>0?number_format($franchisePercentage,2,',','.').'%':'—' ?></strong><small><?= $escape($processingLabels[$financialProcessing]??'Processamento não definido') ?></small></div></div>
   <div class="col-md-4"><div class="metric-card h-100"><span>Mensalidade</span><strong><?= $monthlyRequired?'R$ '.number_format((float)$latest['monthly_fixed_amount'],2,',','.'):'Sem mensalidade' ?></strong><small><?= $monthlyRequired?($recurringReady?'Link recorrente pronto':'Link ainda não gerado'):'Modelo somente percentual' ?></small></div></div>
  </div>
  <?php endif;?>

  <?php if($splitRequired):?><details class="border rounded-3 p-3 mb-3" <?= !$splitReady?'open':'' ?>><summary><strong><i class="fa-solid fa-wallet"></i> Wallet Asaas e split</strong> <span class="badge <?= $splitReady?'badge-success':'badge-warning' ?>"><?= $splitReady?'Pronto':'Pendente' ?></span></summary>
   <form class="form-grid mt-3" method="post" action="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/finance-inline"><?= $csrfField ?>
    <label class="form-span-2">Wallet ID da franquia<input maxlength="80" name="asaas_wallet_id" value="<?= $escape($organization['asaas_wallet_id']??'') ?>" placeholder="00000000-0000-0000-0000-000000000000"><small>Copie o identificador da conta no Asaas e confirme a situação após a conferência.</small></label>
    <label>Situação da validação<select name="asaas_wallet_status"><?php foreach($walletLabels as$value=>$label):?><option value="<?= $escape($value) ?>" <?= ($organization['asaas_wallet_status']??'not_configured')===$value?'selected':'' ?>><?= $escape($label) ?></option><?php endforeach;?></select></label>
    <label class="checkbox-row"><input type="checkbox" name="split_enabled" value="1" <?= !empty($organization['split_enabled'])?'checked':'' ?>> Ativar split nas novas vendas</label>
    <label class="form-span-2">Observações financeiras<textarea maxlength="500" rows="2" name="asaas_finance_notes"><?= $escape($organization['asaas_finance_notes']??'') ?></textarea></label>
    <div class="form-actions form-span-2"><button class="button button-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar e validar configuração</button><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/finance">Configuração avançada</a></div>
   </form>
  </details><?php elseif($financialProcessing==='franchise_asaas'):?><div class="alert <?= $financeReady?'alert-success':'alert-warning' ?>"><strong><i class="fa-solid fa-building-columns"></i> Asaas exclusivo da franquia.</strong> <?= $financeReady?'A integração exclusiva foi testada e está pronta.':'Conclua o teste da integração na aba Integrações do cadastro da franquia.' ?></div><?php elseif($latest!==null):?><div class="alert alert-info"><strong><i class="fa-solid fa-calendar-check"></i> Fechamento e repasse mensal.</strong> O financeiro será consolidado pela conta central, sem exigir Wallet da franquia.</div><?php endif;?>

  <?php if($latest!==null):?><div class="border rounded-3 p-3"><div class="d-flex flex-wrap justify-content-between gap-3 align-items-center"><div><strong><i class="fa-solid fa-bolt"></i> Ações comerciais</strong><p class="meta mb-0"><?= $commercialActive?'A regra contratual já está ativa para as novas vendas.':'Ative somente depois de conferir assinatura, Wallet e percentuais.' ?></p></div><div class="page-actions">
   <?php if($monthlyRequired&&empty($latest['asaas_payment_link_url'])):?><form method="post" action="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/contracts/<?= (int)$latest['id'] ?>/recurring-link"><?= $csrfField ?><button class="button button-secondary" type="submit" <?= (!$contractSigned||!$asaasReady)?'disabled':'' ?>><i class="fa-solid fa-link"></i> Gerar link mensal</button></form><?php elseif($monthlyRequired):?><a class="button button-secondary" href="<?= $escape((string)$latest['asaas_payment_link_url']) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir link mensal</a><?php endif;?>
   <?php if(!$commercialActive):?><form method="post" action="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/contracts/<?= (int)$latest['id'] ?>/activate"><?= $csrfField ?><button class="button button-primary" type="submit" <?= !$canActivateCommercial?'disabled':'' ?>><i class="fa-solid fa-circle-play"></i> Ativar operação comercial</button></form><?php else:?><span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Operação ativa</span><?php endif;?>
  </div></div><?php if(!$canActivateCommercial&&!$commercialActive):?><div class="alert alert-warning mt-3 mb-0">Antes de ativar: <?= !$organizationActive?'ative a franquia; ':'' ?><?= !$contractSigned?'obtenha a assinatura do contrato; ':'' ?><?= !$financeReady?'conclua os requisitos do processamento financeiro; ':'' ?></div><?php endif;?></div><?php endif;?>
 </div>
</section>

<?php if($latest!==null&&$billingEvents!==[]):?><section class="card mb-4"><div class="card-header"><div><h2>Histórico operacional</h2><p class="meta">Últimos registros financeiros e comerciais do contrato atual.</p></div></div><div class="table-responsive"><table><thead><tr><th>Data</th><th>Evento</th><th>Responsável</th></tr></thead><tbody><?php foreach(array_slice($billingEvents,0,5)as$event):?><tr><td><?= $escape(date('d/m/Y H:i',strtotime((string)$event['created_at']))) ?></td><td><strong><?= $escape((string)$event['description']) ?></strong></td><td><?= $escape((string)($event['user_name']?:'Sistema')) ?></td></tr><?php endforeach;?></tbody></table></div></section><?php endif;?>

<section class="card"><div class="card-header"><div><h2>Histórico contratual</h2><p class="meta">Contratos, renovações, assinatura e situação financeira em uma única linha do tempo.</p></div><span class="badge"><?= count($contracts) ?></span></div>
<?php if($contracts===[]):?><div class="empty-state"><i class="fa-solid fa-file-signature"></i><strong>Nenhum contrato vinculado</strong></div><?php else:?><div class="table-responsive"><table><thead><tr><th>Contrato</th><th>Regra</th><th>Processamento</th><th>Vigência</th><th>Assinatura</th><th>Financeiro</th><th>Ação</th></tr></thead><tbody><?php foreach($contracts as$c):$rowRule=(string)($c['commercial_rule']??'');if($rowRule==='')$rowRule=($c['commercial_model']??'')==='fixed_plus_percentage'?((float)($c['sales_fee_percentage']??0)>0?'hybrid':'fixed_monthly'):'percentage_commission';$rowProcessing=(string)($c['financial_processing']??'');if($rowProcessing==='')$rowProcessing=(float)($c['sales_fee_percentage']??0)>0?'central_automatic_split':'central_monthly_settlement';?><tr><td><strong>#<?= (int)($c['contract_number']??1) ?> · <?= $escape((string)$c['title']) ?></strong><small class="d-block meta"><?= ($c['contract_type']??'new')==='renewal'?'Renovação':'Novo contrato' ?></small></td><td><?= $escape($rules[$rowRule]??'Legado') ?></td><td><?= $escape($processingLabels[$rowProcessing]??'Não definido') ?></td><td><?= $c['valid_from']?$escape(date('d/m/Y',strtotime((string)$c['valid_from']))):'Sem início' ?> — <?= $c['valid_until']?$escape(date('d/m/Y',strtotime((string)$c['valid_until']))):'indeterminada' ?></td><td><span class="badge <?= $c['status']==='signed'?'badge-success':'badge-warning' ?>"><?= $escape($contractLabels[$c['status']]??(string)$c['status']) ?></span></td><td><?= $escape($billingLabels[$c['billing_issue_state']??'not_issued']??'Não configurada') ?></td><td><a class="button button-secondary button-icon" title="Abrir contrato" href="<?= $escape($basePath) ?>/admin/franchise-contracts/<?= (int)$c['id'] ?>"><i class="fa-solid fa-eye"></i></a></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
