<?php

$labels = [
    'invited' => 'Convite aguardando',
    'submitted' => 'Aguardando análise',
    'reviewing' => 'Em análise',
    'approved' => 'Aprovada',
    'rejected' => 'Rejeitada',
    'cancelled' => 'Cancelada',
];
$contractLabels = [
    'draft' => 'Rascunho',
    'sent' => 'Enviado',
    'viewed' => 'Visualizado',
    'signed' => 'Assinado',
    'cancelled' => 'Cancelado',
];
$done = !empty($application['organization_id']);
$applicationStatus = $labels[$application['status']] ?? $application['status'];
$applicationBadge = $application['status'] === 'approved'
    ? 'badge-success'
    : (in_array($application['status'], ['rejected', 'cancelled'], true) ? 'badge-danger' : 'badge-warning');

$latestContract = $contracts[0] ?? null;
$hasSignedContract = false;
foreach ($contracts as $contract) {
    if (($contract['status'] ?? '') === 'signed') {
        $hasSignedContract = true;
        break;
    }
}
$contractSummary = $latestContract === null
    ? 'A definir'
    : ($contractLabels[$latestContract['status']] ?? $latestContract['status']);
$signatureSummary = $hasSignedContract ? 'Concluída' : ($latestContract === null ? 'Após contrato' : 'Pendente');

$addressMain = trim(implode(', ', array_filter([
    trim((string) ($application['address'] ?? '')),
    trim((string) ($application['address_number'] ?? '')),
])));
$addressDetails = implode(' · ', array_filter([
    trim((string) ($application['address_complement'] ?? '')),
    trim((string) ($application['neighborhood'] ?? '')),
]));
$addressLocality = implode(' · ', array_filter([
    trim((string) ($application['postal_code'] ?? '')),
    trim(implode(' / ', array_filter([
        trim((string) ($application['city'] ?? '')),
        trim((string) ($application['state'] ?? '')),
    ]))),
]));
?>

<header class="page-header application-detail-header">
  <div>
    <p class="eyebrow">Franquias · Solicitação #<?= (int) $application['id'] ?></p>
    <h1><?= $escape($application['display_name'] ?: 'Convite ainda não preenchido') ?></h1>
    <span class="badge <?= $applicationBadge ?>"><?= $escape($applicationStatus) ?></span>
  </div>
  <div class="page-actions">
    <a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/franchise-applications">
      <i class="fa-solid fa-arrow-left"></i> Voltar
    </a>
  </div>
</header>

<?php if (!empty($message)): ?>
  <div class="alert alert-success"><?= $escape($message) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?= $escape($error) ?></div>
<?php endif; ?>

<?php if (!empty($application['ticket_id'])): ?>
  <section class="card application-ticket-card mb-4">
    <div>
      <p class="eyebrow">Atendimento interno</p>
      <h2>Ticket #F-<?= (int) $application['ticket_id'] ?></h2>
      <p class="meta">Conferência, negociação, contrato e ativação acompanhados pela equipe central.</p>
    </div>
    <a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/tickets?q=F-<?= (int) $application['ticket_id'] ?>">
      <i class="fa-solid fa-ticket"></i> Abrir no Tickets
    </a>
  </section>
<?php endif; ?>

<section class="platform-summary application-summary">
  <article>
    <span class="platform-summary-icon"><i class="fa-solid fa-building"></i></span>
    <div><small>Cadastro</small><strong><?= $application['submitted_at'] ? 'Recebido' : 'Pendente' ?></strong></div>
  </article>
  <article>
    <span class="platform-summary-icon"><i class="fa-solid fa-file-signature"></i></span>
    <div><small>Contrato</small><strong><?= $escape($contractSummary) ?></strong></div>
  </article>
  <article>
    <span class="platform-summary-icon"><i class="fa-solid fa-signature"></i></span>
    <div><small>Assinatura</small><strong><?= $escape($signatureSummary) ?></strong></div>
  </article>
  <article>
    <span class="platform-summary-icon"><i class="fa-solid fa-money-bill-transfer"></i></span>
    <div><small>Cobrança</small><strong><?= (int) $application['billing_required'] === 1 ? 'Prevista' : 'Opcional' ?></strong></div>
  </article>
</section>

