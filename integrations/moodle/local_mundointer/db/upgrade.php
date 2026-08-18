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
    if ($oldversion < 2026081617) {
        global $DB;

        $formatportuguesetitle = static function (string $title): string {
            $title = trim((string)preg_replace('/\s+/u', ' ', $title));
            if ($title === '') {
                return '';
            }
            $title = mb_convert_case(mb_strtolower($title, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
            $connectors = ['A', 'As', 'E', 'Em', 'Na', 'Nas', 'No', 'Nos', 'O', 'Os', 'Ou', 'Com', 'Da', 'Das', 'De', 'Do', 'Dos', 'Para', 'Por', 'Sem', 'Sob'];
            foreach ($connectors as $connector) {
                $title = (string)preg_replace('/(?<!^)\b' . preg_quote($connector, '/') . '\b/u', mb_strtolower($connector, 'UTF-8'), $title);
            }
            $title = strtr($title, ['Ava' => 'AVA', 'Avp' => 'AVP', 'Eja' => 'EJA', 'Ia' => 'IA', 'Lgpd' => 'LGPD', 'Lti' => 'LTI', 'Mba' => 'MBA', 'Rh' => 'RH', 'Ti' => 'TI', 'Tti' => 'TTI']);
            return (string)preg_replace_callback('/\b(?:I|Ii|Iii|Iv|V|Vi|Vii|Viii|Ix|X)\b/u', static fn(array $match): string => mb_strtoupper($match[0], 'UTF-8'), $title);
        };

        $managedcourses = $DB->get_records_select('course', 'idnumber LIKE :trail', ['trail' => 'mi-trilha-%'], 'id ASC', 'id');
        $ltimodule = $DB->get_record('modules', ['name' => 'lti'], 'id');
        foreach ($managedcourses as $managedcourse) {
            $sections = $DB->get_records_select('course_sections', 'course = :course AND section > 0', ['course' => $managedcourse->id], 'section ASC');
            foreach ($sections as $section) {
                $modulelabel = trim((string)preg_replace('/^M[oó]dulo\s+\d+\s*[-:–—]\s*/iu', '', (string)$section->name));
                $modulelabel = $formatportuguesetitle($modulelabel);
                if ($modulelabel === '') {
                    continue;
                }
                $DB->set_field('course_sections', 'name', 'Módulo ' . (int)$section->section . ' - ' . $modulelabel, ['id' => $section->id]);
                if (!$ltimodule) {
                    continue;
                }
                $modules = $DB->get_records('course_modules', ['course' => $managedcourse->id, 'section' => $section->id, 'module' => $ltimodule->id]);
                foreach ($modules as $coursemodule) {
                    if (!str_contains((string)$coursemodule->idnumber, '-assessment-')) {
                        continue;
                    }
                    $DB->set_field('lti', 'name', \core_text::substr('AVP - Avaliação: ' . $modulelabel, 0, 255), ['id' => $coursemodule->instance]);
                }
            }
            rebuild_course_cache((int)$managedcourse->id, true);
        }

        upgrade_plugin_savepoint(true, 2026081617, 'local', 'mundointer');
    }
    if ($oldversion < 2026081618) {
        global $DB;

        // Some installations use a manually-created web service and token
        // instead of the component-owned service declared in db/services.php.
        // Any service that already exposes the Mundo Inter ping is therefore
        // an existing connector service and must receive the publication
        // functions required by the MASTER and Trail automation.
        $connectorservices = $DB->get_records_sql(
            "SELECT DISTINCT service.id
               FROM {external_services} service
               JOIN {external_services_functions} functionlink
                 ON functionlink.externalserviceid = service.id
              WHERE functionlink.functionname = :ping",
            ['ping' => 'local_mundointer_ping']
        );
        foreach ($connectorservices as $service) {
            foreach (['local_mundointer_materialize_lti_course', 'local_mundointer_sync_trail_sections'] as $functionname) {
                if (!$DB->record_exists('external_services_functions', [
                    'externalserviceid' => (int)$service->id,
                    'functionname' => $functionname,
                ])) {
                    $DB->insert_record('external_services_functions', (object)[
                        'externalserviceid' => (int)$service->id,
                        'functionname' => $functionname,
                    ]);
                }
            }
        }

        upgrade_plugin_savepoint(true, 2026081618, 'local', 'mundointer');
    }
    if ($oldversion < 2026081800) {
        global $DB;

        // Registra o login automático (SSO) nos serviços conector já existentes.
        $connectorservices = $DB->get_records_sql(
            "SELECT DISTINCT service.id
               FROM {external_services} service
               JOIN {external_services_functions} functionlink
                 ON functionlink.externalserviceid = service.id
              WHERE functionlink.functionname = :ping",
            ['ping' => 'local_mundointer_ping']
        );
        foreach ($connectorservices as $service) {
            if (!$DB->record_exists('external_services_functions', [
                'externalserviceid' => (int)$service->id,
                'functionname' => 'local_mundointer_create_sso_session',
            ])) {
                $DB->insert_record('external_services_functions', (object)[
                    'externalserviceid' => (int)$service->id,
                    'functionname' => 'local_mundointer_create_sso_session',
                ]);
            }
        }

        upgrade_plugin_savepoint(true, 2026081800, 'local', 'mundointer');
    }
    if ($oldversion < 2026081801) {
        // Sem mudança de esquema. A home do aluno passa a ser a página Minha
        // instituição e o logout preserva a marca da franquia.
        upgrade_plugin_savepoint(true, 2026081801, 'local', 'mundointer');
    }
    if ($oldversion < 2026081802) {
        // Sem mudança de esquema. A experiência do aluno passa a usar a página
        // nativa Meus cursos (/my/courses.php) com o cabeçalho da franquia.
        upgrade_plugin_savepoint(true, 2026081802, 'local', 'mundointer');
    }
    if ($oldversion < 2026081803) {
        // Sem mudança de esquema. O cabeçalho da franquia em Meus cursos passa a
        // detectar a página pela URL e a montar somente após o DOM estar pronto.
        upgrade_plugin_savepoint(true, 2026081803, 'local', 'mundointer');
    }
    if ($oldversion < 2026081804) {
        // Sem mudança de esquema. O logout volta para o link personalizado da
        // franquia e o cabeçalho exibe somente a logo.
        upgrade_plugin_savepoint(true, 2026081804, 'local', 'mundointer');
    }
    if ($oldversion < 2026081805) {
        // Sem mudança de esquema. Ajustes visuais: favicon no cabeçalho, contatos
        // do topo removidos e título nativo de Meus cursos oculto.
        upgrade_plugin_savepoint(true, 2026081805, 'local', 'mundointer');
    }
    if ($oldversion < 2026081806) {
        // Sem mudança de esquema. Oculta título nativo, atalhos do menu do
        // usuário e o bloco Resumo dos cursos na página Meus cursos.
        upgrade_plugin_savepoint(true, 2026081806, 'local', 'mundointer');
    }
    if ($oldversion < 2026081807) {
        // Sem mudança de esquema. Atalhos do menu e Resumo dos cursos ficam
        // ocultos em todas as páginas da marca, inclusive dentro dos cursos.
        upgrade_plugin_savepoint(true, 2026081807, 'local', 'mundointer');
    }
    if ($oldversion < 2026081808) {
        // Sem mudança de esquema. Administradores do Moodle ficam isentos da
        // identidade visual e dos ajustes de experiência da franquia.
        upgrade_plugin_savepoint(true, 2026081808, 'local', 'mundointer');
    }
    if ($oldversion < 2026081809) {
        require_once(__DIR__ . '/field_helpers.php');
        local_mundointer_ensure_identity_fields();
        // Sem mudança de esquema adicional. Adiciona a página Meu espaço com
        // Jornada, Matrículas, Financeiro, Tickets e Documentos do aluno.
        upgrade_plugin_savepoint(true, 2026081809, 'local', 'mundointer');
    }
    if ($oldversion < 2026081810) {
        // Sem mudança de esquema. A testeira de Meus cursos volta a cumprimentar
        // o aluno pelo nome (Olá, Nome!).
        upgrade_plugin_savepoint(true, 2026081810, 'local', 'mundointer');
    }
    if ($oldversion < 2026081811) {
        // Sem mudança de esquema. A saudação usa o primeiro nome do usuário
        // diretamente do perfil, evitando falhas de fullname() no hook.
        upgrade_plugin_savepoint(true, 2026081811, 'local', 'mundointer');
    }
    if ($oldversion < 2026081812) {
        // Sem mudança de esquema. Meu espaço identifica a franquia pelo código
        // da marca para mostrar os dados corretos em CPFs de múltiplas franquias.
        upgrade_plugin_savepoint(true, 2026081812, 'local', 'mundointer');
    }
    if ($oldversion < 2026081813) {
        // Sem mudança de esquema. Meu espaço ganha abas coloridas, segunda via
        // de parcelas, abertura de tickets e envio de documentos.
        upgrade_plugin_savepoint(true, 2026081813, 'local', 'mundointer');
    }
    if ($oldversion < 2026081814) {
        // Sem mudança de esquema. Secretaria Digital: saudação com o nome do
        // aluno (global $USER) e botões do herói com fundo claro.
        upgrade_plugin_savepoint(true, 2026081814, 'local', 'mundointer');
    }
    if ($oldversion < 2026081815) {
        // Sem mudança de esquema. Link Secretaria Digital ao lado do título
        // nativo em Meus cursos.
        upgrade_plugin_savepoint(true, 2026081815, 'local', 'mundointer');
    }
    if ($oldversion < 2026081816) {
        // Sem mudança de esquema. Secretaria Digital aparece uma única vez,
        // com fundo branco, ao lado do link nativo Meus cursos.
        upgrade_plugin_savepoint(true, 2026081816, 'local', 'mundointer');
    }
    if ($oldversion < 2026081817) {
        // Sem mudança de esquema. Fim do redirecionamento em JS (loop) e
        // botão único Secretaria Digital na testeira com fundo branco.
        upgrade_plugin_savepoint(true, 2026081817, 'local', 'mundointer');
    }
    if ($oldversion < 2026081818) {
        // Sem mudança de esquema. A testeira com o botão Secretaria Digital é
        // renderizada no servidor e apenas posicionada pelo navegador.
        upgrade_plugin_savepoint(true, 2026081818, 'local', 'mundointer');
    }
    if ($oldversion < 2026081819) {
        // Sem mudança de esquema. A home do aluno passa a ser a Secretaria
        // Digital logo após o login, deixando Meus cursos como listagem nativa.
        upgrade_plugin_savepoint(true, 2026081819, 'local', 'mundointer');
    }
    if ($oldversion < 2026081820) {
        // Sem mudança de esquema. A Secretaria Digital ganhou acesso direto
        // aos cursos liberados (Iniciar curso / Acessar curso).
        upgrade_plugin_savepoint(true, 2026081820, 'local', 'mundointer');
    }
    if ($oldversion < 2026081821) {
        // Sem mudança de esquema. Portal: botão Acessar curso com contraste,
        // status Acesso liberado com progresso e último acesso; botão Iniciar
        // curso removido da testeira.
        upgrade_plugin_savepoint(true, 2026081821, 'local', 'mundointer');
    }
    if ($oldversion < 2026081822) {
        // Sem mudança de esquema. A tela Secretaria Digital foi renomeada
        // para Portal do Aluno.
        upgrade_plugin_savepoint(true, 2026081822, 'local', 'mundointer');
    }
    if ($oldversion < 2026081823) {
        // Sem mudança de esquema. Atalho flutuante 'Portal do Aluno' dentro
        // das páginas de curso (course-view).
        upgrade_plugin_savepoint(true, 2026081823, 'local', 'mundointer');
    }
    if ($oldversion < 2026081824) {
        // Sem mudança de esquema. Detecção robusta de curso para o atalho
        // flutuante e logo da franquia agora leva ao Portal do Aluno.
        upgrade_plugin_savepoint(true, 2026081824, 'local', 'mundointer');
    }
    if ($oldversion < 2026081825) {
        // Sem mudança de esquema. Hook do body agora declara global $PAGE,
        // corrigindo a detecção de páginas de curso e de Meus cursos.
        upgrade_plugin_savepoint(true, 2026081825, 'local', 'mundointer');
    }
    if ($oldversion < 2026081826) {
        // Sem mudança de esquema. Botão Portal do Aluno ao lado do logo da
        // franquia no navbar (substitui o botão flutuante do curso).
        upgrade_plugin_savepoint(true, 2026081826, 'local', 'mundointer');
    }
    if ($oldversion < 2026081827) {
        // Sem mudança de esquema. Botão Portal do Aluno movido para o lado
        // direito do navbar (fim da barra), ao lado do logo.
        upgrade_plugin_savepoint(true, 2026081827, 'local', 'mundointer');
    }
    if ($oldversion < 2026081828) {
        // Sem mudança de esquema. Botão Portal do Aluno passa a ser renderizado
        // no servidor dentro do bloco da marca, ficando ao lado do logo.
        upgrade_plugin_savepoint(true, 2026081828, 'local', 'mundointer');
    }
    if ($oldversion < 2026081829) {
        // Sem mudança de esquema. O SSO carrega a marca da franquia na sessão,
        // garantindo logo, cores e botão do Portal do Aluno para todos os alunos.
        upgrade_plugin_savepoint(true, 2026081829, 'local', 'mundointer');
    }
    if ($oldversion < 2026081830) {
        // Sem mudança de esquema. Navbar restaurado (logo nativa da franquia) e
        // botão Portal do Aluno passa a ser flutuante no canto inferior esquerdo
        // em todas as páginas, sem depender da marca da sessão.
        upgrade_plugin_savepoint(true, 2026081830, 'local', 'mundointer');
    }
    if ($oldversion < 2026081831) {
        // Sem mudança de esquema. Navbar só é alterado quando existe imagem da
        // marca; em falha, restaura a logo original e preserva a logo nativa.
        upgrade_plugin_savepoint(true, 2026081831, 'local', 'mundointer');
    }
    if ($oldversion < 2026081832) {
        // Sem mudança de esquema. Correção crítica: o hook do body voltou a
        // resolver a marca da sessão antes de montar o bloco da marca.
        upgrade_plugin_savepoint(true, 2026081832, 'local', 'mundointer');
    }
    if ($oldversion < 2026081833) {
        // Sem mudança de esquema. Logo da marca leva ao Portal do Aluno e o
        // botão flutuante fica centralizado horizontalmente.
        upgrade_plugin_savepoint(true, 2026081833, 'local', 'mundointer');
    }
    if ($oldversion < 2026081834) {
        // Sem mudança de esquema. A testeira de Meus cursos agora é posicionada
        // dentro do conteúdo após o carregamento da página (e fica oculta até lá).
        upgrade_plugin_savepoint(true, 2026081834, 'local', 'mundointer');
    }
    if ($oldversion < 2026081835) {
        // Sem mudança de esquema. A testeira da marca passou a aparecer também
        // dentro dos cursos e o botão flutuante foi removido.
        upgrade_plugin_savepoint(true, 2026081835, 'local', 'mundointer');
    }
    if ($oldversion < 2026081836) {
        // Sem mudança de esquema. Ícone educacional no botão Portal do Aluno e
        // espaçamento entre ícone e texto.
        upgrade_plugin_savepoint(true, 2026081836, 'local', 'mundointer');
    }
    if ($oldversion < 2026081837) {
        // Sem mudança de esquema. Portal do Aluno: avisos da franquia, jornada
        // com progresso e nota, continuar de onde parou, próximas parcelas e PIX.
        upgrade_plugin_savepoint(true, 2026081837, 'local', 'mundointer');
    }
    if ($oldversion < 2026081838) {
        // Sem mudança de esquema. As abas do Portal do Aluno passam a obedecer
        // a configuração por franquia (ADM > Portal do Aluno > Abas e seções) e
        // os comunicados respeitam a data de expiração.
        upgrade_plugin_savepoint(true, 2026081838, 'local', 'mundointer');
    }
    if ($oldversion < 2026081839) {
        // Sem mudança de esquema. KPIs: removida Liberadas no AVA e Certificados
        // movido para o final.
        upgrade_plugin_savepoint(true, 2026081839, 'local', 'mundointer');
    }
    if ($oldversion < 2026081840) {
        // Sem mudança de esquema. Portal: certificados com download, materiais
        // da franquia, alerta de parcelas vencidas, anexos em tickets e NPS.
        upgrade_plugin_savepoint(true, 2026081840, 'local', 'mundointer');
    }
    if ($oldversion < 2026081841) {
        // Sem mudança de esquema. O bloco de avaliação (NPS) passa a aparecer
        // dentro dos cursos, abaixo da testeira da marca.
        upgrade_plugin_savepoint(true, 2026081841, 'local', 'mundointer');
    }
    if ($oldversion < 2026081842) {
        // Sem mudança de esquema. Avaliação rápida por estrelas na Jornada do
        // Portal do Aluno, ao lado do nome de cada curso.
        upgrade_plugin_savepoint(true, 2026081842, 'local', 'mundointer');
    }
    if ($oldversion < 2026081843) {
        // Sem mudança de esquema. Jornada: ícone de livro no nome do curso e
        // último acesso com fallback para o registro do Moodle; título do portal
        // com ícone de capelo.
        upgrade_plugin_savepoint(true, 2026081843, 'local', 'mundointer');
    }
    if ($oldversion < 2026081844) {
        // Sem mudança de esquema. Nova caixinha de Documentos com a contagem de
        // documentos obrigatórios pendentes do aluno.
        upgrade_plugin_savepoint(true, 2026081844, 'local', 'mundointer');
    }
    if ($oldversion < 2026081845) {
        // Sem mudança de esquema. Testeira da marca também em atividades (mod)
        // e logo sempre leva ao Portal do Aluno; estrelas pintam na hora e
        // permitem reavaliar o curso.
        upgrade_plugin_savepoint(true, 2026081845, 'local', 'mundointer');
    }
    if ($oldversion < 2026081846) {
        // Sem mudança de esquema. Login: aviso de cookies oculto e bloco de
        // suporte redesenhado em cartão moderno com pílulas de contato.
        upgrade_plugin_savepoint(true, 2026081846, 'local', 'mundointer');
    }
    if ($oldversion < 2026081847) {
        // Sem mudança de esquema. Pílulas de contato centralizadas na caixa
        // de suporte do login.
        upgrade_plugin_savepoint(true, 2026081847, 'local', 'mundointer');
    }
    if ($oldversion < 2026081848) {
        // Sem mudança de esquema. Links auxiliares do login (Perdeu a senha,
        // criar conta) com cor neutra, independente da cor da franquia.
        upgrade_plugin_savepoint(true, 2026081848, 'local', 'mundointer');
    }
    if ($oldversion < 2026081849) {
        // Sem mudança de esquema. Links auxiliares do login neutros em todos os
        // fluxos (login direto, admin e página da franquia).
        upgrade_plugin_savepoint(true, 2026081849, 'local', 'mundointer');
    }
    if ($oldversion < 2026081850) {
        // Sem mudança de esquema. Regra neutra dos links do login também no
        // fluxo com marca da franquia.
        upgrade_plugin_savepoint(true, 2026081850, 'local', 'mundointer');
    }
    if ($oldversion < 2026081851) {
        // Sem mudança de esquema. Reforço da regra neutra do link Perdeu a senha
        // para todos os estados (:link, :visited, :hover, :active).
        upgrade_plugin_savepoint(true, 2026081851, 'local', 'mundointer');
    }
    if ($oldversion < 2026081852) {
        // Sem mudança de esquema. Cor neutra do link aplicada também via estilo
        // inline por JavaScript (imune a qualquer conflito de cascata).
        upgrade_plugin_savepoint(true, 2026081852, 'local', 'mundointer');
    }
    if ($oldversion < 2026081853) {
        // Sem mudança de esquema. Foco de links sem classe deixa de usar o
        // vermelho vivo do Moodle core e passa para cinza suave.
        upgrade_plugin_savepoint(true, 2026081853, 'local', 'mundointer');
    }
    if ($oldversion < 2026081854) {
        // Sem mudança de esquema. Foco neutro dos links sem classe com
        // !important para vencer a cascata do tema.
        upgrade_plugin_savepoint(true, 2026081854, 'local', 'mundointer');
    }
    if ($oldversion < 2026081855) {
        // Sem mudança de esquema. Mobile: barra inferior fixa com ícones para as
        // seções do portal, KPIs compactos e jornada logo após a testeira.
        upgrade_plugin_savepoint(true, 2026081855, 'local', 'mundointer');
    }
    if ($oldversion < 2026081856) {
        // Sem mudança de esquema. Mobile: KPIs ocultos, jornada em carrossel de
        // cartões, alvos de toque maiores e botão Acessar no lugar de Continuar.
        upgrade_plugin_savepoint(true, 2026081856, 'local', 'mundointer');
    }
    if ($oldversion < 2026081857) {
        // Sem mudança de esquema. Botão Acessar abre a página principal do curso,
        // não a última atividade.
        upgrade_plugin_savepoint(true, 2026081857, 'local', 'mundointer');
    }
    return true;
}

