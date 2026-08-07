<?php

declare(strict_types=1);

use Interferencia\Kernel\Config\Config;
use Interferencia\Kernel\Database\Connection;
use Interferencia\Kernel\Database\MigrationRepository;
use Interferencia\Kernel\Environment\Environment;
use Interferencia\Kernel\Http\Request;
use Interferencia\Kernel\Http\Response;
use Interferencia\Kernel\Http\Router;
use Interferencia\Kernel\Http\Middleware;
use Interferencia\Kernel\Log\JsonLogger;
use Interferencia\Kernel\Security\Csrf;
use Interferencia\Kernel\Session\Session;
use Interferencia\Kernel\Validation\Validator;
use Interferencia\Modules\Identity\PasswordHasher;
use Interferencia\Modules\Finance\AsaasClient;
use Interferencia\Modules\Finance\WebhookVerifier as AsaasWebhookVerifier;
use Interferencia\Kernel\Security\SecretCipher;
use Interferencia\Modules\Organization\OrganizationRepository;

$rootPath = dirname(__DIR__);
$autoload = $rootPath . '/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "Autoload ausente. Execute: composer dump-autoload\n");
    exit(1);
}

require $autoload;

$tests = [];

$tests['carrega a fundação multiempresa com resolução segura por domínio'] = static function () use ($rootPath): void {
    $repository = new MigrationRepository($rootPath.'/database/migrations');
    assertTrue(in_array('20260806_740000_create_organization_foundation', array_map(static fn($migration): string => $migration->id(), $repository->all()), true));
    assertSame('painel.mundointer.com.br', OrganizationRepository::normalizeHost('PAINEL.MUNDOINTER.COM.BR:443'));
    assertSame('painel.mundointer.com.br', OrganizationRepository::normalizeHost('painel.mundointer.com.br.'));
    assertSame(null, OrganizationRepository::normalizeHost('domínio inválido/empresa'));
    assertTrue(is_file($rootPath.'/modules/Organization/OrganizationContext.php'));
    $bootstrap=(string)file_get_contents($rootPath.'/bootstrap/app.php');
    assertTrue(str_contains($bootstrap, 'findActiveByHost'));
    assertTrue(str_contains($bootstrap, 'new UnitRepository($database, $organizationId)'));
    $units=(string)file_get_contents($rootPath.'/modules/Organization/UnitRepository.php');
    assertTrue(str_contains($units, 'organization_id'));
    assertTrue(in_array('20260806_750000_scope_crm_by_organization', array_map(static fn($migration): string => $migration->id(), $repository->all()), true));
    assertTrue(in_array('20260806_780000_add_organization_panel_identity', array_map(static fn($migration): string => $migration->id(), $repository->all()), true));
    assertSame('franquia-tijucas', OrganizationRepository::normalizeSlug('Franquia-Tijucas'));
    assertSame(null, OrganizationRepository::normalizeSlug('admin'));
    assertSame(null, OrganizationRepository::normalizeSlug('franquia/invalida'));
    assertTrue(str_contains($bootstrap, 'findActiveByPanelSlug'));
    $crm=(string)file_get_contents($rootPath.'/modules/Crm/ContactRepository.php');
    assertTrue(str_contains($crm, 'private int $organizationId'));
};

$tests['mantém integração financeira segura por padrão'] = static function (): void {
    assertTrue(!(new AsaasClient('sandbox',''))->ready());
    assertTrue(!(new AsaasClient('production','$aact_hmlg_incorreta'))->ready());
    assertTrue(!(new AsaasClient('sandbox','$aact_hmlg_teste'))->paymentsWriteEnabled());
    $verifier=new AsaasWebhookVerifier(str_repeat('a',32));
    assertTrue($verifier->ready());
    assertTrue($verifier->valid(str_repeat('a',32)));
    assertTrue(!$verifier->valid(str_repeat('b',32)));
};

$tests['criptografa segredos financeiros quando a chave-mestra está disponível'] = static function (): void {
    $cipher=new SecretCipher(base64_encode(str_repeat('k',32)));
    assertTrue($cipher->ready());
    $encrypted=$cipher->encrypt('$aact_hmlg_teste');
    assertTrue($encrypted!=='$aact_hmlg_teste');
    assertSame('$aact_hmlg_teste',$cipher->decrypt($encrypted));
};

$tests['carrega migração de sincronização financeira em lotes'] = static function () use ($rootPath): void {
    $repository=new MigrationRepository($rootPath.'/database/migrations');
    assertTrue(in_array('20260804_450000_create_finance_sync_cursors',array_map(static fn($migration):string=>$migration->id(),$repository->all()),true));
};