<section class="card application-data-card mt-4">
  <header class="application-section-header">
    <div>
      <p class="eyebrow">Cadastro externo</p>
      <h2>Dados recebidos</h2>
      <p class="meta">Informações enviadas pela futura franquia para conferência.</p>
    </div>
  </header>

  <dl class="application-data-grid">
    <div>
      <dt>Razão social</dt>
      <dd><?= $escape($application['legal_name'] ?: '—') ?></dd>
    </div>
    <div>
      <dt>CNPJ</dt>
      <dd><?= $escape($application['cnpj'] ?: '—') ?></dd>
    </div>
    <div>
      <dt>Gestor responsável</dt>
      <dd class="application-contact-value">
        <strong><?= $escape($application['manager_name'] ?: '—') ?></strong>
        <?php if (!empty($application['manager_email'])): ?><span><i class="fa-solid fa-envelope fa-fw"></i><?= $escape($application['manager_email']) ?></span><?php endif; ?>
        <?php if (!empty($application['manager_phone'])): ?><span><i class="fa-solid fa-phone fa-fw"></i><?= $escape($application['manager_phone']) ?></span><?php endif; ?>
      </dd>
    </div>
    <div>
      <dt>Gerente operacional</dt>
      <dd class="application-contact-value">
        <strong><?= $escape($application['general_manager_name'] ?: 'Não informado') ?></strong>
        <?php if (!empty($application['general_manager_email'])): ?><span><i class="fa-solid fa-envelope fa-fw"></i><?= $escape($application['general_manager_email']) ?></span><?php endif; ?>
        <?php if (!empty($application['general_manager_phone'])): ?><span><i class="fa-solid fa-phone fa-fw"></i><?= $escape($application['general_manager_phone']) ?></span><?php endif; ?>
      </dd>
    </div>
    <div>
      <dt>Endereço</dt>
      <dd class="application-address-value">
        <strong><?= $escape($addressMain ?: 'Não informado') ?></strong>
        <?php if ($addressDetails !== ''): ?><span><?= $escape($addressDetails) ?></span><?php endif; ?>
        <?php if ($addressLocality !== ''): ?><span><?= $escape($addressLocality) ?></span><?php endif; ?>
      </dd>
    </div>
    <div>
      <dt>Site atual</dt>
      <dd>
        <?php if (!empty($application['site_host'])): ?>
          <a href="https://<?= $escape(preg_replace('#^https?://#i', '', (string) $application['site_host'])) ?>" target="_blank" rel="noopener noreferrer">
            <?= $escape($application['site_host']) ?> <i class="fa-solid fa-arrow-up-right-from-square"></i>
          </a>
        <?php else: ?>—<?php endif; ?>
      </dd>
    </div>
  </dl>

  <?php if (!empty($application['negotiation_notes'])): ?>
    <div class="application-notes">
      <h3>Observações</h3>
      <p><?= nl2br($escape($application['negotiation_notes'])) ?></p>
    </div>
  <?php endif; ?>
</section>

<?php if (!$done && $application['status'] !== 'invited'): ?>
  <div class="row mt-4">
    <section class="col-sm-6">
      <div class="card h-100 p-4">
        <h2>Análise</h2>
        <form method="post" action="<?= $escape($basePath) ?>/admin/franchise-applications/<?= (int) $application['id'] ?>/status">
          <?= $csrfField ?>
          <label>Situação
            <select name="status" required>
              <option value="reviewing">Em análise</option>
              <option value="rejected">Rejeitada</option>
              <option value="cancelled">Cancelada</option>
            </select>
          </label>
          <button class="button button-secondary" type="submit">Atualizar situação</button>
        </form>
      </div>
    </section>
    <section class="col-sm-6">
      <div class="card h-100 p-4">
        <h2>Aprovar e criar franquia</h2>
        <p class="meta">O cadastro oficial só nasce após esta confirmação.</p>
        <form method="post" action="<?= $escape($basePath) ?>/admin/franchise-applications/<?= (int) $application['id'] ?>/approve">
          <?= $csrfField ?>
          <label>Código interno *<input required name="code" pattern="[a-z0-9_-]{3,80}" placeholder="franquia_tijucas"></label>
          <label>Endereço privado *
            <div class="input-prefix"><span>mundointer.com.br/</span><input required name="panel_slug" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="franquia-tijucas"></div>
          </label>
          <p class="alert alert-info mb-0"><i class="fa-solid fa-shield-halved"></i> A franquia será criada suspensa e liberada somente após concluir o fluxo de implantação.</p>
          <button class="button button-primary" type="submit" data-confirm-submit="Aprovar esta solicitação e criar a franquia?">
            <i class="fa-solid fa-circle-check"></i> Aprovar solicitação
          </button>
        </form>
      </div>
    </section>
  </div>
<?php elseif ($done): ?>
  <div class="alert alert-success mt-4">
    Franquia criada: <strong><?= $escape($application['organization_name']) ?></strong>.
    <a href="<?= $escape($basePath) ?>/admin/organizations/<?= (int) $application['organization_id'] ?>">Abrir cadastro oficial</a>.
  </div>
<?php endif; ?>

