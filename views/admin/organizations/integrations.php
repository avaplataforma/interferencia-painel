<?php

declare(strict_types=1);

$asaasSettings = $franchiseAsaasSettings ?? [
    'account_mode' => 'central',
    'environment' => 'production',
    'configured' => false,
    'api_key_last4' => '',
    'webhook_token' => '',
    'is_active' => false,
    'last_test_status' => 'not_tested',
    'last_tested_at' => null,
    'last_test_error' => null,
];
$asaasMode = (string) ($asaasSettings['account_mode'] ?? 'central');
$testLabels = ['not_tested' => 'Não testada', 'pending' => 'Aguardando teste', 'success' => 'Conexão validada', 'failed' => 'Falha no teste'];
$testStatus = (string) ($asaasSettings['last_test_status'] ?? 'not_tested');
?>
<section class="card organization-section franchise-integrations" id="integracoes" data-organization-panel="integracoes" hidden>
 <header class="organization-section-header"><span class="organization-section-icon"><i class="fa-solid fa-plug-circle-bolt"></i></span><div><h2>Integrações da franquia</h2><p class="meta">Defina se as novas operações financeiras usarão a conta central do Mundo Inter ou uma conta Asaas exclusiva desta franquia.</p></div></header>

 <?php if(!($franchiseAsaasEncryptionReady??false)):?><div class="alert alert-warning"><strong>Proteção pendente.</strong> A chave-mestra da plataforma precisa estar configurada antes de armazenar credenciais exclusivas.</div><?php endif;?>

 <form class="franchise-asaas-form" method="post" action="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/integrations/asaas"><?= $csrfField ?>
  <div class="integration-mode-grid">
   <label class="integration-mode <?= $asaasMode==='central'?'selected':'' ?>">
    <input type="radio" name="account_mode" value="central" <?= $asaasMode==='central'?'checked':'' ?>>
    <span class="integration-mode-icon"><i class="fa-solid fa-building-shield"></i></span>
    <span><strong>Conta central Mundo Inter</strong><small>Usa a integração central e mantém o modelo de split e repasses definido no contrato da franquia.</small></span>
   </label>
   <label class="integration-mode <?= $asaasMode==='exclusive'?'selected':'' ?>">
    <input type="radio" name="account_mode" value="exclusive" <?= $asaasMode==='exclusive'?'checked':'' ?>>
    <span class="integration-mode-icon"><i class="fa-solid fa-wallet"></i></span>
    <span><strong>Conta Asaas exclusiva</strong><small>As cobranças novas desta franquia são emitidas diretamente na conta informada abaixo.</small></span>
   </label>
  </div>

  <div class="exclusive-asaas-settings" data-exclusive-asaas-settings <?= $asaasMode==='exclusive'?'':'hidden' ?>>
   <div class="integration-status-row">
    <div><span class="meta">Estado da credencial</span><strong><?= !empty($asaasSettings['configured'])?'Chave terminada em ••••'.$escape((string)$asaasSettings['api_key_last4']):'Nenhuma chave cadastrada' ?></strong></div>
    <span class="connection-badge <?= $testStatus==='success'?'connection-approved':($testStatus==='failed'?'connection-rejected':'connection-pending') ?>"><?= $escape($testLabels[$testStatus]??'Não testada') ?></span>
   </div>
   <div class="organization-fields integration-fields">
    <label>Ambiente *<select name="environment"><option value="production" <?= ($asaasSettings['environment']??'production')==='production'?'selected':'' ?>>Produção</option><option value="sandbox" <?= ($asaasSettings['environment']??'')==='sandbox'?'selected':'' ?>>Sandbox (testes)</option></select><small>A chave precisa pertencer ao ambiente selecionado.</small></label>
    <label>Nova chave da API<?= empty($asaasSettings['configured'])?' *':'' ?><input name="api_key" type="password" autocomplete="new-password" placeholder="<?= empty($asaasSettings['configured'])?'Cole a chave da conta da franquia':'Deixe em branco para manter a chave atual' ?>" <?= !($franchiseAsaasEncryptionReady??false)?'disabled':'' ?>><small>A chave nunca volta a ser exibida depois de salva.</small></label>
    <label class="checkbox-row field-full"><input type="checkbox" name="is_active" value="1" <?= !empty($asaasSettings['is_active'])?'checked':'' ?>> Ativar a conta exclusiva após a validação</label>
   </div>
   <?php if($testStatus==='failed'&&!empty($asaasSettings['last_test_error'])):?><div class="alert alert-danger"><strong>Último teste:</strong> <?= $escape((string)$asaasSettings['last_test_error']) ?></div><?php endif;?>
   <?php if(!empty($asaasSettings['last_tested_at'])):?><p class="meta"><i class="fa-regular fa-clock"></i> Último teste em <?= $escape(date('d/m/Y H:i',strtotime((string)$asaasSettings['last_tested_at']))) ?></p><?php endif;?>
  </div>

  <div class="integration-actions"><p class="meta"><i class="fa-solid fa-shield-halved"></i> A troca só afeta novas operações. O histórico financeiro permanece vinculado à conta que o originou.</p><button class="button button-primary" type="submit" <?= !($franchiseAsaasEncryptionReady??false)&&$asaasMode==='exclusive'?'disabled':'' ?>><i class="fa-solid fa-floppy-disk"></i> Salvar integração</button></div>
 </form>

 <?php if($asaasMode==='exclusive'&&!empty($asaasSettings['configured'])):?>
 <div class="integration-webhook">
  <div><span class="integration-mode-icon"><i class="fa-solid fa-code-branch"></i></span><div><h3>Webhook exclusivo</h3><p class="meta">Cadastre estes dados na conta Asaas da franquia para receber pagamentos e atualizações.</p></div></div>
  <label>URL do webhook<div class="d-flex gap-2"><input id="franchise-asaas-webhook-url" readonly value="<?= $escape((string)($franchiseAsaasWebhookUrl??'')) ?>"><button class="button button-secondary" type="button" data-copy-target="franchise-asaas-webhook-url" title="Copiar URL"><i class="fa-regular fa-copy"></i></button></div></label>
  <label>Token de autenticação<div class="d-flex gap-2"><input id="franchise-asaas-webhook-token" type="password" readonly autocomplete="off" value="<?= $escape((string)($asaasSettings['webhook_token']??'')) ?>"><button class="button button-secondary" type="button" data-copy-target="franchise-asaas-webhook-token" title="Copiar token"><i class="fa-regular fa-copy"></i></button></div></label>
  <form method="post" action="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/integrations/asaas/test"><?= $csrfField ?><button class="button button-secondary" type="submit"><i class="fa-solid fa-plug-circle-check"></i> Testar conexão</button></form>
 </div>
 <?php endif;?>

 <div class="integration-note"><i class="fa-solid fa-circle-info"></i><div><strong>Regra de segurança</strong><p>Enquanto a conta exclusiva não estiver ativa e validada, a franquia continua usando a integração central. Assim uma chave incorreta nunca interrompe novas matrículas ou cobranças.</p></div></div>
