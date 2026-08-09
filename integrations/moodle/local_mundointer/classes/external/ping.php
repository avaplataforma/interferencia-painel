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
        $plugininfo = \core_plugin_manager::instance()->get_plugin_info('local_mundointer');
        $catalog=json_decode((string)($plugin->brandcatalog ?? ''),true);
        $brandcount=is_array($catalog)&&is_array($catalog['brands']??null)?count($catalog['brands']):0;
        return [
            'status' => !empty($plugin->enabled) ? 'ok' : 'disabled',
            'component' => 'local_mundointer',
            'pluginversion' => (string)($plugin->version ?? '2026080800'),
            'release' => (string)($plugininfo->release ?? ''),
            'siteuuid' => (string)($plugin->site_uuid ?? ''),
            'moodlerelease' => (string)$CFG->release,
            'servertime' => time(),
            'brandcount' => $brandcount,
            'brandversion' => (string)($plugin->brand_catalog_version ?? ''),
            'brandsyncedat' => (int)($plugin->brand_synced_at ?? 0),
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
            'brandcount' => new external_value(PARAM_INT, 'Quantidade de identidades disponíveis.'),
            'brandversion' => new external_value(PARAM_ALPHANUM, 'Versão do catálogo de identidades.'),
            'brandsyncedat' => new external_value(PARAM_INT, 'Última sincronização das identidades.'),
        ]);
    }
}
