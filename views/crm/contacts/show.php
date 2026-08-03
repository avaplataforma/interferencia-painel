<?php
declare(strict_types=1);
/** @var Closure(mixed): string $escape */
$sourceLabels=['internal'=>'Cadastro interno','external_form'=>'Site externo','whatsapp'=>'WhatsApp'];
$followUpLabels=['pending'=>'Pendente','completed'=>'Concluído','cancelled'=>'Cancelado'];
$eventIcons=['created'=>'fa-user-plus','status_changed'=>'fa-arrows-rotate','responsible_changed'=>'fa-user-check','tags_changed'=>'fa-tags','notes_changed'=>'fa-note-sticky'];
$phoneDigits=preg_replace('/\D/','',(string)($contact['phone']??''));
$whatsAppDigits=in_array(strlen((string)$phoneDigits),[10,11],true)?'55'.$phoneDigits:$phoneDigits;
$tagsData=array_filter(explode(';;',(string)($contact['tags_data']??'')));
$nextOverdue=$nextFollowUp!==null&&strtotime((string)$nextFollowUp['scheduled_at'])<strtotime('today');
?>
<?php if($message):?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif;?>
<?php if($error):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>

<div class="contact-profile-heading">
  <div><a class="back-link" href="<?= $escape($basePath) ?>/crm/contacts"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Contatos</a><span class="status">CRM · <?= $escape($contact['unit_name']) ?></span><h1><?= $escape($contact['name']) ?></h1><div class="contact-profile-badges"><span class="crm-status-badge" style="--status-color:<?= $escape($contact['status_color']) ?>"><?= $escape($contact['status_name']) ?></span><?php foreach($tagsData as $tagData):?><?php [$tagName,$tagColor]=array_pad(explode('|',$tagData,2),2,'#64748b');?><span class="tag-badge" style="--tag-color:<?= $escape($tagColor) ?>"><?= $escape($tagName) ?></span><?php endforeach;?></div></div>
  <div class="contact-profile-actions">
    <?php if($phoneDigits!==''):?><a class="contact-action contact-action-secondary" href="tel:+<?= $escape($whatsAppDigits) ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i> Ligar</a><a class="contact-action whatsapp-action" href="https://wa.me/<?= $escape($whatsAppDigits) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-comments" aria-hidden="true"></i> WhatsApp</a><?php endif;?>
    <?php if(!empty($contact['email'])):?><a class="contact-action contact-action-secondary" href="mailto:<?= $escape($contact['email']) ?>"><i class="fa-solid fa-envelope" aria-hidden="true"></i> E-mail</a><?php endif;?>
    <?php if($canEdit):?><a class="contact-action contact-action-primary" href="<?= $escape($basePath) ?>/crm/contacts/<?= $escape($contact['id']) ?>/follow-ups/create"><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i> Novo follow-up</a><a class="contact-action contact-action-secondary" href="<?= $escape($basePath) ?>/crm/contacts/<?= $escape($contact['id']) ?>/edit"><i class="fa-solid fa-pen" aria-hidden="true"></i> Editar</a><?php endif;?>
  </div>
</div>
<?php if(!$canEdit):?><div class="contact-context-notice"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> <?php if($canManageContacts):?>Para editar ou criar um follow-up, selecione a unidade <strong><?= $escape($contact['unit_name']) ?></strong> no topo da tela.<?php else:?>Seu perfil possui acesso somente para consulta deste contato.<?php endif;?></div><?php endif;?>

