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
    if ($oldversion < 2026081328) {
        global $DB;
        $services = $DB->get_records('external_services', ['component' => 'local_mundointer'], '', 'id');
        foreach ($services as $service) {
            if (!$DB->record_exists('external_services_functions', [
                'externalserviceid' => (int)$service->id,
                'functionname' => 'local_mundointer_lti_selections',
            ])) {
                $DB->insert_record('external_services_functions', (object)[
                    'externalserviceid' => (int)$service->id,
                    'functionname' => 'local_mundointer_lti_selections',
                ]);
            }
        }
        upgrade_plugin_savepoint(true, 2026081328, 'local', 'mundointer');
    }
    if ($oldversion < 2026081329) {
        global $DB;
        $services = $DB->get_records('external_services', ['component' => 'local_mundointer'], '', 'id');
        foreach ($services as $service) {
            if (!$DB->record_exists('external_services_functions', [
                'externalserviceid' => (int)$service->id,
                'functionname' => 'local_mundointer_materialize_lti_course',
            ])) {
                $DB->insert_record('external_services_functions', (object)[
                    'externalserviceid' => (int)$service->id,
                    'functionname' => 'local_mundointer_materialize_lti_course',
                ]);
            }
        }
        upgrade_plugin_savepoint(true, 2026081329, 'local', 'mundointer');
    }
    if ($oldversion < 2026081330) {
        // No schema change. The version checkpoint publishes the new grouped
        // MASTER materializer while preserving the existing web service.
        upgrade_plugin_savepoint(true, 2026081330, 'local', 'mundointer');
    }
    if ($oldversion < 2026081331) {
        // No schema change. MASTER courses now receive the reviewed final
        // assessment and official cover through the existing materializer.
        upgrade_plugin_savepoint(true, 2026081331, 'local', 'mundointer');
    }
    if ($oldversion < 2026081332) {
        // No schema change. MASTER now preserves the official IESDE Deep
        // Linking assessment instead of generating a local AI quiz.
        upgrade_plugin_savepoint(true, 2026081332, 'local', 'mundointer');
    }
    if ($oldversion < 2026081405) {
        global $DB;
        $services = $DB->get_records('external_services', ['component' => 'local_mundointer'], '', 'id');
        foreach ($services as $service) {
            if (!$DB->record_exists('external_services_functions', [
                'externalserviceid' => (int)$service->id,
                'functionname' => 'core_course_update_categories',
            ])) {
                $DB->insert_record('external_services_functions', (object)[
                    'externalserviceid' => (int)$service->id,
                    'functionname' => 'core_course_update_categories',
                ]);
            }
        }
        upgrade_plugin_savepoint(true, 2026081405, 'local', 'mundointer');
    }
    if ($oldversion < 2026081406) {
        // No schema change. This release fixes the real Moodle section order
        // used to group IESDE lessons, books and the official assessment.
        upgrade_plugin_savepoint(true, 2026081406, 'local', 'mundointer');
    }
    if ($oldversion < 2026081407) {
        // No schema change. The MASTER materializer now distinguishes the
        // complete book from the interactive materials in the same module.
        upgrade_plugin_savepoint(true, 2026081407, 'local', 'mundointer');
    }
    if ($oldversion < 2026081408) {
        // No schema change. Duplicate IESDE titles are now separated by the
        // official Deep Linking resource order (interactive material/book).
        upgrade_plugin_savepoint(true, 2026081408, 'local', 'mundointer');
    }
    return true;
}
