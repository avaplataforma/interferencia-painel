<?php
$risk=$risk??'all';
$lastAccessFrom=$lastAccessFrom??'';
$lastAccessTo=$lastAccessTo??'';
$riskLabels=['ok'=>'Regular','pending'=>'Aguardando liberação','blocked'=>'Acesso bloqueado','never_accessed'=>'Nunca acessou','inactive_7'=>'Sem acesso há 7 dias','inactive_15'=>'Sem acesso há 15 dias','inactive_30'=>'Sem acesso há 30 dias','stalled'=>'Progresso parado','unavailable'=>'Acompanhamento não fornecido'];
$riskShortLabels=['ok'=>'Regular','pending'=>'A liberar','blocked'=>'Bloqueado','never_accessed'=>'Sem acesso','inactive_7'=>'Inativo 7d','inactive_15'=>'Inativo 15d','inactive_30'=>'Inativo 30d','stalled'=>'Parado','unavailable'=>'Sem dados'];
$riskIcons=['ok'=>'circle-check','pending'=>'clock','blocked'=>'lock','never_accessed'=>'triangle-exclamation','inactive_7'=>'clock-rotate-left','inactive_15'=>'triangle-exclamation','inactive_30'=>'circle-exclamation','stalled'=>'pause','unavailable'=>'circle-info'];
$filterQuery=http_build_query(array_filter(['q'=>$search,'risk'=>$risk==='all'?null:$risk,'last_access_from'=>$lastAccessFrom?:null,'last_access_to'=>$lastAccessTo?:null],static fn(mixed$value):bool=>$value!==null&&$value!==''));
?>

<div class="page-heading pedagogical-heading">
  <div>
    <p class="eyebrow">Alunos</p>
    <h1>Pedagógico</h1>
    <p class="meta">Acompanhe o desempenho dos alunos e priorize as ações acadêmicas.</p>
  </div>
  <?php if($canManage):?>
    <form method="post" action="<?= $escape($basePath) ?>/students/pedagogical/sync">
      <?= $csrfField ?>
      <button class="button-secondary" type="submit"><i class="fa-solid fa-rotate"></i><span>Atualizar acompanhamento</span></button>
    </form>
  <?php endif;?>
</div>

<?php if($message):?><p class="alert alert-success"><?= $escape($message) ?></p><?php endif;?>
<?php if($error):?><p class="alert alert-danger"><?= $escape($error) ?></p><?php endif;?>

<div class="pedagogical-summary mb-4">
  <a class="metric-card pedagogical-metric<?= $risk==='all'?' is-active':'' ?>" href="<?= $escape($basePath) ?>/students/pedagogical?risk=all">
    <span class="pedagogical-metric-icon"><i class="fa-solid fa-user-graduate"></i></span><span class="pedagogical-metric-copy"><small>Alunos</small><strong><?= (int)$summary['students'] ?></strong><span><?= (int)$summary['enrolments'] ?> matrícula(s)</span></span>
  </a>
  <a class="metric-card pedagogical-metric<?= $risk==='never_accessed'?' is-active':'' ?>" href="<?= $escape($basePath) ?>/students/pedagogical?risk=never_accessed">
    <span class="pedagogical-metric-icon is-warning"><i class="fa-solid fa-right-to-bracket"></i></span><span class="pedagogical-metric-copy"><small>Sem primeiro acesso</small><strong><?= (int)$summary['never_accessed'] ?></strong><span>já liberados no AVA</span></span>
  </a>
  <a class="metric-card pedagogical-metric<?= $risk==='inactive_15'?' is-active':'' ?>" href="<?= $escape($basePath) ?>/students/pedagogical?risk=inactive_15">
    <span class="pedagogical-metric-icon is-warning"><i class="fa-solid fa-clock-rotate-left"></i></span><span class="pedagogical-metric-copy"><small>Sem acesso há 15 dias</small><strong><?= (int)$summary['inactive_15'] ?></strong><span>precisam de contato</span></span>
  </a>
  <a class="metric-card pedagogical-metric<?= $risk==='blocked'?' is-active':'' ?>" href="<?= $escape($basePath) ?>/students/pedagogical?risk=blocked">
    <span class="pedagogical-metric-icon is-danger"><i class="fa-solid fa-lock"></i></span><span class="pedagogical-metric-copy"><small>Acessos bloqueados</small><strong><?= (int)$summary['blocked'] ?></strong><span>em todo o AVA</span></span>
  </a>
  <div class="metric-card pedagogical-metric">
    <span class="pedagogical-metric-icon is-success"><i class="fa-solid fa-circle-check"></i></span><span class="pedagogical-metric-copy"><small>Aprovados</small><strong><?= (int)($summary['approved']??0) ?></strong><span>com nota de aprovação</span></span>
  </div>
  <div class="metric-card pedagogical-metric">
    <span class="pedagogical-metric-icon is-success"><i class="fa-solid fa-certificate"></i></span><span class="pedagogical-metric-copy"><small>Certificados</small><strong><?= (int)($summary['certificates']??0) ?></strong><span>já emitidos no AVA</span></span>
  </div>
