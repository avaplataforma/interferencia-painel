<?php

require_once(__DIR__ . '/../../config.php');

require_login();
if (is_siteadmin()) {
    redirect(new moodle_url('/my/courses.php'));
}
$brand = \local_mundointer\local\brand_resolver::current();
$cpf = preg_replace('/\D/', '', (string) $USER->idnumber) ?? '';
$token = '';
if ($cpf !== '') {
    $field = $DB->get_record('user_info_field', ['shortname' => 'mundointer_portal_token']);
    if ($field) {
        $token = (string) ($DB->get_field('user_info_data', 'data', ['userid' => $USER->id, 'fieldid' => $field->id]) ?: '');
    }
}
$central = rtrim((string) get_config('local_mundointer', 'centralurl'), '/');
$orgCode = $brand !== null ? (string) ($brand['code'] ?? '') : '';

$PAGE->set_url('/local/mundointer/portal.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Secretaria Digital');
$PAGE->set_heading('Secretaria Digital');
echo $OUTPUT->header();

$data = null;
$error = '';
if ($cpf === '' || $token === '') {
    $error = 'Seu acesso ainda não está vinculado ao Mundo Inter. Fale com a franquia.';
} else {
    $curl = new \curl();
    $params = ['cpf' => $cpf, 'token' => $token];
    if ($orgCode !== '') $params['org'] = $orgCode;
    $response = $curl->get($central . '/portal/aluno', $params, ['CURLOPT_TIMEOUT' => 20, 'CURLOPT_CONNECTTIMEOUT' => 10]);
    $decoded = json_decode((string) $response, true);
    if (is_array($decoded) && !empty($decoded['ok'])) {
        $data = $decoded;
    } else {
        $error = 'Não foi possível carregar seus dados agora. Tente novamente em instantes.';
    }
}

