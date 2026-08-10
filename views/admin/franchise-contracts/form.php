<?php
$renewing = is_array($renewFrom);
$organizationId = (int) ($application['organization_id'] ?? 0);
$backUrl = $organizationId > 0
    ? $basePath . '/admin/organizations/' . $organizationId . '/edit#contrato'
    : $basePath . '/admin/franchise-applications/' . (int) $application['id'];
?>
<style>
.contract-page-header{align-items:center;margin-bottom:1.25rem}
.contract-page-header .eyebrow{display:flex;align-items:center;gap:.45rem}
.contract-workspace{width:100%;max-width:none!important;border:1px solid #dce4e9;border-radius:1rem;box-shadow:0 .75rem 2.25rem rgb(23 43 58 / 7%);overflow:hidden}
.contract-workspace label{display:flex;min-width:0;flex-direction:column;gap:.4rem;font-weight:700;color:var(--inter-ink)}
.contract-workspace input:not([type=checkbox]),.contract-workspace select,.contract-workspace textarea{box-sizing:border-box;width:100%;min-width:0;margin:0;padding:.75rem .85rem;border:1px solid #b9c5cd;border-radius:.65rem;background:#fff;color:var(--inter-ink);font:inherit;font-weight:400;transition:border-color .18s,box-shadow .18s}
.contract-workspace input:not([type=checkbox]),.contract-workspace select{height:3rem}
.contract-workspace textarea{min-height:7.5rem;resize:vertical;line-height:1.5}
.contract-workspace input:focus,.contract-workspace select:focus,.contract-workspace textarea:focus{border-color:var(--inter-accent);box-shadow:0 0 0 .2rem rgb(237 28 36 / 10%);outline:0}
.contract-source-summary{display:flex;align-items:center;gap:1rem;padding:1rem 1.4rem;border-bottom:1px solid #dce4e9;background:linear-gradient(90deg,#fff6f6,#fff)}
.contract-source-icon{display:grid;width:2.75rem;height:2.75rem;flex:0 0 2.75rem;place-items:center;border-radius:.75rem;background:#ffe7e8;color:var(--inter-accent)}
.contract-source-copy{display:flex;min-width:0;flex:1;flex-direction:column;gap:.15rem}.contract-source-copy small{color:var(--inter-muted)}.contract-source-copy strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.contract-source-badge{padding:.4rem .7rem;border-radius:999px;background:#fff;border:1px solid #f0c8ca;color:var(--inter-accent-dark);font-size:.82rem;font-weight:700}
.contract-form-section{padding:1.35rem 1.5rem;border-bottom:1px solid #e2e8ec}
.contract-section-heading{display:flex;align-items:flex-start;gap:.8rem;margin-bottom:1.15rem}
.contract-section-heading>i{display:grid;width:2.4rem;height:2.4rem;flex:0 0 2.4rem;place-items:center;border-radius:.7rem;background:#fff0f1;color:var(--inter-accent)}
.contract-section-heading h2{margin:0;font-size:1.15rem}.contract-section-heading p{margin:.2rem 0 0;color:var(--inter-muted);font-size:.9rem}
.contract-document-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:1rem}
.contract-col-3{grid-column:span 3}.contract-col-4{grid-column:span 4}.contract-col-5{grid-column:span 5}.contract-col-6{grid-column:span 6}.contract-col-8{grid-column:span 8}.contract-col-12{grid-column:1/-1}
.contract-business-grid{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(20rem,.85fr);gap:1.1rem;padding:1.35rem 1.5rem;background:#f5f8fa}
.contract-rule-panel{display:flex;min-width:0;flex-direction:column;padding:1.25rem;border:1px solid #dce4e9;border-radius:.9rem;background:#fff;box-shadow:0 .35rem 1rem rgb(23 43 58 / 4%)}
.contract-rule-panel .contract-section-heading{margin-bottom:1rem}
.contract-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.contract-fields label{margin:0}.contract-fields .span-2{grid-column:1/-1}.contract-field[hidden]{display:none!important}
.contract-workspace small{color:var(--inter-muted);font-size:.8rem;font-weight:400;line-height:1.35}
.contract-rule-note{display:flex;gap:.75rem;align-items:flex-start;padding:.9rem;border:1px solid #d6e4f2;border-radius:.7rem;background:#eef5ff;color:#29435d}.contract-rule-note i{margin-top:.2rem;color:var(--inter-accent)}
.contract-protection-note{display:flex;gap:.75rem;align-items:flex-start;min-width:0;color:#395368}.contract-protection-note i{margin-top:.2rem;color:var(--inter-accent)}.contract-protection-note strong{display:block;margin-bottom:.15rem;color:var(--inter-ink)}.contract-protection-note span{font-size:.9rem;line-height:1.4}
.contract-action-bar{display:flex;align-items:center;justify-content:space-between;gap:1.25rem;padding:1.15rem 1.5rem;background:#fff}
.contract-action-bar .button{min-width:12rem;justify-content:center}
@media(max-width:1099.98px){.contract-business-grid{grid-template-columns:1fr}.contract-col-3,.contract-col-4{grid-column:span 6}.contract-col-5{grid-column:span 6}.contract-col-8{grid-column:span 6}}
@media(max-width:767.98px){.contract-form-section,.contract-business-grid,.contract-action-bar{padding:1rem}.contract-document-grid,.contract-fields{grid-template-columns:1fr}.contract-document-grid>*{grid-column:1/-1}.contract-fields .span-2{grid-column:auto}.contract-source-summary{align-items:flex-start;padding:1rem}.contract-source-badge{display:none}.contract-action-bar{align-items:stretch;flex-direction:column}.contract-action-bar .button{width:100%}.contract-source-copy strong{white-space:normal}}
</style>

<div class="page-header contract-page-header">
  <div>
    <p class="eyebrow"><i class="fa-solid fa-file-signature"></i> Franquias · <?= $escape($application['display_name']) ?></p>
    <h1><?= $renewing ? 'Renovar contrato' : 'Novo contrato' ?></h1>
    <p>Defina documento, condições comerciais e processamento financeiro em uma única etapa.</p>
  </div>
  <a class="button button-secondary" href="<?= $escape($backUrl) ?>"><i class="fa-solid fa-arrow-left"></i> Voltar</a>
</div>

<?php if (!empty($error)): ?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif; ?>

<form class="card contract-workspace" method="post" action="<?= $escape($basePath) ?>/admin/franchise-applications/<?= (int) $application['id'] ?>/contracts" data-commercial-contract-form>
  <?= $csrfField ?>

  <?php if ($renewing): ?>
    <div class="contract-source-summary">
      <span class="contract-source-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
      <span class="contract-source-copy">
        <small>Contrato usado como base para esta renovação</small>
        <strong>#<?= (int) $renewFrom['contract_number'] ?> · <?= $escape($renewFrom['title']) ?></strong>
      </span>
      <span class="contract-source-badge"><i class="fa-solid fa-shield-halved"></i> Histórico preservado</span>
    </div>
  <?php endif; ?>

  <section class="contract-form-section">
    <div class="contract-section-heading">
      <i class="fa-solid fa-file-lines"></i>
      <div><h2>Documento e vigência</h2><p>Identifique o instrumento, o período e as condições que serão apresentadas para assinatura.</p></div>
    </div>
    <div class="contract-document-grid">
      <label class="contract-col-3">Tipo *
        <select name="contract_type" required>
          <option value="new" <?= !$renewing ? 'selected' : '' ?>>Novo contrato</option>
          <option value="renewal" <?= $renewing ? 'selected' : '' ?>>Renovação</option>
        </select>
      </label>
      <label class="contract-col-5">Contrato anterior
        <select name="parent_contract_id">
          <option value="">Não se aplica</option>
          <?php foreach ($previousContracts as $item): ?>
            <option value="<?= (int) $item['id'] ?>" <?= $renewing && (int) $renewFrom['id'] === (int) $item['id'] ? 'selected' : '' ?>>#<?= (int) $item['contract_number'] ?> · <?= $escape($item['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="contract-col-4">Modelo do documento *
        <select name="template_id" required>
          <option value="">Selecione</option>
          <?php foreach ($templates as $item): ?><option value="<?= (int) $item['id'] ?>"><?= $escape($item['title']) ?> · v<?= $escape($item['version']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label class="contract-col-8">Título
        <input name="title" maxlength="180" value="<?= $renewing ? $escape('Renovação — ' . $renewFrom['title']) : '' ?>" placeholder="Preenchido automaticamente se ficar vazio">
      </label>
      <label class="contract-col-4">Vigência por extenso *
        <input name="term" required placeholder="Ex.: 12 meses">
      </label>
      <label class="contract-col-3">Início da vigência
        <input type="date" name="valid_from" value="<?= $escape(date('Y-m-d')) ?>">
      </label>
      <label class="contract-col-3">Fim da vigência
        <input type="date" name="valid_until">
      </label>
      <label class="contract-col-6">Condições comerciais *
        <textarea name="commercial_terms" rows="4" required placeholder="Serviços incluídos, reajuste e demais condições acordadas."><?= $renewing ? $escape('Renovação das condições do contrato #' . $renewFrom['contract_number'] . '.') : '' ?></textarea>
      </label>
    </div>
  </section>

  <div class="contract-business-grid">
    <section class="contract-rule-panel">
      <div class="contract-section-heading">
        <i class="fa-solid fa-chart-pie"></i>
        <div><h2>1. Regra comercial</h2><p>Como o Mundo Inter será remunerado neste contrato.</p></div>
      </div>
      <div class="contract-fields">
        <label class="span-2">Regra contratada *
          <select name="commercial_rule" required data-commercial-rule>
            <option value="">Selecione</option>
            <option value="percentage_commission">Comissão percentual por venda</option>
            <option value="fixed_monthly">Mensalidade fixa</option>
            <option value="hybrid">Mensalidade fixa + comissão percentual</option>
            <option value="per_enrollment">Valor fixo por matrícula</option>
          </select>
        </label>
        <label class="contract-field" data-rule-field="monthly">Mensalidade fixa *<input name="monthly_fixed_amount" inputmode="decimal" placeholder="0,00"><small>Valor mensal cobrado da franquia.</small></label>
        <label class="contract-field" data-rule-field="enrollment">Valor por matrícula *<input name="fixed_fee_per_enrollment" inputmode="decimal" placeholder="0,00"><small>Valor devido a cada matrícula confirmada.</small></label>
        <label class="contract-field" data-rule-field="percentage">Percentual Mundo Inter *<input name="sales_fee_percentage" type="number" min="0.0001" max="99.9999" step="0.0001" placeholder="30,0000"><small>Parcela destinada ao Mundo Inter.</small></label>
        <label class="contract-field" data-rule-field="percentage">Percentual da franquia *<input name="franchise_fee_percentage" type="number" min="0.0001" max="99.9999" step="0.0001" placeholder="70,0000"><small>Os percentuais devem somar 100%.</small></label>
        <label class="span-2 contract-field" data-rule-field="monthly">Descrição da mensalidade<input name="billing_description" maxlength="190" placeholder="Licenciamento mensal Mundo Inter" data-optional></label>
      </div>
    </section>

    <section class="contract-rule-panel">
      <div class="contract-section-heading">
        <i class="fa-solid fa-building-columns"></i>
        <div><h2>2. Processamento financeiro</h2><p>Qual conta recebe e quando a franquia recebe sua parte.</p></div>
      </div>
      <div class="contract-fields">
        <label class="span-2">Forma de processamento *
          <select name="financial_processing" required data-financial-processing>
            <option value="">Selecione</option>
            <option value="central_monthly_settlement">Conta central · fechamento e repasse mensal</option>
            <option value="central_automatic_split">Conta central · split automático por venda</option>
            <option value="franchise_asaas">Conta Asaas exclusiva da franquia</option>
          </select>
        </label>
        <label class="contract-field" data-processing-field="settlement">Dia do fechamento *<input name="closing_day" type="number" min="1" max="31" value="30"><small>Dia em que o período mensal é encerrado.</small></label>
        <label class="contract-field" data-processing-field="settlement">Dia do repasse *<input name="settlement_day" type="number" min="1" max="31" value="10"><small>Dia previsto para pagar a franquia.</small></label>
        <div class="span-2 contract-rule-note" data-processing-note><i class="fa-solid fa-circle-info"></i><span>Escolha o processamento para ver os requisitos operacionais.</span></div>
      </div>
    </section>
  </div>

  <div class="contract-action-bar">
    <div class="contract-protection-note">
      <i class="fa-solid fa-shield-halved"></i>
      <span><strong>Fluxo protegido</strong>O documento será gerado em rascunho. Assinatura, integração financeira e ativação continuam sendo conferidas separadamente.</span>
    </div>
    <button class="button button-primary" type="submit"><i class="fa-solid fa-file-circle-plus"></i> Gerar rascunho</button>
  </div>
</form>

<script>
(()=>{const form=document.querySelector('[data-commercial-contract-form]');if(!form)return;const rule=form.querySelector('[data-commercial-rule]');const processing=form.querySelector('[data-financial-processing]');const note=form.querySelector('[data-processing-note]');const toggle=(element,visible,required)=>{element.hidden=!visible;element.querySelectorAll('input').forEach(input=>{input.disabled=!visible;input.required=visible&&required&&!input.hasAttribute('data-optional');});};const refresh=()=>{const value=rule.value;form.querySelectorAll('[data-rule-field="monthly"]').forEach(el=>toggle(el,['fixed_monthly','hybrid'].includes(value),true));form.querySelectorAll('[data-rule-field="percentage"]').forEach(el=>toggle(el,['percentage_commission','hybrid'].includes(value),true));form.querySelectorAll('[data-rule-field="enrollment"]').forEach(el=>toggle(el,value==='per_enrollment',true));const monthly=processing.value==='central_monthly_settlement';form.querySelectorAll('[data-processing-field="settlement"]').forEach(el=>toggle(el,monthly,true));note.innerHTML={central_monthly_settlement:'<i class="fa-solid fa-calendar-check"></i><span>As vendas entram na conta central. O sistema fecha o período e calcula o valor a repassar à franquia.</span>',central_automatic_split:'<i class="fa-solid fa-arrows-split-up-and-left"></i><span>Cada venda é dividida automaticamente. Exige Wallet Asaas validada e uma regra com percentuais.</span>',franchise_asaas:'<i class="fa-solid fa-building-columns"></i><span>O pagamento entra diretamente na conta exclusiva da franquia. A integração precisa estar testada e ativa.</span>'}[processing.value]||'<i class="fa-solid fa-circle-info"></i><span>Escolha o processamento para ver os requisitos operacionais.</span>';};rule.addEventListener('change',refresh);processing.addEventListener('change',refresh);refresh();})();
</script>
