<?php

declare(strict_types=1);

/** @var Closure(mixed): string $escape */
?>
<?php if ($message ?? null): ?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif; ?>
<?php if ($error ?? null): ?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif; ?>
<span class="text-success fw-semibold">Visão geral</span>
<h1 class="mt-2">Olá, <?= $escape($user->name) ?></h1>
<p class="text-secondary">Acesse rapidamente as áreas administrativas do PAINEL INTER.</p>
<div class="row g-3 mt-2">
  <div class="col-sm-6 col-xl-4"><div class="card h-100 border-0 shadow-sm"><div class="card-body"><div class="text-secondary small">Unidade ativa</div><h2 class="h5 mt-2 mb-0"><?= $escape($currentUnit['name'] ?? 'Nenhuma unidade') ?></h2></div></div></div>
  <div class="col-sm-6 col-xl-4"><div class="card h-100 border-0 shadow-sm"><div class="card-body"><div class="text-secondary small">Unidades permitidas</div><h2 class="display-6 mb-0"><?= $escape((string) count($unitScopes)) ?></h2></div></div></div>
  <div class="col-sm-6 col-xl-4"><div class="card h-100 border-0 shadow-sm"><div class="card-body"><div class="text-secondary small">Sessão</div><h2 class="h5 mt-2 mb-0">Protegida e ativa</h2></div></div></div>