$brandName = $brand !== null ? s((string) ($brand['name'] ?? '')) : '';
$site = $brand !== null ? s((string) ($brand['site_url'] ?? '')) : '';
$firstName = s((string) explode(' ', (string) $USER->firstname, 2)[0]);
$statusLabel = static function (string $status): string {
    return match ($status) {
        'released' => 'Acesso liberado',
        'payment_confirmed' => 'Pagamento confirmado',
        'payment_waived' => 'Dispensado',
        'payment_pending' => 'Aguardando pagamento',
        default => $status,
    };
};
$statusTone = static function (string $status): string {
    return in_array($status, ['released', 'payment_confirmed', 'payment_waived'], true) ? 'good' : (in_array($status, ['payment_pending'], true) ? 'warn' : 'neutral');
};
$canAccess = static function (array $enrollment): bool {
    return (int) ($enrollment['ava_course_id'] ?? 0) > 0 && (string) ($enrollment['moodle_enrolment_status'] ?? '') === 'released';
};
$courseUrl = static function (array $enrollment): string {
    return (new moodle_url('/course/view.php', ['id' => (int) $enrollment['ava_course_id']]))->out(false);
};
$firstCourse = null;
foreach (($data['enrollments'] ?? []) as $enrollment) {
    if ($canAccess($enrollment)) { $firstCourse = $enrollment; break; }
}
?>
<style>
.mi-dash{max-width:76rem;margin:0 auto;padding:0 1rem 2rem}
.mi-dash-hero{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin:0 0 1.3rem;padding:1.4rem 1.5rem;border-radius:1rem;color:#fff;background:linear-gradient(120deg,var(--mundointer-primary),var(--mundointer-secondary));box-shadow:0 .7rem 1.8rem color-mix(in srgb,var(--mundointer-primary) 25%,transparent)}
.mi-dash-hero strong,.mi-dash-hero small{display:block}
.mi-dash-hero strong{font-size:1.45rem}
.mi-dash-hero small{opacity:.9;margin-top:.2rem}
.mi-dash-hero .mi-dash-hero-actions{margin-left:auto;display:flex;gap:.5rem;flex-wrap:wrap}
.mi-dash-hero a{display:inline-flex;align-items:center;gap:.45rem;padding:.55rem .9rem;border-radius:.65rem;background:#fff;color:var(--mundointer-primary);text-decoration:none;font-weight:700;box-shadow:0 .3rem .8rem rgb(20 40 70 / 14%)}
.mi-dash-hero a:hover{background:#f4f7f9}
.mi-dash-error{padding:1rem 1.2rem;border:1px solid #f0c9cc;border-radius:.8rem;background:#fdf0f1;color:#a3271e}
.mi-dash-tabs{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.6rem;margin:0 0 1.2rem}
.mi-dash-tab{display:flex;align-items:center;justify-content:center;gap:.5rem;min-height:3.4rem;padding:.6rem .8rem;border:0;border-radius:.8rem;color:#fff;font:inherit;font-weight:800;cursor:pointer;box-shadow:0 .4rem 1rem rgb(20 40 70 / 10%);transition:transform .15s ease}
.mi-dash-tab:hover{transform:translateY(-2px)}
.mi-dash-tab[aria-selected="true"]{outline:3px solid rgb(255 255 255 / 60%);outline-offset:2px}
.mi-dash-tab i{font-size:1.05rem}
.mi-dash-tab.journey{background:linear-gradient(135deg,#2563eb,#1d4ed8)}
.mi-dash-tab.enroll{background:linear-gradient(135deg,#16a34a,#15803d)}
.mi-dash-tab.finance{background:linear-gradient(135deg,#f59e0b,#d97706)}
.mi-dash-tab.tickets{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}
.mi-dash-tab.documents{background:linear-gradient(135deg,#0ea5e9,#0284c7)}
.mi-dash-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.7rem;margin-bottom:1.2rem}
.mi-dash-kpi{display:flex;align-items:center;gap:.75rem;padding:.95rem 1rem;border:1px solid #e3e8ec;border-radius:.9rem;background:#fff}
.mi-dash-kpi i{display:grid;place-items:center;width:2.5rem;height:2.5rem;flex:0 0 2.5rem;border-radius:.75rem;color:#fff;font-size:1rem}
.mi-dash-kpi strong,.mi-dash-kpi small{display:block}
.mi-dash-kpi strong{font-size:1.3rem}
.mi-dash-kpi small{color:#647482}
.mi-dash-panel{display:none;margin-bottom:1.1rem;padding:1.2rem;border:1px solid #dfe5ea;border-top:5px solid #999;border-radius:.9rem;background:#fff}
.mi-dash-panel[data-active="1"]{display:block}
.mi-dash-panel.journey{border-top-color:#2563eb}
.mi-dash-panel.enroll{border-top-color:#16a34a}
.mi-dash-panel.finance{border-top-color:#f59e0b}
.mi-dash-panel.tickets{border-top-color:#8b5cf6}
.mi-dash-panel.documents{border-top-color:#0ea5e9}
.mi-dash-panel h2{display:flex;align-items:center;gap:.55rem;margin:0 0 .9rem;font-size:1.15rem}
.mi-dash-row{display:flex;align-items:flex-start;justify-content:space-between;gap:.8rem;padding:.75rem 0;border-bottom:1px solid #eef2f5}
.mi-dash-row:last-child{border-bottom:0}
.mi-dash-row strong,.mi-dash-row small{display:block}
.mi-dash-row small{color:#647482;margin-top:.15rem}
.mi-dash-badge{padding:.28rem .6rem;border-radius:999px;font-size:.72rem;font-weight:800;white-space:nowrap}
.mi-dash-badge.good{color:#176b3a;background:#e7f7ef}
.mi-dash-badge.warn{color:#946200;background:#fff3cf}
.mi-dash-badge.neutral{color:#405267;background:#eef2f5}
.mi-dash-progress{height:.5rem;margin-top:.4rem;border-radius:999px;background:#eef2f5;overflow:hidden}
.mi-dash-progress span{display:block;height:100%;border-radius:999px;background:#16a34a}
.mi-dash-actions{display:flex;gap:.45rem;flex-wrap:wrap;justify-content:flex-end}
.mi-dash-actions a,.mi-dash-actions button{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .8rem;border:0;border-radius:.6rem;color:#fff;text-decoration:none;font:inherit;font-weight:700;cursor:pointer}
.mi-dash-actions .slip{background:#f59e0b}
.mi-dash-actions .invoice{background:#647482}
.mi-dash-actions .new{background:var(--mundointer-primary)}
.mi-dash-form{display:grid;gap:.7rem;margin-top:1rem;padding:1rem;border:1px dashed #cfd8df;border-radius:.8rem;background:#f8fafb}
.mi-dash-form label{display:grid;gap:.3rem;color:#647482;font-size:.82rem;font-weight:700}
.mi-dash-form input,.mi-dash-form textarea,.mi-dash-form select{width:100%;min-height:2.7rem;padding:.6rem .7rem;border:1px solid #c8d4dc;border-radius:.6rem;font:inherit;background:#fff}
.mi-dash-form textarea{resize:vertical}
.mi-dash-form-feedback{margin-top:.5rem;font-size:.85rem}
.mi-dash-form-feedback.ok{color:#176b3a}
.mi-dash-form-feedback.err{color:#a3271e}
.mi-dash-empty{color:#647482;padding:.4rem 0}
@media(max-width:900px){.mi-dash-tabs,.mi-dash-kpis{grid-template-columns:repeat(2,1fr)}}
</style>
<div class="mi-dash">
 <header class="mi-dash-hero">
  <div><strong>Olá, <?php echo $firstName; ?>! 👋</strong><small><?php echo $brandName !== '' ? $brandName : ''; ?><?php echo $data !== null ? ' · ' . s((string) ($data['student']['unit'] ?? '')) : ''; ?></small></div>
  <div class="mi-dash-hero-actions">
   <?php if ($firstCourse !== null): ?><a href="<?php echo $courseUrl($firstCourse); ?>"><i class="fa-solid fa-play"></i> Iniciar curso</a><?php endif; ?>
   <?php if ($site !== ''): ?><a href="<?php echo $site; ?>" target="_blank" rel="noopener"><i class="fa-solid fa-globe"></i> Site da franquia</a><?php endif; ?>
  </div>
 </header>
 <?php if ($error !== ''): ?><div class="mi-dash-error"><?php echo s($error); ?></div><?php endif; ?>
 <?php if ($data !== null): ?>
 <nav class="mi-dash-tabs" role="tablist" aria-label="Seções do Secretaria Digital">
  <button class="mi-dash-tab journey" type="button" role="tab" data-mi-tab="journey" aria-selected="true"><i class="fa-solid fa-route"></i> Jornada</button>
  <button class="mi-dash-tab enroll" type="button" role="tab" data-mi-tab="enroll" aria-selected="false"><i class="fa-solid fa-graduation-cap"></i> Matrículas</button>
  <button class="mi-dash-tab finance" type="button" role="tab" data-mi-tab="finance" aria-selected="false"><i class="fa-solid fa-wallet"></i> Financeiro</button>
  <button class="mi-dash-tab tickets" type="button" role="tab" data-mi-tab="tickets" aria-selected="false"><i class="fa-solid fa-headset"></i> Tickets</button>
  <button class="mi-dash-tab documents" type="button" role="tab" data-mi-tab="documents" aria-selected="false"><i class="fa-solid fa-folder-open"></i> Documentos</button>
 </nav>
 <div class="mi-dash-kpis">
  <div class="mi-dash-kpi"><i class="fa-solid fa-route" style="background:#2563eb"></i><div><strong><?php echo (int) $data['journey']['matriculas']; ?></strong><small>Matrículas</small></div></div>
  <div class="mi-dash-kpi"><i class="fa-solid fa-check-circle" style="background:#16a34a"></i><div><strong><?php echo (int) $data['journey']['liberadas']; ?></strong><small>Liberadas no AVA</small></div></div>
  <div class="mi-dash-kpi"><i class="fa-solid fa-award" style="background:#8b5cf6"></i><div><strong><?php echo (int) $data['journey']['certificados']; ?></strong><small>Certificados</small></div></div>
  <div class="mi-dash-kpi"><i class="fa-solid fa-file-invoice-dollar" style="background:#f59e0b"></i><div><strong><?php echo (int) $data['journey']['pagamentos_abertos']; ?></strong><small>Parcelas a pagar</small></div></div>
  <div class="mi-dash-kpi"><i class="fa-solid fa-ticket" style="background:#0ea5e9"></i><div><strong><?php echo (int) $data['journey']['tickets_abertos']; ?></strong><small>Tickets abertos</small></div></div>
 </div>
 <section class="mi-dash-panel journey" data-mi-panel="journey" data-active="1">
  <h2><i class="fa-solid fa-route" style="color:#2563eb"></i> Jornada</h2>
  <div class="mi-dash-row"><div><strong>Seu caminho na <?php echo $brandName !== '' ? $brandName : 'franquia'; ?></strong><small>Acompanhe abaixo cada etapa: matrícula, pagamento, acesso ao AVA e certificado.</small></div></div>
  <?php foreach (($data['enrollments'] ?? []) as $enrollment): ?>
  <div class="mi-dash-row"><div><strong><?php echo s((string) ($enrollment['course_name'] ?? 'Curso')); ?></strong><small><?php echo s($statusLabel((string) ($enrollment['status'] ?? ''))); ?><?php echo (float) ($enrollment['academic_progress_percent'] ?? 0) > 0 ? ' · Progresso: ' . round((float) $enrollment['academic_progress_percent'], 1) . '%' : ''; ?></small><?php if ((float) ($enrollment['academic_progress_percent'] ?? 0) > 0): ?><div class="mi-dash-progress"><span style="width:<?php echo round((float) $enrollment['academic_progress_percent'], 1); ?>%"></span></div><?php endif; ?></div><div class="mi-dash-actions"><?php if ($canAccess($enrollment)): ?><a class="new" href="<?php echo $courseUrl($enrollment); ?>"><i class="fa-solid fa-play"></i> Acessar curso</a><?php endif; ?><span class="mi-dash-badge <?php echo $statusTone((string) ($enrollment['status'] ?? '')); ?>"><?php echo s($statusLabel((string) ($enrollment['status'] ?? ''))); ?></span></div></div>
  <?php endforeach; ?>
 </section>
 <section class="mi-dash-panel enroll" data-mi-panel="enroll">
  <h2><i class="fa-solid fa-graduation-cap" style="color:#16a34a"></i> Matrículas</h2>
  <?php if (($data['enrollments'] ?? []) === []): ?><p class="mi-dash-empty">Nenhuma matrícula encontrada.</p><?php endif; ?>
  <?php foreach (($data['enrollments'] ?? []) as $enrollment): ?>
  <div class="mi-dash-row"><div><strong><?php echo s((string) ($enrollment['course_name'] ?? 'Curso')); ?></strong><small>Matrícula em <?php echo s((string) substr((string) ($enrollment['created_at'] ?? ''), 0, 10)); ?><?php echo (string) ($enrollment['academic_certificate_status'] ?? '') === 'available' ? ' · Certificado disponível' : ''; ?></small></div><div class="mi-dash-actions"><?php if ($canAccess($enrollment)): ?><a class="new" href="<?php echo $courseUrl($enrollment); ?>"><i class="fa-solid fa-play"></i> Acessar curso</a><?php endif; ?><span class="mi-dash-badge <?php echo $statusTone((string) ($enrollment['status'] ?? '')); ?>"><?php echo s($statusLabel((string) ($enrollment['status'] ?? ''))); ?></span></div></div>
  <?php endforeach; ?>
 </section>
 <section class="mi-dash-panel finance" data-mi-panel="finance">
  <h2><i class="fa-solid fa-wallet" style="color:#f59e0b"></i> Financeiro</h2>
  <?php if (($data['payments'] ?? []) === []): ?><p class="mi-dash-empty">Nenhum pagamento registrado.</p><?php endif; ?>
  <?php foreach (($data['payments'] ?? []) as $payment): $open=in_array((string)$payment['status'],['PENDING','OVERDUE'],true); ?>
  <div class="mi-dash-row"><div><strong>R$ <?php echo number_format((float) $payment['value'], 2, ',', '.'); ?></strong><small><?php echo s((string) ($payment['description'] ?? '')) ?: 'Cobrança'; ?> · vencimento <?php echo s((string) substr((string) ($payment['due_date'] ?? ''), 0, 10)); ?><?php echo (string) ($payment['payment_date'] ?? '') !== '' ? ' · pago em ' . s((string) substr((string) $payment['payment_date'], 0, 10)) : ''; ?></small></div><div class="mi-dash-actions"><?php if ($open && !empty($payment['bank_slip_url'])): ?><a class="slip" href="<?php echo s((string) $payment['bank_slip_url']); ?>" target="_blank" rel="noopener"><i class="fa-solid fa-barcode"></i> 2ª via</a><?php endif; ?><?php if (!empty($payment['invoice_url'])): ?><a class="invoice" href="<?php echo s((string) $payment['invoice_url']); ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-invoice"></i> Nota</a><?php endif; ?><span class="mi-dash-badge <?php echo in_array((string) $payment['status'], ['CONFIRMED', 'RECEIVED'], true) ? 'good' : ($open ? 'warn' : 'neutral'); ?>"><?php echo s((string) $payment['status']); ?></span></div></div>
  <?php endforeach; ?>
 </section>
 <section class="mi-dash-panel tickets" data-mi-panel="tickets">
  <h2><i class="fa-solid fa-headset" style="color:#8b5cf6"></i> Tickets</h2>
  <?php if (($data['tickets'] ?? []) === []): ?><p class="mi-dash-empty">Nenhum ticket aberto.</p><?php endif; ?>
  <?php foreach (($data['tickets'] ?? []) as $ticket): ?>
  <div class="mi-dash-row"><div><strong><?php echo s((string) $ticket['subject']); ?></strong><small>Aberto em <?php echo s((string) substr((string) ($ticket['created_at'] ?? ''), 0, 10)); ?></small></div><span class="mi-dash-badge <?php echo in_array((string) $ticket['status'], ['resolved', 'closed'], true) ? 'good' : 'warn'; ?>"><?php echo s((string) $ticket['status']); ?></span></div>
  <?php endforeach; ?>
  <form class="mi-dash-form" data-mi-ticket-form>
   <label>Assunto<input name="subject" required minlength="3" maxlength="180" placeholder="Ex.: Dúvida sobre meu acesso"></label>
   <label>Descrição<textarea name="description" required minlength="3" maxlength="10000" rows="4" placeholder="Descreva sua necessidade..."></textarea></label>
   <div class="mi-dash-actions"><button class="new" type="submit"><i class="fa-solid fa-paper-plane"></i> Abrir ticket</button></div>
   <p class="mi-dash-form-feedback" data-mi-ticket-feedback hidden></p>
  </form>
 </section>
 <section class="mi-dash-panel documents" data-mi-panel="documents">
  <h2><i class="fa-solid fa-folder-open" style="color:#0ea5e9"></i> Documentos</h2>
  <?php if (($data['documents'] ?? []) === []): ?><p class="mi-dash-empty">Nenhum documento enviado.</p><?php endif; ?>
  <?php foreach (($data['documents'] ?? []) as $document): ?>
  <div class="mi-dash-row"><div><strong><i class="fa-solid fa-file" style="color:#0ea5e9"></i> <?php echo s((string) ($document['title'] ?: $document['original_name'])); ?></strong><small><?php echo s((string) ($document['category'] ?? 'Documento')); ?> · <?php echo s((string) substr((string) ($document['created_at'] ?? ''), 0, 10)); ?></small></div></div>
  <?php endforeach; ?>
  <form class="mi-dash-form" data-mi-document-form enctype="multipart/form-data">
   <label>Título<input name="title" maxlength="190" placeholder="Ex.: Comprovante de pagamento"></label>
   <label>Tipo<select name="category"><?php foreach (($data['document_categories'] ?? []) as $code => $label): ?><option value="<?php echo s($code); ?>"><?php echo s($label); ?></option><?php endforeach; ?></select></label>
   <label>Arquivo (PDF, imagem, Word, Excel ou texto)<input type="file" name="document" required></label>
   <label>Observação<textarea name="description" maxlength="1000" rows="2"></textarea></label>
   <div class="mi-dash-actions"><button class="new" type="submit"><i class="fa-solid fa-upload"></i> Enviar documento</button></div>
   <p class="mi-dash-form-feedback" data-mi-document-feedback hidden></p>
  </form>
 </section>
 <script>
 (function () {
   var tabs = document.querySelectorAll(".mi-dash-tab[data-mi-tab]");
   var panels = document.querySelectorAll(".mi-dash-panel[data-mi-panel]");
   function activate(name) {
     tabs.forEach(function (tab) { tab.setAttribute("aria-selected", tab.dataset.miTab === name ? "true" : "false"); });
     panels.forEach(function (panel) { panel.dataset.active = panel.dataset.miPanel === name ? "1" : "0"; });
   }
   tabs.forEach(function (tab) { tab.addEventListener("click", function () { activate(tab.dataset.miTab); }); });

   var baseUrl = "<?php echo s($central); ?>/portal/aluno";
   var portalParams = { cpf: "<?php echo s($cpf); ?>", token: "<?php echo s($token); ?>"<?php if ($orgCode !== ''): ?>, org: "<?php echo s($orgCode); ?>"<?php endif; ?> };

   var ticketForm = document.querySelector("[data-mi-ticket-form]");
   var ticketFeedback = document.querySelector("[data-mi-ticket-feedback]");
   if (ticketForm) {
     ticketForm.addEventListener("submit", function (event) {
       event.preventDefault();
       ticketFeedback.hidden = true;
       var body = new URLSearchParams(portalParams);
       body.set("subject", ticketForm.querySelector("[name=subject]").value);
       body.set("description", ticketForm.querySelector("[name=description]").value);
       fetch(baseUrl + "/ticket", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: body.toString() })
         .then(function (response) { return response.json(); })
         .then(function (result) {
           ticketFeedback.hidden = false;
           ticketFeedback.className = "mi-dash-form-feedback " + (result.ok ? "ok" : "err");
           ticketFeedback.textContent = result.ok ? "Ticket aberto! Nossa equipe já pode acompanhar." : (result.error || "Não foi possível abrir o ticket.");
           if (result.ok) { ticketForm.reset(); setTimeout(function () { window.location.reload(); }, 1200); }
         })
         .catch(function () { ticketFeedback.hidden = false; ticketFeedback.className = "mi-dash-form-feedback err"; ticketFeedback.textContent = "Falha de conexão. Tente novamente."; });
     });
   }

   var documentForm = document.querySelector("[data-mi-document-form]");
   var documentFeedback = document.querySelector("[data-mi-document-feedback]");
   if (documentForm) {
     documentForm.addEventListener("submit", function (event) {
       event.preventDefault();
       documentFeedback.hidden = true;
       var formData = new FormData();
       Object.keys(portalParams).forEach(function (key) { formData.set(key, portalParams[key]); });
       formData.set("title", documentForm.querySelector("[name=title]").value);
       formData.set("category", documentForm.querySelector("[name=category]").value);
       formData.set("description", documentForm.querySelector("[name=description]").value);
       formData.set("document", documentForm.querySelector("[name=document]").files[0]);
       fetch(baseUrl + "/document", { method: "POST", body: formData })
         .then(function (response) { return response.json(); })
         .then(function (result) {
           documentFeedback.hidden = false;
           documentFeedback.className = "mi-dash-form-feedback " + (result.ok ? "ok" : "err");
           documentFeedback.textContent = result.ok ? "Documento enviado! A franquia pode vê-lo no seu cadastro." : (result.error || "Não foi possível enviar o documento.");
           if (result.ok) { documentForm.reset(); setTimeout(function () { window.location.reload(); }, 1200); }
         })
         .catch(function () { documentFeedback.hidden = false; documentFeedback.className = "mi-dash-form-feedback err"; documentFeedback.textContent = "Falha de conexão. Tente novamente."; });
     });
   }
 })();
 </script>
 <?php endif; ?>
</div>
<?php
echo $OUTPUT->footer();
