<?php

require_once(__DIR__ . '/../../config.php');

require_login();
$brand = \local_mundointer\local\brand_resolver::current();
$PAGE->set_url('/local/mundointer/instituicao.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('institutiontitle', 'local_mundointer'));
$PAGE->set_heading(get_string('institutiontitle', 'local_mundointer'));
echo $OUTPUT->header();
if ($brand === null) {
    echo html_writer::tag('p', get_string('institutionnobrand', 'local_mundointer'));
} else {
    $logo = s((string) ($brand['logo_url'] ?? ''));
    $name = s((string) ($brand['name'] ?? ''));
    $welcome = s((string) ($brand['welcome_text'] ?? ''));
    $email = s((string) ($brand['support_email'] ?? ''));
    $phone = s((string) ($brand['support_phone'] ?? ''));
    $site = s((string) ($brand['site_url'] ?? ''));
    $digits = preg_replace('/\D/', '', $phone) ?? '';
    $whatsapp = strlen($digits) >= 10 ? 'https://wa.me/55' . $digits : '';
    $logohtml = $logo !== '' ? '<img src="' . $logo . '" alt="" style="width:5rem;height:5rem;object-fit:contain;margin-bottom:.8rem;">' : '';
    $html = '<div class="mundointer-welcome">' . $logohtml . '<div><strong>' . $name . '</strong><small>' . $welcome . '</small></div></div>';
    $contacts = '<strong>' . get_string('supportlabel', 'local_mundointer') . '</strong>';
    if ($email !== '') $contacts .= '<br><a href="mailto:' . $email . '">' . $email . '</a>';
    if ($whatsapp !== '') $contacts .= '<br><a href="' . $whatsapp . '" target="_blank" rel="noopener">WhatsApp</a>';
    if ($site !== '') $contacts .= '<br><a href="' . $site . '" target="_blank" rel="noopener">' . get_string('institutionsite', 'local_mundointer') . '</a>';
    $html .= '<div class="mundointer-welcome">' . $contacts . '</div>';
    echo $html;
}
echo $OUTPUT->footer();