</div>

<?php if((int)$summary['attention']>0):?>
  <div class="alert alert-warning pedagogical-alert"><i class="fa-solid fa-triangle-exclamation"></i><span><strong><?= (int)$summary['attention'] ?> matrícula(s) precisam de atenção pedagógica.</strong> Use os filtros para priorizar os contatos.</span></div>
<?php endif;?>

<section class="card pedagogical-card">
  <div class="card-body">
    <div class="pedagogical-toolbar-heading">
      <div><h2>Acompanhamento dos alunos</h2><p class="meta"><?= count($students) ?> registro(s) no filtro atual</p></div>
      <a class="button-secondary button-small" href="<?= $escape($basePath) ?>/students/pedagogical<?= $filterQuery!==''?'?'.$escape($filterQuery).'&export=csv':'?export=csv' ?>"><i class="fa-solid fa-file-arrow-down"></i><span>Exportar CSV</span></a>
    </div>
    <form class="pedagogical-filters mb-3" method="get" action="<?= $escape($basePath) ?>/students/pedagogical">
      <label class="pedagogical-filter-field pedagogical-filter-search"><span>Pesquisar</span><div class="pedagogical-input-icon"><i class="fa-solid fa-magnifying-glass"></i><input name="q" value="<?= $escape($search) ?>" placeholder="Aluno, CPF ou curso"></div></label>
      <label class="pedagogical-filter-field"><span>Situação</span><select name="risk" aria-label="Situação pedagógica"><option value="all">Todas as situações</option><?php foreach($riskLabels as$code=>$label):?><option value="<?= $escape($code) ?>"<?= $risk===$code?' selected':'' ?>><?= $escape($label) ?></option><?php endforeach;?></select></label>
      <label class="pedagogical-filter-field"><span>Último acesso de</span><input type="date" name="last_access_from" value="<?= $escape($lastAccessFrom) ?>"></label>
      <label class="pedagogical-filter-field"><span>Até</span><input type="date" name="last_access_to" value="<?= $escape($lastAccessTo) ?>"></label>
      <div class="pedagogical-filter-actions"><button class="button-primary" type="submit"><i class="fa-solid fa-filter"></i><span>Filtrar</span></button><a class="button-secondary" href="<?= $escape($basePath) ?>/students/pedagogical" title="Limpar filtros"><i class="fa-solid fa-eraser"></i><span>Limpar</span></a></div>
    </form>

    <div class="table-responsive pedagogical-table-wrap">
      <table class="pedagogical-table">
        <thead><tr><th>Aluno</th><th>Curso</th><th>Unidade</th><th>Situação</th><th>Progresso</th><th>Nota</th><th>Último acesso</th><th>AVA</th><th class="pedagogical-actions-heading">Ações</th></tr></thead>
        <tbody>
        <?php if($students===[]):?><tr><td colspan="9"><div class="pedagogical-empty"><i class="fa-solid fa-user-graduate"></i><strong>Nenhum aluno encontrado</strong><span>Altere os filtros para ampliar a consulta.</span></div></td></tr><?php endif;?>
        <?php foreach($students as$item):?>
          <?php
          $last=(int)($item['last_access']??0);
          $percent=$item['completion_percent']===null?null:max(0,min(100,(float)$item['completion_percent']));
          $grade=$item['academic_grade_percent']===null?null:max(0,min(100,(float)$item['academic_grade_percent']));
          $gradeStatus=(string)($item['academic_grade_status']??'not_available');
          $certificateStatus=(string)($item['academic_certificate_status']??'not_available');
          $certificateUrl=trim((string)($item['academic_certificate_url']??''));
          $suspended=(int)($item['suspended']??0)===1;
          $riskCode=(string)$item['risk_code'];
          $riskClass=in_array($riskCode,['inactive_30','blocked'],true)?'connection-error':(in_array($riskCode,['never_accessed','inactive_15','stalled','pending'],true)?'connection-awaiting_official_api':'connection-connected');
          ?>
          <tr>
            <td><strong><?= $escape($item['name']) ?></strong><div class="meta"><?= $escape($item['cpf_cnpj']?:'CPF não informado') ?></div></td>
            <td><?= $escape($item['course_name']) ?></td>
            <td><?= $escape($item['unit_name']) ?></td>
            <td><span class="connection-badge pedagogical-risk-badge <?= $riskClass ?>" title="<?= $escape($riskLabels[$riskCode]??'Em acompanhamento') ?>"><i class="fa-solid fa-<?= $escape($riskIcons[$riskCode]??'circle-info') ?>"></i><span><?= $escape($riskShortLabels[$riskCode]??'Acompanhar') ?></span></span></td>
            <td><?php if($item['completion_status']==='not_configured'):?><span class="badge badge-warning">Sem critérios no AVA</span><?php elseif($percent===null):?><span class="meta">Não consultado</span><?php else:?><div class="pedagogical-progress"><div><span style="width:<?= $percent ?>%"></span></div><small><?= number_format($percent,0,',','.') ?>% · <?= $escape(match($item['completion_status']){'completed'=>'Concluído','in_progress'=>'Em andamento','not_started'=>'Não iniciado',default=>'Indisponível'}) ?></small></div><?php endif;?></td>
            <td><?php if($grade===null):?><span class="meta">Não fornecida</span><?php else:?><strong><?= number_format($grade,1,',','.') ?>%</strong><div><span class="connection-badge <?= $gradeStatus==='passed'?'connection-connected':($gradeStatus==='failed'?'connection-error':'connection-awaiting_official_api') ?>"><?= $escape(match($gradeStatus){'passed'=>'Aprovado','failed'=>'Reprovado',default=>'Em avaliação'}) ?></span></div><?php endif;?></td>
            <td><?= $last>0?$escape(date('d/m/Y H:i',$last)):'Ainda não acessou' ?></td>
            <td><div class="pedagogical-ava-cell"><?php if($suspended):?><span class="connection-badge connection-error"><i class="fa-solid fa-lock"></i><span>Bloqueado</span></span><?php elseif($item['moodle_enrolment_status']==='released'):?><span class="connection-badge connection-connected"><i class="fa-solid fa-circle-check"></i><span>Liberado</span></span><?php else:?><span class="connection-badge connection-awaiting_official_api"><i class="fa-solid fa-clock"></i><span>Pendente</span></span><?php endif;?><?php if($certificateStatus==='issued'&&$certificateUrl!==''):?><a class="pedagogical-certificate" href="<?= $escape($certificateUrl) ?>" target="_blank" rel="noopener" title="Abrir certificado"><i class="fa-solid fa-certificate"></i><span>Certificado</span></a><?php elseif($certificateStatus==='issued'):?><small><i class="fa-solid fa-certificate"></i> Emitido</small><?php elseif($certificateStatus==='available'):?><small><i class="fa-solid fa-certificate"></i> Disponível</small><?php else:?><small class="meta">Sem certificado</small><?php endif;?></div></td>
            <td class="pedagogical-actions-cell"><div class="table-actions pedagogical-actions"><a class="button-secondary button-small pedagogical-action" href="<?= $escape($basePath) ?>/finance/customers/<?= (int)$item['customer_id'] ?>" title="Abrir cadastro" aria-label="Abrir cadastro"><i class="fa-solid fa-eye"></i></a><?php if($canManage&&$item['moodle_enrolment_status']==='released'):?><a class="button-secondary button-small pedagogical-action" href="<?= $escape($basePath) ?>/students/enrollments/<?= (int)$item['enrollment_id'] ?>/access" title="Dados de acesso do aluno" aria-label="Dados de acesso do aluno"><i class="fa-solid fa-key"></i></a><?php endif;?><?php if($navigation['tickets_create']??false):?><a class="button-secondary button-small pedagogical-action" href="<?= $escape($basePath) ?>/tickets/create?student=<?= (int)$item['customer_id'] ?>" title="Abrir ticket para este aluno" aria-label="Abrir ticket"><i class="fa-solid fa-ticket"></i></a><?php endif;?><?php if($canManage):?><a class="button-secondary button-small pedagogical-action" href="<?= $escape($basePath) ?>/students/enrollments/create?student=<?= (int)$item['customer_id'] ?>" title="Nova matrícula" aria-label="Nova matrícula"><i class="fa-solid fa-graduation-cap"></i></a><?php endif;?><?php if($canManage&&(int)($item['ava_user_id']??0)>0):?><form class="pedagogical-action-form" method="post" action="<?= $escape($basePath) ?>/students/enrollments/<?= (int)$item['enrollment_id'] ?>/ava-status" onsubmit="return confirm('<?= $suspended?'Reativar o acesso deste aluno ao AVA?':'Bloquear este aluno em todo o AVA? Ele não conseguirá acessar nenhum curso.' ?>')"><?= $csrfField ?><input type="hidden" name="status" value="<?= $suspended?'active':'blocked' ?>"><input type="hidden" name="confirm" value="1"><button class="<?= $suspended?'button-secondary':'button-danger' ?> button-small pedagogical-action<?= $suspended?'':' pedagogical-action-danger' ?>" type="submit" title="<?= $suspended?'Reativar acesso ao AVA':'Bloquear acesso em todo o AVA' ?>" aria-label="<?= $suspended?'Reativar acesso ao AVA':'Bloquear acesso ao AVA' ?>"><i class="fa-solid fa-<?= $suspended?'unlock':'user-lock' ?>"></i></button></form><?php endif;?></div></td>
          </tr>
        <?php endforeach;?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<style>
