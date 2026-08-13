<?php

declare(strict_types=1);

/** @var Closure(mixed): string $escape */
$hasAdministration = $canManageUsers || $canManageUnits || $canManageRoles || $canManageTags || $canManageStatuses || $canManageExternalForms || $canManageWhatsAppLines;
$tagSuffix = $selectedTag > 0 ? '&tag=' . $selectedTag : '';
$allSourceUrl = $basePath . '/?' . ($selectedTag > 0 ? 'tag=' . $selectedTag : '');
?>
<?php if ($message ?? null): ?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif; ?>
<?php if ($error ?? null): ?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif; ?>

<header class="dashboard-hero">
  <div>
    <span class="status">Visão geral</span>
    <h1>Olá, <?= $escape($user->name) ?></h1>
    <p>Acompanhe os contatos e as tarefas que precisam de atenção.</p>
  </div>
  <div class="dashboard-context" aria-label="Contexto atual">
    <span><i class="fa-solid fa-building" aria-hidden="true"></i><small>Unidade ativa</small><strong><?= $escape($currentUnit['name'] ?? 'Nenhuma unidade') ?></strong></span>
    <span><i class="fa-solid fa-layer-group" aria-hidden="true"></i><small>Unidades permitidas</small><strong><?= $escape((string) count($unitScopes)) ?></strong></span>
  </div>
</header>

