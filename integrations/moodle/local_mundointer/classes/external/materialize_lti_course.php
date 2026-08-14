<?php

namespace local_mundointer\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Materializes every IESDE selection from the import course into one reusable
 * Mundo Inter course, grouping numbered sections and activities by base title.
 */
final class materialize_lti_course extends external_api
{
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'sourcecmid' => new external_value(PARAM_INT, 'Atividade LTI MASTER homologada.'),
            'targetcourseid' => new external_value(PARAM_INT, 'Curso Individual definitivo.'),
            'activityname' => new external_value(PARAM_TEXT, 'Nome comercial da atividade inicial.'),
            'idnumber' => new external_value(PARAM_ALPHANUMEXT, 'Código idempotente da atividade inicial.'),
            'coverurl' => new external_value(PARAM_URL, 'Capa comercial do Curso Individual MASTER.', VALUE_DEFAULT, ''),
            'coveralt' => new external_value(PARAM_TEXT, 'Texto alternativo da capa.', VALUE_DEFAULT, ''),
            'assessmentjson' => new external_value(PARAM_RAW, 'Avaliação final revisada em JSON.', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $sourcecmid, int $targetcourseid, string $activityname, string $idnumber, string $coverurl='', string $coveralt='', string $assessmentjson=''): array
    {
        global $CFG, $DB;

        $parameters = self::validate_parameters(self::execute_parameters(), compact('sourcecmid', 'targetcourseid', 'activityname', 'idnumber', 'coverurl', 'coveralt', 'assessmentjson'));
        $system = \context_system::instance();
        self::validate_context($system);
        require_capability('local/mundointer:manage', $system);

        $sourcecm = $DB->get_record('course_modules', ['id' => $parameters['sourcecmid']], '*', MUST_EXIST);
        $ltimodule = $DB->get_record('modules', ['name' => 'lti'], '*', MUST_EXIST);
        if ((int) $sourcecm->module !== (int) $ltimodule->id) {
            throw new \invalid_parameter_exception('A origem selecionada não é uma atividade LTI.');
        }

        $selectedsource = $DB->get_record('lti', ['id' => $sourcecm->instance], '*', MUST_EXIST);
        self::assert_iesde_source($selectedsource);
        $course = $DB->get_record('course', ['id' => $parameters['targetcourseid']], '*', MUST_EXIST);
        if (!str_starts_with((string) $course->idnumber, 'mi-master-content-') && !str_starts_with((string) $course->idnumber, 'mi-master-course-')) {
            throw new \invalid_parameter_exception('Somente Cursos Individuais MASTER gerenciados pelo Mundo Inter podem receber estas atividades.');
        }

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/lti/lib.php');
        require_once($CFG->dirroot . '/mod/lti/locallib.php');
        require_once($CFG->libdir . '/filelib.php');

        if ((int) $course->enablecompletion !== 1) {
            $DB->set_field('course', 'enablecompletion', 1, ['id' => $course->id]);
            $course->enablecompletion = 1;
        }

        $sources = self::ordered_iesde_sources((int) $sourcecm->course, (int) $ltimodule->id);
        if ($sources === []) {
            throw new \moodle_exception('Nenhuma aula MASTER foi encontrada no curso de importação.');
        }

        $groups = self::group_sources($sources);
        $firstcmid = 0;
        $firstactivityid = 0;
        $reusedactivities = 0;
        $activitycount = 0;
        $sectionnumber = 0;

        foreach ($groups as $group) {
            $sectionnumber++;
            $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sectionnumber]);
            if (!$section) {
                $section = course_create_section((int) $course->id, $sectionnumber);
            }
            $DB->update_record('course_sections', (object) [
                'id' => $section->id,
                'name' => !empty($group['assessment']) ? 'Avaliação final' : 'Módulo ' . $sectionnumber . ' - ' . $group['name'],
                'visible' => 1,
            ]);