<?php if ($application['status'] !== 'invited'): ?>
  <section class="card application-contracts mt-4">
    <header class="application-section-header application-contracts-header">
      <div>
        <p class="eyebrow">Histórico documental</p>
        <h2>Contratos</h2>
        <p class="meta">Documentos gerados e preservados para esta negociação.</p>
      </div>
      <a class="button button-primary" href="<?= $escape($basePath) ?>/admin/franchise-applications/<?= (int) $application['id'] ?>/contracts/create">
        <i class="fa-solid fa-file-circle-plus"></i> Gerar contrato
      </a>
    </header>

    <?php if ($contracts === []): ?>
      <div class="application-empty-state">
        <i class="fa-solid fa-file-lines"></i>
        <strong>Nenhum contrato gerado</strong>
        <span>Use o botão acima para iniciar o primeiro documento.</span>
      </div>
    <?php else: ?>
      <div class="application-table-wrap">
        <table>
          <thead><tr><th>Contrato</th><th>Situação</th><th>Gerado</th><th>Ação</th></tr></thead>
          <tbody>
          <?php foreach ($contracts as $contract): ?>
            <?php
            $contractStatus = $contractLabels[$contract['status']] ?? $contract['status'];
            $contractBadge = $contract['status'] === 'signed'
                ? 'badge-success'
                : ($contract['status'] === 'cancelled' ? 'badge-danger' : 'badge-warning');
            ?>
            <tr>
              <td><span class="table-cell-stack"><strong><?= $escape($contract['title']) ?></strong><small>Contrato #<?= (int) ($contract['contract_number'] ?? 1) ?></small></span></td>
              <td><span class="badge <?= $contractBadge ?>"><?= $escape($contractStatus) ?></span></td>
              <td><?= $escape(date('d/m/Y H:i', strtotime((string) $contract['created_at']))) ?></td>
              <td><a class="button button-secondary button-small" href="<?= $escape($basePath) ?>/admin/franchise-contracts/<?= (int) $contract['id'] ?>"><i class="fa-solid fa-eye"></i> Abrir</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<style>
  .application-detail-header h1 { margin-bottom: .55rem; }
  .application-ticket-card { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.2rem 1.3rem; border: 1px solid #dfe5e9; }
  .application-ticket-card h2 { margin: .15rem 0 .25rem; font-size: 1.25rem; }
  .application-ticket-card p { margin: 0; }
  .application-summary strong { overflow-wrap: anywhere; font-size: clamp(1.2rem, 1.8vw, 1.65rem); line-height: 1.15; }
  .application-data-card, .application-contracts { overflow: hidden; border: 1px solid #dfe5e9; box-shadow: 0 .35rem 1.1rem rgb(23 33 43 / 5%); }
  .application-section-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1.3rem 1.4rem; }
  .application-section-header h2 { margin: .15rem 0 .25rem; font-size: 1.7rem; }
  .application-section-header p { margin: 0; }
  .application-data-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); margin: 0; border-top: 1px solid #e3e8eb; }
  .application-data-grid > div { display: grid; align-content: start; gap: .38rem; min-width: 0; min-height: 7rem; padding: 1.1rem 1.25rem; border-bottom: 1px solid #e3e8eb; }
  .application-data-grid > div:nth-child(odd) { border-right: 1px solid #e3e8eb; }
  .application-data-grid dt { color: var(--inter-muted); font-size: .8rem; font-weight: 600; }
  .application-data-grid dd { display: grid; gap: .25rem; margin: 0; overflow-wrap: anywhere; color: var(--inter-ink); font-size: .95rem; font-weight: 650; line-height: 1.4; }
  .application-data-grid dd > strong { display: block; font-size: 1rem; }
  .application-data-grid dd > span { display: flex; align-items: flex-start; gap: .35rem; color: var(--inter-muted); font-size: .85rem; font-weight: 450; }
  .application-data-grid dd > span i { margin-top: .22rem; color: var(--inter-accent); }
  .application-data-grid a { width: fit-content; overflow-wrap: anywhere; }
  .application-notes { margin: 1.2rem 1.4rem 1.4rem; padding: 1rem; border-radius: .7rem; background: #f7f9fa; }
  .application-notes h3 { margin: 0 0 .35rem; font-size: 1rem; }
  .application-notes p { margin: 0; color: #4e5d69; }
  .application-contracts { padding-bottom: 1rem; }
  .application-contracts-header { align-items: center; border-bottom: 1px solid #e3e8eb; }
  .application-table-wrap { overflow-x: auto; padding: 0 1.25rem; }
  .application-contracts table { min-width: 38rem; margin: 0; }
  .application-empty-state { display: grid; place-items: center; gap: .35rem; min-height: 11rem; padding: 2rem; color: var(--inter-muted); text-align: center; }
  .application-empty-state i { color: #b6c0c8; font-size: 2rem; }
  .application-empty-state strong { color: var(--inter-ink); }
  @media (max-width: 767.98px) {
    .application-ticket-card, .application-section-header { align-items: stretch; flex-direction: column; }
    .application-data-grid { grid-template-columns: 1fr; }
    .application-data-grid > div:nth-child(odd) { border-right: 0; }
    .application-contracts-header .button { width: 100%; justify-content: center; }
  }
</style>
