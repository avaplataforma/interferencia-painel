<?php $renewing=is_array($renewFrom); ?>
<style>
.contract-rule-panel{padding:1.25rem;border:1px solid #dfe6eb;border-radius:.85rem;background:#f8fafb}.contract-rule-panel h2{margin:0 0 .25rem}.contract-rule-panel>p{margin:0 0 1rem}.contract-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.contract-fields label{margin:0}.contract-fields .span-2{grid-column:1/-1}.contract-field[hidden]{display:none!important}.contract-rule-note{display:flex;gap:.75rem;align-items:flex-start;padding:.85rem;border-radius:.65rem;background:#eef5ff;color:#29435d}.contract-rule-note i{margin-top:.2rem;color:var(--inter-accent)}
@media(max-width:767.98px){.contract-fields{grid-template-columns:1fr}.contract-fields .span-2{grid-column:auto}}
</style>
<div class="page-header"><div><p class="eyebrow">Franquias · <?= $escape($application['display_name']) ?></p><h1><?= $renewing?'Renovar contrato':'Novo contrato' ?></h1><p>Regra comercial e processamento financeiro são definidos separadamente.</p></div><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/franchise-applications/<?= (int)$application['id'] ?>"><i class="fa-solid fa-arrow-left"></i> Voltar</a></div>
<?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>
<form class="card form-grid" method="post" action="<?= $escape($basePath) ?>/admin/franchise-applications/<?= (int)$application['id'] ?>/contracts" data-commercial-contract-form><?= $csrfField ?>
  <label>Tipo *<select name="contract_type" required><option value="new" <?= !$renewing?'selected':'' ?>>Novo contrato</option><option value="renewal" <?= $renewing?'selected':'' ?>>Renovação</option></select></label>
  <label>Contrato anterior<select name="parent_contract_id"><option value="">Não se aplica</option><?php foreach($previousContracts as$item):?><option value="<?= (int)$item['id'] ?>" <?= $renewing&&(int)$renewFrom['id']===(int)$item['id']?'selected':'' ?>>#<?= (int)$item['contract_number'] ?> · <?= $escape($item['title']) ?></option><?php endforeach;?></select></label>
  <label class="form-span-2">Modelo do documento *<select name="template_id" required><option value="">Selecione</option><?php foreach($templates as$item):?><option value="<?= (int)$item['id'] ?>"><?= $escape($item['title']) ?> · v<?= $escape($item['version']) ?></option><?php endforeach;?></select></label>
  <label class="form-span-2">Título<input name="title" maxlength="180" value="<?= $renewing?$escape('Renovação — '.$renewFrom['title']):'' ?>" placeholder="Preenchido automaticamente se ficar vazio"></label>
  <label class="form-span-2">Condições comerciais *<textarea name="commercial_terms" rows="5" required placeholder="Serviços incluídos, reajuste e demais condições acordadas."><?= $renewing?$escape('Renovação das condições do contrato #'.$renewFrom['contract_number'].'.'):'' ?></textarea></label>
  <label>Início da vigência<input type="date" name="valid_from" value="<?= $escape(date('Y-m-d')) ?>"></label>
  <label>Fim da vigência<input type="date" name="valid_until"></label>
  <label class="form-span-2">Vigência por extenso *<input name="term" required placeholder="Ex.: 12 meses, com renovação mediante novo instrumento"></label>

  <section class="contract-rule-panel form-span-2">
    <h2>1. Regra comercial</h2>
    <p>Define como o Mundo Inter será remunerado. Não define por qual conta o dinheiro será recebido.</p>
    <div class="contract-fields">
      <label class="span-2">Regra contratada *<select name="commercial_rule" required data-commercial-rule><option value="">Selecione</option><option value="percentage_commission">Comissão percentual por venda</option><option value="fixed_monthly">Mensalidade fixa</option><option value="hybrid">Mensalidade fixa + comissão percentual</option><option value="per_enrollment">Valor fixo por matrícula</option></select></label>
      <label class="contract-field" data-rule-field="monthly">Mensalidade fixa *<input name="monthly_fixed_amount" inputmode="decimal" placeholder="0,00"><small>Valor mensal cobrado da franquia.</small></label>
      <label class="contract-field" data-rule-field="enrollment">Valor por matrícula *<input name="fixed_fee_per_enrollment" inputmode="decimal" placeholder="0,00"><small>Valor devido a cada nova matrícula confirmada.</small></label>
      <label class="contract-field" data-rule-field="percentage">Percentual Mundo Inter *<input name="sales_fee_percentage" type="number" min="0.0001" max="99.9999" step="0.0001" placeholder="30,0000"><small>Parcela da venda destinada ao Mundo Inter.</small></label>
      <label class="contract-field" data-rule-field="percentage">Percentual da franquia *<input name="franchise_fee_percentage" type="number" min="0.0001" max="99.9999" step="0.0001" placeholder="70,0000"><small>Os dois percentuais devem somar 100%.</small></label>
      <label class="span-2 contract-field" data-rule-field="monthly">Descrição da mensalidade<input name="billing_description" maxlength="190" placeholder="Licenciamento mensal Mundo Inter" data-optional></label>
    </div>
  </section>

  <section class="contract-rule-panel form-span-2">
    <h2>2. Processamento financeiro</h2>
    <p>Define qual conta recebe o pagamento e quando a franquia recebe sua parte.</p>
    <div class="contract-fields">
      <label class="span-2">Forma de processamento *<select name="financial_processing" required data-financial-processing><option value="">Selecione</option><option value="central_monthly_settlement">Conta central · fechamento e repasse mensal</option><option value="central_automatic_split">Conta central · split automático por venda</option><option value="franchise_asaas">Conta Asaas exclusiva da franquia</option></select></label>
      <label class="contract-field" data-processing-field="settlement">Dia do fechamento *<input name="closing_day" type="number" min="1" max="31" value="30"><small>Dia em que o período mensal é encerrado.</small></label>
      <label class="contract-field" data-processing-field="settlement">Dia do repasse *<input name="settlement_day" type="number" min="1" max="31" value="10"><small>Dia previsto para pagar a franquia.</small></label>
      <div class="span-2 contract-rule-note" data-processing-note><i class="fa-solid fa-circle-info"></i><span>Escolha o processamento para ver os requisitos operacionais.</span></div>
    </div>
  </section>

  <div class="form-span-2 alert alert-info"><strong>Fluxo protegido:</strong> o contrato será gerado em rascunho. Assinatura, integração financeira e ativação comercial continuam sendo conferidas separadamente antes das novas vendas.</div>
  <div class="form-span-2"><button class="button button-primary" type="submit"><i class="fa-solid fa-file-circle-plus"></i> Gerar rascunho</button></div>
</form>
<script>
(()=>{const form=document.querySelector('[data-commercial-contract-form]');if(!form)return;const rule=form.querySelector('[data-commercial-rule]');const processing=form.querySelector('[data-financial-processing]');const note=form.querySelector('[data-processing-note]');const toggle=(element,visible,required)=>{element.hidden=!visible;element.querySelectorAll('input').forEach(input=>{input.disabled=!visible;input.required=visible&&required&&!input.hasAttribute('data-optional');});};const refresh=()=>{const value=rule.value;form.querySelectorAll('[data-rule-field="monthly"]').forEach(el=>toggle(el,['fixed_monthly','hybrid'].includes(value),true));form.querySelectorAll('[data-rule-field="percentage"]').forEach(el=>toggle(el,['percentage_commission','hybrid'].includes(value),true));form.querySelectorAll('[data-rule-field="enrollment"]').forEach(el=>toggle(el,value==='per_enrollment',true));const monthly=processing.value==='central_monthly_settlement';form.querySelectorAll('[data-processing-field="settlement"]').forEach(el=>toggle(el,monthly,true));note.innerHTML={central_monthly_settlement:'<i class="fa-solid fa-calendar-check"></i><span>As vendas entram na conta central. O sistema fecha o período e calcula o valor a repassar à franquia.</span>',central_automatic_split:'<i class="fa-solid fa-arrows-split-up-and-left"></i><span>Cada venda é dividida automaticamente. Exige Wallet Asaas validada e uma regra com percentuais.</span>',franchise_asaas:'<i class="fa-solid fa-building-columns"></i><span>O pagamento entra diretamente na conta exclusiva da franquia. A integração precisa estar testada e ativa.</span>'}[processing.value]||'<i class="fa-solid fa-circle-info"></i><span>Escolha o processamento para ver os requisitos operacionais.</span>';};rule.addEventListener('change',refresh);processing.addEventListener('change',refresh);refresh();})();
</script>
