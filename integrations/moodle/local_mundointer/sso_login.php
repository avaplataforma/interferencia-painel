<?php

require_once(__DIR__ . '/../../config.php');

$token = required_param('token', PARAM_ALPHANUM);
$cache = cache::make('local_mundointer', 'sso_sessions');
$key = hash('sha256', $token);
$session = $cache->get($key);
$cache->delete($key);

if (!is_array($session) || (int) ($session['expiresat'] ?? 0) < time()) {
    throw new moodle_exception('O acesso expirou. Solicite um novo link no Painel Mundo Inter.');
}

$user = $DB->get_record('user', ['id' => (int) ($session['userid'] ?? 0), 'deleted' => 0, 'suspended' => 0], '*', MUST_EXIST);
complete_user_login($user);
$brand = \local_mundointer\local\brand_resolver::current();
if ($brand !== null && !empty($brand['slug'])) {
    \local_mundointer\local\brand_resolver::remember((string) $brand['slug']);
}
$courseid = (int) ($session['courseid'] ?? 0);
redirect($courseid > 0 ? new moodle_url('/course/view.php', ['id' => $courseid]) : new moodle_url('/my/courses.php'));