$tests['carrega serviços de conciliação financeira'] = static function () use ($rootPath): void {
    assertTrue(is_file($rootPath.'/modules/Finance/FinanceRepository.php'));
    assertTrue(is_file($rootPath.'/views/finance/customers/index.php'));
    assertTrue(is_file($rootPath.'/views/finance/customers/show.php'));
    assertTrue(is_file($rootPath.'/views/finance/payments/pix.php'));
    assertTrue(is_file($rootPath.'/views/finance/payments/edit.php'));
    assertTrue(is_file($rootPath.'/views/finance/payments/index.php'));
    $financeNavigation = file_get_contents($rootPath.'/views/layouts/app.php').file_get_contents($rootPath.'/views/layouts/navigation.php');
    $paymentList = file_get_contents($rootPath.'/views/finance/payments/index.php');
    $customerList = file_get_contents($rootPath.'/views/finance/customers/index.php');
    assertTrue(is_string($financeNavigation) && str_contains($financeNavigation, '>ALUNOS</span>'));
    assertTrue(is_string($financeNavigation) && str_contains($financeNavigation, '>Financeiro</a>'));
    assertTrue(is_string($financeNavigation) && str_contains($financeNavigation, '>Cadastro</a>'));
    assertTrue(is_string($financeNavigation) && str_contains($financeNavigation, 'select:not([multiple])'));
    assertTrue(str_contains((string) file_get_contents($rootPath.'/bootstrap/app.php'), "'crm_manage' => \$auth->can('crm.contacts.manage')"));
    assertTrue(is_string($financeNavigation) && str_contains($financeNavigation, '>Matrículas</a>'));
    assertTrue(is_string($customerList) && str_contains($customerList, 'finance-section-tabs'));
    assertTrue(is_string($customerList) && str_contains($customerList, '/finance/payments'));
    assertTrue(is_string($paymentList) && str_contains($paymentList, '<th>Unidade</th><th>Ações</th>'));
    assertTrue(is_string($customerList) && str_contains($customerList, 'Editar cliente'));
    assertTrue(is_string($customerList) && str_contains($customerList, 'Cobrança <i class="fa-solid fa-plus"'));
    $customerDetail = file_get_contents($rootPath.'/views/finance/customers/show.php');
    assertTrue(is_string($customerDetail) && str_contains($customerDetail, 'finance-reconcile-card'));
    assertTrue(is_string($customerDetail) && str_contains($customerDetail, 'finance-history-card'));
    assertTrue(is_string($customerDetail) && !str_contains($customerDetail, 'ID no Asaas'));
    assertTrue(is_string($customerDetail) && str_contains($customerDetail, 'finance-document-actions'));
    $issueMigration = file_get_contents($rootPath.'/database/migrations/20260804_510000_add_finance_payment_issue_permission.php');
    assertTrue(is_string($issueMigration) && str_contains($issueMigration, 'finance.payments.issue'));
    assertTrue(is_string($issueMigration) && str_contains($issueMigration, 'finance.payments.modify'));
    assertTrue(is_string($issueMigration) && str_contains($issueMigration, "r.code IN('super_admin','headquarters')"));
    $webRoutes = file_get_contents($rootPath.'/routes/web.php');
    assertTrue(is_string($webRoutes) && str_contains($webRoutes, "RequirePermission(\$auth,'finance.payments.modify')"));
    assertTrue(is_string($webRoutes) && str_contains($webRoutes, "RequirePermission(\$auth,'finance.payments.issue')"));
    assertTrue(is_string($webRoutes) && str_contains($webRoutes, "['PIX','BOLETO','CREDIT_CARD']"));
    $contactFormView = file_get_contents($rootPath.'/views/crm/contacts/form.php');
    assertTrue(is_string($contactFormView) && str_contains($contactFormView, 'name="unit_id"'));
    assertTrue(is_string($contactFormView) && str_contains($contactFormView, 'data-contact-responsible'));
    $headquartersMigration = file_get_contents($rootPath.'/database/migrations/20260804_500000_add_headquarters_role_and_customer_delete.php');
    assertTrue(is_string($headquartersMigration) && str_contains($headquartersMigration, "'headquarters','Sede'"));
    assertTrue(is_string($headquartersMigration) && str_contains($headquartersMigration, 'finance.customers.delete'));
    assertTrue(is_string($headquartersMigration) && !str_contains($headquartersMigration, "r.code='headquarters' AND p.code IN('users.manage'"));
    $roleRenameMigration = file_get_contents($rootPath.'/database/migrations/20260804_520000_rename_system_roles.php');
    assertTrue(is_string($roleRenameMigration) && str_contains($roleRenameMigration, "name='Admin System'"));
    assertTrue(is_string($roleRenameMigration) && str_contains($roleRenameMigration, "name='Sede'"));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/views/finance/payments/form.php'),'installment_count'));
    assertTrue(is_file($rootPath.'/views/finance/subscriptions/index.php'));
    assertTrue(is_file($rootPath.'/views/finance/subscriptions/form.php'));
};

$tests['carrega diagnóstico idempotente do webhook financeiro'] = static function () use ($rootPath): void {
    $repository=new MigrationRepository($rootPath.'/database/migrations');
    assertTrue(in_array('20260804_460000_add_finance_webhook_diagnostics',array_map(static fn($migration):string=>$migration->id(),$repository->all()),true));
};

$tests['carrega estrutura de assinaturas financeiras'] = static function () use ($rootPath): void {
    $repository=new MigrationRepository($rootPath.'/database/migrations');
    assertTrue(in_array('20260804_470000_create_finance_subscriptions',array_map(static fn($migration):string=>$migration->id(),$repository->all()),true));
    $synchronizer=(string)file_get_contents($rootPath.'/modules/Finance/AsaasSynchronizer.php');
    assertTrue(str_contains($synchronizer,'listSubscriptions'));
    assertTrue(str_contains($synchronizer,"syncCursor('subscriptions')"));
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    assertTrue(str_contains($routes,"payload['subscription']"));
};

$tests['carrega ambiente sem sobrescrever valores existentes'] = static function (): void {
    $suffix = strtoupper(bin2hex(random_bytes(4)));
    $first = 'TEST_FIRST_' . $suffix;
    $second = 'TEST_SECOND_' . $suffix;
    $path = tempnam(sys_get_temp_dir(), 'env_');

    assertTrue($path !== false, 'Não foi possível criar arquivo temporário.');
    file_put_contents($path, sprintf("%s=arquivo\n%s=\"valor com espaço\"\n", $first, $second));
    putenv($first . '=sistema');

    try {
        Environment::load($path);
        assertSame('sistema', getenv($first));
        assertSame('valor com espaço', getenv($second));
    } finally {
        @unlink($path);
        putenv($first);
        putenv($second);
        unset($_ENV[$first], $_ENV[$second], $_SERVER[$first], $_SERVER[$second]);
    }
};

$tests['consulta configuração com notação por pontos'] = static function (): void {
    $config = new Config(['app' => ['name' => 'Painel', 'debug' => false, 'root_path' => '/', 'nested_path' => '/painel/']]);

    assertSame('Painel', $config->string('app.name'));
    assertSame(false, $config->bool('app.debug'));
    assertSame('', $config->path('app.root_path'));
    assertSame('/painel', $config->path('app.nested_path'));
    assertSame('padrão', $config->get('app.ausente', 'padrão'));
};

