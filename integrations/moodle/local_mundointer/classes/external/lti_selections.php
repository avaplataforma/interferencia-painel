<?php

namespace local_mundointer\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Exposes only the LTI activities selected for an approved course provider.
 *
 * The ADM Central never receives LTI secrets or user data. It only receives
 * the Moodle course and activity metadata required to build the commercial
 * catalog after a teacher selected the content through Deep Linking.
 */
final class lti_selections extends external_api
{
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'provider' => new external_value(PARAM_ALPHANUMEXT, 'Approved provider code.', VALUE_DEFAULT, 'iesde'),
        ]);
    }

    public static function execute(string $provider = 'iesde'): array
    {
        global $DB;

        $parameters = self::validate_parameters(self::execute_parameters(), ['provider' => $provider]);
        $provider = strtolower(trim((string)$parameters['provider']));
        self::validate_context(\context_system::instance());
        require_capability('local/mundointer:manage', \context_system::instance());

        if ($provider !== 'iesde') {
            throw new \invalid_parameter_exception('Provider not approved for this connector.');
        }

        $moduleid = (int)($DB->get_field('modules', 'id', ['name' => 'lti']) ?: 0);
        if ($moduleid < 1) {
            return self::response($provider, []);
        }

        $sql = "SELECT l.id AS ltiid,l.course,l.name,l.intro,l.typeid,l.toolurl,l.timemodified,
                       cm.id AS cmid,cm.visible,cs.section AS sectionnumber,
                       c.fullname,c.shortname,c.idnumber,c.category
                  FROM {lti} l
                  JOIN {course_modules} cm ON cm.instance=l.id AND cm.module=:moduleid
                  JOIN {course} c ON c.id=l.course
             LEFT JOIN {course_sections} cs ON cs.id=cm.section
              ORDER BY c.sortorder,cs.section,cm.id";
        $records = $DB->get_records_sql($sql, ['moduleid' => $moduleid]);
        $courses = [];

        foreach ($records as $record) {
            $type = false;
            if ((int)$record->typeid > 0) {
                $type = $DB->get_record('lti_types', ['id' => (int)$record->typeid]);
            }
            $typeName = is_object($type) ? (string)($type->name ?? '') : '';
            $typeUrl = is_object($type) ? (string)($type->baseurl ?? $type->tooldomain ?? '') : '';
            $toolUrl = trim((string)$record->toolurl);
            $fingerprint = mb_strtolower($typeName . ' ' . $typeUrl . ' ' . $toolUrl);
            if (!str_contains($fingerprint, 'iesde') && !str_contains($fingerprint, 'api-fornecimento')) {
                continue;
            }

            $courseid = (int)$record->course;
            if (!isset($courses[$courseid])) {
                $courses[$courseid] = [
                    'id' => 'moodle-course-' . $courseid,
                    'nome' => clean_param((string)$record->fullname, PARAM_TEXT),
                    'slug' => clean_param((string)($record->idnumber ?: $record->shortname), PARAM_ALPHANUMEXT),
                    'categoria' => 'MASTER',
                    'tipo_acesso' => 'LTI 1.3',
                    'status' => 'available',
                    'updated_at' => date('c', (int)$record->timemodified),
                    'conteudos' => [],
                ];
            }

            $name = clean_param((string)$record->name, PARAM_TEXT);
            $courses[$courseid]['conteudos'][] = [
                'batch' => 'moodle-lti-' . (int)$record->ltiid,
                'type' => 'lti',
                'name' => $name,
                'discipline' => $name,
                'semester' => max(1, (int)$record->sectionnumber),
                'position' => max(0, (int)$record->sectionnumber),
                'raw' => [
                    'moodle_course_id' => $courseid,
                    'course_module_id' => (int)$record->cmid,
                    'lti_activity_id' => (int)$record->ltiid,
                    'section' => max(0, (int)$record->sectionnumber),
                    'visible' => (int)$record->visible === 1,
                    'tool_name' => clean_param($typeName, PARAM_TEXT),
                    'launch_host' => self::safeHost($toolUrl !== '' ? $toolUrl : $typeUrl),
                    'description' => clean_param(strip_tags((string)$record->intro), PARAM_TEXT),
                ],
            ];
        }

        return self::response($provider, array_values($courses));
    }

    /** @param list<array<string,mixed>> $courses */
    private static function response(string $provider, array $courses): array
    {
        $count = 0;
        foreach ($courses as $course) {
            $count += count((array)($course['conteudos'] ?? []));
        }
        return [
            'payload' => json_encode([
                'provider' => $provider,
                'courses' => $courses,
                'coursecount' => count($courses),
                'contentcount' => $count,
                'syncedat' => time(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ];
    }

    private static function safeHost(string $url): string
    {
        $host = parse_url(trim($url), PHP_URL_HOST);
        return is_string($host) ? strtolower($host) : '';
    }

    public static function execute_returns(): external_single_structure
    {
        return new external_single_structure([
            'payload' => new external_value(PARAM_RAW, 'JSON catalog of selected LTI activities.'),
        ]);
    }
}
