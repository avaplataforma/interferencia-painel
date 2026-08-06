<div class="page-heading"><div><p class="eyebrow">Alunos</p><h1>Pedagógico</h1><p class="meta">Acompanhe progresso, acesso ao AVA e ações acadêmicas das unidades permitidas.</p></div><?php if($canManage):?><form method="post" action="<?= $escape($basePath) ?>/students/pedagogical/sync"><?= $csrfField ?><button class="button-secondary" type="submit"><i class="fa-solid fa-rotate"></i> Atualizar acompanhamento</button></form><?php endif;?></div>
<?php if($message):?><p class="alert alert-success"><?= $escape($message) ?></p><?php endif;?><?php if($error):?><p class="alert alert-danger"><?= $escape($error) ?></p><?php endif;?>
<div class="dashboard-grid mb-4">
  <article class="metric-card"><span>Alunos</span><strong><?= (int)$summary['students'] ?></strong><small>com matrícula no Painel</small></article>
  <article class="metric-card"><span>Matrículas</span><strong><?= (int)$summary['enrolments'] ?></strong><small>cursos contratados</small></article>
  <article class="metric-card"><span>Acessos liberados</span><strong><?= (int)$summary['released'] ?></strong><small>disponíveis no AVA</small></article>
  <article class="metric-card"><span>Pendências</span><strong><?= (int)$summary['pending'] ?></strong><small>aguardando liberação</small></article>
</div>
<section class="card"><div class="card-body">
  <form class="finance-search-row mb-3" method="get" action="<?= $escape($basePath) ?>/students/pedagogical"><input name="q" value="<?= $escape($search) ?>" placeholder="Aluno, CPF ou curso"><button class="button-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Pesquisar</button></form>
  <div class="table-responsive"><table><thead><tr><th>Aluno</th><th>Curso</th><th>Unidade</th><th>Progresso</th><th>Último acesso</th><th>Acesso AVA</th><th>Ações</th></tr></thead><tbody>
  <?php if($students===[]):?><tr><td colspan="7">Nenhum aluno encontrado.</td></tr><?php endif;?>
  <?php foreach($students as$item):?><?php $last=(int)($item['last_access']??0);$percent=$item['completion_percent']===null?null:max(0,min(100,(float)$item['completion_percent']));$suspended=(int)($item['suspended']??0)===1;?>
    <tr>
      <td><strong><?= $escape($item['name']) ?></strong><div class="meta"><?= $escape($item['cpf_cnpj']?:'CPF não informado') ?></div></td>
      <td><?= $escape($item['course_name']) ?></td><td><?= $escape($item['unit_name']) ?></td>
      <td><?php if($percent===null):?><span class="meta">Não consultado</span><?php else:?><div style="min-width:120px"><div style="height:8px;background:#e5e7eb;border-radius:999px;overflow:hidden"><span style="display:block;height:100%;width:<?= $percent ?>%;background:#ed1c24"></span></div><small><?= number_format($percent,0,',','.') ?>% · <?= $escape(match($item['completion_status']){'completed'=>'Concluído','in_progress'=>'Em andamento','not_started'=>'Não iniciado',default=>'Indisponível'}) ?></small></div><?php endif;?></td>
      <td><?= $last>0?$escape(date('d/m/Y H:i',$last)):'Ainda não acessou' ?></td>
      <td><?php if($suspended):?><span class="connection-badge connection-error"><i class="fa-solid fa-lock"></i> Bloqueado</span><?php elseif($item['moodle_enrolment_status']==='released'):?><span class="connection-badge connection-connected"><i class="fa-solid fa-circle-check"></i> Liberado</span><?php else:?><span class="connection-badge connection-awaiting_official_api"><i class="fa-solid fa-clock"></i> Pendente</span><?php endif;?></td>
      <td><div class="table-actions">
        <a class="button-secondary button-small" href="<?= $escape($basePath) ?>/finance/customers/<?= (int)$item['customer_id'] ?>" title="Abrir cadastro" aria-label="Abrir cadastro"><i class="fa-solid fa-eye"></i></a>
        <?php if($navigation['tickets_create']??false):?><a class="button-secondary button-small" href="<?= $escape($basePath) ?>/tickets/create?student=<?= (int)$item['customer_id'] ?>" title="Abrir ticket para este aluno" aria-label="Abrir ticket"><i class="fa-solid fa-ticket"></i></a><?php endif;?>
        <?php if($canManage):?><a class="button-secondary button-small" href="<?= $escape($basePath) ?>/students/enrollments/create?student=<?= (int)$item['customer_id'] ?>" title="Nova matrícula" aria-label="Nova matrícula"><i class="fa-solid fa-graduation-cap"></i></a><?php endif;?>
        <?php if($canManage&&(int)($item['ava_user_id']??0)>0):?><form method="post" action="<?= $escape($basePath) ?>/students/enrollments/<?= (int)$item['enrollment_id'] ?>/ava-status" onsubmit="return confirm('<?= $suspended?'Reativar o acesso deste aluno ao AVA?':'Bloquear este aluno em todo o AVA? Ele não conseguirá acessar nenhum curso.' ?>')"><?= $csrfField ?><input type="hidden" name="status" value="<?= $suspended?'active':'blocked' ?>"><input type="hidden" name="confirm" value="1"><button class="<?= $suspended?'button-secondary':'button-danger' ?> button-small" type="submit" title="<?= $suspended?'Reativar acesso ao AVA':'Bloquear acesso em todo o AVA' ?>" aria-label="<?= $suspended?'Reativar acesso ao AVA':'Bloquear acesso ao AVA' ?>"><i class="fa-solid fa-<?= $suspended?'unlock':'user-lock' ?>"></i></button></form><?php endif;?>
      </div></td>
    </tr>
  <?php endforeach;?>
  </tbody></table></div>
</div></section>
