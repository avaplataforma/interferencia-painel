<?php
$financeLabels=['awaiting_charge'=>'Aguardando cobrança','awaiting_payment'=>'Aguardando pagamento','payment_confirmed'=>'Pagamento confirmado','payment_waived'=>'Pagamento dispensado','payment_interrupted'=>'Pagamento interrompido'];
$eventIcons=['enrollment_created'=>'fa-user-graduate','charge_created'=>'fa-wallet','payment_confirmed'=>'fa-circle-check','payment_waived'=>'fa-hand-holding-heart','payment_interrupted'=>'fa-circle-xmark','ava_released'=>'fa-graduation-cap','ava_release_failed'=>'fa-triangle-exclamation','access_sent'=>'fa-paper-plane'];
?>
<div class="page-heading"><div><p class="eyebrow">Alunos</p><h1>Matrículas</h1><p class="meta">Acompanhe curso, cobrança, pagamento e liberação no AVA em um único fluxo.</p></div><a class="button-primary" href="<?= $escape($basePath) ?>/students/enrollments/create"><i class="fa-solid fa-plus"></i> Nova matrícula</a></div>
<?php if($message):?><p class="alert alert-success"><?= $escape($message) ?></p><?php endif;?>
<?php if($error):?><p class="alert alert-danger"><?= $escape($error) ?></p><?php endif;?>
<section class="card"><div class="table-responsive"><table>
<thead><tr><th>Aluno</th><th>Curso</th><th>Unidade</th><th>Atendente</th><th>Financeiro</th><th>Acesso AVA</th><th>Ação</th></tr></thead>
<tbody>
<?php if($items===[]):?><tr><td colspan="7">Nenhuma matrícula cadastrada.</td></tr><?php endif;?>
<?php foreach($items as$item):
    $canDelete=$item['status']==='awaiting_charge'&&$item['finance_payment_id']===null&&$item['moodle_enrolment_status']==='not_released';
    $canRelease=in_array($item['status'],['payment_confirmed','payment_waived'],true)&&$item['moodle_enrolment_status']!=='released';
    $events=$eventMap[(int)$item['id']]??[];
?>
<tr>
<td><strong><?= $escape($item['student_name']) ?></strong><br><small><?= $escape($item['cpf_cnpj']?:'Sem documento') ?></small></td>
<td><?= $escape($item['course_name']) ?></td><td><?= $escape($item['unit_name']) ?></td><td><?= $escape($item['attendant_name']?:'Não definido') ?></td>
<td><span class="connection-badge <?= $item['status']==='payment_confirmed'?'connection-connected':($item['status']==='payment_interrupted'?'connection-rejected':'connection-awaiting_official_api') ?>"><?= $escape($financeLabels[$item['status']]??$item['status']) ?></span><?php if($item['product_name']):?><br><small><?= $escape($item['product_name']) ?> · R$ <?= number_format((float)$item['value'],2,',','.') ?></small><?php endif;?></td>
<td><span class="connection-badge <?= $item['moodle_enrolment_status']==='released'?'connection-connected':'connection-awaiting_official_api' ?>"><i class="fa-solid fa-graduation-cap"></i> <?= $item['moodle_enrolment_status']==='released'?'Liberado':'Não liberado' ?></span><?php if($item['ava_last_error']):?><br><small class="text-danger" title="<?= $escape($item['ava_last_error']) ?>"><i class="fa-solid fa-triangle-exclamation"></i> Última tentativa falhou</small><?php endif;?></td>
<td><div class="d-flex gap-2 flex-wrap">
<?php if($item['status']==='awaiting_charge'):?><a class="button-primary button-small" href="<?= $escape($basePath) ?>/finance/customers/<?= (int)$item['finance_customer_id'] ?>/payments/create?enrollment=<?= (int)$item['id'] ?>" title="Gerar cobrança"><i class="fa-solid fa-wallet"></i></a><?php endif;?>
<?php if($canRelease):?><form class="inline-form" method="post" action="<?= $escape($basePath) ?>/students/enrollments/<?= (int)$item['id'] ?>/release-ava" data-confirm-submit="Liberar este aluno no curso do AVA agora?"><?= $csrfField ?><input type="hidden" name="confirm_release" value="1"><button class="button-primary button-small" type="submit" title="Liberar no AVA"><i class="fa-solid fa-graduation-cap"></i> Liberar no AVA</button></form><?php endif;?>
<a class="button-secondary button-small" href="<?= $escape($basePath) ?>/finance/customers/<?= (int)$item['finance_customer_id'] ?>" title="Abrir cadastro"><i class="fa-solid fa-eye"></i></a>
<?php if($canDelete):?><form class="inline-form" method="post" action="<?= $escape($basePath) ?>/students/enrollments/<?= (int)$item['id'] ?>/delete" data-confirm-submit="Excluir esta matrícula? Esta ação não poderá ser desfeita."><?= $csrfField ?><input type="hidden" name="confirm_delete" value="1"><button class="button-danger button-small" type="submit" title="Excluir matrícula" aria-label="Excluir matrícula"><i class="fa-solid fa-trash"></i></button></form><?php endif;?>
</div></td></tr>
<tr class="enrollment-history-row"><td colspan="7"><details><summary><i class="fa-solid fa-clock-rotate-left"></i> Histórico da matrícula <span class="finance-count-badge"><?= count($events) ?></span></summary><div class="enrollment-timeline"><?php foreach($events as$event):?><article><i class="fa-solid <?= $escape($eventIcons[$event['event_type']]??'fa-circle') ?>"></i><div><strong><?= $escape($event['description']) ?></strong><small><?= $escape(date('d/m/Y H:i',strtotime($event['created_at']))) ?><?= $event['user_name']?' · '.$escape($event['user_name']):'' ?></small></div></article><?php endforeach;?></div></details></td></tr>
<?php endforeach;?>
</tbody></table></div></section>