$tests['grava JSON e remove segredos do contexto'] = static function (): void {
    $path = tempnam(sys_get_temp_dir(), 'log_');
    assertTrue($path !== false, 'Não foi possível criar arquivo temporário.');

    try {
        $logger = new JsonLogger($path, 'debug');
        $logger->log('info', 'Teste', [
            'user' => 'teste',
            'password' => 'não-pode-aparecer',
            'nested' => ['access_token' => 'também-não'],
        ]);

        $record = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        assertSame('Teste', $record['message'] ?? null);
        assertSame('teste', $record['context']['user'] ?? null);
        assertSame('[REDACTED]', $record['context']['password'] ?? null);
        assertSame('[REDACTED]', $record['context']['nested']['access_token'] ?? null);
    } finally {
        @unlink($path);
    }
};

$tests['roteia dentro do prefixo e captura parâmetros restritos'] = static function (): void {
    $router = new Router('/painel');
    $router->get(
        '/unidades/{id:\d+}',
        static fn (Request $request, array $parameters): Response => Response::text($parameters['id']),
    );

    $response = $router->dispatch(new Request('GET', '/painel/unidades/42/'));
    assertSame(200, $response->status());
    assertSame('42', $response->body());

    assertSame(404, $router->dispatch(new Request('GET', '/painel/unidades/abc'))->status());
    assertSame(404, $router->dispatch(new Request('GET', '/outro/unidades/42'))->status());
};

$tests['diferencia método não permitido de rota inexistente'] = static function (): void {
    $router = new Router('/painel');
    $router->get('/status', static fn (): Response => Response::text('ok'));

    $notAllowed = $router->dispatch(new Request('POST', '/painel/status'));
    assertSame(405, $notAllowed->status());
    assertSame('GET, HEAD', $notAllowed->header('Allow'));
    assertSame(404, $router->dispatch(new Request('GET', '/painel/ausente'))->status());
};

$tests['aceita HEAD em rota GET'] = static function (): void {
    $router = new Router('/painel');
    $router->get('/status', static fn (): Response => Response::text('ok'));

    assertSame(200, $router->dispatch(new Request('HEAD', '/painel/status'))->status());
};

$tests['monta DSN MariaDB sem incluir credenciais'] = static function (): void {
    $dsn = Connection::dsn('127.0.0.1', 3306, 'painel_inter', 'utf8mb4');

    assertSame('mysql:host=127.0.0.1;port=3306;dbname=painel_inter;charset=utf8mb4', $dsn);
    assertTrue(!str_contains($dsn, 'password'), 'O DSN não deve conter senha.');
};

$tests['descobre e ordena migrações por identificador'] = static function (): void {
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'migrations_' . bin2hex(random_bytes(6));
    assertTrue(mkdir($directory, 0700), 'Não foi possível criar diretório temporário.');

    $migrationTemplate = <<<'PHP'
<?php
return new class ('%s') implements \Interferencia\Kernel\Database\Migration {
    public function __construct(private readonly string $migrationId) {}
    public function id(): string { return $this->migrationId; }
    public function up(\PDO $database): void {}
    public function down(\PDO $database): void {}
};
PHP;

    $files = [
        $directory . DIRECTORY_SEPARATOR . '20260731_200000_segunda.php' => '20260731_200000_segunda',
        $directory . DIRECTORY_SEPARATOR . '20260731_190000_primeira.php' => '20260731_190000_primeira',
    ];

    try {
        foreach ($files as $file => $id) {
            file_put_contents($file, sprintf($migrationTemplate, $id));
        }

        $migrations = (new MigrationRepository($directory))->all();
        assertSame(
            ['20260731_190000_primeira', '20260731_200000_segunda'],
            array_keys($migrations),
        );
    } finally {
        foreach (array_keys($files) as $file) {
            @unlink($file);
        }
        @rmdir($directory);
    }
};

$tests['mantém flash por uma requisição completa'] = static function (): void {
    $_SESSION = [];
    $session = testSession();
    $session->flash('success', 'Salvo');

    $session->ageFlash();
    assertSame('Salvo', $session->get('success'));

    $session->ageFlash();
    assertSame(null, $session->get('success'));
};

$tests['gera valida e rotaciona token CSRF'] = static function (): void {
    $_SESSION = [];
    $csrf = new Csrf(testSession());
    $token = $csrf->token();

    assertSame(64, strlen($token));
    assertTrue($csrf->validate($token), 'Token recém-gerado deve ser válido.');
    assertTrue($csrf->validateRequest(new Request('GET', '/painel/status')), 'GET não exige token.');
    assertTrue($csrf->validateRequest(new Request('POST', '/painel', [], [], '', ['_token' => $token])));
    assertTrue(!$csrf->validateRequest(new Request('POST', '/painel', [], [], '', ['_token' => 'inválido'])));

    $newToken = $csrf->rotate();
    assertTrue($newToken !== $token, 'A rotação deve trocar o token.');
    assertTrue(!$csrf->validate($token), 'Token anterior deve ser invalidado.');
};

$tests['roteador protege mutações com CSRF por padrão'] = static function (): void {
    $_SESSION = [];
    $csrf = new Csrf(testSession());
    $token = $csrf->token();
    $router = new Router('/painel', $csrf);
    $router->post('/salvar', static fn (): Response => Response::text('salvo'));

    assertSame(419, $router->dispatch(new Request('POST', '/painel/salvar'))->status());
    assertSame(200, $router->dispatch(new Request(
        'POST',
        '/painel/salvar',
        [],
        [],
        '',
        ['_token' => $token],
    ))->status());

    $router->postWithoutCsrf('/integracao', static fn (): Response => Response::json(['ok' => true]));
    assertSame(200, $router->dispatch(new Request('POST', '/painel/integracao'))->status());
};

$tests['valida campos e retorna somente valores declarados'] = static function (): void {
    $validator = new Validator();
    $result = $validator->validate([
        'name' => 'Maria',
        'email' => 'maria@example.com',
        'password' => 'segredo-forte',
        'password_confirmation' => 'segredo-forte',
        'admin' => '1',
    ], [
        'name' => 'required|string|min:3|max:80',
        'email' => 'required|email|max:255',
        'password' => 'required|string|min:12|confirmed',
    ]);

    assertTrue($result->passes(), 'Dados válidos não devem gerar erros.');
    assertSame(['name', 'email', 'password'], array_keys($result->values()));
    assertSame(null, $result->value('admin'));
};

