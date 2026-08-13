<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_local_mundointer_upgrade(int $oldversion): bool
{
    if($oldversion<2026080903){require_once(__DIR__.'/field_helpers.php');local_mundointer_ensure_identity_fields();upgrade_plugin_savepoint(true,2026080903,'local','mundointer');}
    if ($oldversion < 2026081327) {
        global $DB;

        // Keep only the service owned by this plugin current. Custom Moodle
        // services and their access scopes remain untouched.
        $services = $DB->get_records('external_services', ['component' => 'local_mundointer'], '', 'id');
        foreach ($services as $service) {
            $serviceid = (int)$service->id;
            foreach (['local_mundointer_ping', 'local_mundointer_academic_snapshot'] as $functionname) {
                if (!$DB->record_exists('external_services_functions', [
                    'externalserviceid' => $serviceid,
                    'functionname' => $functionname,
                ])) {
                    $DB->insert_record('external_services_functions', (object)[
                        'externalserviceid' => $serviceid,
                        'functionname' => $functionname,
                    ]);
                }
            }
        }

        upgrade_plugin_savepoint(true, 2026081327, 'local', 'mundointer');
    }
    return true;
}
