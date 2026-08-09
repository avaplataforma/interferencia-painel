<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_mundointer', get_string('pluginname', 'local_mundointer'));
    $settings->add(new admin_setting_configcheckbox('local_mundointer/enabled', get_string('enabled', 'local_mundointer'), get_string('enabled_desc', 'local_mundointer'), 1));
    $settings->add(new admin_setting_configtext('local_mundointer/centralurl', get_string('centralurl', 'local_mundointer'), get_string('centralurl_desc', 'local_mundointer'), 'https://mundointer.com.br', PARAM_URL));
    $settings->add(new admin_setting_configtext('local_mundointer/profilefield', get_string('profilefield', 'local_mundointer'), get_string('profilefield_desc', 'local_mundointer'), 'polo_presencial', PARAM_ALPHANUMEXT));
    $settings->add(new admin_setting_configtext('local_mundointer/defaultbrand', get_string('defaultbrand', 'local_mundointer'), get_string('defaultbrand_desc', 'local_mundointer'), '', PARAM_ALPHANUMEXT));
    $ADMIN->add('localplugins', $settings);
}
