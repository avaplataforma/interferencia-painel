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
use Interferencia\Kernel\Http\Request;
use Interferencia\Modules\Identity\Auth;
use Interferencia\Modules\Identity\PasswordHasher;
use Interferencia\Modules\Identity\UserRepository;
use Interferencia\Modules\Identity\UserManager;
use Interferencia\Modules\Identity\RoleManager;
use Interferencia\Modules\Identity\RoleRepository;
use Interferencia\Modules\Organization\UnitManager;
use Interferencia\Modules\Organization\UnitRepository;
use Interferencia\Modules\Organization\UnitContext;
use Interferencia\Modules\Organization\OrganizationRepository;
use Interferencia\Modules\Organization\FranchiseApplicationRepository;
use Interferencia\Modules\Organization\FranchiseContractRepository;
use Interferencia\Modules\Organization\FranchiseContractBillingService;
use Interferencia\Modules\Organization\PlatformSettingsRepository;
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
use Interferencia\Modules\Finance\CatalogRepository;
use Interferencia\Modules\Finance\CampaignRepository;
use Interferencia\Modules\Tickets\TicketRepository;
use Interferencia\Modules\Tickets\DepartmentRepository;
use Interferencia\Modules\Moodle\IntegrationRepository as MoodleIntegrationRepository;
use Interferencia\Modules\Moodle\MoodleClient;
use Interferencia\Modules\Moodle\MoodleRepository;
use Interferencia\Modules\Moodle\MoodleSynchronizer;
use Interferencia\Modules\Moodle\EnrollmentRepository;
use Interferencia\Modules\Moodle\AvaEnrollmentReleaser;
use Interferencia\Modules\Moodle\AvaAccessNotifier;
use Interferencia\Modules\Moodle\PedagogicalSynchronizer;

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
    $config->path('app.base_path'),
    $sessionLifetime,
    $config->bool('session.secure'),
    $config->bool('session.http_only'),
    $config->string('session.same_site'),
    $rootPath . '/storage/sessions',
);
$session->start();