<?php if((int)($studentActionQueue['total']??0)>0):?>
<style>.dashboard-student-actions{margin-bottom:1.4rem;padding:1.15rem;border:1px solid #dce3e8;border-radius:1rem;background:#fff;box-shadow:0 .5rem 1.6rem rgba(15,23,42,.04)}.dashboard-student-actions header{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:.85rem}.dashboard-student-actions h2{margin:0;font-size:1.35rem}.dashboard-student-actions header p{margin:.2rem 0 0}.dashboard-action-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.65rem}.dashboard-action-item{display:grid;grid-template-columns:2.55rem minmax(0,1fr) auto;align-items:center;gap:.65rem;padding:.75rem;border:1px solid #e1e7ec;border-radius:.8rem;color:var(--inter-ink);text-decoration:none}.dashboard-action-item:hover{border-color:var(--inter-accent);background:#fff8f8}.dashboard-action-item>i:first-child{display:grid;width:2.55rem;height:2.55rem;place-items:center;border-radius:.7rem;background:#fff0f1;color:var(--inter-accent)}.dashboard-action-item strong,.dashboard-action-item small{display:block}.dashboard-action-item small{color:var(--inter-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}@media(max-width:900px){.dashboard-action-grid{grid-template-columns:1fr 1fr}}@media(max-width:600px){.dashboard-student-actions header{align-items:flex-start;flex-direction:column}.dashboard-action-grid{grid-template-columns:1fr}}</style>
<section class="dashboard-student-actions"><header><div><span class="section-eyebrow">Alunos</span><h2>Próximas ações</h2><p class="meta"><?= (int)$studentActionQueue['total'] ?> aluno(s) precisam de uma ação da equipe.</p></div><a class="button-secondary" href="<?= $escape($basePath) ?>/students/actions">Ver fila completa <i class="fa-solid fa-arrow-right"></i></a></header><div class="dashboard-action-grid"><?php foreach($studentActionQueue['items'] as$item):?><a class="dashboard-action-item" href="<?= $escape($basePath) ?>/students/<?= (int)$item['student_id'] ?>?tab=journey"><i class="fa-solid <?= $escape((string)$item['icon']) ?>"></i><span><strong><?= $escape((string)$item['student_name']) ?></strong><small><?= $escape((string)$item['label']) ?><?= $item['course_name']!==''?' · '.$escape((string)$item['course_name']):'' ?></small></span><i class="fa-solid fa-chevron-right"></i></a><?php endforeach;?></div></section>
<?php endif;?>

<?php if((int)($siteSettings['is_enabled']??0)===1||($siteOrders??[])!==[]):?>
<?php
$siteOrdersTitle='Pedidos recentes';
$siteOrdersDescription='Compras iniciadas pelo site da franquia e encaminhadas para matrícula.';
$siteOrdersShowActions=false;
$siteOrdersViewAllUrl=$basePath.'/students/site-orders';
require __DIR__.'/site/orders-panel.php';
?>
<?php endif;?>

<?php if ($followUpSummary !== null || $newContacts !== null): ?>
<section class="dashboard-section dashboard-operation">
  <div class="dashboard-section-heading">
    <div><span class="section-eyebrow">Operação</span><h2>Seu trabalho no CRM</h2><p>Informações operacionais visíveis conforme as unidades liberadas para você.</p></div>
    <div class="section-actions"><a class="button-secondary" href="<?= $escape($basePath) ?>/crm/contacts"><i class="fa-solid fa-user-tag" aria-hidden="true"></i> Leads</a><a class="button-secondary" href="<?= $escape($basePath) ?>/crm/follow-ups"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Follow-ups</a></div>
  </div>

  <?php if ($followUpSummary !== null): ?>
  <div class="dashboard-subsection">
    <div class="subsection-title"><div><h3>Agenda de follow-ups</h3><p>Retornos pendentes organizados por prazo.</p></div></div>
    <div class="row g-3">
      <div class="col-md-4"><a class="followup-card overdue" href="<?= $escape($basePath) ?>/crm/follow-ups?status=pending&amp;period=overdue"><span><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Atrasados</span><strong><?= $escape($followUpSummary['overdue']) ?></strong></a></div>
      <div class="col-md-4"><a class="followup-card today" href="<?= $escape($basePath) ?>/crm/follow-ups?status=pending&amp;period=today"><span><i class="fa-solid fa-calendar-day" aria-hidden="true"></i> Para hoje</span><strong><?= $escape($followUpSummary['today']) ?></strong></a></div>
      <div class="col-md-4"><a class="followup-card future" href="<?= $escape($basePath) ?>/crm/follow-ups?status=pending&amp;period=future"><span><i class="fa-solid fa-calendar" aria-hidden="true"></i> Futuros</span><strong><?= $escape($followUpSummary['future']) ?></strong></a></div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($newContacts !== null): ?>
  <div class="dashboard-subsection dashboard-contacts">
    <div class="subsection-title"><div><h3>Novos leads</h3><p>Leads que ainda estão no status Novo.</p></div><a href="<?= $escape($basePath) ?>/crm/contacts">Ver lista completa <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div>

    <nav class="intake-filters" aria-label="Filtrar novos contatos pela origem">
      <a class="intake-filter <?= $selectedSource === '' ? 'active' : '' ?>" href="<?= $escape($allSourceUrl) ?>"><span>Todos</span><strong><?= $escape($newContacts['total']) ?></strong></a>
      <a class="intake-filter <?= $selectedSource === 'internal' ? 'active' : '' ?>" href="<?= $escape($basePath) ?>/?source=internal<?= $escape($tagSuffix) ?>"><span><i class="fa-solid fa-user-pen" aria-hidden="true"></i> Cadastro interno</span><strong><?= $escape($newContacts['internal']) ?></strong></a>
      <a class="intake-filter <?= $selectedSource === 'external_form' ? 'active' : '' ?>" href="<?= $escape($basePath) ?>/?source=external_form<?= $escape($tagSuffix) ?>"><span><i class="fa-solid fa-globe" aria-hidden="true"></i> Sites externos</span><strong><?= $escape($newContacts['external_form']) ?></strong></a>
      <a class="intake-filter <?= $selectedSource === 'whatsapp' ? 'active' : '' ?>" href="<?= $escape($basePath) ?>/?source=whatsapp<?= $escape($tagSuffix) ?>"><span><i class="fa-solid fa-comments" aria-hidden="true"></i> WhatsApp</span><strong><?= $escape($newContacts['whatsapp']) ?></strong></a>
    </nav>

    <form class="dashboard-tag-filter" method="get" action="<?= $escape($basePath) ?>/">
      <?php if ($selectedSource !== ''): ?><input type="hidden" name="source" value="<?= $escape($selectedSource) ?>"><?php endif; ?>
      <label>Filtrar por etiqueta / site<select class="form-select" name="tag"><option value="0">Todas as etiquetas</option><?php foreach ($contactTags as $tag): ?><option value="<?= $escape($tag['id']) ?>" <?= $selectedTag === (int) $tag['id'] ? 'selected' : '' ?>><?= $escape($tag['name']) ?></option><?php endforeach; ?></select></label>
      <button type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Aplicar</button>
      <?php if ($selectedSource !== '' || $selectedTag > 0): ?><a class="button-secondary" href="<?= $escape($basePath) ?>/">Limpar filtros</a><?php endif; ?>
    </form>

    <div class="table-responsive dashboard-contact-table"><table><thead><tr><th>Contato</th><?php if ($allUnits): ?><th>Unidade</th><?php endif; ?><th>Curso</th><th>Origem</th><th>Etiqueta / site</th><th>Entrada</th></tr></thead><tbody>
    <?php if ($newContacts['items'] === []): ?><tr><td colspan="<?= $allUnits ? '6' : '5' ?>"><div class="empty-dashboard"><i class="fa-solid fa-inbox" aria-hidden="true"></i><span>Nenhum contato novo encontrado com estes filtros.</span></div></td></tr><?php endif; ?>
    <?php foreach ($newContacts['items'] as $item): ?><?php $sourceLabels = ['internal' => 'Cadastro interno', 'external_form' => 'Site externo', 'whatsapp' => 'WhatsApp']; ?><tr><td><span class="new-contact-name"><span class="new-dot" aria-label="Novo"></span><a class="contact-name-link" href="<?= $escape($basePath) ?>/crm/contacts/<?= $escape($item['id']) ?>"><strong><?= $escape($item['name']) ?></strong></a></span><br><span class="meta"><?= $escape($item['phone'] ?: 'Sem telefone') ?></span></td><?php if ($allUnits): ?><td><?= $escape($item['unit_name']) ?></td><?php endif; ?><td><?= $escape($item['course'] ?: '—') ?></td><td><span class="source-badge source-<?= $escape($item['registration_source']) ?>"><?= $escape($sourceLabels[$item['registration_source']] ?? $item['registration_source']) ?></span></td><td><div class="tag-list"><?php foreach (array_filter(explode(';;', (string) ($item['tags_data'] ?? ''))) as $tagData): ?><?php [$tagName, $tagColor] = array_pad(explode('|', $tagData, 2), 2, '#64748b'); ?><span class="tag-badge" style="--tag-color:<?= $escape($tagColor) ?>"><?= $escape($tagName) ?></span><?php endforeach; ?><?php if (empty($item['tags_data'])): ?><span class="meta">—</span><?php endif; ?></div></td><td><?= $escape(date('d/m/Y H:i', strtotime((string) $item['registered_at']))) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($hasAdministration): ?>
<section class="dashboard-section dashboard-administration">
  <div class="dashboard-section-heading">
    <div><span class="section-eyebrow"><i class="fa-solid fa-lock" aria-hidden="true"></i> Administração</span><h2>Configurações do sistema</h2><p>Esta área aparece somente conforme as permissões administrativas do usuário.</p></div>
  </div>
  <div class="admin-links">
    <?php if ($canManageUsers): ?><a class="admin-link" href="<?= $escape($basePath) ?>/users"><span class="quick-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span><span><strong>Usuários</strong><small>Contas e acessos</small></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a><?php endif; ?>
    <?php if ($canManageUnits): ?><a class="admin-link" href="<?= $escape($basePath) ?>/units"><span class="quick-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></span><span><strong>Unidades</strong><small>Polos e operação</small></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a><?php endif; ?>
    <?php if ($canManageRoles): ?><a class="admin-link" href="<?= $escape($basePath) ?>/roles"><span class="quick-icon"><i class="fa-solid fa-user-shield" aria-hidden="true"></i></span><span><strong>Perfis</strong><small>Níveis de acesso</small></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a><?php endif; ?>
    <?php if ($canManageTags): ?><a class="admin-link" href="<?= $escape($basePath) ?>/tags"><span class="quick-icon"><i class="fa-solid fa-tags" aria-hidden="true"></i></span><span><strong>Etiquetas</strong><small>Origem e classificação</small></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a><?php endif; ?>
    <?php if ($canManageStatuses): ?><a class="admin-link" href="<?= $escape($basePath) ?>/statuses"><span class="quick-icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span><span><strong>Status do CRM</strong><small>Etapas dos contatos</small></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a><?php endif; ?>
    <?php if ($canManageExternalForms): ?><a class="admin-link" href="<?= $escape($basePath) ?>/external-forms"><span class="quick-icon"><i class="fa-solid fa-globe" aria-hidden="true"></i></span><span><strong>Sites externos</strong><small>Captação e formulários</small></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a><?php endif; ?>
    <?php if ($canManageWhatsAppLines): ?><a class="admin-link" href="<?= $escape($basePath) ?>/whatsapp/lines"><span class="quick-icon"><i class="fa-solid fa-sim-card" aria-hidden="true"></i></span><span><strong>Linhas do WhatsApp</strong><small>Unidades e usuários</small></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a><?php endif; ?>
  </div>
</section>
<?php endif; ?>