$tests['informa erros de validação por campo'] = static function (): void {
    $result = (new Validator())->validate([
        'email' => 'email-inválido',
        'role' => 'root',
    ], [
        'name' => 'required|string',
        'email' => 'required|email',
        'role' => 'required|in:admin,atendente',
    ], ['name' => 'nome', 'email' => 'e-mail', 'role' => 'perfil']);

    assertTrue($result->fails(), 'Dados inválidos devem falhar.');
    assertTrue($result->firstError('name') !== null, 'Nome deve possuir erro.');
    assertTrue($result->firstError('email') !== null, 'E-mail deve possuir erro.');
    assertTrue($result->firstError('role') !== null, 'Perfil deve possuir erro.');
};

$tests['carrega migrações de identidade, acesso e unidades'] = static function () use ($rootPath): void {
    $migrations = (new MigrationRepository($rootPath . '/database/migrations'))->all();

    assertTrue(isset($migrations['20260731_210000_create_identity_and_access']));
    assertTrue(isset($migrations['20260731_220000_grant_dashboard_to_operational_roles']));
    assertTrue(isset($migrations['20260731_230000_rename_tijucas_units']));
    assertTrue(isset($migrations['20260731_240000_add_units_management_permission']));
    assertTrue(isset($migrations['20260731_250000_create_crm_contacts']));
    assertTrue(isset($migrations['20260731_260000_add_external_form_fields']));
    assertTrue(isset($migrations['20260731_270000_create_crm_tags']));
    assertTrue(isset($migrations['20260731_280000_add_crm_status_management']));
    assertTrue(isset($migrations['20260731_290000_create_crm_follow_ups']));
    assertTrue(isset($migrations['20260803_300000_create_external_forms']));
    assertTrue(isset($migrations['20260803_310000_create_crm_contact_events']));
    assertTrue(isset($migrations['20260803_320000_create_whatsapp_lines']));
    assertTrue(isset($migrations['20260803_330000_create_whatsapp_messaging']));
    assertTrue(isset($migrations['20260803_340000_prepare_whatsapp_inbox']));
    assertTrue(isset($migrations['20260803_350000_add_whatsapp_simulation_flag']));
    assertTrue(isset($migrations['20260803_360000_add_whatsapp_assignment_permission']));
    assertTrue(isset($migrations['20260803_370000_enable_whatsapp_crm_intake']));
    assertTrue(isset($migrations['20260803_380000_add_whatsapp_delivery_diagnostics']));
    assertTrue(isset($migrations['20260803_390000_create_whatsapp_templates']));
    assertTrue(isset($migrations['20260803_400000_add_whatsapp_attachments']));
    assertTrue(isset($migrations['20260803_410000_add_whatsapp_media_queue']));
    assertTrue(isset($migrations['20260803_420000_add_whatsapp_template_delivery']));
};

$tests['carrega serviços administrativos'] = static function (): void {
    assertTrue(class_exists(Interferencia\Modules\Identity\RoleManager::class));
    assertTrue(class_exists(Interferencia\Modules\Identity\RoleRepository::class));
    assertTrue(class_exists(Interferencia\Modules\Organization\UnitManager::class));
    assertTrue(class_exists(Interferencia\Modules\Organization\UnitRepository::class));
    assertTrue(class_exists(Interferencia\Modules\Organization\UnitContext::class));
    assertTrue(class_exists(Interferencia\Modules\Crm\ContactRepository::class));
    assertTrue(method_exists(Interferencia\Modules\Crm\ContactRepository::class, 'newContactsDashboard'));
    assertTrue(class_exists(Interferencia\Modules\Crm\ContactManager::class));
    assertTrue(class_exists(Interferencia\Modules\Crm\ExternalContactIntake::class));
    assertTrue(class_exists(Interferencia\Modules\Crm\TagRepository::class));
    assertTrue(class_exists(Interferencia\Modules\Crm\StatusRepository::class));
    assertTrue(class_exists(Interferencia\Modules\Crm\FollowUpRepository::class));
    assertTrue(class_exists(Interferencia\Modules\Crm\ExternalFormRepository::class));
    assertTrue(class_exists(Interferencia\Modules\WhatsApp\LineRepository::class));
    assertTrue(class_exists(Interferencia\Modules\WhatsApp\MessageRepository::class));
    assertTrue(class_exists(Interferencia\Modules\WhatsApp\WebhookVerifier::class));
    assertTrue(class_exists(Interferencia\Modules\WhatsApp\CloudApiClient::class));
    assertTrue(class_exists(Interferencia\Modules\WhatsApp\TemplateRepository::class));
};

$tests['mantém envio oficial bloqueado por padrão'] = static function (): void {
    $client = new Interferencia\Modules\WhatsApp\CloudApiClient('token', 'v23.0', false);
    assertTrue(!$client->ready());
    assertSame(function_exists('curl_init'), $client->canReceiveMedia());
    assertTrue(!(new Interferencia\Modules\WhatsApp\CloudApiClient('', 'v23.0', false))->canReceiveMedia());
    try {
        $client->sendTemplate('123456', '5548999999999', 'modelo_teste', 'pt_BR', ['Contato']);
        throw new RuntimeException('O envio de modelo deveria permanecer bloqueado.');
    } catch (RuntimeException $exception) {
        assertTrue(str_contains($exception->getMessage(), 'bloqueado'));
    }
};

