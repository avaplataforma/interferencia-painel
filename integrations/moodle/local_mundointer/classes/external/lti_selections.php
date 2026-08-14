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
              ORDER BY c.sortorder,cs.section,FIND_IN_SET(cm.id,cs.sequence),cm.id";
        $records = $DB->get_records_sql($sql, ['moduleid' => $moduleid]);
        $sourcecourses = [];

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
            $courseidnumber = trim((string)$record->idnumber);
            if (str_starts_with($courseidnumber, 'mi-master-content-') ||
                str_starts_with($courseidnumber, 'mi-master-course-') ||
                str_starts_with($courseidnumber, 'mi-trilha-')) {
                // Definitive courses are outputs from this connector, never
                // new import sources. Skipping them prevents recursion.
                continue;
            }
            if (!isset($sourcecourses[$courseid])) {
                $sourcecourses[$courseid] = [
                    'name' => clean_param((string)$record->fullname, PARAM_TEXT),
                    'slug' => clean_param((string)($record->idnumber ?: $record->shortname), PARAM_ALPHANUMEXT),
                    'items' => [],
                ];
            }

            $name = clean_param((string)$record->name, PARAM_TEXT);
            $isassessment = self::isAssessmentName($name);
            $sourcecourses[$courseid]['items'][] = [
                'batch' => 'moodle-lti-' . (int)$record->ltiid,
                'type' => $isassessment ? 'assessment' : 'lti',
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
                    'is_assessment' => $isassessment,
                    'timemodified' => (int)$record->timemodified,
                ],
            ];
        }

        $courses = [];
        foreach ($sourcecourses as $courseid => $sourcecourse) {
            $segments = self::selectionSegments((array)$sourcecourse['items']);
            foreach ($segments as $segment => $contents) {
                $courses[] = [
                    'id' => 'moodle-course-' . $courseid . ($segment > 1 ? '-segment-' . $segment : ''),
                    'nome' => self::selectionName((string)$sourcecourse['name'], $contents),
                    'slug' => clean_param((string)$sourcecourse['slug'] . ($segment > 1 ? '-segment-' . $segment : ''), PARAM_ALPHANUMEXT),
                    'categoria' => 'MASTER',
                    'tipo_acesso' => 'LTI 1.3',
                    'status' => 'available',
                    'updated_at' => self::selectionUpdatedAt($contents),
                    'conteudos' => $contents,
                    'source_course_id' => $courseid,
                    'selection_segment' => $segment,
                ];
            }
        }

        return self::response($provider, $courses);
    }

    /**
     * A presentation starts each Deep Linking selection. An assessment does
     * not necessarily finish it because IESDE can append the complete book
     * after the assessment. Legacy imports can also contain a descriptive
     * header immediately followed by the provider's generic presentation;
     * both resources belong to the same discipline.
     *
     * @param list<array<string,mixed>> $items
     * Sparse numeric keys intentionally preserve the legacy segment identity.
     * This keeps approved courses and publication history attached when two
     * adjacent presentation headers are consolidated.
     *
     * @return array<int,list<array<string,mixed>>>
     */
    private static function selectionSegments(array $items): array
    {
        $segments = [];
        $current = [];
        $currentsegment = 1;
        $nextsegment = 2;
        $selectionstarted = false;
        foreach ($items as $item) {
            $name = (string)($item['name'] ?? '');
            $isstart = self::isSelectionStartName($name);
            if (!$selectionstarted && $isstart) {
                if ($current !== []) {
                    // Ignore loose legacy/test activities before the first
                    // complete Deep Linking selection while preserving the
                    // historical ordinal of existing selections.
                    $current = [];
                    $currentsegment = $nextsegment;
                    $nextsegment++;
                }
                $selectionstarted = true;
            } else if ($selectionstarted && $current !== [] && $isstart) {
                if (self::isAdjacentSelectionHeader($current, $name)) {
                    // Preserve the old ordinal that this second header would
                    // have received before both headers were consolidated.
                    $nextsegment++;
                } else {
                    $segments[$currentsegment] = $current;
                    $current = [];
                    $currentsegment = $nextsegment;
                    $nextsegment++;
                }
            }
            $current[] = $item;
        }
        if ($current !== []) {
            $segments[$currentsegment] = $current;
        }
        if (!$selectionstarted && $segments === [] && $items !== []) {
            $segments[1] = array_values($items);
        }
        return $segments;
    }

    /** @param list<array<string,mixed>> $contents */
    private static function selectionName(string $fallback, array $contents): string
    {
        foreach (array_reverse($contents) as $content) {
            if (!(bool)($content['raw']['is_assessment'] ?? false)) {
                continue;
            }
            $name = trim((string)($content['name'] ?? ''));
            $name = trim((string)preg_replace('/^(avalia[cç][aã]o|prova|exame)(\s+final)?\s*[-:–—]\s*/iu', '', $name));
            if ($name !== '') {
                return clean_param($name, PARAM_TEXT);
            }
        }
        foreach ($contents as $content) {
            $title = self::selectionTitleFromHeader((string)($content['name'] ?? ''));
            if ($title !== null) {
                return clean_param($title, PARAM_TEXT);
            }
        }
        return clean_param($fallback, PARAM_TEXT);
    }

    /** @param list<array<string,mixed>> $current */
    private static function isAdjacentSelectionHeader(array $current, string $nextname): bool
    {
        if (count($current) !== 1 || !self::isGenericPresentationName($nextname)) {
            return false;
        }
        return self::selectionTitleFromHeader((string)($current[0]['name'] ?? '')) !== null;
    }

    private static function isGenericPresentationName(string $name): bool
    {
        return preg_match('/^(?:aula\s*[-:–—]\s*)?apresenta[cç][aã]o(?:\s*0)?$/iu', trim($name)) === 1;
    }

    private static function selectionTitleFromHeader(string $name): ?string
    {
        if (preg_match('/^aula\s*[-:–—]\s*(.+?)\s*[-:–—]\s*apresenta[cç][aã]o(?:\s*0)?$/iu', trim($name), $match) !== 1) {
            return null;
        }
        $title = trim((string)$match[1]);
        return $title !== '' ? $title : null;
    }

    /** @param list<array<string,mixed>> $contents */
    private static function selectionUpdatedAt(array $contents): string
    {
        $latest = 0;
        foreach ($contents as $content) {
            $latest = max($latest, (int)($content['raw']['timemodified'] ?? 0));
        }
        return date('c', $latest > 0 ? $latest : time());
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

    private static function isAssessmentName(string $name): bool
    {
        $folded = \core_text::strtolower(trim($name));
        return preg_match('/avalia|prova|exame/u', $folded) === 1;
    }

    private static function isSelectionStartName(string $name): bool
    {
        $folded = \core_text::strtolower(trim($name));
        return preg_match('/apresenta/u', $folded) === 1;
    }

    public static function execute_returns(): external_single_structure
    {
        return new external_single_structure([
            'payload' => new external_value(PARAM_RAW, 'JSON catalog of selected LTI activities.'),
        ]);
    }
}
