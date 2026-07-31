<?php

declare(strict_types=1);

use Interferencia\Kernel\Application;
use Interferencia\Kernel\Config\Config;
use Interferencia\Kernel\Environment\Environment;
use Interferencia\Kernel\Error\ErrorHandler;
use Interferencia\Kernel\Log\JsonLogger;
use Interferencia\Kernel\Http\Router;
use Interferencia\Kernel\View\View;
use Interferencia\Kernel\Session\Session;
use Interferencia\Kernel\Security\Csrf;
use Interferencia\Kernel\Database\Connection;
use Interferencia\Kernel\Validation\Validator;
use Interferencia\Modules\Identity\Auth;
use Interferencia\Modules\Identity\PasswordHasher;
use Interferencia\Modules\Identity\UserRepository;
use Interferencia\Modules\Identity\UserManager;
use Interferencia\Modules\Identity\RoleManager;
use Interferencia\Modules\Identity\RoleRepository;
use Interferencia\Modules\Organization\UnitManager;
use Interferencia\Modules\Organization\UnitRepository;
use Interferencia\Modules\Organization\UnitContext;
use Interferencia\Modules\Crm\ContactManager;
use Interferencia\Modules\Crm\ContactRepository;
use Interferencia\Modules\Crm\ExternalContactIntake;

$rootPath = dirname(__DIR__);
$autoload = $rootPath . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Aplicação indisponível: dependências não instaladas.\n";
    exit(1);
}

require $autoload;

Environment::load($rootPath . '/.env');

$config = Config::fromDirectory($rootPath . '/config');
$timezone = $config->string('app.timezone');

if (!date_default_timezone_set($timezone)) {
    throw new RuntimeException(sprintf('Fuso horário inválido: %s', $timezone));
}

$logger = new JsonLogger(
    $rootPath . '/storage/logs/' . $config->string('app.log_channel') . '.log',
    $config->string('app.log_level'),
);

$errorHandler = new ErrorHandler($logger, $config->bool('app.debug'));
$errorHandler->register();

$sessionLifetime = $config->get('session.lifetime');

if (!is_int($sessionLifetime)) {
    throw new RuntimeException('Tempo de sessão inválido.');
}

$session = new Session(
    $config->string('session.name'),
    $config->string('app.base_path'),
    $sessionLifetime,
    $config->bool('session.secure'),
    $config->bool('session.http_only'),
    $config->string('session.same_site'),
    $rootPath . '/storage/sessions',
);
$session->start();

$csrf = new Csrf($session);
$database = (new Connection($config))->pdo();
$users = new UserRepository($database);
$units = new UnitRepository($database);
$roles = new RoleRepository($database);
$contacts = new ContactRepository($database);
$auth = new Auth($users, new PasswordHasher(), $session, $csrf);
$router = new Router($config->string('app.base_path'), $csrf);
$view = new View($rootPath . '/views');
$unitContext = new UnitContext($auth, $units, $session);
$currentUser = $auth->user();
$view->share([
    'basePath' => $config->string('app.base_path'),
    'csrfField' => $csrf->field(),
    'currentUser' => $currentUser,
    'navigation' => [
        'users' => $auth->can('users.manage'),
        'units' => $auth->can('units.manage'),
        'roles' => $auth->can('roles.manage'),
        'crm' => $auth->can('crm.contacts.view'),
    ],
    'availableUnits' => $currentUser === null ? [] : $unitContext->available(),
    'currentUnit' => $currentUser === null ? null : $unitContext->current(),
]);
$registerRoutes = require $rootPath . '/routes/web.php';
$registerRoutes($router, $config, $view, $session, $csrf, new Validator(), $auth, $users, new UserManager($users, new PasswordHasher()), $units, new UnitManager($units), $roles, new RoleManager($roles), $unitContext, $contacts, new ContactManager($contacts), new ExternalContactIntake($contacts, $config->string('app.external_form_key')));

return new Application($router);