$tests['carrega suporte seguro a anexos do WhatsApp'] = static function () use ($rootPath): void {
    assertTrue(class_exists(Interferencia\Kernel\Http\UploadedFile::class));
    assertTrue(class_exists(Interferencia\Modules\WhatsApp\MediaStorage::class));
    $migration = require $rootPath . '/database/migrations/20260803_400000_add_whatsapp_attachments.php';
    assertSame('20260803_400000_add_whatsapp_attachments', $migration->id());
    $queueMigration = require $rootPath . '/database/migrations/20260803_410000_add_whatsapp_media_queue.php';
    assertSame('20260803_410000_add_whatsapp_media_queue', $queueMigration->id());
    $templateDeliveryMigration = require $rootPath . '/database/migrations/20260803_420000_add_whatsapp_template_delivery.php';
    assertSame('20260803_420000_add_whatsapp_template_delivery', $templateDeliveryMigration->id());
};

$tests['valida webhook oficial do WhatsApp'] = static function (): void {
    $verifier = new Interferencia\Modules\WhatsApp\WebhookVerifier('token-verificacao', 'segredo-app');
    assertSame('12345', $verifier->challenge('subscribe', 'token-verificacao', '12345'));
    assertSame(null, $verifier->challenge('subscribe', 'incorreto', '12345'));
    $body = '{"object":"whatsapp_business_account"}';
    $signature = 'sha256=' . hash_hmac('sha256', $body, 'segredo-app');
    assertTrue($verifier->validSignature($body, $signature));
    assertTrue(!$verifier->validSignature($body, 'sha256=incorreta'));
};

$tests['gera e verifica senha com Argon2id'] = static function (): void {
    $hasher = new PasswordHasher(['memory_cost' => 8192, 'time_cost' => 1, 'threads' => 1]);
    $hash = $hasher->hash('uma-senha-de-teste-segura');

    assertTrue(str_starts_with($hash, '$argon2id$'));
    assertTrue($hasher->verify('uma-senha-de-teste-segura', $hash));
    assertTrue(!$hasher->verify('senha-incorreta', $hash));
};

$tests['executa middleware antes do controlador'] = static function (): void {
    $state = new class {
        /** @var list<string> */
        public array $events = [];
    };
    $middleware = new class ($state) implements Middleware {
        public function __construct(private object $state) {}
        public function handle(Request $request, Closure $next): Response
        {
            $this->state->events[] = 'middleware';
            return $next($request);
        }
    };
    $router = new Router('/painel');
    $router->get('/', static function () use ($state): Response {
        $state->events[] = 'controlador';
        return Response::text('ok');
    }, [$middleware]);

    assertSame(200, $router->dispatch(new Request('GET', '/painel'))->status());
    assertSame(['middleware', 'controlador'], $state->events);
};

$tests['cria redirecionamento HTTP'] = static function (): void {
    $response = Response::redirect('/painel/login');

    assertSame(302, $response->status());
    assertSame('/painel/login', $response->header('Location'));
};

$tests['carrega catálogo e checkout financeiro'] = static function (): void {
    $root = dirname(__DIR__);
    $migration = file_get_contents($root.'/database/migrations/20260804_480000_create_finance_products_and_checkouts.php');
    $client = file_get_contents($root.'/modules/Finance/AsaasClient.php');
    $routes = file_get_contents($root.'/routes/web.php');
    assertTrue(is_string($migration) && str_contains($migration, 'finance_products') && str_contains($migration, 'finance_checkouts'));
    assertTrue(is_string($client) && str_contains($client, "'/checkouts'"));
    assertTrue(is_string($routes) && str_contains($routes, '/admin/finance/products') && str_contains($routes, '/checkouts/create'));
};

$tests['permite atualizar cliente financeiro no Asaas'] = static function (): void {
    $root = dirname(__DIR__);
    $client = file_get_contents($root.'/modules/Finance/AsaasClient.php');
    $routes = file_get_contents($root.'/routes/web.php');
    assertTrue(is_string($client) && str_contains($client, 'function updateCustomer') && str_contains($client, "'/customers/'"));
    assertTrue(is_string($routes) && str_contains($routes, '/finance/customers/{id:\\d+}/edit'));
    assertTrue(is_file($root.'/views/finance/customers/edit.php'));
    assertTrue(is_file($root.'/database/migrations/20260804_490000_add_finance_customer_address.php'));
};

$tests['carrega módulo de tickets internos'] = static function () use ($rootPath): void {
    $migration=file_get_contents($rootPath.'/database/migrations/20260804_530000_create_internal_tickets.php');
    $routes=file_get_contents($rootPath.'/routes/web.php');
    $layout=file_get_contents($rootPath.'/views/layouts/app.php').file_get_contents($rootPath.'/views/layouts/navigation.php');
    assertTrue(is_string($migration) && str_contains($migration,'CREATE TABLE tickets'));
    assertTrue(is_string($migration) && str_contains($migration,"'tickets.manage'"));
    assertTrue(is_file($rootPath.'/database/migrations/20260804_540000_link_tickets_to_crm_contacts.php'));
    assertTrue(is_file($rootPath.'/modules/Tickets/TicketRepository.php'));
    assertTrue(is_file($rootPath.'/views/tickets/index.php'));
    assertTrue(is_file($rootPath.'/views/tickets/form.php'));
    assertTrue(is_file($rootPath.'/views/tickets/show.php'));
    assertTrue(is_string($routes) && str_contains($routes,"'/tickets/{id:\\d+}'"));
    assertTrue(is_string($layout) && str_contains($layout,'>Tickets'));
    assertTrue(is_string($layout) && str_contains($layout,'ticketAlerts'));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/views/tickets/form.php'),'Aluno vinculado'));
    assertTrue(is_file($rootPath.'/database/migrations/20260804_550000_create_ticket_departments_and_attachments.php'));
    assertTrue(is_file($rootPath.'/modules/Tickets/DepartmentRepository.php'));
    assertTrue(is_file($rootPath.'/views/tickets/departments/index.php'));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/views/tickets/form.php'),'data-ticket-contact-results'));
    assertTrue(is_file($rootPath.'/database/migrations/20260805_560000_distinguish_students_from_leads.php'));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/views/tickets/form.php'),'data-ticket-students'));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/public/assets/js/app.js'),"document.querySelectorAll('[data-ticket-students]')"));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/views/layouts/navigation.php'),'>Setores</a>'));
};