</section>

<style>
.integration-mode-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;margin:1rem 0}.integration-mode{display:grid;grid-template-columns:auto auto 1fr;align-items:start;gap:.8rem;padding:1.1rem;border:1px solid #d7e0e7;border-radius:1rem;background:#fff;cursor:pointer;transition:.18s ease}.integration-mode:hover{border-color:#f2a3a8;transform:translateY(-1px)}.integration-mode.selected{border-color:var(--inter-accent);background:#fff7f7;box-shadow:0 .35rem 1.2rem rgb(237 28 36 / 9%)}.integration-mode>input{margin-top:.35rem}.integration-mode-icon{display:inline-flex;align-items:center;justify-content:center;width:2.65rem;height:2.65rem;border-radius:.8rem;background:#ffecee;color:var(--inter-accent)}.integration-mode strong,.integration-mode small{display:block}.integration-mode small{margin-top:.3rem;color:#5f6d7b;line-height:1.45}.exclusive-asaas-settings{padding:1rem;border:1px solid #dce3e8;border-radius:1rem;background:#f8fafb}.exclusive-asaas-settings[hidden]{display:none!important}.integration-status-row{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem}.integration-status-row strong,.integration-status-row span{display:block}.integration-fields{margin-top:0}.integration-actions{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-top:1rem}.integration-actions p{margin:0;max-width:52rem}.integration-webhook{display:grid;grid-template-columns:minmax(14rem,1fr) minmax(18rem,1.25fr) minmax(18rem,1.25fr) auto;align-items:end;gap:1rem;margin-top:1rem;padding:1rem;border:1px solid #dce3e8;border-radius:1rem;background:#fff}.integration-webhook>div:first-child{display:flex;align-items:center;gap:.7rem}.integration-webhook h3,.integration-webhook p{margin:0}.integration-webhook label{min-width:0}.integration-webhook input{width:100%}.integration-note{display:flex;gap:.8rem;margin-top:1rem;padding:1rem;border-radius:.85rem;background:#eef6ff;color:#183e65}.integration-note p{margin:.2rem 0 0}@media(max-width:960px){.integration-webhook{grid-template-columns:1fr 1fr}.integration-webhook>div:first-child{grid-column:1/-1}}@media(max-width:720px){.integration-mode-grid,.integration-webhook{grid-template-columns:1fr}.integration-actions,.integration-status-row{align-items:stretch;flex-direction:column}.integration-actions .button{width:100%}}
</style>

<script>
(() => {
 const modes=Array.from(document.querySelectorAll('input[name="account_mode"]'));
 const settings=document.querySelector('[data-exclusive-asaas-settings]');
 const refresh=()=>{const exclusive=modes.some(input=>input.checked&&input.value==='exclusive');if(settings instanceof HTMLElement)settings.hidden=!exclusive;modes.forEach(input=>input.closest('.integration-mode')?.classList.toggle('selected',input.checked));};
 modes.forEach(input=>input.addEventListener('change',refresh));refresh();
})();
</script>
