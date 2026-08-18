<?php

namespace local_mundointer\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use cache;
use context_system;
use moodle_url;

class sso_session extends external_api
{
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'username' => new external_value(PARAM_USERNAME, 'Login (username) do usuário no Moodle', VALUE_REQUIRED),
            'courseid' => new external_value(PARAM_INT, 'Curso opcional para redirecionamento após o login', VALUE_OPTIONAL, 0),
        ]);
    }

    /** @return array{token:string,loginurl:string} */
    public static function execute(string $username, int $courseid = 0): array
    {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), ['username' => $username, 'courseid' => $courseid]);
        require_capability('local/mundointer:manage', context_system::instance());
        if (!get_config('local_mundointer', 'ssoenabled')) {
            throw new \moodle_exception('O login automático está desativado nesta instalação.', 'local_mundointer');
        }
        $user = $DB->get_record('user', ['username' => $params['username'], 'deleted' => 0, 'suspended' => 0], 'id', MUST_EXIST);
        $token = bin2hex(random_bytes(24));
        $cache = cache::make('local_mundointer', 'sso_sessions');
        $cache->set(hash('sha256', $token), [
            'userid' => (int) $user->id,
            'courseid' => max(0, (int) $params['courseid']),
            'expiresat' => time() + 120,
        ]);
        return [
            'token' => $token,
            'loginurl' => (new moodle_url('/local/mundointer/sso_login.php', ['token' => $token]))->out(false),
        ];
    }

    public static function execute_returns(): external_single_structure
    {
        return new external_single_structure([
            'token' => new external_value(PARAM_ALPHANUM, 'Token de uso único'),
            'loginurl' => new external_value(PARAM_URL, 'Endereço para autenticação automática'),
        ]);
    }
}