<div class="contact-profile-grid">
  <section class="profile-card profile-main">
    <div class="profile-card-title"><i class="fa-solid fa-address-card" aria-hidden="true"></i><h2>Dados do contato</h2></div>
    <dl class="contact-data-grid">
      <div><dt>Telefone/WhatsApp</dt><dd><?= $escape($contact['phone']?:'—') ?></dd></div><div><dt>E-mail</dt><dd><?= $escape($contact['email']?:'—') ?></dd></div>
      <div><dt>CPF/CNPJ</dt><dd><?= $escape($contact['document']?:'—') ?></dd></div><div><dt>Curso</dt><dd><?= $escape($contact['course']?:'—') ?></dd></div>
      <div><dt>Interesse</dt><dd><?= $contact['interest_score']===null?'—':$escape($contact['interest_score'].'/10') ?></dd></div><div><dt>Polo/Cidade</dt><dd><?= $escape($contact['origin_city']?:'—') ?></dd></div>
      <div><dt>Atendente</dt><dd><?= $escape($contact['responsible_name']?:'Não definido') ?></dd></div><div><dt>Unidade responsável</dt><dd><?= $escape($contact['unit_name']) ?></dd></div>
      <div><dt>Origem</dt><dd><?= $escape($sourceLabels[$contact['registration_source']]??$contact['registration_source']) ?></dd></div><div><dt>Entrada</dt><dd><?= $escape(date('d/m/Y H:i',strtotime((string)$contact['registered_at']))) ?></dd></div>
    </dl>
    <div class="contact-notes"><h3>Observações</h3><p><?= nl2br($escape($contact['notes']?:'Nenhuma observação registrada.')) ?></p></div>
  </section>

  <aside class="profile-card next-followup-card <?= $nextOverdue?'is-overdue':'' ?>">
    <div class="profile-card-title"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i><h2>Próximo retorno</h2></div>
    <?php if($nextFollowUp===null):?><div class="profile-empty"><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i><p>Nenhum retorno pendente.</p><?php if($canEdit):?><a href="<?= $escape($basePath) ?>/crm/contacts/<?= $escape($contact['id']) ?>/follow-ups/create">Agendar follow-up</a><?php endif;?></div><?php else:?><span class="followup-status followup-pending"><?= $nextOverdue?'Atrasado':'Pendente' ?></span><strong class="next-action"><?= $escape($nextFollowUp['action']) ?></strong><time><?= $escape(date('d/m/Y H:i',strtotime((string)$nextFollowUp['scheduled_at']))) ?></time><span class="meta">Atendente: <?= $escape($nextFollowUp['responsible_name']) ?></span><p><?= nl2br($escape($nextFollowUp['notes'])) ?></p><?php endif;?>
  </aside>
</div>

<section class="profile-card contact-history-card">
  <div class="profile-card-title"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><h2>Histórico de follow-ups</h2><span class="history-count"><?= count($followUps) ?></span></div>
  <?php if($followUps===[]):?><div class="profile-empty horizontal"><i class="fa-solid fa-inbox" aria-hidden="true"></i><p>Nenhum follow-up registrado.</p></div><?php else:?><div class="contact-timeline"><?php foreach($followUps as $item):?><article><span class="timeline-marker"></span><div class="timeline-content"><header><strong><?= $escape($item['action']) ?></strong><span class="followup-status followup-<?= $escape($item['status']) ?>"><?= $escape($followUpLabels[$item['status']]??$item['status']) ?></span></header><div class="meta"><?= $escape(date('d/m/Y H:i',strtotime((string)$item['scheduled_at']))) ?> · <?= $escape($item['responsible_name']) ?></div><p><?= nl2br($escape($item['notes'])) ?></p></div></article><?php endforeach;?></div><?php endif;?>
</section>

<section class="profile-card contact-history-card">
  <div class="profile-card-title"><i class="fa-solid fa-list" aria-hidden="true"></i><h2>Alterações do cadastro</h2><span class="history-count"><?= count($events) ?></span></div>
  <div class="contact-event-list"><?php foreach($events as $event):?><article><span class="event-icon"><i class="fa-solid <?= $escape($eventIcons[$event['event_type']]??'fa-circle-info') ?>" aria-hidden="true"></i></span><div><strong><?= $escape($event['description']) ?></strong><div class="meta"><?= $escape(date('d/m/Y H:i',strtotime((string)$event['created_at']))) ?> · <?= $escape($event['actor_name']?:'Sistema') ?></div></div></article><?php endforeach;?></div>
</section>
