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
$PAGE->set_title('Meu espaço');
$PAGE->set_heading('Meu espaço');
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
$email = $brand !== null ? s((string) ($brand['support_email'] ?? '')) : '';
$phoneDigits = preg_replace('/\D/', '', (string) ($brand['support_phone'] ?? '')) ?? '';
$whatsapp = strlen($phoneDigits) >= 10 ? 'https://wa.me/55' . $phoneDigits : '';
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
?>
<style>
.mi-portal{max-width:72rem;margin:0 auto;padding:0 1rem 2rem}
.mi-portal-hero{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin:0 0 1.3rem;padding:1.1rem 1.3rem;border:1px solid #dfe5ea;border-left:5px solid var(--mundointer-primary);border-radius:.9rem;background:linear-gradient(135deg,#fff,var(--mundointer-primary-soft))}
.mi-portal-hero strong,.mi-portal-hero small{display:block}
.mi-portal-hero strong{color:var(--mundointer-secondary);font-size:1.2rem}
.mi-portal-hero small{color:#647482;margin-top:.2rem}
.mi-portal-back{margin-left:auto;padding:.5rem .85rem;border-radius:.6rem;background:var(--mundointer-primary);color:#fff;text-decoration:none;font-weight:600}
.mi-portal-error{padding:1rem 1.2rem;border:1px solid #f0c9cc;border-radius:.8rem;background:#fdf0f1;color:#a3271e}
.mi-portal-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.7rem;margin-bottom:1.2rem}
.mi-portal-kpi{padding:.9rem;border:1px solid #e3e8ec;border-radius:.8rem;background:#fff}
.mi-portal-kpi strong,.mi-portal-kpi small{display:block}
.mi-portal-kpi strong{font-size:1.35rem;color:var(--mundointer-primary)}
.mi-portal-kpi small{color:#647482}
.mi-portal-card{margin-bottom:1.2rem;padding:1.15rem;border:1px solid #dfe5ea;border-radius:.9rem;background:#fff}
.mi-portal-card h2{margin:0 0 .8rem;font-size:1.1rem;color:var(--mundointer-secondary)}
.mi-portal-row{display:flex;align-items:flex-start;justify-content:space-between;gap:.8rem;padding:.7rem 0;border-bottom:1px solid #eef2f5}
.mi-portal-row:last-child{border-bottom:0}
.mi-portal-row strong,.mi-portal-row small{display:block}
.mi-portal-row small{color:#647482;margin-top:.15rem}
.mi-portal-badge{padding:.25rem .55rem;border-radius:999px;font-size:.72rem;font-weight:800;white-space:nowrap}
.mi-portal-badge.good{color:#176b3a;background:#e7f7ef}
.mi-portal-badge.warn{color:#946200;background:#fff3cf}
.mi-portal-badge.neutral{color:#405267;background:#eef2f5}
.mi-portal-contacts{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem}
.mi-portal-contacts a{padding:.45rem .8rem;border-radius:.6rem;background:var(--mundointer-primary);color:#fff;text-decoration:none;font-weight:600}
@media(max-width:840px){.mi-portal-kpis{grid-template-columns:repeat(2,1fr)}}
</style>
<div class="mi-portal">
 <header class="mi-portal-hero">
  <div><strong>Meu espaço</strong><small><?php echo $brandName !== '' ? $brandName : ''; ?><?php echo $data !== null ? ' · ' . s((string) ($data['student']['name'] ?? '')) : ''; ?></small></div>
  <a class="mi-portal-back" href="<?php echo (new moodle_url('/my/courses.php'))->out(false); ?>">Meus cursos</a>
 </header>
 <?php if ($error !== ''): ?><div class="mi-portal-error"><?php echo s($error); ?></div><?php endif; ?>
 <?php if ($data !== null): ?>
 <div class="mi-portal-kpis">
  <div class="mi-portal-kpi"><strong><?php echo (int) $data['journey']['matriculas']; ?></strong><small>Matrículas</small></div>
  <div class="mi-portal-kpi"><strong><?php echo (int) $data['journey']['liberadas']; ?></strong><small>Liberadas no AVA</small></div>
  <div class="mi-portal-kpi"><strong><?php echo (int) $data['journey']['certificados']; ?></strong><small>Certificados</small></div>
  <div class="mi-portal-kpi"><strong><?php echo (int) $data['journey']['pagamentos_abertos']; ?></strong><small>Pagamentos abertos</small></div>
  <div class="mi-portal-kpi"><strong><?php echo (int) $data['journey']['tickets_abertos']; ?></strong><small>Tickets abertos</small></div>
 </div>
 <section class="mi-portal-card">
  <h2>Matrículas</h2>
  <?php if (($data['enrollments'] ?? []) === []): ?><small style="color:#647482">Nenhuma matrícula encontrada.</small><?php endif; ?>
  <?php foreach (($data['enrollments'] ?? []) as $enrollment): ?>
  <div class="mi-portal-row"><div><strong><?php echo s((string) ($enrollment['course_name'] ?? 'Curso')); ?></strong><small>Matrícula em <?php echo s((string) substr((string) ($enrollment['created_at'] ?? ''), 0, 10)); ?><?php echo (float) ($enrollment['academic_progress_percent'] ?? 0) > 0 ? ' · Progresso: ' . round((float) $enrollment['academic_progress_percent'], 1) . '%' : ''; ?></small></div><span class="mi-portal-badge <?php echo $statusTone((string) ($enrollment['status'] ?? '')); ?>"><?php echo s($statusLabel((string) ($enrollment['status'] ?? ''))); ?></span></div>
  <?php endforeach; ?>
 </section>
 <section class="mi-portal-card">
  <h2>Financeiro</h2>
  <?php if (($data['payments'] ?? []) === []): ?><small style="color:#647482">Nenhum pagamento registrado.</small><?php endif; ?>
  <?php foreach (($data['payments'] ?? []) as $payment): ?>
  <div class="mi-portal-row"><div><strong>R$ <?php echo number_format((float) $payment['value'], 2, ',', '.'); ?></strong><small><?php echo s((string) ($payment['description'] ?? '')) ?: 'Cobrança'; ?> · vencimento <?php echo s((string) substr((string) ($payment['due_date'] ?? ''), 0, 10)); ?></small></div><span class="mi-portal-badge <?php echo in_array((string) $payment['status'], ['CONFIRMED', 'RECEIVED'], true) ? 'good' : (in_array((string) $payment['status'], ['PENDING', 'OVERDUE'], true) ? 'warn' : 'neutral'); ?>"><?php echo s((string) $payment['status']); ?></span></div>
  <?php endforeach; ?>
 </section>
 <section class="mi-portal-card">
  <h2>Tickets</h2>
  <?php if (($data['tickets'] ?? []) === []): ?><small style="color:#647482">Nenhum ticket aberto.</small><?php endif; ?>
  <?php foreach (($data['tickets'] ?? []) as $ticket): ?>
  <div class="mi-portal-row"><div><strong><?php echo s((string) $ticket['subject']); ?></strong><small>Aberto em <?php echo s((string) substr((string) ($ticket['created_at'] ?? ''), 0, 10)); ?></small></div><span class="mi-portal-badge <?php echo in_array((string) $ticket['status'], ['resolved', 'closed'], true) ? 'good' : 'warn'; ?>"><?php echo s((string) $ticket['status']); ?></span></div>
  <?php endforeach; ?>
 </section>
 <section class="mi-portal-card">
  <h2>Documentos</h2>
  <?php if (($data['documents'] ?? []) === []): ?><small style="color:#647482">Nenhum documento disponível.</small><?php endif; ?>
  <?php foreach (($data['documents'] ?? []) as $document): ?>
  <div class="mi-portal-row"><div><strong><?php echo s((string) ($document['title'] ?: $document['original_name'])); ?></strong><small><?php echo s((string) ($document['category'] ?? 'Documento')); ?> · <?php echo s((string) substr((string) ($document['created_at'] ?? ''), 0, 10)); ?></small></div></div>
  <?php endforeach; ?>
 </section>
 <div class="mi-portal-contacts">
  <?php if ($site !== ''): ?><a href="<?php echo $site; ?>" target="_blank" rel="noopener">Site da franquia</a><?php endif; ?>
  <?php if ($email !== ''): ?><a href="mailto:<?php echo $email; ?>">E-mail de suporte</a><?php endif; ?>
  <?php if ($whatsapp !== ''): ?><a href="<?php echo $whatsapp; ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
 </div>
 <?php endif; ?>
</div>
<?php
echo $OUTPUT->footer();
