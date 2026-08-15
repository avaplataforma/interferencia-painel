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
        $sources = self::selection_for_source($sources, (int)$sourcecm->id);
        if ($sources === []) {
            throw new \moodle_exception('A seleção MASTER desta disciplina não foi encontrada no curso de importação.');
        }
        sync_trail_sections::apply_managed_course_format($course);

        $groups = self::order_groups(self::group_sources($sources), (string)$course->fullname);
        $firstcmid = 0;
        $firstactivityid = 0;
        $reusedactivities = 0;
        $activitycount = 0;
        $sectionnumber = 0;
        $modulenumber = 0;
        $booksection = null;
        $bookhasinteractivematerial = false;
        $interactiveactivities = [];

        foreach ($groups as $group) {
            $sectionnumber++;
            $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sectionnumber]);
            if (!$section) {
                $section = course_create_section((int) $course->id, $sectionnumber);
            }
            $isassessment = !empty($group['assessment']);
            $isbook = !$isassessment && self::is_complete_book_group((string)$group['name'], (string)$course->fullname);
            if (!$isassessment && !$isbook) {
                $modulenumber++;
            }
            $sectionname = $isassessment
                ? 'Avaliação final'
                : ($isbook
                    ? 'Livro e Materiais Interativos'
                    : 'Módulo ' . $modulenumber . ' - ' . $group['name']);
            $DB->update_record('course_sections', (object) [
                'id' => $section->id,
                'name' => $sectionname,
                'visible' => 1,
            ]);

            $bookitemcount = $isbook ? count($group['items']) : 0;
            $bookhasexplicitname = $isbook && array_reduce($group['items'], static function(bool $found, array $candidate): bool {
                return $found || preg_match('/^(?:nome\s+do\s+)?livro\s*:/iu', trim((string)$candidate['lti']->name)) === 1;
            }, false);
            $bookitemindex = 0;
            foreach ($group['items'] as $item) {
                if ($isbook) {
                    $bookitemindex++;
                }
                $sourceitemcm = $item['cm'];
                $source = $item['lti'];
                $moduleidnumber = (int) $sourceitemcm->id === (int) $sourcecm->id
                    ? $parameters['idnumber']
                    : 'mi-master-lti-cm-' . (int) $sourceitemcm->id;
                $displayname = $isbook
                    ? self::book_activity_display_name((string)$source->name, (string)$course->fullname, $bookitemindex, $bookitemcount, $bookhasexplicitname)
                    : self::activity_display_name((string) $source->name, $item['kind'], $item['number']);
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
                if ($isbook) {
                    $booksection = $section;
                    $bookhasinteractivematerial = $bookhasinteractivematerial || $displayname === 'Materiais Interativos';
                } else if (!$isassessment) {
                    $interactiveactivities[] = ['cmid' => $cmid, 'name' => $sectionname];
                }
                $activitycount++;
            }
        }

        // Some MASTER titles expose only the complete PDF as complementary
        // material. In that case, keep the agreed first block complete with a
        // Moodle page that indexes every interactive lesson already imported.
        // This avoids duplicating the PDF under a misleading second label.
        if ($booksection && !$bookhasinteractivematerial && $interactiveactivities !== []) {
            self::ensure_materials_index($course, $booksection, $interactiveactivities);
            $activitycount++;
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

    /**
     * Keeps only the Deep Linking selection that owns the source activity.
     * The next presentation starts a new selection. The official assessment
     * is not a boundary because IESDE may append the complete book after it.
     *
     * @param array<int,array{cm:object,lti:object}> $sources
     * @return array<int,array{cm:object,lti:object}>
     */
    private static function selection_for_source(array $sources, int $sourcecmid): array
    {
        $segments = [];
        $current = [];
        $selectionstarted = false;
        foreach ($sources as $source) {
            $name = (string)$source['lti']->name;
            $isstart = self::is_selection_start_name($name);
            if (!$selectionstarted && $isstart) {
                if ($current !== []) {
                    $current = [];
                }
                $selectionstarted = true;
            } else if ($selectionstarted && $current !== [] && $isstart && !self::is_adjacent_selection_header($current, $name)) {
                $segments[] = $current;
                $current = [];
            }
            $current[] = $source;
        }
        if ($current !== []) {
            $segments[] = $current;
        }
        if (!$selectionstarted && $segments === [] && $sources !== []) {
            $segments[] = $sources;
        }
        foreach ($segments as $segment) {
            foreach ($segment as $source) {
                if ((int)$source['cm']->id === $sourcecmid) {
                    return $segment;
                }
            }
        }
        return [];
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

    /**
     * Keeps the reusable course predictable: complete book first, teaching
     * modules in their original order and the official assessment last.
     *
     * @param array<int,array{name:string,assessment?:bool,items:array}> $groups
     * @return array<int,array{name:string,assessment?:bool,items:array}>
     */
    private static function order_groups(array $groups, string $coursename): array
    {
        $books = [];
        $lessons = [];
        $assessments = [];
        foreach ($groups as $group) {
            if (!empty($group['assessment'])) {
                $assessments[] = $group;
            } else if (self::is_complete_book_group((string)$group['name'], $coursename)) {
                $books[] = $group;
            } else {
                $lessons[] = $group;
            }
        }
        return array_merge($books, $lessons, $assessments);
    }

    /** @return array{base:string,kind:string,number:int} */
    private static function lesson_parts(string $name): array
    {
        $name = trim((string) preg_replace('/^aula\s*[-:–—]\s*/iu', '', clean_param($name, PARAM_TEXT)));
        $name = trim((string)preg_replace('/^(?:nome\s+do\s+)?livro\s*:\s*/iu', '', $name));
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

    private static function book_activity_display_name(string $original, string $coursename, int $itemindex, int $itemcount, bool $hasexplicitname): string
    {
        if (preg_match('/^(?:nome\s+do\s+)?livro\s*:/iu', trim($original)) === 1) {
            return 'Livro - ' . trim($coursename);
        }
        // Some IESDE selections send the HTML and PDF resources with the
        // exact same title. In that case the final item is the complete book,
        // matching the order presented by the official Deep Linking picker.
        if (!$hasexplicitname && ($itemcount === 1 || $itemindex === $itemcount)) {
            return 'Livro - ' . trim($coursename);
        }
        return 'Materiais Interativos';
    }

    private static function is_assessment_name(string $name): bool
    {
        return preg_match('/avalia|prova|exame/u', self::fold($name)) === 1;
    }

    private static function is_selection_start_name(string $name): bool
    {
        return preg_match('/apresenta/u', self::fold($name)) === 1;
    }

    /** @param array<int,array{cm:object,lti:object}> $current */
    private static function is_adjacent_selection_header(array $current, string $nextname): bool
    {
        if (count($current) !== 1 || !self::is_generic_presentation_name($nextname)) {
            return false;
        }
        return self::selection_title_from_header((string)$current[0]['lti']->name) !== null;
    }

    private static function is_generic_presentation_name(string $name): bool
    {
        return preg_match('/^(?:aula\s*[-:–—]\s*)?apresenta[cç][aã]o(?:\s*0)?$/iu', trim($name)) === 1;
    }

    private static function selection_title_from_header(string $name): ?string
    {
        if (preg_match('/^aula\s*[-:–—]\s*(.+?)\s*[-:–—]\s*apresenta[cç][aã]o(?:\s*0)?$/iu', trim($name), $match) !== 1) {
            return null;
        }
        $title = trim((string)$match[1]);
        return $title !== '' ? $title : null;
    }

    private static function is_complete_book_group(string $groupname, string $coursename): bool
    {
        $groupname = trim((string)preg_replace('/^(?:nome\s+do\s+)?livro\s*:\s*/iu', '', $groupname));
        $groupkey = trim((string)preg_replace('/[^a-z0-9]+/', ' ', self::fold($groupname)));
        $coursekey = trim((string)preg_replace('/[^a-z0-9]+/', ' ', self::fold($coursename)));
        return $groupkey !== '' && $coursekey !== '' && $groupkey === $coursekey;
    }

    /**
     * Creates or refreshes the fallback interactive-material index used when
     * the provider exposes the PDF book but no separate HTML book resource.
     *
     * @param array<int,array{cmid:int,name:string}> $activities
     */
    private static function ensure_materials_index(object $course, object $section, array $activities): void
    {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/page/lib.php');
        $pagemodule = $DB->get_record('modules', ['name' => 'page'], '*', MUST_EXIST);
        $idnumber = 'mi-master-materials-index-' . (int)$course->id;
        $items = [];
        foreach ($activities as $activity) {
            $url = new \moodle_url('/mod/lti/view.php', ['id' => (int)$activity['cmid']]);
            $items[] = \html_writer::tag('li', \html_writer::link($url, format_string((string)$activity['name'])));
        }
        $content = \html_writer::div(
            \html_writer::tag('p', 'Acesse os materiais interativos organizados por módulo.')
            . \html_writer::tag('ol', implode('', $items)),
            'mundointer-materials-index'
        );
        $existing = $DB->get_record('course_modules', [
            'course' => (int)$course->id,
            'module' => (int)$pagemodule->id,
            'idnumber' => $idnumber,
        ]);
        if ($existing) {
            $DB->update_record('page', (object)[
                'id' => (int)$existing->instance,
                'name' => 'Materiais Interativos',
                'content' => $content,
                'contentformat' => FORMAT_HTML,
                'timemodified' => time(),
            ]);
            $DB->update_record('course_modules', (object)[
                'id' => (int)$existing->id,
                'visible' => 1,
                'visibleoncoursepage' => 1,
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'completionview' => 1,
            ]);
            $cm = get_coursemodule_from_id('page', (int)$existing->id, (int)$course->id, false, MUST_EXIST);
            moveto_module($cm, $section);
            return;
        }

        $moduleinfo = (object)[
            'course' => (int)$course->id,
            'name' => 'Materiais Interativos',
            'modulename' => 'page',
            'module' => (int)$pagemodule->id,
            'section' => (int)$section->section,
            'visible' => 1,
            'visibleoncoursepage' => 1,
            'cmidnumber' => $idnumber,
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'content' => $content,
            'contentformat' => FORMAT_HTML,
            'display' => 5,
            'displayoptions' => serialize([]),
            'revision' => 1,
            'groupmode' => 0,
            'groupingid' => 0,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
            'completionexpected' => 0,
            'coursemodule' => 0,
            'instance' => 0,
        ];
        add_moduleinfo($moduleinfo, $course);
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
