<?php
$contractStatuses=['draft'=>'Rascunho','sent'=>'Enviado','viewed'=>'Visualizado','signed'=>'Assinado','cancelled'=>'Cancelado'];
$contractRules=['percentage_commission'=>'Comissão percentual','fixed_monthly'=>'Mensalidade fixa','hybrid'=>'Mensalidade + comissão','per_enrollment'=>'Valor por matrícula'];
$contractProcessing=['central_monthly_settlement'=>'Repasse mensal','central_automatic_split'=>'Split automático','franchise_asaas'=>'Asaas exclusivo da franquia'];
$currentContract=null;$pendingContract=null;
foreach($contracts as$item){
    if($pendingContract===null&&in_array((string)($item['status']??''),['draft','sent','viewed'],true))$pendingContract=$item;
    if($currentContract===null&&($item['status']??'')==='signed'&&($item['commercial_flow_status']??'pending')==='active')$currentContract=$item;
}
if($currentContract===null)foreach($contracts as$item)if(($item['status']??'')==='signed'){$currentContract=$item;break;}
$planContract=$currentContract??$pendingContract??($contracts[0]??null);
$commercialRule=static function(array$contract):string{
    $rule=(string)($contract['commercial_rule']??'');
    if($rule!=='')return$rule;
    return($contract['commercial_model']??'')==='fixed_plus_percentage'?((float)($contract['sales_fee_percentage']??0)>0?'hybrid':'fixed_monthly'):'percentage_commission';
};
$financialProcessing=static function(array$contract):string{
    $processing=(string)($contract['financial_processing']??'');
    return$processing!==''?$processing:((float)($contract['sales_fee_percentage']??0)>0?'central_automatic_split':'central_monthly_settlement');
};
$money=static fn(mixed$value):string=>'R$ '.number_format((float)$value,2,',','.');
$planValue=static function(array$contract)use($commercialRule,$money):string{
    return match($commercialRule($contract)){
        'fixed_monthly'=>$money($contract['monthly_fixed_amount']??0).' por mês',
        'hybrid'=>$money($contract['monthly_fixed_amount']??0).' + '.number_format((float)($contract['sales_fee_percentage']??0),2,',','.').'%',
        'per_enrollment'=>$money($contract['fixed_fee_per_enrollment']??0).' por matrícula',
        default=>number_format((float)($contract['sales_fee_percentage']??0),2,',','.').'% por venda',
    };
};
?>
<style>
.organization-contract-panel{padding:0;overflow:hidden}.organization-contract-panel>.organization-section-header{margin:0;padding:1.5rem}.contract-plan-hero{display:grid;grid-template-columns:minmax(0,1.4fr) repeat(3,minmax(10rem,.7fr));gap:.85rem;padding:1.25rem 1.5rem;background:#f8fafb;border-block:1px solid #e2e8ec}.contract-plan-main,.contract-plan-fact{min-width:0;padding:1rem;border:1px solid #dce3e8;border-radius:.9rem;background:#fff}.contract-plan-main{display:flex;align-items:center;gap:1rem}.contract-plan-main>i{display:grid;place-items:center;flex:0 0 3rem;width:3rem;height:3rem;border-radius:.8rem;background:#fff0f1;color:var(--inter-accent);font-size:1.25rem}.contract-plan-main strong,.contract-plan-main small,.contract-plan-fact strong,.contract-plan-fact small{display:block}.contract-plan-main strong{font-size:1.12rem}.contract-plan-main small,.contract-plan-fact small{margin-top:.2rem;color:var(--inter-muted)}.contract-plan-actions{display:flex;flex-wrap:wrap;gap:.65rem;padding:1rem 1.5rem;border-bottom:1px solid #e2e8ec}.contract-plan-notice{margin:1rem 1.5rem 0}.contract-history{padding:1.25rem 1.5rem 1.5rem}.contract-history-header{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:.85rem}.contract-history-header h3,.contract-history-header p{margin:0}.contract-history table td{vertical-align:middle}.contract-history-title strong,.contract-history-title small{display:block}.contract-history-title small{margin-top:.2rem;color:var(--inter-muted)}.contract-history-actions{display:flex;gap:.35rem}.contract-history-actions .button{display:grid!important;place-items:center;width:2.55rem!important;height:2.55rem!important;min-width:2.55rem!important;min-height:2.55rem!important;padding:0!important}@media(max-width:1050px){.contract-plan-hero{grid-template-columns:repeat(2,minmax(0,1fr))}.contract-plan-main{grid-column:1/-1}}@media(max-width:650px){.contract-plan-hero{grid-template-columns:1fr}.contract-plan-main{grid-column:auto}.contract-plan-actions .button{width:100%}.contract-history{padding:1rem}.organization-contract-panel>.organization-section-header,.contract-plan-hero,.contract-plan-actions{padding-inline:1rem}}
</style>
<section class="card organization-section organization-contract-panel" id="contrato" data-organization-panel="contrato" hidden>
  <header class="organization-section-header"><span class="organization-section-icon"><i class="fa-solid fa-file-signature"></i></span><div><h2>Contrato e plano</h2><p class="meta">Consulte a regra vigente, altere o plano com segurança e preserve todo o histórico contratual.</p></div></header>

  <?php if($planContract!==null):?>
  <?php $planRule=$commercialRule($planContract);$planProcessing=$financialProcessing($planContract);$planStatus=(string)($planContract['status']??'draft');$flowActive=($planContract['commercial_flow_status']??'pending')==='active';?>
  <div class="contract-plan-hero">
    <article class="contract-plan-main"><i class="fa-solid fa-layer-group"></i><div><small><?= $currentContract!==null?'Plano vigente':'Configuração mais recente' ?></small><strong><?= $escape($contractRules[$planRule]??'Regra comercial não definida') ?></strong><small>Contrato #<?= (int)($planContract['contract_number']??1) ?> · <?= $escape((string)$planContract['title']) ?></small></div></article>
    <article class="contract-plan-fact"><small>Condição comercial</small><strong><?= $escape($planValue($planContract)) ?></strong></article>
    <article class="contract-plan-fact"><small>Processamento</small><strong><?= $escape($contractProcessing[$planProcessing]??'Não definido') ?></strong></article>
    <article class="contract-plan-fact"><small>Situação</small><strong><span class="badge <?= $planStatus==='cancelled'?'badge-danger':($planStatus==='signed'&&$flowActive?'badge-success':'badge-warning') ?>"><?= $escape($planStatus==='signed'&&$flowActive?'Ativo':($contractStatuses[$planStatus]??$planStatus)) ?></span></strong><small><?= !empty($planContract['valid_until'])?'Válido até '.date('d/m/Y',strtotime((string)$planContract['valid_until'])):'Sem término definido' ?></small></article>
  </div>
  <?php else:?>
  <div class="empty-state"><i class="fa-solid fa-file-circle-question"></i><strong>Nenhum plano definido</strong><p>Crie o primeiro contrato para definir a regra comercial e o processamento financeiro da franquia.</p></div>
  <?php endif;?>

  <div class="contract-plan-actions">
    <?php if(is_array($application)):?>
      <?php if($pendingContract!==null):?>
        <a class="button button-primary" href="<?= $escape($basePath) ?>/admin/franchise-contracts/<?= (int)$pendingContract['id'] ?>"><i class="fa-solid fa-pen-to-square"></i> Continuar alteração</a>
      <?php elseif($currentContract!==null):?>
        <a class="button button-primary" href="<?= $escape($basePath) ?>/admin/franchise-applications/<?= (int)$application['id'] ?>/contracts/create?renew_from=<?= (int)$currentContract['id'] ?>"><i class="fa-solid fa-arrows-rotate"></i> Alterar plano</a>
      <?php else:?>
        <a class="button button-primary" href="<?= $escape($basePath) ?>/admin/franchise-applications/<?= (int)$application['id'] ?>/contracts/create"><i class="fa-solid fa-file-circle-plus"></i> Definir primeiro plano</a>
      <?php endif;?>
      <?php if($currentContract!==null):?><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/franchise-contracts/<?= (int)$currentContract['id'] ?>"><i class="fa-solid fa-eye"></i> Abrir contrato vigente</a><?php endif;?>
    <?php else:?>
      <a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/franchise-applications"><i class="fa-solid fa-circle-info"></i> Concluir cadastro-base</a>
    <?php endif;?>
  </div>
  <?php if($pendingContract!==null&&$currentContract!==null):?><div class="alert alert-warning contract-plan-notice"><strong>Alteração em andamento:</strong> o plano vigente continua ativo até o novo contrato ser assinado e liberado.</div><?php endif;?>

  <div class="contract-history">
    <div class="contract-history-header"><div><h3>Histórico de contratos</h3><p class="meta">Contratos, renovações e mudanças de plano permanecem disponíveis para auditoria.</p></div><span class="badge"><?= count($contracts) ?></span></div>
    <?php if($contracts===[]):?>
      <div class="empty-state"><i class="fa-regular fa-folder-open"></i><strong>Histórico vazio</strong><p>O primeiro contrato aparecerá aqui após ser gerado.</p></div>
    <?php else:?>
      <div class="table-responsive"><table><thead><tr><th>Contrato</th><th>Plano</th><th>Processamento</th><th>Vigência</th><th>Situação</th><th>Ações</th></tr></thead><tbody>
      <?php foreach($contracts as$contract):$rule=$commercialRule($contract);$processing=$financialProcessing($contract);$status=(string)($contract['status']??'draft');$active=$status==='signed'&&($contract['commercial_flow_status']??'pending')==='active';?>
        <tr>
          <td class="contract-history-title"><strong>#<?= (int)($contract['contract_number']??1) ?> · <?= $escape((string)$contract['title']) ?></strong><small><?= ($contract['contract_type']??'new')==='renewal'?'Mudança ou renovação':'Contrato inicial' ?> · <?= date('d/m/Y H:i',strtotime((string)$contract['created_at'])) ?></small></td>
          <td><strong><?= $escape($contractRules[$rule]??'Legado') ?></strong><small class="d-block meta"><?= $escape($planValue($contract)) ?></small></td>
          <td><?= $escape($contractProcessing[$processing]??'Não definido') ?></td>
          <td><?= !empty($contract['valid_from'])?date('d/m/Y',strtotime((string)$contract['valid_from'])):'Sem início' ?> — <?= !empty($contract['valid_until'])?date('d/m/Y',strtotime((string)$contract['valid_until'])):'indeterminada' ?></td>
          <td><span class="badge <?= $status==='cancelled'?'badge-danger':($active?'badge-success':'badge-warning') ?>"><?= $escape($active?'Ativo':($contractStatuses[$status]??$status)) ?></span></td>
          <td><div class="contract-history-actions"><a class="button button-secondary" title="Abrir contrato" href="<?= $escape($basePath) ?>/admin/franchise-contracts/<?= (int)$contract['id'] ?>"><i class="fa-solid fa-eye"></i></a><?php if($status==='signed'&&$pendingContract===null):?><a class="button button-secondary" title="Usar como base para alterar o plano" href="<?= $escape($basePath) ?>/admin/franchise-applications/<?= (int)$application['id'] ?>/contracts/create?renew_from=<?= (int)$contract['id'] ?>"><i class="fa-solid fa-rotate"></i></a><?php endif;?></div></td>
        </tr>
      <?php endforeach;?>
      </tbody></table></div>
    <?php endif;?>
  </div>
</section>