$csrf = new Csrf($session);
$database = (new Connection($config))->pdo();
$request = Request::fromGlobals();
$organizations = new OrganizationRepository($database);
$franchiseApplications = new FranchiseApplicationRepository($database);
$franchiseContracts = new FranchiseContractRepository($database);
$platformSettingsRepository = new PlatformSettingsRepository($database);
$platformSettings = $platformSettingsRepository->settings();
$configuredBasePath=$config->path('app.base_path');
$requestPath=$request->path();
$relativePath=$configuredBasePath!==''&&str_starts_with($requestPath,$configuredBasePath.'/')?substr($requestPath,strlen($configuredBasePath)):$requestPath;
$firstSegment=explode('/',trim($relativePath,'/'))[0]??'';
$requestHost=OrganizationRepository::normalizeHost((string)$request->header('host',''));
$centralHost=OrganizationRepository::normalizeHost($config->string('app.central_host'));
$tenantOrganization=$requestHost===$centralHost&&$firstSegment!==''?$organizations->findActiveByPanelSlug($firstSegment):null;
$isCentralContext=$requestHost===$centralHost&&$tenantOrganization===null;
$currentOrganization=$tenantOrganization??($isCentralContext?$organizations->findActiveByCode('interferencia'):$organizations->findActiveByHost((string)$request->header('host','')));
$effectiveBasePath=$configuredBasePath.($tenantOrganization!==null?'/'.$tenantOrganization->panelSlug:'');
$organizationId = $currentOrganization?->id ?? 0;
$users = new UserRepository($database,$isCentralContext?null:$organizationId,$isCentralContext);
$units = new UnitRepository($database, $organizationId);
$roles = new RoleRepository($database,$isCentralContext);
$contacts = new ContactRepository($database,$organizationId);
$tags = new TagRepository($database,$organizationId);
$statuses = new StatusRepository($database,$organizationId);
$followUps = new FollowUpRepository($database);
$externalForms = new ExternalFormRepository($database,$organizationId);
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
$finance = new FinanceRepository($database,$organizationId);
$financeCatalog = new CatalogRepository($database);
$financeCampaigns = new CampaignRepository($database);
$tickets = new TicketRepository($database);
$ticketDepartments = new DepartmentRepository($database);
$ticketFiles = new MediaStorage($rootPath . '/storage/tickets');
$financeIntegrations = new IntegrationRepository($database,new SecretCipher((string)$config->get('app.encryption_key')));
$moodleIntegrations = new MoodleIntegrationRepository($database,new SecretCipher((string)$config->get('app.encryption_key')));
$moodleSettings=$moodleIntegrations->settings();
$moodleClient=new MoodleClient((string)$moodleSettings['base_url'],(string)$moodleSettings['token'],$moodleSettings['is_active']);
$moodleRepository=new MoodleRepository($database);
$studentEnrollments=new EnrollmentRepository($database,$organizationId);
$avaEnrollmentReleaser=new AvaEnrollmentReleaser($moodleClient,$moodleIntegrations,$moodleRepository,$studentEnrollments,(string)$config->get('app.ava_auto_release_from'));
$avaAccessNotifier=new AvaAccessNotifier($studentEnrollments,$moodleIntegrations);
$moodleSynchronizer=new MoodleSynchronizer($moodleClient,$moodleRepository);
$pedagogicalSynchronizer=new PedagogicalSynchronizer($moodleClient,$moodleRepository);
$asaasSettings=$financeIntegrations->asaas();
$asaasEnvironment=$asaasSettings['configured']?(string)$asaasSettings['environment']:(string)$config->get('app.asaas_environment');
$asaasApiKey=$asaasSettings['configured']&&$asaasSettings['is_active']?(string)$asaasSettings['api_key']:(string)$config->get('app.asaas_api_key');
$asaasWebhookToken=$asaasSettings['configured']?(string)$asaasSettings['webhook_token']:(string)$config->get('app.asaas_webhook_token');
$splitLifecycle=static function(string$phase,array$context)use($franchiseContracts,$organizationId):mixed{if($phase==='prepare')return$franchiseContracts->prepareSplit($organizationId,(float)$context['gross'],(string)$context['reference']);$prepared=$context['prepared'];if($phase==='complete'){$franchiseContracts->completeSplit((int)$prepared['attempt_id'],$context['result']);return null;}if($phase==='fail')$franchiseContracts->failSplit((int)$prepared['attempt_id'],(string)$context['error']);return null;};
$asaas = new AsaasClient($asaasEnvironment,$asaasApiKey,$config->bool('app.asaas_payments_write_enabled'),$splitLifecycle);
$franchiseContractBilling = new FranchiseContractBillingService($franchiseContracts,$asaas);
$asaasSynchronizer = new AsaasSynchronizer($asaas,$finance);
$asaasWebhook = new AsaasWebhookVerifier($asaasWebhookToken);
$auth = new Auth($users,new PasswordHasher(),$session,$csrf,$isCentralContext?'platform':'franchise');
$router = new Router($effectiveBasePath, $csrf);
$view = new View($rootPath . '/views');
$unitContext = new UnitContext($auth, $units, $session);
$currentUser = $auth->user();
$alertUnitIds=[];
if($currentUser!==null&&$auth->can('crm.contacts.view')){$alertUnit=$unitContext->current();$alertUnitIds=$alertUnit===null?[]:($alertUnit['id']===null?array_map(static fn(array $item):int=>(int)$item['id'],$unitContext->available()):[(int)$alertUnit['id']]);}
$whatsappAlertLineIds=$currentUser!==null&&$auth->can('whatsapp.inbox.view')?array_map(static fn(array $line):int=>(int)$line['id'],$whatsappLines->authorizedForUser($currentUser->id)):[];
$avaAlerts=$currentUser!==null&&$auth->can('finance.manage')?$studentEnrollments->avaNotificationSummary(array_map(static fn(array$unit):int=>(int)$unit['id'],$unitContext->available())):['ready'=>0,'failed'=>0];
$view->share([
    'basePath' => $effectiveBasePath,
    'assetBasePath' => $configuredBasePath,
    'isCentralContext' => $isCentralContext,
    'brandName' => $isCentralContext ? (string) $platformSettings['display_name'] : ($currentOrganization?->displayName ?? 'MUNDO INTER'),
    'brandLogo' => $isCentralContext ? (string) $platformSettings['logo_path'] : ($currentOrganization?->logoPath ?? '/assets/media/painel-inter.png'),
    'brandFavicon' => $isCentralContext ? (string) $platformSettings['favicon_path'] : ($currentOrganization?->faviconPath ?? '/assets/media/painel-inter-icon.png'),
    'brandPrimaryColor' => $isCentralContext ? (string) $platformSettings['primary_color'] : ($currentOrganization?->primaryColor ?? '#ed1c24'),
    'brandSecondaryColor' => $isCentralContext ? (string) $platformSettings['secondary_color'] : ($currentOrganization?->secondaryColor ?? '#082d72'),
    'brandLoginTitle' => $isCentralContext ? (string) ($platformSettings['login_title'] ?: $platformSettings['display_name']) : ($currentOrganization?->loginTitle ?: ($currentOrganization?->displayName ?? 'MUNDO INTER')),
    'brandWelcomeText' => $isCentralContext ? (string) ($platformSettings['login_welcome_text'] ?: 'Use suas credenciais para continuar.') : ($currentOrganization?->loginWelcomeText ?: 'Use suas credenciais para continuar.'),
    'csrfField' => $csrf->field(),
    'currentUser' => $currentUser,
    'currentOrganization' => $currentOrganization,
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
        'finance_manage' => $auth->can('finance.manage'),
        'finance_issue' => $auth->can('finance.payments.issue'),
        'finance_modify' => $auth->can('finance.payments.modify'),
        'finance_settings' => $auth->can('finance.settings.manage'),
        'finance_products' => $auth->can('finance.settings.manage'),
        'finance_campaigns' => $auth->can('finance.settings.manage'),
        'whatsapp_transfer' => $auth->can('whatsapp.conversations.assign'),
        'crm' => $auth->can('crm.contacts.view'),
        'crm_manage' => $auth->can('crm.contacts.manage'),
        'tickets' => $auth->can('tickets.view'),
        'tickets_create' => $auth->can('tickets.create'),
        'tickets_manage' => $auth->can('tickets.manage'),
        'ticket_departments' => $auth->can('tickets.departments.manage'),
        'moodle_settings' => $auth->can('moodle.settings.manage'),
        'organizations' => $isCentralContext && $auth->isSuperAdmin() && ($currentOrganization?->code === 'interferencia'),
    ],
    'availableUnits' => $currentUser === null ? [] : $unitContext->available(),
    'currentUnit' => $currentUser === null ? null : $unitContext->current(),
    'followUpAlerts' => $currentUser === null || !$auth->can('crm.contacts.view') ? null : $followUps->summary($alertUnitIds,$currentUser->id),
    'whatsappAlerts' => $currentUser === null ? ['unread'=>0,'unassigned'=>0] : $whatsappMessages->notificationSummary($whatsappAlertLineIds),
    'ticketAlerts' => $currentUser === null || !$auth->can('tickets.view') ? ['open'=>0,'unread'=>0,'overdue'=>0] : $tickets->notificationSummary($currentUser->id,array_map(static fn(array $unit):int=>(int)$unit['id'],$unitContext->available())),
    'avaAlerts' => $avaAlerts,
]);
$registerRoutes = require $rootPath . '/routes/web.php';
$registerRoutes($router, $config, $effectiveBasePath, $view, $session, $csrf, new Validator(), $auth, $organizations, $franchiseApplications, $franchiseContracts, $franchiseContractBilling, $platformSettingsRepository, $organizationId, $users, new UserManager($users, new PasswordHasher()), $units, new UnitManager($units), $roles, new RoleManager($roles), $unitContext, $contacts, new ContactManager($contacts,$tags), new ExternalContactIntake($contacts, $config->string('app.external_form_key')), $tags, $statuses, $followUps, $externalForms, $whatsappLines, $whatsappMessages, $whatsappTemplates, $whatsappMedia, $whatsappWebhook, $whatsappCloudApi,$finance,$financeCatalog,$financeCampaigns,$asaas,$asaasSynchronizer,$asaasWebhook,$financeIntegrations,$tickets,$ticketDepartments,$ticketFiles,$moodleIntegrations,$moodleClient,$moodleRepository,$moodleSynchronizer,$pedagogicalSynchronizer,$studentEnrollments,$avaEnrollmentReleaser,$avaAccessNotifier);

return new Application($router, $request);