$tests['separa cadastro de leads e alunos'] = static function () use ($rootPath): void {
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $layout=(string)file_get_contents($rootPath.'/views/layouts/app.php').file_get_contents($rootPath.'/views/layouts/navigation.php');
    $asaas=(string)file_get_contents($rootPath.'/modules/Finance/AsaasClient.php');
    $leadForm=(string)file_get_contents($rootPath.'/views/crm/contacts/form.php');
    $contactManager=(string)file_get_contents($rootPath.'/modules/Crm/ContactManager.php');
    $financeRepository=(string)file_get_contents($rootPath.'/modules/Finance/FinanceRepository.php');
    assertTrue(str_contains($routes,"'/finance/customers/create'"));
    assertTrue(str_contains($routes,'createCustomer(['));
    assertTrue(str_contains($layout,'/finance/customers'));
    assertTrue(str_contains($layout,'/students/enrollments'));
    assertTrue(str_contains($layout,'>Leads</a>'));
    assertTrue(str_contains($asaas,'function createCustomer'));
    assertTrue(str_contains($leadForm,'(opcional para Lead)'));
    assertTrue(str_contains($contactManager,'documentConflict'));
    assertTrue(str_contains($financeRepository,'function customerByDocument'));
    assertTrue(str_contains($routes,'markEnrolled'));
    assertTrue(is_file($rootPath.'/views/finance/customers/create.php'));
};

$tests['carrega integração Moodle com liberação assistida'] = static function () use ($rootPath): void {
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $bootstrap=(string)file_get_contents($rootPath.'/bootstrap/app.php');
    $layout=(string)file_get_contents($rootPath.'/views/layouts/app.php').file_get_contents($rootPath.'/views/layouts/navigation.php');
    $client=(string)file_get_contents($rootPath.'/modules/Moodle/MoodleClient.php');
    assertTrue(is_file($rootPath.'/database/migrations/20260805_570000_create_moodle_integration.php'));
    assertTrue(is_file($rootPath.'/views/moodle/settings.php'));
    assertTrue(str_contains($routes,"'/admin/integrations/moodle'"));
    assertTrue(str_contains($routes,'syncBatch'));
    assertTrue(str_contains($bootstrap,'MoodleSynchronizer'));
    assertTrue(str_contains($layout,'>Integrações</a>'));
    assertTrue(is_file($rootPath.'/views/admin/integrations.php'));
    assertTrue(str_contains($client,'core_webservice_get_site_info'));
    assertTrue(str_contains($client,'core_enrol_get_enrolled_users'));
    assertTrue(str_contains($client,'core_user_create_users'));
    assertTrue(str_contains($client,'core_user_update_users'));
    assertTrue(str_contains($client,'enrol_manual_enrol_users'));
    assertTrue(str_contains($client,"'Função Moodle: '") && str_contains($client,"'errorcode'"));
    assertTrue(!str_contains((string)file_get_contents($rootPath.'/modules/Moodle/EnrollmentRepository.php'),'reconciled_by'));
    assertTrue(str_contains($routes,'release-ava'));
    assertTrue(is_file($rootPath.'/modules/Moodle/AvaEnrollmentReleaser.php'));
    assertTrue(str_contains($routes,'$avaEnrollmentReleaser->release($confirmedEnrollment)'));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/modules/Moodle/EnrollmentRepository.php'),'avaNotificationSummary'));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/modules/Moodle/AvaEnrollmentReleaser.php'),'manual_flow'));
    assertTrue(is_file($rootPath.'/modules/Moodle/AvaAccessNotifier.php'));
    assertTrue(str_contains($routes,'$avaAccessNotifier->notify($confirmedEnrollment)'));
    assertTrue(str_contains($routes,"'/students/pedagogical'"));
    assertTrue(is_file($rootPath.'/views/moodle/pedagogical.php'));
    assertTrue(!str_contains($routes,'static function(array$params)'));
    assertTrue(is_file($rootPath.'/database/migrations/20260806_670000_add_ava_release_to_enrollments.php'));
    assertTrue(str_contains($routes,"'/admin/ava'"));
    assertTrue(is_file($rootPath.'/database/migrations/20260806_680000_add_payment_waiver_to_enrollments.php'));
    assertTrue(is_file($rootPath.'/database/migrations/20260806_690000_create_moodle_unit_mappings.php'));
    assertTrue(str_contains($routes,"'/admin/ava/unit-mappings'"));
    assertTrue(is_file($rootPath.'/database/migrations/20260806_700000_add_ava_password_policy.php'));
    assertTrue(str_contains($routes,"'/admin/ava/password-policy'"));
    assertTrue(str_contains($routes,'substr($document,0,5)'));
    assertTrue(is_file($rootPath.'/database/migrations/20260806_710000_create_ava_access_communications.php'));
    assertTrue(is_file($rootPath.'/views/moodle/enrollments/access.php'));
    assertTrue(str_contains($routes,"'/students/enrollments/{id:\\d+}/access'"));
};

$tests['carrega conciliação segura de alunos do Moodle'] = static function () use ($rootPath): void {
    assertTrue(is_file($rootPath.'/database/migrations/20260805_580000_add_moodle_reconciliation.php'));
    assertTrue(is_file($rootPath.'/views/moodle/reconciliation.php'));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/modules/Moodle/MoodleRepository.php'),'reconcileAutomatically'));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/routes/web.php'),"'/students/moodle-reconciliation'"));
};

