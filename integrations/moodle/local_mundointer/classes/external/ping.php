<?php

namespace local_mundointer\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

final class ping extends external_api
{
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([]);
    }

    public static function execute(): array
    {
        global $CFG;
        self::validate_context(\context_system::instance());
        require_capability('local/mundointer:manage', \context_system::instance());
        $plugin = get_config('local_mundointer');
        return [
            'status' => !empty($plugin->enabled) ? 'ok' : 'disabled',
            'component' => 'local_mundointer',
            'pluginversion' => (string)($plugin->version ?? '2026080700'),
            'release' => '0.1.0',
            'siteuuid' => (string)($plugin->site_uuid ?? ''),
            'moodlerelease' => (string)$CFG->release,
            'servertime' => time(),
        ];
    }

    public static function execute_returns(): external_single_structure
    {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'Estado do conector.'),
            'component' => new external_value(PARAM_COMPONENT, 'Componente Moodle.'),
            'pluginversion' => new external_value(PARAM_ALPHANUM, 'Versão interna.'),
            'release' => new external_value(PARAM_TEXT, 'Versão pública.'),
            'siteuuid' => new external_value(PARAM_ALPHANUM, 'Identificador anônimo da instalação.'),
            'moodlerelease' => new external_value(PARAM_TEXT, 'Versão do Moodle.'),
            'servertime' => new external_value(PARAM_INT, 'Horário do servidor.'),
        ]);
    }
}