            foreach ($group['items'] as $item) {
                $sourceitemcm = $item['cm'];
                $source = $item['lti'];
                $moduleidnumber = (int) $sourceitemcm->id === (int) $sourcecm->id
                    ? $parameters['idnumber']
                    : 'mi-master-lti-cm-' . (int) $sourceitemcm->id;
                $displayname = self::activity_display_name((string) $source->name, $item['kind'], $item['number']);
                $existing = $DB->get_record('course_modules', [
                    'course' => $course->id,
                    'module' => $ltimodule->id,
                    'idnumber' => $moduleidnumber,
                ]);

                if ($existing) {
                    $DB->set_field('lti', 'name', $displayname, ['id' => $existing->instance]);
                    $DB->update_record('course_modules', (object) [
                        'id' => $existing->id,
                        'visible' => 1,
                        'visibleoncoursepage' => 1,
                        'completion' => COMPLETION_TRACKING_AUTOMATIC,
                        'completionview' => 1,
                    ]);
                    $cm = get_coursemodule_from_id('lti', (int) $existing->id, (int) $course->id, false, MUST_EXIST);
                    moveto_module($cm, $section);
                    $cmid = (int) $existing->id;
                    $activityid = (int) $existing->instance;
                    $reusedactivities++;
                } else {
                    $moduleinfo = clone $source;
                    unset($moduleinfo->id, $moduleinfo->timecreated, $moduleinfo->timemodified);
                    $moduleinfo->course = (int) $course->id;
                    $moduleinfo->name = $displayname;
                    $moduleinfo->modulename = 'lti';
                    $moduleinfo->module = (int) $ltimodule->id;
                    $moduleinfo->section = $sectionnumber;
                    $moduleinfo->visible = 1;
                    $moduleinfo->visibleoncoursepage = 1;
                    $moduleinfo->cmidnumber = $moduleidnumber;
                    $moduleinfo->groupmode = 0;
                    $moduleinfo->groupingid = 0;
                    $moduleinfo->completion = COMPLETION_TRACKING_AUTOMATIC;
                    $moduleinfo->completionview = 1;
                    $moduleinfo->completionexpected = 0;
                    $moduleinfo->coursemodule = 0;
                    $moduleinfo->instance = 0;
                    $created = add_moduleinfo($moduleinfo, $course);
                    $cmid = (int) ($created->coursemodule ?? 0);
                    $activityid = (int) ($created->instance ?? 0);
                    if ($cmid < 1 || $activityid < 1) {
                        throw new \moodle_exception('O Moodle não confirmou a criação de uma atividade LTI MASTER.');
                    }
                }

                if ($firstcmid < 1) {
                    $firstcmid = $cmid;
                    $firstactivityid = $activityid;
                }
                $activitycount++;
            }
        }

        $cover=sync_trail_sections::apply_managed_cover($course,(string)$parameters['coverurl'],(string)$parameters['coveralt']);
        // MASTER assessments are Deep Linking LTI activities created from the
        // official IESDE question bank. Never replace them with AI questions.
        $quiz=['quiz'=>0,'questions'=>0,'conflict'=>0];
        rebuild_course_cache((int) $course->id, true);
        return self::result((int) $course->id, $firstactivityid, $firstcmid, $reusedactivities > 0, count($groups), $activitycount, $reusedactivities,$cover,$quiz);
    }

    /** @return array<int,array{cm:object,lti:object}> */
    private static function ordered_iesde_sources(int $courseid, int $ltimoduleid): array
    {
        global $DB;

        $ordered = [];
        $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC', 'id,sequence');
        foreach ($sections as $section) {
            foreach (array_filter(array_map('intval', explode(',', (string) $section->sequence))) as $cmid) {
                $cm = $DB->get_record('course_modules', ['id' => $cmid, 'course' => $courseid, 'module' => $ltimoduleid]);
                if (!$cm) {
                    continue;
                }
                $lti = $DB->get_record('lti', ['id' => $cm->instance]);
                if ($lti && self::is_iesde_source($lti)) {
                    $ordered[] = ['cm' => $cm, 'lti' => $lti];
                }
            }
        }
        return $ordered;
    }

    /** @param array<int,array{cm:object,lti:object}> $sources
     *  @return array<int,array{name:string,assessment?:bool,items:array<int,array{cm:object,lti:object,kind:string,number:int}>}>
     */
    private static function group_sources(array $sources): array
    {
        $groups = [];
        $index = [];
        $assessments = [];
        foreach ($sources as $source) {
            if (self::is_assessment_name((string) $source['lti']->name)) {
                $assessments[] = ['cm' => $source['cm'], 'lti' => $source['lti'], 'kind' => 'assessment', 'number' => 0];
                continue;
            }
            $parts = self::lesson_parts((string) $source['lti']->name);
            $key = self::fold($parts['base']);
            if (!isset($index[$key])) {
                $index[$key] = count($groups);
                $groups[] = ['name' => $parts['base'], 'items' => []];
            }
            $groups[$index[$key]]['items'][] = [
                'cm' => $source['cm'],
                'lti' => $source['lti'],
                'kind' => $parts['kind'],
                'number' => $parts['number'],
            ];
        }
        if ($assessments !== []) {
            $groups[] = ['name' => 'Avaliação final', 'assessment' => true, 'items' => $assessments];
        }
        return $groups;
    }

    /** @return array{base:string,kind:string,number:int} */
    private static function lesson_parts(string $name): array
    {
        $name = trim((string) preg_replace('/^aula\s*[-:–—]\s*/iu', '', clean_param($name, PARAM_TEXT)));
        if (preg_match('/^(.*?)\s*[-:–—]\s*(se[cç][aã]o|atividade)\s*(\d+)\s*$/iu', $name, $match)) {
            return [
                'base' => trim($match[1]),
                'kind' => str_starts_with(self::fold($match[2]), 'sec') ? 'section' : 'activity',
                'number' => (int) $match[3],
            ];
        }
        return ['base' => $name, 'kind' => self::fold($name) === 'apresentacao' ? 'presentation' : 'lesson', 'number' => 0];
    }

    private static function activity_display_name(string $original, string $kind, int $number): string
    {
        return match ($kind) {
            'section' => 'Aula - Seção ' . $number,
            'activity' => 'Atividade ' . $number,
            'presentation' => 'Aula - Apresentação',
            'assessment' => 'Avaliação final',
            default => trim((string) preg_replace('/^aula\s*[-:–—]\s*/iu', '', $original)),
        };
    }

    private static function is_assessment_name(string $name): bool
    {
        return preg_match('/avalia|prova|exame/u', self::fold($name)) === 1;
    }

    private static function fold(string $value): string
    {
        $value = \core_text::strtolower(trim($value));
        return str_replace(['á', 'à', 'ã', 'â', 'ä', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'õ', 'ô', 'ö', 'ú', 'ù', 'û', 'ü', 'ç'], ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c'], $value);
    }

    private static function assert_iesde_source(object $source): void
    {
        if (!self::is_iesde_source($source)) {
            throw new \invalid_parameter_exception('A atividade não pertence ao conector LTI homologado do IESDE.');
        }
    }

    private static function is_iesde_source(object $source): bool
    {
        global $DB;
        $type = (int) ($source->typeid ?? 0) > 0 ? $DB->get_record('lti_types', ['id' => (int) $source->typeid]) : false;
        $typename = is_object($type) ? (string) ($type->name ?? '') : '';
        $baseurl = is_object($type) ? (string) ($type->baseurl ?? '') : '';
        $tooldomain = is_object($type) ? (string) ($type->tooldomain ?? '') : '';
        $fingerprint = \core_text::strtolower((string) ($source->toolurl ?? '') . ' ' . $typename . ' ' . $baseurl . ' ' . $tooldomain);
        return str_contains($fingerprint, 'iesde') || str_contains($fingerprint, 'api-fornecimento');
    }

    private static function result(int $courseid, int $activityid, int $cmid, bool $reused, int $sections, int $activities, int $reusedactivities,array$cover,array$quiz): array
    {
        return [
            'status' => 'ok',
            'courseid' => $courseid,
            'activityid' => $activityid,
            'cmid' => $cmid,
            'reused' => $reused,
            'sections' => $sections,
            'activities' => $activities,
            'reusedactivities' => $reusedactivities,
            'coverstatus'=>(string)($cover['coverstatus']??'missing'),
            'courseimage'=>(int)($cover['courseimage']??0),
            'coursebanner'=>(int)($cover['coursebanner']??0),
            'quizcmid'=>(int)($quiz['quiz']??0),
            'quizquestions'=>(int)($quiz['questions']??0),
            'quizconflict'=>(int)($quiz['conflict']??0),
        ];
    }

    public static function execute_returns(): external_single_structure
    {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'Estado da operação.'),
            'courseid' => new external_value(PARAM_INT, 'Curso Individual reutilizável.'),
            'activityid' => new external_value(PARAM_INT, 'Primeira atividade LTI criada ou atualizada.'),
            'cmid' => new external_value(PARAM_INT, 'Primeiro módulo do curso.'),
            'reused' => new external_value(PARAM_BOOL, 'Indica se alguma atividade existente foi reutilizada.'),
            'sections' => new external_value(PARAM_INT, 'Blocos pedagógicos sincronizados.'),
            'activities' => new external_value(PARAM_INT, 'Atividades LTI sincronizadas.'),
            'reusedactivities' => new external_value(PARAM_INT, 'Atividades existentes reutilizadas.'),
            'coverstatus' => new external_value(PARAM_ALPHANUMEXT, 'Situação da capa comercial.'),
            'courseimage' => new external_value(PARAM_INT, 'Confirma a imagem oficial do curso.'),
            'coursebanner' => new external_value(PARAM_INT, 'Confirma a testeira do curso.'),
            'quizcmid' => new external_value(PARAM_INT, 'Questionário final criado ou preservado.'),
            'quizquestions' => new external_value(PARAM_INT, 'Questões vinculadas ao questionário final.'),
            'quizconflict' => new external_value(PARAM_INT, 'Questionário preservado por possuir tentativas.'),
        ]);
    }
}
