<?php

require_once(__DIR__ . '/../../config.php');

require_login();
$slug = optional_param('slug', '', PARAM_ALPHANUMEXT);
$brand = \local_mundointer\local\brand_resolver::current();
if ($slug === '' && $brand !== null && !empty($brand['slug'])) {
    $slug = (string) $brand['slug'];
}
if ($slug === '') {
    $slug = (string) get_config('local_mundointer', 'defaultbrand');
}
\core\session\manager::kill_user_sessions((int) $USER->id);
if ($slug !== '') {
    redirect(new moodle_url('/franquia.php', ['slug' => $slug]));
}
redirect(new moodle_url('/login/index.php'));