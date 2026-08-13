<?php
$summary=$queue['summary']??[];
$labels=[
 'incomplete_registration'=>['Cadastro incompleto','fa-user-pen','danger'],
 'pending_payment'=>['Financeiro pendente','fa-wallet','warning'],
 'pending_ava'=>['Acesso AVA pendente','fa-key','warning'],
 'inactive'=>['Atenção pedagógica','fa-person-circle-exclamation','danger'],
 'certificate_available'=>['Certificado disponível','fa-award','success'],
];
$actionHref=static function(array$item)use($basePath):string{
 $studentId=(int)($item['student_id']??0);$enrollmentId=(int)($item['enrollment_id']??0);
 return match((string)($item['action']??'')){
  'edit_profile'=>$basePath.'/finance/customers/'.$studentId.'/edit',
  'charge'=>$basePath.'/finance/customers/'.$studentId.'/payments/create'.($enrollmentId>0?'?enrollment='.$enrollmentId:''),
  'finance'=>$basePath.'/students/'.$studentId.'?tab=finance',
  'release_ava'=>$enrollmentId>0?$basePath.'/students/enrollments?focus='.$enrollmentId.'#enrollment-'.$enrollmentId:$basePath.'/students/enrollments',
  'pedagogical'=>$basePath.'/students/'.$studentId.'?tab=pedagogical',
  'certificate'=>$basePath.'/students/'.$studentId.'?tab=ava',
  default=>$basePath.'/students/'.$studentId.'?tab=journey',
 };
};
?>
<style>
.student-actions-head{display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;margin-bottom:1.2rem}.student-actions-head h1{margin:.1rem 0}.action-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.75rem;margin-bottom:1rem}.action-summary a{display:flex;align-items:center;gap:.75rem;padding:1rem;border:1px solid #dce3e8;border-radius:.9rem;background:#fff;color:var(--inter-ink);text-decoration:none;box-shadow:0 .4rem 1.2rem rgba(15,23,42,.04)}.action-summary a.active{border-color:var(--inter-accent);box-shadow:0 0 0 2px rgba(239,29,46,.09)}.action-summary i{display:grid;width:2.6rem;height:2.6rem;place-items:center;border-radius:.75rem;background:#fff0f1;color:var(--inter-accent)}.action-summary small,.action-summary strong{display:block}.action-summary small{color:var(--inter-muted)}.action-filter{display:grid;grid-template-columns:minmax(16rem,1fr) minmax(13rem,.45fr) auto;gap:.65rem;padding:1rem;border:1px solid #dce3e8;border-radius:.9rem;background:#fff;margin-bottom:1rem}.action-filter>*{min-height:2.9rem;margin:0!important}.student-action-list{overflow:hidden;border:1px solid #dce3e8;border-radius:.9rem;background:#fff}.student-action-list table{margin:0}.student-action-list td{vertical-align:middle}.action-student strong,.action-student small{display:block}.action-copy{display:grid;grid-template-columns:2.6rem minmax(0,1fr);align-items:center;gap:.7rem}.action-copy>i{display:grid;width:2.6rem;height:2.6rem;place-items:center;border-radius:.7rem;background:#fff0f1;color:var(--inter-accent)}.action-copy small{display:block;color:var(--inter-muted);margin-top:.15rem}.action-owner{display:block;color:var(--inter-muted);font-size:.8rem}.student-action-empty{padding:3rem;text-align:center;color:var(--inter-muted)}@media(max-width:1050px){.action-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.student-action-list{overflow-x:auto}.student-action-list table{min-width:850px}}@media(max-width:650px){.student-actions-head{align-items:flex-start;flex-direction:column}.action-summary,.action-filter{grid-template-columns:1fr}}
</style>
<div class="student-actions-head"><div><p class="eyebrow">Alunos</p><h1>Pendências dos alunos</h1><p class="meta">Uma fila única com a próxima ação necessária para cada aluno.</p></div><a class="button-secondary" href="<?= $escape($basePath) ?>/students/pedagogical"><i class="fa-solid fa-chart-line"></i> Pedagógico</a></div>

<section class="action-summary" aria-label="Resumo das pendências">
 <?php foreach($labels as$key=>$meta):?><a class="<?= $type===$key?'active':'' ?>" href="<?= $escape($basePath) ?>/students/actions?type=<?= $escape($key) ?>"><i class="fa-solid <?= $escape($meta[1]) ?>"></i><span><small><?= $escape($meta[0]) ?></small><strong><?= (int)($summary[$key]??0) ?></strong></span></a><?php endforeach;?>
</section>

<form class="action-filter" method="get" action="<?= $escape($basePath) ?>/students/actions"><input type="search" name="q" value="<?= $escape($search) ?>" placeholder="Aluno, CPF, curso ou unidade"><select name="type"><option value="">Todas as pendências</option><?php foreach($labels as$key=>$meta):?><option value="<?= $escape($key) ?>" <?= $type===$key?'selected':'' ?>><?= $escape($meta[0]) ?></option><?php endforeach;?></select><button class="button-primary" type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button></form>

<section class="student-action-list">
 <?php if($items===[]):?><div class="student-action-empty"><i class="fa-solid fa-circle-check"></i><p>Nenhuma pendência encontrada neste filtro.</p></div><?php else:?><table><thead><tr><th>Aluno</th><th>Unidade</th><th>Próxima ação</th><th>Curso</th><th>Responsável</th><th>Ação</th></tr></thead><tbody><?php foreach($items as$item):?><tr><td class="action-student"><strong><?= $escape((string)$item['student_name']) ?></strong><small><?= $escape((string)$item['student_document']) ?></small></td><td><?= $escape((string)($item['unit_name']?:'Sem unidade')) ?></td><td><div class="action-copy"><i class="fa-solid <?= $escape((string)$item['icon']) ?>"></i><span><strong><?= $escape((string)$item['label']) ?></strong><small><?= $escape((string)$item['description']) ?></small></span></div></td><td><?= $escape((string)($item['course_name']?:'—')) ?></td><td><?= $escape((string)($item['attendant_name']?:'Equipe da unidade')) ?></td><td><a class="button-primary button-small" href="<?= $escape($actionHref($item)) ?>" title="Resolver pendência"><i class="fa-solid fa-arrow-right"></i> Resolver</a></td></tr><?php endforeach;?></tbody></table><?php endif;?>
</section>
