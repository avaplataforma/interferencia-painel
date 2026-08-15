<?php

namespace local_mundointer\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Prepares the single technical Moodle course used by the unattended IESDE
 * Deep Linking robot. The optional URL authenticates the web-service user
 * once, expires quickly and never exposes a Moodle password.
 */
final class prepare_lti_robot extends external_api
{
    private const COURSE_IDNUMBER = 'mi-master-staging';
    private const COURSE_NAME = 'Migração LTI';
    private const LEGACY_COURSE_NAME = 'TESTES - Funções';

    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'withlogin' => new external_value(PARAM_BOOL, 'Create a short-lived one-time browser login URL.', VALUE_DEFAULT, true),
            'resetcourse' => new external_value(PARAM_BOOL, 'Remove previous IESDE selections from the staging course.', VALUE_DEFAULT, false),
            'cleanlegacy' => new external_value(PARAM_BOOL, 'Remove old robot selections left in Sala MASTER.', VALUE_DEFAULT, false),
        ]);
    }

    public static function execute(bool $withlogin = true, bool $resetcourse = false, bool $cleanlegacy = false): array
    {
        global $CFG, $DB, $USER;

        $parameters = self::validate_parameters(self::execute_parameters(), [
            'withlogin' => $withlogin,
            'resetcourse' => $resetcourse,
            'cleanlegacy' => $cleanlegacy,
        ]);
        self::validate_context(\context_system::instance());
        require_capability('local/mundointer:manage', \context_system::instance());

        $course = $DB->get_record('course', ['idnumber' => self::COURSE_IDNUMBER]);
        if (!$course) {
            $course = $DB->get_record('course', ['fullname' => self::COURSE_NAME], '*', IGNORE_MULTIPLE);
        }
        if (!$course) {
            $course = $DB->get_record('course', ['fullname' => self::LEGACY_COURSE_NAME], '*', IGNORE_MULTIPLE);
        }
        if (!$course) {
            require_once($CFG->dirroot . '/course/lib.php');
            $category = \core_course_category::get_default();
            $course = create_course((object)[
                'fullname' => self::COURSE_NAME,
                'shortname' => 'MI-MASTER-STAGING',
                'idnumber' => self::COURSE_IDNUMBER,
                'category' => (int)$category->id,
                'visible' => 0,
                'summary' => 'Área técnica exclusiva para seleção automática de conteúdos MASTER.',
                'summaryformat' => FORMAT_HTML,
                'format' => 'topics',
            ]);
        } else {
            $updates = ['id' => (int)$course->id];
            $changed = false;
            if (trim((string)$course->idnumber) !== self::COURSE_IDNUMBER) {
                $updates['idnumber'] = self::COURSE_IDNUMBER;
                $changed = true;
            }
            if (trim((string)$course->fullname) !== self::COURSE_NAME) {
                $updates['fullname'] = self::COURSE_NAME;
                $changed = true;
            }
            if ((int)$course->visible !== 0) {
                $updates['visible'] = 0;
                $changed = true;
            }
            if ($changed) {
                $DB->update_record('course', (object)$updates);
                $course = $DB->get_record('course', ['id' => (int)$course->id], '*', MUST_EXIST);
            }
        }

        $typeid = 0;
        foreach ($DB->get_records('lti_types', null, 'id ASC') as $type) {
            $fingerprint = \core_text::strtolower(implode(' ', [
                (string)($type->name ?? ''),
                (string)($type->baseurl ?? ''),
                (string)($type->tooldomain ?? ''),
                (string)($type->toolproxyid ?? ''),
            ]));
            if (str_contains($fingerprint, 'iesde') || str_contains($fingerprint, 'api-fornecimento')) {
                $typeid = (int)$type->id;
                break;
            }
        }
        if ($typeid < 1) {
            throw new \moodle_exception('A ferramenta LTI Hub IESDE não está configurada no AVA Cursos.');
        }

        if ((bool)$parameters['resetcourse'] || (bool)$parameters['cleanlegacy']) {
            require_once($CFG->dirroot . '/course/lib.php');
            $moduleid = (int)($DB->get_field('modules', 'id', ['name' => 'lti']) ?: 0);
            if ($moduleid > 0) {
                $cleanupcourses = [];
                if ((bool)$parameters['resetcourse']) $cleanupcourses[] = (int)$course->id;
                if ((bool)$parameters['cleanlegacy']) {
                    $legacy = $DB->get_record('course', ['fullname' => 'Sala MASTER'], 'id', IGNORE_MULTIPLE);
                    if ($legacy) $cleanupcourses[] = (int)$legacy->id;
                }
                foreach (array_unique($cleanupcourses) as $cleanupcourseid) {
                    $modules = $DB->get_records('course_modules', ['course' => $cleanupcourseid, 'module' => $moduleid]);
                    foreach ($modules as $module) {
                        $instance = $DB->get_record('lti', ['id' => (int)$module->instance], 'id,typeid');
                        if ($instance && (int)$instance->typeid === $typeid) {
                            course_delete_module((int)$module->id);
                        }
                    }
                }
            }
        }

        $loginurl = '';
        $expiresat = 0;
        if ((bool)$parameters['withlogin']) {
            $token = bin2hex(random_bytes(32));
            $expiresat = time() + 120;
            $cache = \cache::make('local_mundointer', 'robot_sessions');
            $cache->set(hash('sha256', $token), [
                'userid' => (int)$USER->id,
                'courseid' => (int)$course->id,
                'typeid' => $typeid,
                'expiresat' => $expiresat,
            ]);
            $loginurl = $CFG->wwwroot . '/local/mundointer/robot_login.php?token=' . rawurlencode($token);
        }

        return [
            'courseid' => (int)$course->id,
            'coursename' => clean_param((string)$course->fullname, PARAM_TEXT),
            'courseidnumber' => self::COURSE_IDNUMBER,
            'typeid' => $typeid,
            'loginurl' => $loginurl,
            'expiresat' => $expiresat,
        ];
    }

    public static function execute_returns(): external_single_structure
    {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Technical staging course id.'),
            'coursename' => new external_value(PARAM_TEXT, 'Technical staging course name.'),
            'courseidnumber' => new external_value(PARAM_TEXT, 'Stable technical staging course identifier.'),
            'typeid' => new external_value(PARAM_INT, 'IESDE LTI type id.'),
            'loginurl' => new external_value(PARAM_RAW, 'Short-lived one-time login URL.'),
            'expiresat' => new external_value(PARAM_INT, 'Login URL expiration timestamp.'),
        ]);
    }
}
