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
    if ($oldversion < 2026081501) {
        global $DB;
        $services = $DB->get_records('external_services', ['component' => 'local_mundointer'], '', 'id');
        foreach ($services as $service) {
            if (!$DB->record_exists('external_services_functions', [
                'externalserviceid' => (int)$service->id,
                'functionname' => 'local_mundointer_prepare_lti_robot',
            ])) {
                $DB->insert_record('external_services_functions', (object)[
                    'externalserviceid' => (int)$service->id,
                    'functionname' => 'local_mundointer_prepare_lti_robot',
                ]);
            }
        }
        upgrade_plugin_savepoint(true, 2026081501, 'local', 'mundointer');
    }

    if ($oldversion < 2026081502) {
        // Refresh the external-function signature after adding the isolated
        // staging reset used by the unattended LTI robot.
        upgrade_plugin_savepoint(true, 2026081502, 'local', 'mundointer');
    }
    if ($oldversion < 2026081503) {
        // The technical LTI bridge now has a neutral name and exposes its
        // stable idnumber so the central robot never depends on a label.
        upgrade_plugin_savepoint(true, 2026081503, 'local', 'mundointer');
    }
    if ($oldversion < 2026081614) {
        global $DB;

        // Bring previously published MASTER modules and Trails to the same
        // naming standard used by new synchronizations. Only records carrying
        // Mundo Inter idnumbers are changed; manual Moodle content is kept.
        $managedcourses = $DB->get_records_select(
            'course',
            'idnumber LIKE :content OR idnumber LIKE :course OR idnumber LIKE :trail',
            [
                'content' => 'mi-master-content-%',
                'course' => 'mi-master-course-%',
                'trail' => 'mi-trail-%',
            ],
            'id ASC',
            'id,fullname,idnumber'
        );
        $ltimodule = $DB->get_record('modules', ['name' => 'lti'], 'id');
        $labelmodule = $DB->get_record('modules', ['name' => 'label'], 'id');

        foreach ($managedcourses as $managedcourse) {
            $sections = $DB->get_records('course_sections', ['course' => $managedcourse->id], 'section ASC');
            foreach ($sections as $section) {
                $sectionname = trim((string)$section->name);
                $modulelabel = preg_replace('/^M[oó]dulo\s+\d+\s*[-:–—]\s*/iu', '', $sectionname);
                $modulelabel = trim((string)$modulelabel);
                if ($modulelabel === '' || preg_match('/avalia|prova|exame/iu', $modulelabel)) {
                    $modulelabel = trim((string)$managedcourse->fullname);
                }
                $assessmentname = \core_text::substr('AVP - Avaliação: ' . $modulelabel, 0, 255);

                if ($ltimodule) {
                    $modules = $DB->get_records('course_modules', [
                        'course' => $managedcourse->id,
                        'section' => $section->id,
                        'module' => $ltimodule->id,
                    ]);
                    foreach ($modules as $coursemodule) {
                        $idnumber = (string)$coursemodule->idnumber;
                        $activity = $DB->get_record('lti', ['id' => $coursemodule->instance], 'id,name');
                        if (!$activity) {
                            continue;
                        }
                        $isassessment = str_contains($idnumber, '-assessment-')
                            || preg_match('/avalia|prova|exame/iu', (string)$activity->name) === 1;
                        if ($isassessment && str_starts_with($idnumber, 'mi-')) {
                            $DB->set_field('lti', 'name', $assessmentname, ['id' => $activity->id]);
                            if (preg_match('/avalia|prova|exame/iu', $sectionname) === 1) {
                                $DB->set_field('course_sections', 'name', $assessmentname, ['id' => $section->id]);
                            }
                        }
                    }
                }

                if ($labelmodule) {
                    $labels = $DB->get_records('course_modules', [
                        'course' => $managedcourse->id,
                        'section' => $section->id,
                        'module' => $labelmodule->id,
                    ]);
                    foreach ($labels as $coursemodule) {
                        if (!str_starts_with((string)$coursemodule->idnumber, 'mi-trail-master-')
                            || !str_contains((string)$coursemodule->idnumber, '-subtitle-')) {
                            continue;
                        }
                        $label = $DB->get_record('label', ['id' => $coursemodule->instance], 'id,name,intro');
                        if (!$label) {
                            continue;
                        }
                        $title = trim(strip_tags((string)$label->intro));
                        if ($title === '') {
                            $title = trim((string)$label->name);
                        }
                        $title = \core_text::strtoupper($title);
                        $DB->update_record('label', (object)[
                            'id' => $label->id,
                            'name' => \core_text::substr($title, 0, 255),
                            'intro' => \html_writer::tag('b', s($title), ['class' => 'mundointer-subtitle']),
                            'introformat' => FORMAT_HTML,
                            'timemodified' => time(),
                        ]);
                    }
                }
            }
            rebuild_course_cache((int)$managedcourse->id, true);
        }

        upgrade_plugin_savepoint(true, 2026081614, 'local', 'mundointer');
    }
    if ($oldversion < 2026081615) {
        global $DB;

        // Some Trails published before stable activity idnumbers were added
        // still carry the legacy "Avaliação oficial/final" name. The course
        // idnumber is already sufficient to keep this migration restricted to
        // Mundo Inter content, so normalize those historical activities too.
        $managedcourses = $DB->get_records_select(
            'course',
            'idnumber LIKE :content OR idnumber LIKE :course OR idnumber LIKE :trail',
            [
                'content' => 'mi-master-content-%',
                'course' => 'mi-master-course-%',
                'trail' => 'mi-trail-%',
            ],
            'id ASC',
            'id,fullname,idnumber'
        );
        $ltimodule = $DB->get_record('modules', ['name' => 'lti'], 'id');

        if ($ltimodule) {
            foreach ($managedcourses as $managedcourse) {
                $sections = $DB->get_records('course_sections', ['course' => $managedcourse->id], 'section ASC');
                foreach ($sections as $section) {
                    $sectionname = trim((string)$section->name);
                    $modulelabel = trim((string)preg_replace('/^M[oó]dulo\s+\d+\s*[-:–—]\s*/iu', '', $sectionname));
                    if ($modulelabel === '' || preg_match('/avalia|prova|exame/iu', $modulelabel)) {
                        $modulelabel = trim((string)$managedcourse->fullname);
                    }
                    $assessmentname = \core_text::substr('AVP - Avaliação: ' . $modulelabel, 0, 255);
                    $modules = $DB->get_records('course_modules', [
                        'course' => $managedcourse->id,
                        'section' => $section->id,
                        'module' => $ltimodule->id,
                    ]);
                    foreach ($modules as $coursemodule) {
                        $activity = $DB->get_record('lti', ['id' => $coursemodule->instance], 'id,name');
                        if (!$activity) {
                            continue;
                        }
                        $idnumber = (string)$coursemodule->idnumber;
                        $isassessment = str_contains($idnumber, '-assessment-')
                            || preg_match('/avalia|prova|exame/iu', (string)$activity->name) === 1;
                        if (!$isassessment) {
                            continue;
                        }
                        $DB->set_field('lti', 'name', $assessmentname, ['id' => $activity->id]);
                        if (preg_match('/avalia|prova|exame/iu', $sectionname) === 1) {
                            $DB->set_field('course_sections', 'name', $assessmentname, ['id' => $section->id]);
                        }
                    }
                }
                rebuild_course_cache((int)$managedcourse->id, true);
            }
        }

        upgrade_plugin_savepoint(true, 2026081615, 'local', 'mundointer');
    }
    if ($oldversion < 2026081616) {
        global $DB;

        // Trail courses use the Portuguese management idnumber "mi-trilha-*".
        // Normalize historical assessment names and visual subtitles that the
        // earlier migration missed while looking only for "mi-trail-*".
        $managedcourses = $DB->get_records_select(
            'course',
            'idnumber LIKE :trail',
            ['trail' => 'mi-trilha-%'],
            'id ASC',
            'id,fullname,idnumber'
        );
        $ltimodule = $DB->get_record('modules', ['name' => 'lti'], 'id');
        $labelmodule = $DB->get_record('modules', ['name' => 'label'], 'id');

        foreach ($managedcourses as $managedcourse) {
            $sections = $DB->get_records('course_sections', ['course' => $managedcourse->id], 'section ASC');
            foreach ($sections as $section) {
                $sectionname = trim((string)$section->name);
                $modulelabel = trim((string)preg_replace('/^M[oó]dulo\s+\d+\s*[-:–—]\s*/iu', '', $sectionname));
                if ($modulelabel === '' || preg_match('/avalia|prova|exame/iu', $modulelabel)) {
                    $modulelabel = trim((string)$managedcourse->fullname);
                }
                $assessmentname = \core_text::substr('AVP - Avaliação: ' . $modulelabel, 0, 255);

                if ($ltimodule) {
                    $modules = $DB->get_records('course_modules', [
                        'course' => $managedcourse->id,
                        'section' => $section->id,
                        'module' => $ltimodule->id,
                    ]);
                    foreach ($modules as $coursemodule) {
                        $activity = $DB->get_record('lti', ['id' => $coursemodule->instance], 'id,name');
                        if (!$activity) {
                            continue;
                        }
                        $idnumber = (string)$coursemodule->idnumber;
                        $isassessment = str_contains($idnumber, '-assessment-')
                            || preg_match('/avalia|prova|exame/iu', (string)$activity->name) === 1;
                        if ($isassessment) {
                            $DB->set_field('lti', 'name', $assessmentname, ['id' => $activity->id]);
                        }
                    }
                }

                if ($labelmodule) {
                    $labels = $DB->get_records('course_modules', [
                        'course' => $managedcourse->id,
                        'section' => $section->id,
                        'module' => $labelmodule->id,
                    ]);
                    foreach ($labels as $coursemodule) {
                        if (!str_starts_with((string)$coursemodule->idnumber, 'mi-trail-master-')
                            || !str_contains((string)$coursemodule->idnumber, '-subtitle-')) {
                            continue;
                        }
                        $label = $DB->get_record('label', ['id' => $coursemodule->instance], 'id,name,intro');
                        if (!$label) {
                            continue;
                        }
                        $title = trim(strip_tags((string)$label->intro));
                        if ($title === '') {
                            $title = trim((string)$label->name);
                        }
                        $title = \core_text::strtoupper($title);
                        $DB->update_record('label', (object)[
                            'id' => $label->id,
                            'name' => \core_text::substr($title, 0, 255),
                            'intro' => \html_writer::tag('b', s($title), ['class' => 'mundointer-subtitle']),
                            'introformat' => FORMAT_HTML,
                            'timemodified' => time(),
                        ]);
                    }
                }
            }
            rebuild_course_cache((int)$managedcourse->id, true);
        }

        upgrade_plugin_savepoint(true, 2026081616, 'local', 'mundointer');
    }
    return true;
}