.pedagogical-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:1.25rem;margin-bottom:1.35rem}.pedagogical-heading form{margin:0}.pedagogical-heading button{display:inline-flex;align-items:center;gap:.48rem;white-space:nowrap}
.pedagogical-summary{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:.75rem}.pedagogical-metric{display:flex!important;align-items:center;gap:.72rem;min-height:6.3rem;padding:1rem!important;color:inherit!important;text-decoration:none!important;transition:transform .16s ease,border-color .16s ease,box-shadow .16s ease}.pedagogical-metric:hover{transform:translateY(-2px);border-color:#f3a1a6;box-shadow:0 10px 24px rgb(25 38 50 / 9%)}.pedagogical-metric.is-active{border-color:#ed1c24;box-shadow:inset 0 0 0 1px #ed1c24}.pedagogical-metric-icon{display:inline-flex;flex:0 0 2.35rem;align-items:center;justify-content:center;width:2.35rem;height:2.35rem;border-radius:.72rem;background:#eaf3ff;color:#1769aa}.pedagogical-metric-icon.is-warning{background:#fff4d7;color:#a15c00}.pedagogical-metric-icon.is-danger{background:#ffe8ea;color:#b4232c}.pedagogical-metric-icon.is-success{background:#e5f7ed;color:#057a45}.pedagogical-metric-copy{display:grid;min-width:0;gap:.1rem}.pedagogical-metric-copy small{color:#5e7081;font-size:.74rem;font-weight:700;line-height:1.25}.pedagogical-metric-copy strong{font-size:1.35rem;line-height:1.15}.pedagogical-metric-copy>span{color:#657789;font-size:.7rem;line-height:1.25}
.pedagogical-alert{display:flex;align-items:center;gap:.55rem;margin-bottom:1rem}.pedagogical-card{overflow:hidden}.pedagogical-card>.card-body{padding:1rem}.pedagogical-toolbar-heading{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem}.pedagogical-toolbar-heading h2{margin:0;font-size:1.18rem}.pedagogical-toolbar-heading p{margin:.15rem 0 0}.pedagogical-toolbar-heading>a{display:inline-flex;align-items:center;gap:.42rem;white-space:nowrap}
.pedagogical-filters{display:grid;grid-template-columns:minmax(13rem,2fr) minmax(11rem,1fr) minmax(9rem,.8fr) minmax(9rem,.8fr) auto;align-items:end;gap:.7rem;padding:1rem;border:1px solid #e0e6ea;border-radius:.85rem;background:#f8fafb}.pedagogical-filter-field{display:grid;gap:.36rem;margin:0}.pedagogical-filter-field>span{color:#314252;font-size:.76rem;font-weight:750}.pedagogical-filter-field input,.pedagogical-filter-field select{box-sizing:border-box;width:100%;height:2.75rem;margin:0}.pedagogical-input-icon{position:relative}.pedagogical-input-icon>i{position:absolute;top:50%;left:.86rem;z-index:1;transform:translateY(-50%);color:#718292}.pedagogical-input-icon input{padding-left:2.55rem}.pedagogical-filter-actions{display:flex;gap:.45rem}.pedagogical-filter-actions>*{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;height:2.75rem;white-space:nowrap}
.pedagogical-table-wrap{margin:0;border:1px solid #e0e6ea;border-radius:.85rem;background:#fff}.pedagogical-table{width:100%;min-width:64rem;margin:0;table-layout:fixed}.pedagogical-table th,.pedagogical-table td{box-sizing:border-box;padding:.82rem .68rem;overflow-wrap:anywhere;vertical-align:middle}.pedagogical-table th{color:#263544;font-size:.76rem;line-height:1.25;text-transform:none}.pedagogical-table td{font-size:.84rem;line-height:1.4}.pedagogical-table tbody tr{transition:background-color .15s ease}.pedagogical-table tbody tr:hover{background:#fffafb}.pedagogical-table th:nth-child(1){width:8.5rem}.pedagogical-table th:nth-child(2){width:8.5rem}.pedagogical-table th:nth-child(3){width:5rem}.pedagogical-table th:nth-child(4){width:6.8rem}.pedagogical-table th:nth-child(5){width:7rem}.pedagogical-table th:nth-child(6){width:4.5rem}.pedagogical-table th:nth-child(7){width:6rem}.pedagogical-table th:nth-child(8){width:7rem}.pedagogical-table th:nth-child(9){width:12rem}
.pedagogical-table .connection-badge{display:inline-flex!important;align-items:center!important;justify-content:center;gap:.32rem!important;max-width:100%;padding:.27rem .58rem!important;line-height:1.15;text-align:center;white-space:nowrap}.pedagogical-table .connection-badge i{flex:0 0 auto;margin:0!important;line-height:1}.pedagogical-risk-badge{font-size:.72rem!important}.pedagogical-progress{min-width:0!important}.pedagogical-progress>div{height:.45rem;background:#e5e7eb;border-radius:999px;overflow:hidden}.pedagogical-progress>div>span{display:block;height:100%;background:#ed1c24}.pedagogical-progress small{display:block;margin-top:.25rem;font-size:.7rem;line-height:1.25}.pedagogical-ava-cell{display:grid;justify-items:start;gap:.38rem}.pedagogical-ava-cell small{font-size:.7rem;line-height:1.2}.pedagogical-certificate{display:inline-flex;align-items:center;gap:.3rem;color:var(--inter-accent-dark);font-size:.72rem;font-weight:700;text-decoration:none}.pedagogical-certificate:hover{text-decoration:underline}
.pedagogical-actions-heading,.pedagogical-actions-cell{position:sticky;right:0;z-index:3;background:#fff;box-shadow:-10px 0 16px -16px rgb(23 33 43 / 55%)}.pedagogical-actions-heading{z-index:4;background:#f8fafb}.pedagogical-actions{display:flex;gap:.3rem;align-items:center;justify-content:flex-end;width:100%}.pedagogical-action-form{display:block;width:2.1rem;height:2.1rem;margin:0!important}.pedagogical-action{display:inline-flex!important;align-items:center!important;justify-content:center!important;box-sizing:border-box!important;width:2.1rem!important;min-width:2.1rem!important;height:2.1rem!important;min-height:2.1rem!important;margin:0!important;padding:0!important;border-radius:.55rem!important;line-height:1!important}.pedagogical-action i{margin:0!important;font-size:.88rem;line-height:1}.pedagogical-action-danger{border:1px solid #b4232c!important;background:#b4232c!important;color:#fff!important}.pedagogical-action-danger:hover{border-color:#8f1720!important;background:#8f1720!important}.pedagogical-empty{display:grid;justify-items:center;gap:.4rem;padding:2.5rem;color:#657789}.pedagogical-empty i{font-size:1.6rem;color:#a9b7c3}.pedagogical-empty strong{color:#263544}
@media(max-width:1080px){.pedagogical-summary{grid-template-columns:repeat(3,minmax(0,1fr))}.pedagogical-filters{grid-template-columns:repeat(2,minmax(0,1fr))}.pedagogical-filter-search{grid-column:1/-1}.pedagogical-filter-actions{justify-content:flex-end}}
@media(max-width:680px){.pedagogical-heading{align-items:stretch;flex-direction:column}.pedagogical-heading button{width:100%;justify-content:center}.pedagogical-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.pedagogical-toolbar-heading{align-items:stretch;flex-direction:column}.pedagogical-toolbar-heading>a{justify-content:center}.pedagogical-filters{grid-template-columns:1fr}.pedagogical-filter-search{grid-column:auto}.pedagogical-filter-actions{display:grid;grid-template-columns:1fr 1fr}.pedagogical-table{min-width:64rem}.pedagogical-table th,.pedagogical-table td{padding:.68rem .52rem}.pedagogical-actions-heading,.pedagogical-actions-cell{box-shadow:-12px 0 18px -16px rgb(23 33 43 / 75%)}}
@media(max-width:420px){.pedagogical-summary{grid-template-columns:1fr}.pedagogical-metric{min-height:5.5rem}}
</style>
