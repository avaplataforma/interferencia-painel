<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_local_mundointer_install(): bool
{
    require_once(__DIR__.'/field_helpers.php');
    set_config('site_uuid', bin2hex(random_bytes(16)), 'local_mundointer');
    set_config('enabled', 1, 'local_mundointer');
    set_config('profilefield','polo_presencial','local_mundointer');
    local_mundointer_ensure_identity_fields();
    return true;
}
