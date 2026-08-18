<?php

require_once(__DIR__ . '/../../config.php');

require_login();
$brand = \local_mundointer\local\brand_resolver::current();
$PAGE->set_url('/local/mundointer/instituicao.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('institutiontitle', 'local_mundointer'));
$PAGE->set_heading(get_string('institutiontitle', 'local_mundointer'));
echo $OUTPUT->header();

$logo = $brand !== null ? s((string) ($brand['logo_url'] ?? '')) : '';
$name = $brand !== null ? s((string) ($brand['name'] ?? '')) : fullname($USER);
$welcome = $brand !== null ? s((string) ($brand['welcome_text'] ?? '')) : '';
$email = $brand !== null ? s((string) ($brand['support_email'] ?? '')) : '';
$phone = $brand !== null ? s((string) ($brand['support_phone'] ?? '')) : '';
$site = $brand !== null ? s((string) ($brand['site_url'] ?? '')) : '';
$digits = preg_replace('/\D/', '', $phone) ?? '';
$whatsapp = strlen($digits) >= 10 ? 'https://wa.me/55' . $digits : '';
$showSupport = (bool) (get_config('local_mundointer', 'supportbutton') ?? true);

$courses = enrol_get_my_courses(['id', 'fullname', 'shortname', 'summary'], 'visible DESC, sortorder ASC');
$courseCards = [];
foreach ($courses as $course) {
    $percent = null;
    if (class_exists('\core_completion\progress')) {
        try {
            $percent = (int) \core_completion\progress::get_course_progress_percentage($course);
        } catch (Throwable $ignored) {
            $percent = null;
        }
    }
    $image = '';
    foreach (\core_course\external\course_summary_exporter::get_course_image($course) as $file) {
        $image = (string) $file;
        break;
    }
    $courseCards[] = [
        'id' => (int) $course->id,
        'fullname' => (string) $course->fullname,
        'summary' => trim(strip_tags((string) $course->summary)),
        'percent' => $percent,
        'image' => $image,
    ];
}
$studentName = fullname($USER);
$profileUrl = new moodle_url('/user/profile.php', ['id' => $USER->id]);
$preferencesUrl = new moodle_url('/user/preferences.php');
$logoutUrl = new moodle_url('/login/logout.php', ['sesskey' => sesskey()]);
?>
<style>
.mi-hub{max-width:72rem;margin:0 auto;padding:0 1rem 2rem}
.mi-hero{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin:0 0 1.4rem;padding:1.2rem 1.4rem;border:1px solid #dfe5ea;border-left:5px solid var(--mundointer-primary);border-radius:.9rem;background:linear-gradient(135deg,#fff,var(--mundointer-primary-soft))}
.mi-hero img{width:4.4rem;height:4.4rem;object-fit:contain}
.mi-hero strong{display:block;font-size:1.35rem;color:var(--mundointer-secondary)}
.mi-hero small{display:block;margin-top:.2rem;color:#647482;line-height:1.4}
.mi-hero .mi-hero-actions{margin-left:auto;display:flex;gap:.5rem;flex-wrap:wrap}
.mi-hero .mi-hero-actions a{display:inline-flex;align-items:center;gap:.4rem;padding:.55rem .9rem;border-radius:.65rem;background:var(--mundointer-primary);color:#fff;text-decoration:none;font-weight:600}
.mi-hero .mi-hero-actions a.mi-ghost{background:#fff;color:var(--mundointer-primary);border:1px solid color-mix(in srgb,var(--mundointer-primary) 40%,#dce3e8)}
.mi-hero .mi-hero-actions a:hover{filter:brightness(.94)}
.mi-grid{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(16rem,.9fr);gap:1.2rem}
.mi-card{padding:1.2rem;border:1px solid #dfe5ea;border-radius:.9rem;background:#fff}
.mi-card h2{margin:0 0 .8rem;font-size:1.15rem;color:var(--mundointer-secondary)}
.mi-courses{display:grid;grid-template-columns:repeat(auto-fill,minmax(15rem,1fr));gap:.9rem}
.mi-course{display:flex;flex-direction:column;border:1px solid #e3e8ec;border-radius:.8rem;overflow:hidden;background:#fff}
.mi-course img{width:100%;height:6.5rem;object-fit:cover;background:var(--mundointer-primary-soft)}
.mi-course .mi-course-body{padding:.8rem .9rem;display:grid;gap:.5rem}
.mi-course strong{line-height:1.3;min-height:2.6em}
.mi-course small{color:#647482;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.mi-progress{height:.45rem;border-radius:999px;background:#eef2f5;overflow:hidden}
.mi-progress span{display:block;height:100%;background:var(--mundointer-primary)}
.mi-course a.mi-go{margin-top:.2rem;padding:.5rem .8rem;border-radius:.6rem;background:var(--mundointer-primary);color:#fff;text-align:center;text-decoration:none;font-weight:600}
.mi-notice{display:grid;gap:.55rem}
.mi-notice article{padding:.85rem .95rem;border:1px solid #e6ebef;border-left:4px solid var(--mundointer-primary);border-radius:.7rem;background:#fbfcfd}
.mi-notice article small{color:#647482;line-height:1.45}
.mi-contact{display:grid;gap:.5rem;margin-top:.9rem}
.mi-contact a{display:flex;align-items:center;gap:.45rem;color:var(--mundointer-primary);text-decoration:none;font-weight:600;overflow-wrap:anywhere}
@media(max-width:840px){.mi-grid{grid-template-columns:1fr}}
</style>
<div class="mi-hub">
 <header class="mi-hero">
  <?php if ($logo !== ''): ?><img src="<?php echo $logo; ?>" alt=""><?php endif; ?>
  <div><strong><?php echo $name; ?></strong><small><?php echo $studentName; ?><?php echo $welcome !== '' ? ' · ' . $welcome : ''; ?></small></div>
  <div class="mi-hero-actions">
   <a href="<?php echo $profileUrl->out(false); ?>">Meu perfil</a>
   <a class="mi-ghost" href="<?php echo $preferencesUrl->out(false); ?>">Preferências</a>
   <?php if ($site !== ''): ?><a class="mi-ghost" href="<?php echo $site; ?>" target="_blank" rel="noopener">Site da franquia</a><?php endif; ?>
   <a class="mi-ghost" href="<?php echo $logoutUrl->out(false); ?>">Sair</a>
  </div>
 </header>
 <div class="mi-grid">
  <section class="mi-card">
   <h2>Meus cursos</h2>
   <?php if ($courseCards === []): ?>
    <small style="color:#647482">Nenhum curso encontrado. Fale com a franquia se isso não estiver correto.</small>
   <?php else: ?>
   <div class="mi-courses">
    <?php foreach ($courseCards as $card): ?>
    <article class="mi-course">
     <?php if ($card['image'] !== ''): ?><img src="<?php echo s($card['image']); ?>" alt=""><?php endif; ?>
     <div class="mi-course-body">
      <strong><?php echo s($card['fullname']); ?></strong>
      <?php if ($card['summary'] !== ''): ?><small><?php echo s($card['summary']); ?></small><?php endif; ?>
      <?php if ($card['percent'] !== null): ?><div class="mi-progress" title="<?php echo $card['percent']; ?>% concluído"><span style="width:<?php echo $card['percent']; ?>%"></span></div><?php endif; ?>
      <a class="mi-go" href="<?php echo (new moodle_url('/course/view.php', ['id' => $card['id']]))->out(false); ?>">Continuar</a>
     </div>
    </article>
    <?php endforeach; ?>
   </div>
   <?php endif; ?>
  </section>
  <aside class="mi-card">
   <h2>Recados</h2>
   <div class="mi-notice">
    <article><small><?php echo $welcome !== '' ? s($welcome) : get_string('institutionwelcome', 'local_mundointer'); ?></small></article>
   </div>
   <?php if ($showSupport && ($email !== '' || $whatsapp !== '' || $site !== '')): ?>
   <h2 style="margin-top:1.1rem"><?php echo get_string('supportlabel', 'local_mundointer'); ?></h2>
   <div class="mi-contact">
    <?php if ($email !== ''): ?><a href="mailto:<?php echo $email; ?>">✉ <?php echo $email; ?></a><?php endif; ?>
    <?php if ($whatsapp !== ''): ?><a href="<?php echo $whatsapp; ?>" target="_blank" rel="noopener">✆ WhatsApp</a><?php endif; ?>
   </div>
   <?php endif; ?>
  </aside>
 </div>
</div>
<?php
echo $OUTPUT->footer();
