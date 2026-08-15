<?php

require_once(__DIR__ . '/../../config.php');

$token = required_param('token', PARAM_ALPHANUM);
$cache = cache::make('local_mundointer', 'robot_sessions');
$key = hash('sha256', $token);
$session = $cache->get($key);
$cache->delete($key);

if (!is_array($session) || (int)($session['expiresat'] ?? 0) < time()) {
    throw new moodle_exception('O acesso técnico expirou. Solicite uma nova execução ao Mundo Inter.');
}

$user = $DB->get_record('user', [
    'id' => (int)($session['userid'] ?? 0),
    'deleted' => 0,
    'suspended' => 0,
], '*', MUST_EXIST);
$courseid = (int)($session['courseid'] ?? 0);
$typeid = (int)($session['typeid'] ?? 0);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$type = $DB->get_record('lti_types', ['id' => $typeid], '*', MUST_EXIST);

if ((string)$course->idnumber !== 'mi-master-staging') {
    throw new moodle_exception('O curso técnico do robô não é válido.');
}

$fingerprint = core_text::strtolower(implode(' ', [
    (string)($type->name ?? ''),
    (string)($type->baseurl ?? ''),
    (string)($type->tooldomain ?? ''),
]));
if (!str_contains($fingerprint, 'iesde') && !str_contains($fingerprint, 'api-fornecimento')) {
    throw new moodle_exception('A ferramenta técnica não pertence ao catálogo MASTER.');
}

complete_user_login($user);
redirect(new moodle_url('/course/modedit.php', [
    'add' => 'lti',
    'return' => 0,
    'course' => $courseid,
    'typeid' => $typeid,
    'section' => 0,
]));
