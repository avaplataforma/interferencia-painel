<?php

namespace local_mundointer\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

final class academic_snapshot extends external_api
{
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Usuário consultado.'),
            'courseid' => new external_value(PARAM_INT, 'Curso consultado.'),
        ]);
    }

    public static function execute(int $userid, int $courseid): array
    {
        global $CFG, $DB;

        $parameters = self::validate_parameters(self::execute_parameters(), compact('userid', 'courseid'));
        $userid = (int)$parameters['userid'];
        $courseid = (int)$parameters['courseid'];
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id', MUST_EXIST);
        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_capability('local/mundointer:manage', \context_system::instance());

        $progress = -1.0;
        $progressstatus = 'not_configured';
        if ((int)$course->enablecompletion === 1) {
            require_once($CFG->libdir . '/completionlib.php');
            $completion = new \completion_info($course);
            $activities = $completion->get_activities();
            $total = 0;
            $completed = 0;
            foreach ($activities as $activity) {
                if (!$activity->uservisible) {
                    continue;
                }
                $total++;
                $data = $completion->get_data($activity, false, $userid);
                if (in_array((int)$data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS, COMPLETION_COMPLETE_FAIL], true)) {
                    $completed++;
                }
            }
            $coursecompletion = $DB->get_record('course_completions', ['course' => $courseid, 'userid' => $userid]);
            if ($coursecompletion !== false && (int)$coursecompletion->timecompleted > 0) {
                $progress = 100.0;
                $progressstatus = 'completed';
            } elseif ($total > 0) {
                $progress = round(($completed / $total) * 100, 2);
                $progressstatus = $progress > 0 ? 'in_progress' : 'not_started';
            }
        }

        $gradepercent = -1.0;
        $gradestatus = 'not_available';
        $gradeitem = $DB->get_record('grade_items', ['courseid' => $courseid, 'itemtype' => 'course']);
        if ($gradeitem !== false) {
            $grade = $DB->get_record('grade_grades', ['itemid' => (int)$gradeitem->id, 'userid' => $userid]);
            if ($grade !== false && $grade->finalgrade !== null) {
                $minimum = (float)$gradeitem->grademin;
                $maximum = (float)$gradeitem->grademax;
                $gradepercent = $maximum > $minimum
                    ? round((((float)$grade->finalgrade - $minimum) / ($maximum - $minimum)) * 100, 2)
                    : 0.0;
                $pass = (float)$gradeitem->gradepass;
                $gradestatus = $pass > 0
                    ? ((float)$grade->finalgrade >= $pass ? 'passed' : 'failed')
                    : 'graded';
            } else {
                $gradestatus = 'not_graded';
            }
        }

        $lastaccess = (int)($DB->get_field('user_lastaccess', 'timeaccess', ['userid' => $userid, 'courseid' => $courseid]) ?: 0);
        $certificatestatus = 'not_available';
        $certificateurl = '';
        $manager = $DB->get_manager();
        if ($manager->table_exists(new \xmldb_table('customcert')) && $manager->table_exists(new \xmldb_table('customcert_issues'))) {
            $certificate = $DB->get_record('customcert', ['course' => $courseid], 'id', IGNORE_MULTIPLE);
            if ($certificate !== false) {
                $certificatestatus = 'available';
                $issue = $DB->get_record('customcert_issues', ['customcertid' => (int)$certificate->id, 'userid' => $userid], 'id', IGNORE_MULTIPLE);
                if ($issue !== false) {
                    $certificatestatus = 'issued';
                    $moduleid = $DB->get_field('modules', 'id', ['name' => 'customcert']);
                    if ($moduleid !== false) {
                        $cmid = $DB->get_field('course_modules', 'id', ['course' => $courseid, 'module' => (int)$moduleid, 'instance' => (int)$certificate->id]);
                        if ($cmid !== false) {
                            $certificateurl = (new \moodle_url('/mod/customcert/view.php', ['id' => (int)$cmid, 'downloadown' => 1]))->out(false);
                        }
                    }
                }
            }
        }

        return [
            'provider' => 'ava_cursos',
            'progresspercent' => $progress,
            'progressstatus' => $progressstatus,
            'gradepercent' => $gradepercent,
            'gradestatus' => $gradestatus,
            'lastaccess' => $lastaccess,
            'certificatestatus' => $certificatestatus,
            'certificateurl' => $certificateurl,
            'syncedat' => time(),
        ];
    }

    public static function execute_returns(): external_single_structure
    {
        return new external_single_structure([
            'provider' => new external_value(PARAM_ALPHANUMEXT, 'Conector acadêmico de origem.'),
            'progresspercent' => new external_value(PARAM_FLOAT, 'Progresso percentual ou -1 quando indisponível.'),
            'progressstatus' => new external_value(PARAM_ALPHANUMEXT, 'Situação normalizada do progresso.'),
            'gradepercent' => new external_value(PARAM_FLOAT, 'Nota percentual ou -1 quando indisponível.'),
            'gradestatus' => new external_value(PARAM_ALPHANUMEXT, 'Situação normalizada da nota.'),
            'lastaccess' => new external_value(PARAM_INT, 'Último acesso ao curso em timestamp Unix.'),
            'certificatestatus' => new external_value(PARAM_ALPHANUMEXT, 'Situação normalizada do certificado.'),
            'certificateurl' => new external_value(PARAM_URL, 'Endereço do certificado, quando disponível.', VALUE_DEFAULT, ''),
            'syncedat' => new external_value(PARAM_INT, 'Momento da leitura acadêmica.'),
        ]);
    }
}
