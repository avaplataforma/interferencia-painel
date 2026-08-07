<div class="page-header"><div><p class="eyebrow">ADM Central</p><h1>Integrações</h1><p>Conexões utilizadas pela operação central do Mundo Inter.</p></div></div>
<div class="row">
  <div class="col-sm-6"><a class="quick-link" href="<?= $escape($basePath) ?>/admin/platform/integrations/asaas"><span class="quick-icon"><i class="fa-solid fa-wallet"></i></span><strong>Asaas</strong><small>Cobranças das franquias, contratos, webhooks e split de pagamentos.</small><span class="connection-badge <?= $asaasConfigured&&$asaasActive?'connection-approved':'connection-pending' ?>"><?= $asaasConfigured&&$asaasActive?'Ativa':'Configuração pendente' ?></span></a></div>
  <div class="col-sm-6"><article class="quick-link"><span class="quick-icon"><i class="fa-solid fa-plus"></i></span><strong>Novas integrações</strong><small>Este espaço receberá outros gateways e serviços da rede.</small><span class="connection-badge">Em preparação</span></article></div>
</div>