</div>
<?php if($followUpSummary!==null):?><h2 class="h5 mt-4">Agenda de follow-ups</h2><div class="row g-3"><div class="col-md-4"><a class="followup-card overdue" href="<?= $escape($basePath) ?>/crm/follow-ups?status=pending&amp;period=overdue"><span>Atrasados</span><strong><?= $escape($followUpSummary['overdue']) ?></strong></a></div><div class="col-md-4"><a class="followup-card today" href="<?= $escape($basePath) ?>/crm/follow-ups?status=pending&amp;period=today"><span>Para hoje</span><strong><?= $escape($followUpSummary['today']) ?></strong></a></div><div class="col-md-4"><a class="followup-card future" href="<?= $escape($basePath) ?>/crm/follow-ups?status=pending&amp;period=future"><span>Futuros</span><strong><?= $escape($followUpSummary['future']) ?></strong></a></div></div><?php endif;?>
<?php if($newContacts!==null):?>
<section class="dashboard-contacts mt-4">
  <div class="actions"><div><h2 class="h5 mb-0">Novos contatos</h2><p class="meta mb-0">Contatos que ainda estão no status Novo.</p></div><a href="<?= $escape($basePath) ?>/crm/contacts">Ver todos os contatos</a></div>
  <div class="row g-3 mt-2">
    <div class="col-sm-6 col-xl-3"><a class="source-card source-total" href="<?= $escape($basePath) ?>/"><span>Todos</span><strong><?= $escape($newContacts['total']) ?></strong></a></div>
    <div class="col-sm-6 col-xl-3"><a class="source-card" href="<?= $escape($basePath) ?>/?source=internal"><span><i class="fa-solid fa-user-pen" aria-hidden="true"></i> Cadastro interno</span><strong><?= $escape($newContacts['internal']) ?></strong></a></div>
    <div class="col-sm-6 col-xl-3"><a class="source-card" href="<?= $escape($basePath) ?>/?source=external_form"><span><i class="fa-solid fa-globe" aria-hidden="true"></i> Sites externos</span><strong><?= $escape($newContacts['external_form']) ?></strong></a></div>
    <div class="col-sm-6 col-xl-3"><a class="source-card" href="<?= $escape($basePath) ?>/?source=whatsapp"><span><i class="fa-solid fa-comments" aria-hidden="true"></i> WhatsApp</span><strong><?= $escape($newContacts['whatsapp']) ?></strong></a></div>
  </div>
  <form class="dashboard-contact-filters" method="get" action="<?= $escape($basePath) ?>/">
    <label>Tipo de cadastro<select class="form-select" name="source"><option value="" <?= $selectedSource===''?'selected':'' ?>>Todos os tipos</option><option value="internal" <?= $selectedSource==='internal'?'selected':'' ?>>Cadastro interno</option><option value="external_form" <?= $selectedSource==='external_form'?'selected':'' ?>>Sites externos</option><option value="whatsapp" <?= $selectedSource==='whatsapp'?'selected':'' ?>>WhatsApp</option></select></label>
    <label>Etiqueta / site<select class="form-select" name="tag"><option value="0">Todas as etiquetas</option><?php foreach($contactTags as $tag):?><option value="<?= $escape($tag['id']) ?>" <?= $selectedTag===(int)$tag['id']?'selected':'' ?>><?= $escape($tag['name']) ?></option><?php endforeach;?></select></label>
    <button type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Filtrar</button>
    <a class="button-secondary" href="<?= $escape($basePath) ?>/">Limpar</a>
  </form>
  <div class="table-responsive dashboard-contact-table"><table><thead><tr><th>Contato</th><?php if($allUnits):?><th>Unidade</th><?php endif;?><th>Curso</th><th>Origem</th><th>Etiqueta / site</th><th>Entrada</th></tr></thead><tbody>
  <?php if($newContacts['items']===[]):?><tr><td colspan="<?= $allUnits?'6':'5' ?>"><div class="empty-dashboard"><i class="fa-solid fa-inbox" aria-hidden="true"></i><span>Nenhum contato novo encontrado com estes filtros.</span></div></td></tr><?php endif;?>
  <?php foreach($newContacts['items'] as $item):?><?php $sourceLabels=['internal'=>'Cadastro interno','external_form'=>'Site externo','whatsapp'=>'WhatsApp'];?><tr><td><span class="new-contact-name"><span class="new-dot" aria-label="Novo"></span><strong><?= $escape($item['name']) ?></strong></span><br><span class="meta"><?= $escape($item['phone']?:'Sem telefone') ?></span></td><?php if($allUnits):?><td><?= $escape($item['unit_name']) ?></td><?php endif;?><td><?= $escape($item['course']?:'—') ?></td><td><span class="source-badge source-<?= $escape($item['registration_source']) ?>"><?= $escape($sourceLabels[$item['registration_source']]??$item['registration_source']) ?></span></td><td><div class="tag-list"><?php foreach(array_filter(explode(';;',(string)($item['tags_data']??''))) as $tagData):?><?php [$tagName,$tagColor]=array_pad(explode('|',$tagData,2),2,'#64748b');?><span class="tag-badge" style="--tag-color:<?= $escape($tagColor) ?>"><?= $escape($tagName) ?></span><?php endforeach;?><?php if(empty($item['tags_data'])):?><span class="meta">—</span><?php endif;?></div></td><td><?= $escape(date('d/m/Y H:i',strtotime((string)$item['registered_at']))) ?></td></tr><?php endforeach;?>
  </tbody></table></div>
</section>
<?php endif;?>
<h2 class="h5 mt-4">Atalhos</h2>
<div class="row g-3">
  <?php if ($canManageUsers): ?><div class="col-md-4"><a class="quick-link" href="<?= $escape($basePath) ?>/users"><span class="quick-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span><strong>Usuários</strong><span>Contas, perfis e unidades permitidas</span></a></div><?php endif; ?>
  <?php if ($canManageUnits): ?><div class="col-md-4"><a class="quick-link" href="<?= $escape($basePath) ?>/units"><span class="quick-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></span><strong>Unidades</strong><span>Cadastro e situação operacional</span></a></div><?php endif; ?>
  <?php if ($canManageRoles): ?><div class="col-md-4"><a class="quick-link" href="<?= $escape($basePath) ?>/roles"><span class="quick-icon"><i class="fa-solid fa-user-shield" aria-hidden="true"></i></span><strong>Perfis</strong><span>Permissões e níveis de acesso</span></a></div><?php endif; ?>
</div>
