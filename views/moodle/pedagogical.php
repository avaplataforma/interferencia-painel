<div class="page-heading"><div><p class="eyebrow">Alunos</p><h1>Pedagógico</h1><p class="meta">Acompanhe matrículas, acesso ao AVA e atividade acadêmica das unidades permitidas.</p></div></div>
<div class="dashboard-grid mb-4">
  <article class="metric-card"><span>Alunos</span><strong><?= (int)$summary['students'] ?></strong><small>com matrícula no Painel</small></article>
  <article class="metric-card"><span>Matrículas</span><strong><?= (int)$summary['enrolments'] ?></strong><small>cursos contratados</small></article>
  <article class="metric-card"><span>Acessos liberados</span><strong><?= (int)$summary['released'] ?></strong><small>disponíveis no AVA</small></article>
  <article class="metric-card"><span>Pendências</span><strong><?= (int)$summary['pending'] ?></strong><small>aguardando liberação</small></article>
</div>
<section class="card"><div class="card-body">
  <form class="finance-search-row mb-3" method="get" action="<?= $escape($basePath) ?>/students/pedagogical"><input name="q" value="<?= $escape($search) ?>" placeholder="Aluno, CPF ou curso"><button class="button-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Pesquisar</button></form>
  <div class="table-responsive"><table><thead><tr><th>Aluno</th><th>Curso</th><th>Unidade</th><th>Acesso AVA</th><th>Último acesso</th><th>Ação</th></tr></thead><tbody>
  <?php if($students===[]):?><tr><td colspan="6">Nenhum aluno encontrado.</td></tr><?php endif;?>
  <?php foreach($students as$item):?><?php $last=(int)($item['last_access']??0);?><tr><td><strong><?= $escape($item['name']) ?></strong><div class="meta"><?= $escape($item['cpf_cnpj']?:'CPF não informado') ?></div></td><td><?= $escape($item['course_name']) ?></td><td><?= $escape($item['unit_name']) ?></td><td><?php if($item['moodle_enrolment_status']==='released'):?><span class="connection-badge connection-connected"><i class="fa-solid fa-circle-check"></i> Liberado</span><?php else:?><span class="connection-badge connection-awaiting_official_api"><i class="fa-solid fa-clock"></i> Pendente</span><?php endif;?></td><td><?= $last>0?$escape(date('d/m/Y H:i',$last)):'Ainda não acessou' ?></td><td><a class="button-secondary button-small" href="<?= $escape($basePath) ?>/finance/customers/<?= (int)$item['customer_id'] ?>" title="Abrir cadastro"><i class="fa-solid fa-eye"></i></a></td></tr><?php endforeach;?>
  </tbody></table></div>
</div></section>
