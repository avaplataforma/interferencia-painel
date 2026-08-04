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
use Interferencia\Kernel\Security\SecretCipher;
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
use Interferencia\Modules\Crm\TagRepository;
use Interferencia\Modules\Crm\StatusRepository;
use Interferencia\Modules\Crm\FollowUpRepository;
use Interferencia\Modules\Crm\ExternalFormRepository;
use Interferencia\Modules\WhatsApp\LineRepository;
use Interferencia\Modules\WhatsApp\MessageRepository;
use Interferencia\Modules\WhatsApp\WebhookVerifier;
use Interferencia\Modules\WhatsApp\CloudApiClient;
use Interferencia\Modules\WhatsApp\TemplateRepository;
use Interferencia\Modules\WhatsApp\MediaStorage;
use Interferencia\Modules\Finance\AsaasClient;
use Interferencia\Modules\Finance\AsaasSynchronizer;
use Interferencia\Modules\Finance\FinanceRepository;
use Interferencia\Modules\Finance\WebhookVerifier as AsaasWebhookVerifier;
use Interferencia\Modules\Finance\IntegrationRepository;

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
$tags = new TagRepository($database);
$statuses = new StatusRepository($database);
$followUps = new FollowUpRepository($database);
$externalForms = new ExternalFormRepository($database);
$whatsappLines = new LineRepository($database);
$whatsappMessages = new MessageRepository($database);
$whatsappTemplates = new TemplateRepository($database);
$whatsappMedia = new MediaStorage($rootPath . '/storage/whatsapp/media');
$whatsappVerifyToken = $config->get('app.whatsapp_verify_token');
$whatsappAppSecret = $config->get('app.whatsapp_app_secret');
$whatsappWebhook = new WebhookVerifier(is_string($whatsappVerifyToken) ? $whatsappVerifyToken : '', is_string($whatsappAppSecret) ? $whatsappAppSecret : '');
$whatsappCloudApi = new CloudApiClient(
    (string) $config->get('app.whatsapp_access_token'),
    (string) $config->get('app.whatsapp_graph_version'),
    $config->bool('app.whatsapp_send_enabled'),
);
$finance = new FinanceRepository($database);
$financeIntegrations = new IntegrationRepository($database,new SecretCipher((string)$config->get('app.encryption_key')));
$asaasSettings=$financeIntegrations->asaas();
$asaasEnvironment=$asaasSettings['configured']?(string)$asaasSettings['environment']:(string)$config->get('app.asaas_environment');
$asaasApiKey=$asaasSettings['configured']&&$asaasSettings['is_active']?(string)$asaasSettings['api_key']:(string)$config->get('app.asaas_api_key');
$asaasWebhookToken=$asaasSettings['configured']?(string)$asaasSettings['webhook_token']:(string)$config->get('app.asaas_webhook_token');
$asaas = new AsaasClient($asaasEnvironment,$asaasApiKey);
$asaasSynchronizer = new AsaasSynchronizer($asaas,$finance);
$asaasWebhook = new AsaasWebhookVerifier($asaasWebhookToken);
$auth = new Auth($users, new PasswordHasher(), $session, $csrf);
$router = new Router($config->string('app.base_path'), $csrf);
$view = new View($rootPath . '/views');
$unitContext = new UnitContext($auth, $units, $session);
$currentUser = $auth->user();
$alertUnitIds=[];
if($currentUser!==null&&$auth->can('crm.contacts.view')){$alertUnit=$unitContext->current();$alertUnitIds=$alertUnit===null?[]:($alertUnit['id']===null?array_map(static fn(array $item):int=>(int)$item['id'],$unitContext->available()):[(int)$alertUnit['id']]);}
$whatsappAlertLineIds=$currentUser!==null&&$auth->can('whatsapp.inbox.view')?array_map(static fn(array $line):int=>(int)$line['id'],$whatsappLines->authorizedForUser($currentUser->id)):[];
$view->share([
    'basePath' => $config->string('app.base_path'),
    'csrfField' => $csrf->field(),
    'currentUser' => $currentUser,
    'navigation' => [
        'users' => $auth->can('users.manage'),
        'units' => $auth->can('units.manage'),
        'roles' => $auth->can('roles.manage'),
        'tags' => $auth->can('crm.tags.manage'),
        'statuses' => $auth->can('crm.statuses.manage'),
        'external_forms' => $auth->can('external_forms.manage'),
        'whatsapp_lines' => $auth->can('whatsapp.lines.manage'),
        'whatsapp_templates' => $auth->can('whatsapp.lines.manage'),
        'whatsapp' => $auth->can('whatsapp.inbox.view'),
        'finance' => $auth->can('finance.view'),
        'finance_settings' => $auth->can('finance.settings.manage'),
        'whatsapp_transfer' => $auth->can('whatsapp.conversations.assign'),
        'crm' => $auth->can('crm.contacts.view'),
    ],
    'availableUnits' => $currentUser === null ? [] : $unitContext->available(),
    'currentUnit' => $currentUser === null ? null : $unitContext->current(),
    'followUpAlerts' => $currentUser === null || !$auth->can('crm.contacts.view') ? null : $followUps->summary($alertUnitIds,$currentUser->id),
    'whatsappAlerts' => $currentUser === null ? ['unread'=>0,'unassigned'=>0] : $whatsappMessages->notificationSummary($whatsappAlertLineIds),
]);
$registerRoutes = require $rootPath . '/routes/web.php';
$registerRoutes($router, $config, $view, $session, $csrf, new Validator(), $auth, $users, new UserManager($users, new PasswordHasher()), $units, new UnitManager($units), $roles, new RoleManager($roles), $unitContext, $contacts, new ContactManager($contacts,$tags), new ExternalContactIntake($contacts, $config->string('app.external_form_key')), $tags, $statuses, $followUps, $externalForms, $whatsappLines, $whatsappMessages, $whatsappTemplates, $whatsappMedia, $whatsappWebhook, $whatsappCloudApi,$finance,$asaas,$asaasSynchronizer,$asaasWebhook,$financeIntegrations);

return new Application($router);
