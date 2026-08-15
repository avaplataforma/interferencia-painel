<style>
.mail-page{max-width:90rem;margin:0 auto}.mail-head{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:1.25rem}.mail-head h1{margin:.15rem 0 .35rem;font-size:clamp(2rem,4vw,3rem)}.mail-head p{margin:0;max-width:54rem;color:var(--inter-muted)}.mail-back{white-space:nowrap}.mail-alert{margin-bottom:1rem;padding:.9rem 1rem;border-radius:.8rem}.mail-alert.ok{color:#086c42;background:#e3f8ed;border:1px solid #a9e2c5}.mail-alert.error{color:#a31621;background:#fff0f1;border:1px solid #ffc2c7}
.mail-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.85rem;margin-bottom:1rem}.mail-stat{padding:1rem 1.1rem;border:1px solid #dce4e9;border-radius:1rem;background:#fff;box-shadow:0 .4rem 1.2rem rgb(23 33 43 / 5%)}.mail-stat span{display:block;color:var(--inter-muted);font-size:.78rem}.mail-stat strong{display:block;margin-top:.25rem;font-size:1.35rem}.mail-stat i{margin-right:.35rem;color:#f97316}
.mail-grid{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(20rem,.75fr);gap:1rem;align-items:start}.mail-card{padding:1.25rem;border:1px solid #dce4e9;border-radius:1rem;background:#fff;box-shadow:0 .45rem 1.4rem rgb(23 33 43 / 6%)}.mail-card h2{margin:0;font-size:1.35rem}.mail-card-head{display:flex;align-items:center;gap:.8rem;padding-bottom:1rem;margin-bottom:1rem;border-bottom:1px solid #e5ebef}.mail-card-head>i{display:grid;place-items:center;width:2.8rem;height:2.8rem;border-radius:.8rem;color:#c2410c;background:#fff2e8}.mail-card-head p{margin:.2rem 0 0;color:var(--inter-muted);font-size:.86rem}.mail-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.9rem}.mail-field label{display:block;margin-bottom:.35rem;font-weight:800;font-size:.86rem}.mail-field input,.mail-field select{width:100%;min-height:2.85rem;padding:.65rem .75rem;border:1px solid #bdcbd4;border-radius:.65rem;background:#fff;color:var(--inter-ink)}.mail-field small{display:block;margin-top:.3rem;color:var(--inter-muted);line-height:1.35}.mail-field.full{grid-column:1/-1}.mail-check{display:flex;align-items:flex-start;gap:.55rem;padding:.85rem;border-radius:.75rem;background:#f5f8fa}.mail-check input{margin-top:.2rem}.mail-actions{display:flex;justify-content:flex-end;gap:.65rem;margin-top:1rem}.mail-btn{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;min-height:2.75rem;padding:.65rem 1rem;border:1px solid #bdcbd4;border-radius:.7rem;background:#fff;color:var(--inter-ink);font-weight:800;text-decoration:none;cursor:pointer}.mail-btn.primary{color:#fff;background:#f11d2d;border-color:#f11d2d}.mail-test{display:grid;gap:.75rem}.mail-test input{width:100%;min-height:2.85rem;padding:.65rem .75rem;border:1px solid #bdcbd4;border-radius:.65rem}.mail-rule{margin-top:1rem;padding:1rem;border:1px solid #fed7aa;border-radius:.85rem;background:#fff7ed}.mail-rule strong{display:block;margin-bottom:.4rem;color:#9a3412}.mail-rule p{margin:0;color:#7c4a25;font-size:.87rem;line-height:1.5}
.mail-section{margin-top:1rem}.mail-section-title{display:flex;align-items:end;justify-content:space-between;gap:1rem;margin-bottom:.8rem}.mail-section-title h2{margin:0}.mail-section-title p{margin:.2rem 0 0;color:var(--inter-muted)}.sender-list{display:grid;gap:.75rem}.sender-card{border:1px solid #dce4e9;border-radius:1rem;background:#fff;overflow:hidden}.sender-summary{display:grid;grid-template-columns:minmax(12rem,1fr) minmax(12rem,1fr) auto;gap:1rem;align-items:center;padding:1rem 1.1rem}.sender-summary strong,.sender-summary small{display:block}.sender-summary small{margin-top:.2rem;color:var(--inter-muted)}.sender-status{display:inline-flex;align-items:center;gap:.35rem;width:max-content;padding:.32rem .6rem;border-radius:999px;font-size:.76rem;font-weight:800}.sender-status.verified{color:#087443;background:#dff7ea}.sender-status.pending{color:#8b5e00;background:#fff0c2}.sender-status.rejected{color:#a31621;background:#ffe1e4}.sender-form{display:grid;grid-template-columns:1fr 1fr 1fr .75fr auto;gap:.75rem;align-items:end;padding:1rem 1.1rem;background:#f8fafb;border-top:1px solid #e5ebef}.sender-form .mail-field{min-width:0}.sender-save{align-self:end}.sender-empty{padding:1.2rem;color:var(--inter-muted)}
.mail-log{overflow:auto;border:1px solid #dce4e9;border-radius:1rem;background:#fff}.mail-log table{width:100%;border-collapse:collapse;min-width:60rem}.mail-log th,.mail-log td{padding:.8rem .9rem;text-align:left;border-bottom:1px solid #e5ebef;vertical-align:top}.mail-log th{font-size:.78rem;text-transform:uppercase;color:var(--inter-muted);background:#f8fafb}.delivery-status{font-weight:800}.delivery-status.sent{color:#087443}.delivery-status.failed{color:#b91c1c}.mail-empty{text-align:center;color:var(--inter-muted);padding:1.5rem!important}
@media(max-width:980px){.mail-grid{grid-template-columns:1fr}.mail-stats{grid-template-columns:repeat(2,1fr)}.sender-form{grid-template-columns:repeat(2,1fr)}.sender-save{grid-column:1/-1}.sender-save .mail-btn{width:100%}}@media(max-width:640px){.mail-head{align-items:flex-start;flex-direction:column}.mail-stats,.mail-fields,.sender-form{grid-template-columns:1fr}.mail-field.full,.sender-save{grid-column:auto}.sender-summary{grid-template-columns:1fr}.mail-actions{flex-direction:column}.mail-actions .mail-btn{width:100%}}
</style>

<div class="mail-page">
 <header class="mail-head">
  <div><p class="eyebrow">ADM Central · Integrações</p><h1>E-mail Central</h1><p>Envios transacionais da rede com identidade de cada franquia, proteção de domínio e histórico completo.</p></div>
  <a class="mail-btn mail-back" href="<?= $escape($basePath) ?>/admin/platform/integrations"><i class="fa-solid fa-arrow-left"></i> Integrações</a>
 </header>

 <?php if(!empty($message)): ?><div class="mail-alert ok"><i class="fa-solid fa-circle-check"></i> <?= $escape((string)$message) ?></div><?php endif; ?>
 <?php if(!empty($error)): ?><div class="mail-alert error"><i class="fa-solid fa-triangle-exclamation"></i> <?= $escape((string)$error) ?></div><?php endif; ?>

 <section class="mail-stats" aria-label="Resumo dos envios">
  <div class="mail-stat"><span><i class="fa-solid fa-power-off"></i>Situação</span><strong><?= !empty($settings['configured'])&& !empty($settings['is_active'])?'Ativa':'Pendente' ?></strong></div>
  <div class="mail-stat"><span><i class="fa-solid fa-paper-plane"></i>Enviados em 30 dias</span><strong><?= (int)($summary['sent']??0) ?></strong></div>
  <div class="mail-stat"><span><i class="fa-solid fa-circle-exclamation"></i>Falhas em 30 dias</span><strong><?= (int)($summary['failed']??0) ?></strong></div>
  <div class="mail-stat"><span><i class="fa-solid fa-vial-circle-check"></i>Último teste</span><strong style="font-size:1rem"><?= !empty($settings['last_tested_at'])?$escape(date('d/m/Y H:i',strtotime((string)$settings['last_tested_at']))):'Não realizado' ?></strong></div>
 </section>

 <div class="mail-grid">
  <section class="mail-card">
   <div class="mail-card-head"><i class="fa-solid fa-envelope-open-text"></i><div><h2>Servidor de envio</h2><p>Credenciais criptografadas e compartilhadas por toda a rede.</p></div></div>
   <form method="post" action="<?= $escape($basePath) ?>/admin/platform/integrations/email">
    <?= $csrfField ?>
    <div class="mail-fields">
     <div class="mail-field"><label for="smtp_host">Servidor SMTP *</label><input id="smtp_host" name="smtp_host" required value="<?= $escape((string)($settings['smtp_host']??'')) ?>" placeholder="smtp.seuprovedor.com"></div>
     <div class="mail-field"><label for="smtp_port">Porta *</label><input id="smtp_port" name="smtp_port" type="number" min="1" max="65535" required value="<?= (int)($settings['smtp_port']??587) ?>"></div>
     <div class="mail-field"><label for="encryption">Segurança *</label><select id="encryption" name="encryption"><option value="tls" <?= ($settings['encryption']??'tls')==='tls'?'selected':'' ?>>STARTTLS</option><option value="ssl" <?= ($settings['encryption']??'')==='ssl'?'selected':'' ?>>SSL/TLS</option><option value="none" <?= ($settings['encryption']??'')==='none'?'selected':'' ?>>Sem criptografia</option></select></div>
     <div class="mail-field"><label for="username">Usuário SMTP *</label><input id="username" name="username" autocomplete="off" placeholder="<?= !empty($settings['username_last4'])?'Credencial salva ····'.$escape((string)$settings['username_last4']):'Usuário do provedor' ?>"><small>Vazio preserva a credencial atual.</small></div>
     <div class="mail-field"><label for="password">Senha SMTP *</label><input id="password" name="password" type="password" autocomplete="new-password" placeholder="<?= !empty($settings['password_last4'])?'Senha salva ····'.$escape((string)$settings['password_last4']):'Senha ou chave SMTP' ?>"><small>Vazio preserva a senha atual.</small></div>
     <div class="mail-field"><label for="from_name">Nome central *</label><input id="from_name" name="from_name" required value="<?= $escape((string)($settings['from_name']??'Mundo Inter')) ?>"></div>
     <div class="mail-field"><label for="from_email">E-mail central *</label><input id="from_email" name="from_email" type="email" required value="<?= $escape((string)($settings['from_email']??'no-reply@mundointer.com.br')) ?>"></div>
     <div class="mail-field"><label for="reply_to_email">Responder para</label><input id="reply_to_email" name="reply_to_email" type="email" value="<?= $escape((string)($settings['reply_to_email']??'')) ?>" placeholder="suporte@mundointer.com.br"></div>
     <label class="mail-check full"><input type="checkbox" name="is_active" value="1" <?= !empty($settings['is_active'])?'checked':'' ?>><span><strong>Ativar envios reais</strong><br><small>Quando ativo, acessos de alunos e demais mensagens transacionais usam este serviço.</small></span></label>
    </div>
    <div class="mail-actions"><button class="mail-btn primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar integração</button></div>
   </form>
  </section>

  <aside class="mail-card">
   <div class="mail-card-head"><i class="fa-solid fa-paper-plane"></i><div><h2>Validar entrega</h2><p>Envie uma mensagem real antes de ativar.</p></div></div>
   <form class="mail-test" method="post" action="<?= $escape($basePath) ?>/admin/platform/integrations/email/test">
    <?= $csrfField ?>
    <label for="test_email"><strong>E-mail para o teste</strong></label>
    <input id="test_email" name="test_email" type="email" required placeholder="seuemail@dominio.com.br">
    <button class="mail-btn primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Enviar teste</button>
   </form>
   <div class="mail-rule"><strong><i class="fa-solid fa-shield-halved"></i> Regra de segurança</strong><p>A franquia sempre aparece no nome e no “Responder para”. O endereço <b>no-reply@dominio-da-franquia</b> só é usado depois da autenticação SPF/DKIM; antes disso, o remetente central evita rejeições e falsificação.</p></div>
   <?php if(!empty($settings['last_error'])): ?><div class="mail-alert error" style="margin:1rem 0 0"><?= $escape((string)$settings['last_error']) ?></div><?php endif; ?>
  </aside>
 </div>

 <section class="mail-section" id="franquias">
  <div class="mail-section-title"><div><h2>Identidade por franquia</h2><p>Configure o remetente personalizado somente após validar o domínio no provedor contratado.</p></div></div>
  <div class="sender-list">
   <?php if(($senders??[])===[]): ?><div class="sender-card sender-empty">Nenhuma franquia cadastrada.</div><?php endif; ?>
   <?php foreach(($senders??[]) as $sender):
    $host=trim((string)($sender['primary_host']??''));
    $status=(string)($sender['domain_status']??'pending');
    $mailDomain=preg_replace('/^www\./','',$host)?:$host;
    $defaultEmail=$mailDomain!==''?'no-reply@'.$mailDomain:'';
   ?>
    <article class="sender-card">
     <div class="sender-summary">
      <div><strong><?= $escape((string)$sender['display_name']) ?></strong><small><?= $host!==''?$escape($host):'Domínio público ainda não configurado' ?></small></div>
      <div><strong><?= !empty($sender['from_email'])?$escape((string)$sender['from_email']):'Remetente central em uso' ?></strong><small><?= !empty($sender['reply_to_email'])?'Respostas: '.$escape((string)$sender['reply_to_email']):'Resposta usa o contato da franquia' ?></small></div>
      <span class="sender-status <?= $escape($status) ?>"><i class="fa-solid <?= $status==='verified'?'fa-circle-check':($status==='rejected'?'fa-circle-xmark':'fa-clock') ?>"></i><?= $status==='verified'?'Domínio autenticado':($status==='rejected'?'Autenticação recusada':'Aguardando DNS') ?></span>
     </div>
     <form class="sender-form" method="post" action="<?= $escape($basePath) ?>/admin/platform/integrations/email/organizations/<?= (int)$sender['organization_id'] ?>">
      <?= $csrfField ?>
      <div class="mail-field"><label>Nome do remetente</label><input name="from_name" required value="<?= $escape((string)($sender['from_name']?:$sender['display_name'])) ?>"></div>
      <div class="mail-field"><label>E-mail no-reply</label><input name="from_email" type="email" required value="<?= $escape((string)($sender['from_email']?:$defaultEmail)) ?>" <?= $host===''?'disabled':'' ?>></div>
      <div class="mail-field"><label>Responder para</label><input name="reply_to_email" type="email" value="<?= $escape((string)($sender['reply_to_email']?:($sender['support_email']?:$sender['manager_email']))) ?>"></div>
      <div class="mail-field"><label>Domínio</label><select name="domain_status"><option value="pending" <?= $status==='pending'?'selected':'' ?>>Aguardando DNS</option><option value="verified" <?= $status==='verified'?'selected':'' ?>>SPF/DKIM validado</option><option value="rejected" <?= $status==='rejected'?'selected':'' ?>>Com problema</option></select></div>
      <div class="sender-save"><label class="mail-check"><input type="checkbox" name="is_active" value="1" <?= !empty($sender['is_active'])?'checked':'' ?>><span><strong>Usar remetente</strong></span></label><button class="mail-btn" type="submit" <?= $host===''?'disabled':'' ?>><i class="fa-solid fa-floppy-disk"></i> Salvar</button></div>
     </form>
    </article>
   <?php endforeach; ?>
  </div>
 </section>

 <section class="mail-section">
  <div class="mail-section-title"><div><h2>Histórico de entregas</h2><p>Últimos envios processados pela integração central.</p></div></div>
  <div class="mail-log"><table><thead><tr><th>Data</th><th>Franquia</th><th>Destinatário</th><th>Assunto</th><th>Tipo</th><th>Situação</th></tr></thead><tbody>
   <?php if(($deliveries??[])===[]): ?><tr><td class="mail-empty" colspan="6">Nenhum e-mail processado ainda.</td></tr><?php endif; ?>
   <?php foreach(($deliveries??[]) as $delivery): ?><tr><td><?= $escape(date('d/m/Y H:i',strtotime((string)$delivery['created_at']))) ?></td><td><?= $escape((string)($delivery['organization_name']??'ADM Central')) ?></td><td><?= $escape((string)$delivery['recipient_email']) ?></td><td><?= $escape((string)$delivery['subject']) ?></td><td><?= $escape((string)$delivery['message_type']) ?></td><td><span class="delivery-status <?= $escape((string)$delivery['status']) ?>"><?= $delivery['status']==='sent'?'Enviado':'Falhou' ?></span><?php if(!empty($delivery['error_message'])): ?><br><small><?= $escape((string)$delivery['error_message']) ?></small><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
 </section>
</div>
