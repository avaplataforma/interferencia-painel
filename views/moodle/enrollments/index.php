<?php
$financeLabels=['awaiting_charge'=>'Aguardando cobrança','awaiting_payment'=>'Aguardando pagamento','payment_confirmed'=>'Pagamento confirmado','payment_waived'=>'Pagamento dispensado','payment_interrupted'=>'Pagamento interrompido'];
$eventIcons=['enrollment_created'=>'fa-user-graduate','charge_created'=>'fa-wallet','payment_confirmed'=>'fa-circle-check','payment_waived'=>'fa-hand-holding-heart','payment_interrupted'=>'fa-circle-xmark','ava_released'=>'fa-graduation-cap','ava_release_failed'=>'fa-triangle-exclamation','access_sent'=>'fa-paper-plane'];
?>
<div class="page-heading"><div><p class="eyebrow">Alunos</p><h1>Matrículas</h1><p class="meta">Acompanhe curso, cobrança, pagamento e liberação no AVA em um único fluxo.</p></div><a class="button-primary" href="<?= $escape($basePath) ?>/students/enrollments/create"><i class="fa-solid fa-plus"></i> Nova matrícula</a></div>
<?php if($message):?><p class="alert alert-success"><?= $escape($message) ?></p><?php endif;?>
<?php if($error):?><p class="alert alert-danger"><?= $escape($error) ?></p><?php endif;?>
<?php
$siteOrdersTitle='Pedidos do Site Institucional';
$siteOrdersDescription='Confira pagamentos, matrículas geradas e liberações no AVA.';
$siteOrdersShowActions=true;
$siteOrdersViewAllUrl=$basePath.'/students/site-orders';
require dirname(__DIR__,2).'/site/orders-panel.php';
?>
<style>
.special-release{margin-bottom:1rem;overflow:hidden}.special-release>summary{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:1.15rem 1.4rem;cursor:pointer;list-style:none}.special-release>summary::-webkit-details-marker{display:none}.special-release>summary span{display:flex;align-items:center;gap:.8rem}.special-release>summary i{color:var(--inter-accent)}.special-release-body{padding:0 1.4rem 1.4rem;border-top:1px solid #e2e8ee}.special-release-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;margin-top:1rem}.special-release-form .form-field{margin:0}.special-release-form .form-field-full{grid-column:1/-1}.special-release-actions{display:flex;justify-content:space-between;gap:1rem;align-items:center;grid-column:1/-1}.special-release-actions .alert{margin:0;flex:1}@media(max-width:760px){.special-release-form{grid-template-columns:1fr}.special-release-actions{align-items:stretch;flex-direction:column}.special-release-actions .button-primary{width:100%}}
</style>
<details class="card special-release" id="special-release">
 <summary><span><i class="fa-solid fa-hand-holding-heart"></i><strong>Liberação especial</strong><small class="meta">Bolsa, cortesia ou acesso sem cobrança</small></span><i class="fa-solid fa-chevron-down"></i></summary>
 <div class="special-release-body">
  <form class="special-release-form" method="post" action="<?= $escape($basePath) ?>/students/enrollments/waivers" data-confirm-submit="Autorizar a liberação deste curso sem pagamento?"><?= $csrfField ?>
   <div class="form-field"><label for="waiver-unit">Unidade <span class="required">*</span></label><select id="waiver-unit" name="unit_id" required><option value="">Selecione</option><?php foreach($units as$unit):?><option value="<?= (int)$unit['id'] ?>"><?= $escape($unit['name']) ?></option><?php endforeach;?></select></div>
   <div class="form-field"><label for="waiver-student">Aluno <span class="required">*</span></label><select id="waiver-student" name="finance_customer_id" required disabled><option value="">Escolha primeiro a unidade</option><?php foreach($students as$student):?><option value="<?= (int)$student['id'] ?>" data-unit-id="<?= (int)$student['unit_id'] ?>" hidden><?= $escape($student['name'].' · '.$student['unit_name'].' · '.($student['cpf_cnpj']?:'sem CPF')) ?></option><?php endforeach;?></select></div>
   <div class="form-field"><label for="waiver-course">Curso <span class="required">*</span></label><select id="waiver-course" name="moodle_course_id" required data-enrollment-product><option value="">Selecione</option><?php foreach($courses as$course):?><option value="<?= (int)$course['id'] ?>"<?= ($avaDestinations[(int)$course['id']]??[])===[]?' disabled':'' ?>><?= $escape($course['fullname'].(($avaDestinations[(int)$course['id']]??[])===[]?' · sem AVA disponível':'')) ?></option><?php endforeach;?></select></div>
   <div class="form-field"><label for="waiver-ava">AVA de destino <span class="required">*</span></label><select id="waiver-ava" name="ava_connection_id" required data-enrollment-ava disabled><option value="">Escolha primeiro o curso</option></select></div>
   <div class="form-field form-field-full"><label for="waiver-reason">Motivo da bolsa ou cortesia <span class="required">*</span></label><textarea id="waiver-reason" name="reason" minlength="10" maxlength="500" rows="3" placeholder="Ex.: Bolsa integral aprovada pela direção..." required></textarea><small>Obrigatório para auditoria. Entre 10 e 500 caracteres.</small></div>
   <div class="special-release-actions"><p class="alert alert-warning"><strong>Atenção:</strong> esta operação dispensa a cobrança. Depois de salvar, confirme a liberação do acesso na própria matrícula.</p><button class="button-primary" type="submit"><i class="fa-solid fa-graduation-cap"></i> Autorizar liberação</button></div>
  </form>
  <script type="application/json" data-enrollment-ava-options><?= json_encode($avaDestinations??[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
 </div>
</details>
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
<td><span class="connection-badge <?= $item['moodle_enrolment_status']==='released'?'connection-connected':'connection-awaiting_official_api' ?>"><i class="fa-solid fa-graduation-cap"></i> <?= $item['moodle_enrolment_status']==='released'?'Liberado':'Não liberado' ?></span><br><small><i class="fa-solid <?= ($item['ava_connection_type']??'shared')==='shared'?'fa-earth-americas':'fa-school' ?>"></i> <?= $escape($item['ava_connection_name']?:'AVA não definido') ?></small><?php if($item['ava_last_error']):?><br><small class="text-danger" title="<?= $escape($item['ava_last_error']) ?>"><i class="fa-solid fa-triangle-exclamation"></i> Última tentativa falhou — tente novamente</small><?php endif;?></td>
<td><div class="d-flex gap-2 flex-wrap">
<?php if($item['status']==='awaiting_charge'):?><a class="button-primary button-small" href="<?= $escape($basePath) ?>/finance/customers/<?= (int)$item['finance_customer_id'] ?>/payments/create?enrollment=<?= (int)$item['id'] ?>" title="Gerar cobrança"><i class="fa-solid fa-wallet"></i></a><?php endif;?>
<?php if($canRelease):?><form class="inline-form" method="post" action="<?= $escape($basePath) ?>/students/enrollments/<?= (int)$item['id'] ?>/release-ava" data-confirm-submit="Liberar este aluno no curso do AVA agora?"><?= $csrfField ?><input type="hidden" name="confirm_release" value="1"><button class="button-primary button-small" type="submit" title="Liberar no AVA"><i class="fa-solid fa-graduation-cap"></i> Liberar no AVA</button></form><?php endif;?>
<?php if($item['moodle_enrolment_status']==='released'):?><a class="button-primary button-small" href="<?= $escape($basePath) ?>/students/enrollments/<?= (int)$item['id'] ?>/access" title="Enviar ou reenviar acesso"><i class="fa-solid fa-paper-plane"></i> Enviar acesso</a><?php endif;?>
<a class="button-secondary button-small" href="<?= $escape($basePath) ?>/finance/customers/<?= (int)$item['finance_customer_id'] ?>" title="Abrir cadastro"><i class="fa-solid fa-eye"></i></a>
<?php if($canDelete):?><form class="inline-form" method="post" action="<?= $escape($basePath) ?>/students/enrollments/<?= (int)$item['id'] ?>/delete" data-confirm-submit="Excluir esta matrícula? Esta ação não poderá ser desfeita."><?= $csrfField ?><input type="hidden" name="confirm_delete" value="1"><button class="button-danger button-small" type="submit" title="Excluir matrícula" aria-label="Excluir matrícula"><i class="fa-solid fa-trash"></i></button></form><?php endif;?>
</div></td></tr>
<tr class="enrollment-history-row"><td colspan="7"><details><summary><i class="fa-solid fa-clock-rotate-left"></i> Histórico da matrícula <span class="finance-count-badge"><?= count($events) ?></span></summary><div class="enrollment-timeline"><?php foreach($events as$event):?><article><i class="fa-solid <?= $escape($eventIcons[$event['event_type']]??'fa-circle') ?>"></i><div><strong><?= $escape($event['description']) ?></strong><small><?= $escape(date('d/m/Y H:i',strtotime($event['created_at']))) ?><?= $event['user_name']?' · '.$escape($event['user_name']):'' ?></small></div></article><?php endforeach;?></div></details></td></tr>
<?php endforeach;?>
</tbody></table></div></section>
<script>
(()=>{const unit=document.getElementById('waiver-unit');const student=document.getElementById('waiver-student');if(!(unit instanceof HTMLSelectElement)||!(student instanceof HTMLSelectElement))return;const refresh=()=>{const value=unit.value;student.value='';student.disabled=value==='';for(const option of student.options){if(!option.dataset.unitId)continue;option.hidden=option.dataset.unitId!==value;}student.options[0].textContent=value===''?'Escolha primeiro a unidade':'Selecione o aluno';};unit.addEventListener('change',refresh);refresh();})();
</script>
