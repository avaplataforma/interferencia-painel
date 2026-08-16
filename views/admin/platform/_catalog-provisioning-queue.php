<?php
$queueData = $provider === $activeProvider && is_array($provisioningQueue ?? null)
    ? $provisioningQueue
    : ['summary' => ['queued' => 0, 'working' => 0, 'completed' => 0, 'failed' => 0, 'attention' => 0, 'created' => 0, 'reused' => 0, 'recovered' => 0, 'total' => 0], 'jobs' => []];
$queueSummary = (array)($queueData['summary'] ?? []);
$queueJobs = (array)($queueData['jobs'] ?? []);
$queueLabels = [
    'queued' => ['Aguardando', 'fa-clock', 'is-queued'],
    'working' => ['Em processamento', 'fa-spinner', 'is-working'],
    'completed' => ['Concluído', 'fa-circle-check', 'is-completed'],
    'failed' => ['Falhou', 'fa-triangle-exclamation', 'is-failed'],
];
$ltiLabels = [
    'requested' => 'Preparando seleção',
    'selected' => 'Seleção capturada',
    'registered' => 'Registrada no Mundo Inter',
    'materialized' => 'Curso confirmado',
    'purged' => 'Área técnica limpa',
    'cleanup_failed' => 'Limpeza pendente',
    'failed' => 'Seleção falhou',
];
?>
<section class="catalog-subpanel catalog-technical-block provisioning-queue" data-catalog-subpanel="<?= $escape($provider) ?>:queue" hidden>
 <header class="technical-block-header">
  <div><span class="eyebrow">Automação do AVA</span><h3>Fila de publicação</h3><p>O Mundo Inter verifica se o curso já existe no AVA Cursos e acompanha a preparação automática solicitada pelas matrículas.</p></div>
  <span class="catalog-badge <?= (int)($queueSummary['failed']??0)>0?'':'ok' ?>"><i class="fa-solid fa-list-check"></i><?= (int)($queueSummary['total']??0) ?> item(ns)</span>
 </header>
 <div class="provisioning-summary">
  <?php foreach($queueLabels as$status=>$definition):?>
   <article class="provisioning-stat <?= $escape($definition[2]) ?>"><i class="fa-solid <?= $escape($definition[1]) ?>"></i><div><small><?= $escape($definition[0]) ?></small><strong><?= (int)($queueSummary[$status]??0) ?></strong></div></article>
  <?php endforeach;?>
 </div>
 <?php if($provider==='iesde'):$homologationSteps=[
   ['created','Curso novo','O robô criou e publicou um curso sem intervenção.','fa-wand-magic-sparkles'],
   ['reused','Reutilização','Uma nova solicitação reaproveitou o mesmo curso.','fa-arrows-rotate'],
   ['recovered','Recuperação','Uma falha foi recuperada pela fila de novas tentativas.','fa-shield-heart'],
  ];$homologationReady=true;foreach($homologationSteps as$step){if((int)($queueSummary[$step[0]]??0)<1)$homologationReady=false;}?>
  <section class="provisioning-homologation <?= $homologationReady?'is-ready':'' ?>">
   <header><div><span class="eyebrow">Homologação de produção</span><h4><?= $homologationReady?'Automação MASTER aprovada':'Validação em andamento' ?></h4></div><span class="catalog-badge <?= $homologationReady?'ok':'' ?>"><i class="fa-solid <?= $homologationReady?'fa-circle-check':'fa-hourglass-half' ?>"></i><?= $homologationReady?'3 de 3 cenários':'Aguardando cenários' ?></span></header>
   <div class="provisioning-scenarios">
    <?php foreach($homologationSteps as$step):$passed=(int)($queueSummary[$step[0]]??0)>0;?><article class="<?= $passed?'is-passed':'' ?>"><i class="fa-solid <?= $escape($step[3]) ?>"></i><div><strong><?= $escape($step[1]) ?></strong><span><?= $escape($step[2]) ?></span></div><small><i class="fa-solid <?= $passed?'fa-check':'fa-clock' ?>"></i><?= $passed?'Validado':'Pendente' ?></small></article><?php endforeach;?>
   </div>
  </section>
 <?php endif;?>
 <?php if((int)($queueSummary['attention']??0)>0):?>
  <div class="alert alert-danger provisioning-attention"><i class="fa-solid fa-triangle-exclamation"></i><div><strong><?= (int)$queueSummary['attention'] ?> publicação(ões) exigem intervenção.</strong><span>As três tentativas automáticas foram usadas. Confira a mensagem da falha e tente novamente após corrigir a conexão.</span></div></div>
 <?php endif;?>
 <?php if($provider!==$activeProvider):?>
  <div class="catalog-empty"><i class="fa-solid fa-arrow-pointer fa-2x"></i><h3>Abra esta Formação</h3><p>A fila é carregada somente para o catálogo selecionado.</p></div>
 <?php elseif($queueJobs===[]):?>
  <div class="catalog-empty compact"><i class="fa-solid fa-circle-check fa-2x"></i><h3>Nenhuma publicação pendente</h3><p>Quando uma matrícula precisar criar um curso no AVA, ela aparecerá aqui automaticamente.</p></div>
 <?php else:?>
  <div class="table-responsive"><table class="provisioning-table"><thead><tr><th>Curso</th><th>Franquia</th><th>Situação</th><th>Tentativas</th><th>Atualizado</th><th>Ação</th></tr></thead><tbody>
   <?php foreach($queueJobs as$job):$status=(string)($job['status']??'queued');$attempts=(int)($job['attempts']??0);$reuseCount=(int)($job['reuse_count']??0);$needsAttention=$status==='failed'&&$attempts>=3;$definition=$queueLabels[$status]??$queueLabels['queued'];?>
    <tr>
     <td><strong><?= $escape((string)($job['course_name']??'Curso não identificado')) ?></strong><small>Formação <?= $escape(strtoupper((string)($job['provider_code']??$provider))) ?></small></td>
     <td><?= $escape((string)($job['organization_name']??'Franquia')) ?></td>
     <td><span class="provisioning-status <?= $escape($definition[2]) ?>"><i class="fa-solid <?= $escape($definition[1]) ?>"></i><?= $escape($needsAttention?'Intervenção necessária':$definition[0]) ?></span><?php $ltiStatus=trim((string)($job['lti_snapshot_status']??''));if($ltiStatus!==''):?><small><i class="fa-solid fa-link"></i> LTI: <?= $escape($ltiLabels[$ltiStatus]??$ltiStatus) ?></small><?php endif;?><?php if(trim((string)($job['last_error']??''))!==''):?><small class="provisioning-error" title="<?= $escape((string)$job['last_error']) ?>"><?= $escape((string)$job['last_error']) ?></small><?php endif;?></td>
     <td><strong><?= $attempts ?>/3</strong><?php if($reuseCount>0):?><small><i class="fa-solid fa-arrows-rotate"></i> reutilizado <?= $reuseCount ?>x</small><?php endif;?></td>
     <td><?= $escape($formatDate($job['updated_at']??null)) ?></td>
     <td><?php if($status==='failed'):?><form method="post" action="<?= $escape($basePath) ?>/admin/platform/integrations/course-providers/provisioning/<?= (int)$job['id'] ?>/retry" onsubmit="return confirm('Tentar preparar este curso novamente?')"><?= $csrfField ?><input type="hidden" name="provider" value="<?= $escape($provider) ?>"><button class="btn btn-secondary" type="submit"><i class="fa-solid fa-rotate-right"></i> Tentar novamente</button></form><?php else:?><span class="muted">Automático</span><?php endif;?></td>
    </tr>
   <?php endforeach;?>
  </tbody></table></div>
 <?php endif;?>
</section>
