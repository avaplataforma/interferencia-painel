<div class="page-header"><div><p class="eyebrow">Mundo Inter</p><h1>Tickets</h1><p>Solicitações e chamados das franquias para a equipe central.</p></div></div>
<section class="platform-summary" aria-label="Resumo dos tickets">
  <article><span class="platform-summary-icon"><i class="fa-solid fa-ticket"></i></span><div><small>Total</small><strong><?= (int)$summary['total'] ?></strong></div></article>
  <article><span class="platform-summary-icon"><i class="fa-solid fa-inbox"></i></span><div><small>Em aberto</small><strong><?= (int)$summary['open'] ?></strong></div></article>
  <article><span class="platform-summary-icon"><i class="fa-solid fa-clock"></i></span><div><small>Atrasados</small><strong><?= (int)$summary['overdue'] ?></strong></div></article>
  <article><span class="platform-summary-icon"><i class="fa-solid fa-circle-check"></i></span><div><small>Concluídos</small><strong><?= (int)$summary['completed'] ?></strong></div></article>
</section>
<form class="ticket-filters mt-4" method="get" action="<?= $escape($basePath) ?>/admin/tickets">
  <input type="search" name="q" value="<?= $escape($search) ?>" placeholder="Franquia, assunto ou solicitante">
  <select name="status"><option value="">Todas as situações</option><option value="open" <?= $status==='open'?'selected':'' ?>>Aberto</option><option value="in_progress" <?= $status==='in_progress'?'selected':'' ?>>Em andamento</option><option value="waiting" <?= $status==='waiting'?'selected':'' ?>>Aguardando</option><option value="resolved" <?= $status==='resolved'?'selected':'' ?>>Resolvido</option><option value="closed" <?= $status==='closed'?'selected':'' ?>>Fechado</option></select>
  <button class="button button-primary" type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button>
</form>
<div class="table-card"><table><thead><tr><th>Ticket</th><th>Franquia</th><th>Solicitante</th><th>Setor</th><th>Prioridade</th><th>Situação</th><th>Atualizado</th></tr></thead><tbody>
<?php $statusLabels=['open'=>'Aberto','in_progress'=>'Em andamento','waiting'=>'Aguardando','resolved'=>'Resolvido','closed'=>'Fechado'];$priorityLabels=['low'=>'Baixa','normal'=>'Normal','high'=>'Alta','urgent'=>'Urgente']; ?>
<?php foreach($tickets as$ticket):?><tr><td><strong>#<?= (int)$ticket['id'] ?> · <?= $escape($ticket['subject']) ?></strong><br><small><?= $escape($ticket['unit_name']) ?></small></td><td><?= $escape($ticket['organization_name']) ?></td><td><?= $escape($ticket['requester_name']) ?></td><td><?= $escape($ticket['department_name']) ?></td><td><?= $escape($priorityLabels[$ticket['priority']]??$ticket['priority']) ?></td><td><?= $escape($statusLabels[$ticket['status']]??$ticket['status']) ?></td><td><?= $escape(date('d/m/Y H:i',strtotime((string)$ticket['updated_at']))) ?></td></tr><?php endforeach; ?>
<?php if($tickets===[]):?><tr><td colspan="7">Nenhum ticket encontrado.</td></tr><?php endif;?></tbody></table></div>