$tests['organiza campos complementares do Moodle'] = static function () use ($rootPath): void {
    assertTrue(is_file($rootPath.'/database/migrations/20260805_590000_create_moodle_profile_fields.php'));
    $repository=(string)file_get_contents($rootPath.'/modules/Moodle/MoodleRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $settings=(string)file_get_contents($rootPath.'/views/moodle/settings.php');
    $customer=(string)file_get_contents($rootPath.'/views/finance/customers/show.php');
    assertTrue(str_contains($repository,'syncProfileFields'));
    assertTrue(str_contains($repository,'academicProfileForCustomer'));
    assertTrue(str_contains($repository,'rebuildProfileFieldsFromStoredUsers'));
    assertTrue(str_contains($routes,"'/admin/integrations/moodle/profile-fields'"));
    assertTrue(str_contains($settings,'Campos complementares'));
    assertTrue(str_contains($customer,'Dados acadêmicos'));
};

$tests['carrega personalização visual das organizações'] = static function () use ($rootPath): void {
    $repository = file_get_contents($rootPath . '/modules/Organization/OrganizationRepository.php');
    $storage = file_get_contents($rootPath . '/modules/Organization/OrganizationBrandingStorage.php');
    $form = file_get_contents($rootPath . '/views/admin/organizations/form.php');
    assertTrue(is_string($repository) && str_contains($repository, 'primary_color') && str_contains($repository, 'login_welcome_text'));
    assertTrue(is_string($storage) && str_contains($storage, "['image/png' => 'png'"));
    assertTrue(is_string($form) && str_contains($form, 'enctype="multipart/form-data"') && str_contains($form, 'name="favicon"'));
};

$tests['organiza a administração central da rede'] = static function () use ($rootPath): void {
    $navigation=(string)file_get_contents($rootPath.'/views/layouts/navigation.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $tickets=(string)file_get_contents($rootPath.'/modules/Tickets/TicketRepository.php');
    assertTrue(str_contains($navigation,'>FRANQUIAS</span>'));
    assertTrue(str_contains($navigation,'/admin/tickets'));
    assertTrue(str_contains($navigation,'/admin/platform/branding'));
    assertTrue(str_contains($routes,"'/admin/tickets'"));
    assertTrue(str_contains($tickets,'function centralAll'));
    assertTrue(is_file($rootPath.'/views/admin/platform/tickets.php'));
    assertTrue(is_file($rootPath.'/views/admin/platform/branding.php'));
    $franchiseIndex=(string)file_get_contents($rootPath.'/views/admin/organizations/index.php');
    $franchiseForm=(string)file_get_contents($rootPath.'/views/admin/organizations/form.php');
    assertTrue(str_contains($franchiseIndex,'<h1>Franquias</h1>'));
    assertTrue(str_contains($franchiseForm,"'Nova franquia'"));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/views/layouts/app.php'),'> ADM Central</span>'));
};

$tests['personaliza a identidade do ADM Central'] = static function () use ($rootPath): void {
    $bootstrap=(string)file_get_contents($rootPath.'/bootstrap/app.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $form=(string)file_get_contents($rootPath.'/views/admin/platform/branding.php');
    assertTrue(is_file($rootPath.'/database/migrations/20260806_790000_create_platform_settings.php'));
    assertTrue(is_file($rootPath.'/database/migrations/20260806_800000_activate_new_mundo_inter_brand.php'));
    assertTrue(is_file($rootPath.'/modules/Organization/PlatformSettingsRepository.php'));
    assertTrue(is_file($rootPath.'/modules/Organization/PlatformBrandingStorage.php'));
    assertTrue(str_contains($bootstrap,'PlatformSettingsRepository'));
    assertTrue(str_contains($bootstrap,"'brandPrimaryColor'"));
    assertTrue(str_contains($routes,"post('/admin/platform/branding'"));
    assertTrue(str_contains($form,'enctype="multipart/form-data"'));
    assertTrue(str_contains($form,'name="login_welcome_text"'));
};

$tests['cadastra franquia com dados empresariais mínimos'] = static function () use ($rootPath): void {
    $repository=(string)file_get_contents($rootPath.'/modules/Organization/OrganizationRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $form=(string)file_get_contents($rootPath.'/views/admin/organizations/form.php');
    assertTrue(is_file($rootPath.'/database/migrations/20260806_810000_add_franchise_company_and_contacts.php'));
    assertTrue(str_contains($repository,'validCnpj'));
    assertTrue(str_contains($repository,"'manager_email'"));
    assertTrue(str_contains($routes,"'general_manager_name'"));
    assertTrue(str_contains($form,'name="cnpj"'));
    assertTrue(str_contains($form,'name="manager_name"'));
    assertTrue(str_contains($form,'name="manager_email"'));
    assertTrue(str_contains($form,'name="manager_phone"'));
    assertTrue(!str_contains($form,'required maxlength="160" name="general_manager_name"'));
};

$tests['prepara fluxo unificado de matrículas'] = static function () use ($rootPath): void {
    assertTrue(is_file($rootPath.'/database/migrations/20260805_600000_create_student_enrollments.php'));
    assertTrue(is_file($rootPath.'/database/migrations/20260805_610000_link_enrollments_to_payments.php'));
    assertTrue(is_file($rootPath.'/modules/Moodle/EnrollmentRepository.php'));
    assertTrue(is_file($rootPath.'/views/moodle/enrollments/index.php'));
    assertTrue(is_file($rootPath.'/views/moodle/enrollments/form.php'));
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $layout=(string)file_get_contents($rootPath.'/views/layouts/app.php').file_get_contents($rootPath.'/views/layouts/navigation.php');
    assertTrue(str_contains($routes,"'/students/enrollments'"));
    assertTrue(str_contains($routes,"'/students/enrollments/{id:\\d+}/charge'"));
    assertTrue(str_contains($layout,'>Matrículas</a>'));
    assertTrue(str_contains($layout,'>Campanhas</a>'));
    assertTrue(is_file($rootPath.'/database/migrations/20260805_620000_create_finance_campaigns.php'));
    assertTrue(is_file($rootPath.'/modules/Finance/CampaignRepository.php'));
    assertTrue(is_file($rootPath.'/database/migrations/20260805_630000_add_max_installments_to_finance_products.php'));
    assertTrue(is_file($rootPath.'/database/migrations/20260805_640000_link_finance_products_to_moodle_courses.php'));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/views/moodle/enrollments/form.php'),'2. Curso contratado'));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/views/moodle/enrollments/form.php'),'5. Atendente'));
    assertTrue(is_file($rootPath.'/database/migrations/20260805_650000_add_attendant_to_student_enrollments.php'));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/views/moodle/enrollments/index.php'),'Excluir matrícula'));
    assertTrue(str_contains($layout,'>Cadastro</a>'));
};

$tests['carrega acompanhamento pedagógico e ações do AVA'] = static function () use ($rootPath): void {
    assertTrue(is_file($rootPath.'/database/migrations/20260806_720000_add_moodle_learning_progress.php'));
    assertTrue(is_file($rootPath.'/database/migrations/20260806_730000_track_moodle_progress_changes.php'));
    assertTrue(is_file($rootPath.'/modules/Moodle/PedagogicalSynchronizer.php'));
    $client=(string)file_get_contents($rootPath.'/modules/Moodle/MoodleClient.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $view=(string)file_get_contents($rootPath.'/views/moodle/pedagogical.php');
    assertTrue(str_contains($client,'core_completion_get_course_completion_status'));
    assertTrue(str_contains($client,'setUserSuspended'));
    assertTrue(str_contains($routes,"'/students/pedagogical/sync'"));
    assertTrue(str_contains($routes,"'/students/enrollments/{id:\\d+}/ava-status'"));
    assertTrue(str_contains($view,'/tickets/create?student='));
    assertTrue(str_contains($view,'/students/enrollments/create?student='));
    assertTrue(str_contains($view,'Sem acesso há 15 dias'));
    assertTrue(str_contains($view,'riskLabels'));
    $console=(string)file_get_contents($rootPath.'/bin/console');
    assertTrue(str_contains($console,"moodle:pedagogical:sync"));
    assertTrue(str_contains($console,'pedagogical-sync.lock'));
    $synchronizer=(string)file_get_contents($rootPath.'/modules/Moodle/PedagogicalSynchronizer.php');
    assertTrue(str_contains($synchronizer,'Código: nocriteriaset'));
    assertTrue(str_contains($view,'Sem critérios no AVA'));
    assertTrue(str_contains($view,'Dados de acesso do aluno'));
    assertTrue(str_contains($view,"fa-key"));
};

$tests['cria captação pública e análise de novas franquias'] = static function () use ($rootPath): void {
    assertTrue(is_file($rootPath.'/database/migrations/20260806_820000_create_franchise_applications.php'));
    assertTrue(is_file($rootPath.'/modules/Organization/FranchiseApplicationRepository.php'));
    assertTrue(is_file($rootPath.'/views/public/franchise-application.php'));
    assertTrue(is_file($rootPath.'/views/admin/franchise-applications/index.php'));
    assertTrue(is_file($rootPath.'/views/admin/franchise-applications/show.php'));
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $navigation=(string)file_get_contents($rootPath.'/views/layouts/navigation.php');
    assertTrue(str_contains($routes,"'/solicitacao-franquia/{token:[a-f0-9]+}'"));
    assertTrue(str_contains($routes,"'/admin/franchise-applications'"));
    assertTrue(str_contains($routes,"'/admin/franchise-applications/{id:\\d+}/approve'"));
    assertTrue(str_contains($navigation,'>Solicitações</a>'));
};

$tests['cria contratos e assinatura digital para franquias'] = static function () use ($rootPath): void {
    assertTrue(is_file($rootPath.'/database/migrations/20260806_830000_create_franchise_contracts.php'));
    assertTrue(is_file($rootPath.'/modules/Organization/FranchiseContractRepository.php'));
    assertTrue(is_file($rootPath.'/views/admin/franchise-contracts/index.php'));
    assertTrue(is_file($rootPath.'/views/admin/franchise-contract-templates/form.php'));
    assertTrue(is_file($rootPath.'/views/public/franchise-contract.php'));
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $navigation=(string)file_get_contents($rootPath.'/views/layouts/navigation.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Organization/FranchiseContractRepository.php');
    assertTrue(str_contains($routes,"'/contrato/{token:[a-f0-9]+}'"));
    assertTrue(str_contains($routes,"'/admin/franchise-contracts'"));
    assertTrue(str_contains($navigation,'>Contratos</a>'));
    assertTrue(str_contains($repository,"evidence_hash"));
    assertTrue(str_contains($repository,"contract_status='signed'"));
};

$tests['integra cobrança de contratos de franquia ao Asaas sem duplicidade'] = static function () use ($rootPath): void {
    assertTrue(is_file($rootPath.'/database/migrations/20260806_840000_add_franchise_contract_billing.php'));
    assertTrue(is_file($rootPath.'/modules/Organization/FranchiseContractBillingService.php'));
    $client=(string)file_get_contents($rootPath.'/modules/Finance/AsaasClient.php');
    $service=(string)file_get_contents($rootPath.'/modules/Organization/FranchiseContractBillingService.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Organization/FranchiseContractRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    assertTrue(str_contains($client,'customersByCpfCnpj'));
    assertTrue(str_contains($service,"mundo-inter:franchise-contract:"));
    assertTrue(str_contains($repository,"status']!=='signed'"));
    assertTrue(str_contains($repository,"billing_issue_state='issuing'"));
    assertTrue(str_contains($routes,"'/admin/franchise-contracts/{id:\\d+}/billing'"));
    assertTrue(str_contains($routes,"'/admin/franchise-contracts/{id:\\d+}/billing/sync'"));
};

$failures = 0;

foreach ($tests as $name => $test) {
    try {
        $test();
        fwrite(STDOUT, "✓ {$name}\n");
    } catch (Throwable $exception) {
        $failures++;
        fwrite(STDERR, "✗ {$name}: {$exception->getMessage()}\n");
    }
}

fwrite(STDOUT, sprintf("\n%d teste(s), %d falha(s).\n", count($tests), $failures));
exit($failures === 0 ? 0 : 1);

function assertSame(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            'Esperado %s, recebido %s.',
            var_export($expected, true),
            var_export($actual, true),
        ));
    }
}

function assertTrue(bool $condition, string $message = 'A condição esperada não foi atendida.'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function testSession(): Session
{
    return new Session(
        'test_session',
        '/painel',
        7200,
        true,
        true,
        'Lax',
        sys_get_temp_dir(),
    );
}
