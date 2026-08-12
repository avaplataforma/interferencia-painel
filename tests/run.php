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
$tests['carrega fluxo financeiro Sandbox por franquia']=static function()use($rootPath):void{
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260807_910000_create_franchise_sandbox_billing_tests.php');
    $service=(string)file_get_contents($rootPath.'/modules/Organization/FranchiseSandboxBillingService.php');
    $view=(string)file_get_contents($rootPath.'/views/admin/platform/asaas-sandbox.php');
    assertTrue(str_contains($migration,'franchise_sandbox_billing_tests'));
    assertTrue(str_contains($service,"new \\DateTimeImmutable('tomorrow')"));
    assertTrue(str_contains($view,'Teste financeiro por franquia'));
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    assertTrue(str_contains($routes,"str_starts_with(\$reference,'mundo-inter:sandbox:franchise-test:')"));
};
$tests['vincula franquias ao histórico contratual']=static function()use($rootPath):void{
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260807_920000_link_organizations_to_contract_history.php');
    $view=(string)file_get_contents($rootPath.'/views/admin/organizations/contracts.php');
    $list=(string)file_get_contents($rootPath.'/views/admin/organizations/index.php');
    assertTrue(str_contains($migration,'WHERE NOT EXISTS'));
    assertTrue(str_contains($view,'Histórico contratual'));
    assertTrue(str_contains($list,'Abrir ficha da franquia'));
    assertTrue(str_contains($list,'Login exclusivo'));
    $overview=(string)file_get_contents($rootPath.'/views/admin/organizations/overview.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    assertTrue(str_contains($overview,'Implantação da franquia'));
    assertTrue(str_contains($overview,'fa-file-signature'));
    assertTrue(str_contains($overview,'Wallet Asaas e split'));
    assertTrue(str_contains($overview,'Ativar operação comercial'));
    assertTrue(str_contains($overview,'Gerar link mensal'));
    assertTrue(str_contains($routes,"'/admin/organizations/{id:\\d+}/finance-inline'"));
    $contractDetail=(string)file_get_contents($rootPath.'/views/admin/franchise-contracts/show.php');
    assertTrue(str_contains($contractDetail,'Configurar Wallet'));
    assertTrue(str_contains($contractDetail,'Processamento financeiro'));
    assertTrue(str_contains($contractDetail,'Assinatura e evidências'));
    assertTrue(str_contains($contractDetail,'Conteúdo completo do contrato'));
};

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
    $avaEnrollmentReleaser=(string)file_get_contents($rootPath.'/modules/Moodle/AvaEnrollmentReleaser.php');
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
    assertTrue(str_contains($routes,"'/students/enrollments/waivers'"));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/views/moodle/enrollments/index.php'),'Liberação especial'));
    assertTrue(is_file($rootPath.'/database/migrations/20260806_680000_add_payment_waiver_to_enrollments.php'));
    assertTrue(is_file($rootPath.'/database/migrations/20260806_690000_create_moodle_unit_mappings.php'));
    assertTrue(!str_contains($layout,'href="<?= $escape($basePath) ?>/units"'));
    assertTrue(is_file($rootPath.'/database/migrations/20260806_700000_add_ava_password_policy.php'));
    assertTrue(str_contains($routes,"'/admin/platform/integrations/ava-cursos/access-policy'"));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/views/admin/platform/ava-cursos.php'),'Acesso do aluno'));
    assertTrue(!str_contains($layout,'href="<?= $escape($basePath) ?>/admin/ava"'));
    assertTrue(str_contains($avaEnrollmentReleaser,'substr($document, 0, 5)'));
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
    $site = file_get_contents($rootPath . '/views/site/admin.php');
    $routes = file_get_contents($rootPath . '/routes/web.php');
    assertTrue(is_string($repository) && str_contains($repository, 'primary_color') && str_contains($repository, 'login_welcome_text'));
    assertTrue(is_string($storage) && str_contains($storage, "['image/png' => 'png'"));
    assertTrue(is_string($form) && !str_contains($form, 'name="favicon"') && str_contains($form, 'Geral e identidade'));
    assertTrue(is_string($site) && str_contains($site, 'enctype="multipart/form-data"') && str_contains($site, 'name="logo"') && str_contains($site, 'name="favicon"'));
    assertTrue(is_string($routes) && str_contains($routes, '$organizationBranding->store($organizationId,$logo'));
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
    assertTrue(str_contains($franchiseForm,"'Cadastrar franquia'"));
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
    $applications=(string)file_get_contents($rootPath.'/views/admin/franchise-applications/index.php');
    $applicationDetail=(string)file_get_contents($rootPath.'/views/admin/franchise-applications/show.php');
    assertTrue(str_contains($applicationDetail,'application-data-grid'));
    assertTrue(str_contains($applicationDetail,'application-contracts-header'));
    $organizationForm=(string)file_get_contents($rootPath.'/views/admin/organizations/form.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Organization/FranchiseApplicationRepository.php');
    assertTrue(str_contains($routes,"'/cadastro-franquia'"));
    assertTrue(str_contains($routes,"'/solicitacao-franquia/{token:[a-f0-9]+}'"));
    assertTrue(str_contains($routes,"'/admin/franchise-applications'"));
    assertTrue(str_contains($routes,"'/admin/franchise-applications/{id:\\d+}/approve'"));
    assertTrue(str_contains($routes,"'/admin/franchise-applications/{id:\\d+}/delete'"));
    assertTrue(str_contains($applications,'Link permanente'));
    assertTrue(str_contains($applications,'Ticket automático'));
    assertTrue(str_contains($applications,'table-cell-stack'));
    assertTrue(str_contains($applications,'Excluir solicitação'));
    assertTrue(str_contains($repository,'submitNew'));
    assertTrue(str_contains($repository,'public function delete'));
    assertTrue(str_contains($repository,'platform_tickets'));
    assertTrue(!str_contains($organizationForm,'Financeiro Asaas'));
    assertTrue(str_contains($navigation,'>Solicitações</a>'));
};

$tests['cria contratos e assinatura digital para franquias'] = static function () use ($rootPath): void {
    assertTrue(is_file($rootPath.'/database/migrations/20260806_830000_create_franchise_contracts.php'));
    assertTrue(is_file($rootPath.'/modules/Organization/FranchiseContractRepository.php'));
    assertTrue(is_file($rootPath.'/modules/Organization/ContractContent.php'));
    assertTrue(is_file($rootPath.'/views/admin/franchise-contracts/index.php'));
    assertTrue(is_file($rootPath.'/views/admin/franchise-contract-templates/form.php'));
    assertTrue(is_file($rootPath.'/views/admin/franchise-contract-templates/compare.php'));
    assertTrue(is_file($rootPath.'/database/migrations/20260807_930000_version_and_cancel_franchise_contracts.php'));
    assertTrue(is_file($rootPath.'/views/public/franchise-contract.php'));
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $navigation=(string)file_get_contents($rootPath.'/views/layouts/navigation.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Organization/FranchiseContractRepository.php');
    $templateForm=(string)file_get_contents($rootPath.'/views/admin/franchise-contract-templates/form.php');
    $publicContract=(string)file_get_contents($rootPath.'/views/public/franchise-contract.php');
    $javascript=(string)file_get_contents($rootPath.'/public/assets/js/app.js');
    assertTrue(str_contains($routes,"'/contrato/{token:[a-f0-9]+}'"));
    assertTrue(str_contains($routes,"'/admin/franchise-contracts'"));
    assertTrue(str_contains($navigation,'>Contratos</a>'));
    assertTrue(str_contains($repository,"evidence_hash"));
    assertTrue(str_contains($repository,"contract_status='signed'"));
    assertTrue(str_contains($repository,'removeTemplate'));
    assertTrue(str_contains($repository,'duplicateTemplate'));
    assertTrue(str_contains($repository,'templateVersions'));
    assertTrue(str_contains($repository,'public function cancel'));
    assertTrue(str_contains($routes,"'/admin/franchise-contract-templates/{id:\\d+}/delete'"));
    assertTrue(str_contains($routes,"'/admin/franchise-contract-templates/{id:\\d+}/duplicate'"));
    assertTrue(str_contains($routes,"'/admin/franchise-contract-templates/{id:\\d+}/compare'"));
    assertTrue(str_contains($routes,"'/admin/franchise-contracts/{id:\\d+}/cancel'"));
    assertTrue(str_contains($templateForm,'data-contract-rich-editor'));
    $viewEngine=(string)file_get_contents($rootPath.'/kernel/View/View.php');
    assertTrue(str_contains($viewEngine,'capture(string $templateName'));
    assertTrue(str_contains($javascript,'data-contract-editor'));
    assertTrue(str_contains($publicContract,'ContractContent::toHtml'));
    assertTrue(str_contains($publicContract,'data-print-page'));
    assertTrue(str_contains($publicContract,'@page{size:A4'));
    assertTrue(str_contains($javascript,"closest('[data-print-page]')"));
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

$tests['separa usuários centrais dos usuários das franquias'] = static function () use ($rootPath): void {
    assertTrue(is_file($rootPath.'/database/migrations/20260806_850000_separate_platform_identity.php'));
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260806_850000_separate_platform_identity.php');
    $users=(string)file_get_contents($rootPath.'/modules/Identity/UserRepository.php');
    $auth=(string)file_get_contents($rootPath.'/modules/Identity/Auth.php');
    $bootstrap=(string)file_get_contents($rootPath.'/bootstrap/app.php');
    assertTrue(str_contains($migration,'CREATE TABLE platform_users'));
    assertTrue(str_contains($migration,'CREATE TABLE platform_roles'));
    assertTrue(str_contains($users,"platform_users"));
    assertTrue(str_contains($auth,"auth.realm"));
    assertTrue(str_contains($bootstrap,"?'platform':'franchise'"));
};

$tests['mantém histórico contratual e modelos comerciais das franquias'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260806_860000_expand_franchise_contract_lifecycle.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Organization/FranchiseContractRepository.php');
    $asaas=(string)file_get_contents($rootPath.'/modules/Finance/AsaasClient.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    assertTrue(str_contains($migration,'parent_contract_id'));
    assertTrue(str_contains($migration,'commercial_model'));
    assertTrue(str_contains($repository,"'fixed_monthly','hybrid'"));
    assertTrue(str_contains($asaas,"'/paymentLinks'"));
    assertTrue(str_contains($routes,"'/admin/franchise-contracts/{id:\\d+}/recurring-link'"));
};

$tests['separa regra comercial do processamento financeiro da franquia'] = static function () use ($rootPath): void {
    $migrationPath=$rootPath.'/database/migrations/20260809_994000_separate_franchise_commercial_processing.php';
    assertTrue(is_file($migrationPath));
    $migration=(string)file_get_contents($migrationPath);
    $repository=(string)file_get_contents($rootPath.'/modules/Organization/FranchiseContractRepository.php');
    $implementation=(string)file_get_contents($rootPath.'/modules/Organization/FranchiseImplementation.php');
    $form=(string)file_get_contents($rootPath.'/views/admin/franchise-contracts/form.php');
    $show=(string)file_get_contents($rootPath.'/views/admin/franchise-contracts/show.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    assertTrue(str_contains($migration,'commercial_rule'));
    assertTrue(str_contains($migration,'financial_processing'));
    assertTrue(str_contains($migration,'franchise_fee_percentage'));
    assertTrue(str_contains($migration,'fixed_fee_per_enrollment'));
    assertTrue(str_contains($repository,'central_monthly_settlement'));
    assertTrue(str_contains($repository,'central_automatic_split'));
    assertTrue(str_contains($repository,'franchise_asaas'));
    assertTrue(str_contains($implementation,"\$financialProcessing === 'franchise_asaas'"));
    assertTrue(str_contains($form,'Regra comercial'));
    assertTrue(str_contains($form,'Processamento financeiro'));
    assertTrue(str_contains($form,'contract-business-grid'));
    assertTrue(str_contains($form,'contract-action-bar'));
    assertTrue(str_contains($form,'Histórico preservado'));
    assertTrue(str_contains($show,'Divisão por venda'));
    assertTrue(str_contains($routes,"'commercial_rule'=>"));
    assertTrue(str_contains($routes,"'financial_processing'=>"));
};

$tests['separa perfis centrais e perfis das franquias com matriz própria'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260807_870000_add_franchise_finance_and_role_matrix.php');
    assertTrue(str_contains($migration,'platform_general_manager'));
    assertTrue(str_contains($migration,"WHEN 'platform_agent' THEN 'Atendente'"));
    assertTrue(str_contains($migration,"WHEN 'headquarters' THEN 'Gestor'"));
    assertTrue(str_contains($migration,"WHEN 'manager' THEN 'Gerente'"));
};

$tests['configura wallet e split por franquia no ADM Central'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260807_870000_add_franchise_finance_and_role_matrix.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Organization/OrganizationRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    assertTrue(str_contains($migration,'asaas_wallet_id'));
    assertTrue(str_contains($migration,'split_enabled'));
    assertTrue(str_contains($repository,'saveFinanceSettings'));
    assertTrue(str_contains($routes,"'/admin/organizations/{id:\\d+}/finance'"));
    assertTrue(is_file($rootPath.'/views/admin/organizations/finance.php'));
};

$tests['organiza integrações exclusivas do ADM Central'] = static function () use ($rootPath): void {
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $navigation=(string)file_get_contents($rootPath.'/views/layouts/navigation.php');
    assertTrue(str_contains($routes,"'/admin/platform/integrations'"));
    assertTrue(str_contains($routes,"'/admin/platform/integrations/asaas'"));
    assertTrue(str_contains($navigation,'/admin/platform/integrations'));
    assertTrue(is_file($rootPath.'/views/admin/platform/integrations.php'));
    assertTrue(is_file($rootPath.'/views/admin/platform/asaas.php'));
};

$tests['controla regras comerciais e financeiro das franquias'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260807_880000_create_franchise_billing_control.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Organization/FranchiseContractRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $navigation=(string)file_get_contents($rootPath.'/views/layouts/navigation.php');
    assertTrue(str_contains($migration,'franchise_billing_events'));
    assertTrue(str_contains($migration,'commercial_flow_status'));
    assertTrue(str_contains($repository,'activateCommercialFlow'));
    assertTrue(str_contains($repository,'billingDashboard'));
    assertTrue(str_contains($repository,'billingAlerts'));
    assertTrue(str_contains($routes,"'/admin/franchise-billing'"));
    assertTrue(str_contains($routes,'activate-commercial-flow'));
    assertTrue(str_contains($navigation,'/admin/franchise-billing'));
    assertTrue(is_file($rootPath.'/views/admin/franchise-billing/index.php'));
    $dashboard=(string)file_get_contents($rootPath.'/views/admin/franchise-billing/index.php');
    assertTrue(str_contains($dashboard,'Financeiro das franquias'));
    assertTrue(str_contains($dashboard,'Histórico de splits e repasses'));
    assertTrue(str_contains($dashboard,'Comissão Mundo Inter'));
    assertTrue(str_contains($dashboard,'franchise-finance-toolbar'));
    assertTrue(str_contains($dashboard,'franchise-finance-metrics'));
    assertTrue(str_contains($dashboard,'.franchise-finance-page{width:100%;max-width:100%;min-width:0}'));
    assertTrue(!str_contains($dashboard,'transform:translateX(-50%)'));
    assertTrue(str_contains($dashboard,'Período inicial'));
    $layout=(string)file_get_contents($rootPath.'/views/layouts/app.php');
    assertTrue(str_contains($layout,'Notificações da rede'));
};

$tests['aplica split contratual nas novas cobranças das franquias'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260807_890000_create_franchise_split_attempts.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Organization/FranchiseContractRepository.php');
    $client=(string)file_get_contents($rootPath.'/modules/Finance/AsaasClient.php');
    assertTrue(str_contains($migration,'franchise_split_attempts'));
    assertTrue(str_contains($repository,'prepareSplit'));
    assertTrue(str_contains($repository,'100-$central'));
    assertTrue(str_contains($client,"payload['split']"));
    assertTrue(str_contains($client,"str_starts_with(\$reference,'painel:')"));
};

$tests['separa Asaas Sandbox da conexão de produção'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260807_900000_separate_asaas_environments.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Finance/IntegrationRepository.php');
    $view=(string)file_get_contents($rootPath.'/views/admin/platform/asaas.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    assertTrue(str_contains($migration,'finance_integrations_provider_environment_unique'));
    assertTrue(str_contains($repository,"asaas(string \$environment='production')"));
    assertTrue(str_contains($view,'Webhook do Sandbox'));
    assertTrue(str_contains($view,'$aact_hmlg_'));
    assertTrue(str_contains($view,'Diagnóstico do webhook'));
    assertTrue(str_contains($routes,"'webhookSummary'=>\$finance->webhookSummary()"));
};

$tests['protege a ativação pelo fluxo de implantação da franquia'] = static function () use ($rootPath): void {
    $service=(string)file_get_contents($rootPath.'/modules/Organization/FranchiseImplementation.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Organization/OrganizationRepository.php');
    $view=(string)file_get_contents($rootPath.'/views/admin/organizations/overview.php');
    assertTrue(str_contains($service,'ready_to_activate'));
    assertTrue(str_contains($service,'Cadastro conferido'));
    assertTrue(str_contains($service,'AVA conectado e testado'));
    assertTrue(str_contains($service,'Polo operacional definido'));
    assertTrue(str_contains($service,'Preparação recomendada')||str_contains($view,'Preparação recomendada'));
    assertTrue(str_contains($routes,"'/admin/organizations/{id:\\d+}/activate'"));
    assertTrue(str_contains($routes,"'status'=>'suspended'"));
    assertTrue(str_contains($repository,'implementationFacts'));
    assertTrue(str_contains($repository,'setStatus'));
    assertTrue(str_contains($view,'Implantação da franquia'));
    assertTrue(str_contains($view,'Ativação protegida'));
    assertTrue(str_contains($routes,'ensureImplementationTicket'));
    assertTrue(str_contains($view,'Criar ticket'));
};

$tests['avalia os requisitos obrigatórios da implantação'] = static function (): void {
    $organization=['legal_name'=>'Inter Treinamento','display_name'=>'Inter','cnpj'=>'05095152000139','manager_name'=>'Gestor','manager_email'=>'gestor@example.com','manager_phone'=>'48999999999','panel_slug'=>'inter','logo_path'=>'/logo.png','favicon_path'=>'/favicon.png','asaas_wallet_status'=>'validated','asaas_wallet_id'=>'wallet','split_enabled'=>1,'status'=>'suspended'];
    $domains=[['purpose'=>'site','status'=>'active','is_primary'=>1]];
    $contract=['status'=>'signed','commercial_model'=>'split_only','sales_fee_percentage'=>20,'monthly_fixed_amount'=>0];
    $implementation=\Interferencia\Modules\Organization\FranchiseImplementation::evaluate($organization,$domains,$contract,['active_admins'=>1,'active_ava_integrations'=>1,'active_poles'=>1,'primary_poles'=>1,'finance_account_ready'=>1,'finance_account_mode'=>'central','franchise_documents'=>1]);
    assertSame(9,$implementation['required_done']);
    assertTrue($implementation['ready_to_activate']);
    assertSame(100,$implementation['progress']);
    assertSame(1,$implementation['recommended_done']);
};

$tests['integra DigitalOcean Spaces com isolamento por franquia'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260807_930000_create_spaces_storage.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Storage/SpacesIntegrationRepository.php');
    $client=(string)file_get_contents($rootPath.'/modules/Storage/SpacesClient.php');
    $manager=(string)file_get_contents($rootPath.'/modules/Storage/SpacesStorageManager.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $media=(string)file_get_contents($rootPath.'/modules/WhatsApp/MediaStorage.php');
    $messages=(string)file_get_contents($rootPath.'/modules/WhatsApp/MessageRepository.php');
    assertTrue(str_contains($migration,'object_storage_integrations'));
    assertTrue(str_contains($migration,'object_storage_objects'));
    assertTrue(str_contains($repository,'SecretCipher'));
    assertTrue(str_contains($repository,'strlen($secret)<32'));
    assertTrue(str_contains($repository,'if(!$replaceCredentials)'));
    assertTrue(str_contains($repository,"'https://mundointer.nyc3.digitaloceanspaces.com'"));
    assertTrue(str_contains($repository,"'central_prefix'=>(string)(\$row['central_prefix']??'adm-central')"));
    assertTrue(str_contains($repository,"\$error!==null?',is_active=0'"));
    assertTrue(str_contains($client,'AWS4-HMAC-SHA256'));
    assertTrue(str_contains($client,"x-amz-acl']='private'"));
    assertTrue(str_contains($manager,"CENTRAL_FOLDERS=['Personalizacao'"));
    assertTrue(str_contains($manager,"FRANCHISE_FOLDERS=['Personalizacao','Alunos'"));
    assertTrue(str_contains($manager,"str_pad((string)\$id,6,'0'"));
    assertTrue(str_contains($routes,"'/admin/platform/integrations/digital-ocean'"));
    assertTrue(str_contains($media,"str_starts_with(\$relative,'spaces:')"));
    assertTrue(str_contains($messages,'$storage->forFranchise($organizationId)'));
    assertTrue(is_file($rootPath.'/views/admin/platform/digital-ocean.php'));
};

$tests['gerencia documentos privados com versões e isolamento por franquia'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260807_940000_create_document_management.php');
    $typesMigration=(string)file_get_contents($rootPath.'/database/migrations/20260807_950000_create_document_types.php');
    $manager=(string)file_get_contents($rootPath.'/modules/Storage/DocumentManager.php');
    $types=(string)file_get_contents($rootPath.'/modules/Storage/DocumentTypeRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $view=(string)file_get_contents($rootPath.'/views/admin/organizations/documents.php');
    $form=(string)file_get_contents($rootPath.'/views/admin/organizations/form.php');
    $navigation=(string)file_get_contents($rootPath.'/views/layouts/navigation.php');
    assertTrue(str_contains($migration,'managed_documents'));
    assertTrue(str_contains($migration,'document_group'));
    assertTrue(str_contains($migration,'version_number'));
    assertTrue(str_contains($manager,'MAX_BYTES = 26214400'));
    assertTrue(str_contains($manager,"storeFranchise((int)\$organizationId,'Documentos'"));
    assertTrue(str_contains($manager,'FILEINFO_MIME_TYPE'));
    assertTrue(str_contains($manager,'deleted_at=NOW()'));
    assertTrue(str_contains($typesMigration,'CREATE TABLE document_types'));
    assertTrue(str_contains($typesMigration,"'contrato_social','Contrato Social',1"));
    assertTrue(str_contains($types,'final readonly class DocumentTypeRepository'));
    assertTrue(str_contains($types,"scope='franchise'"));
    assertTrue(str_contains($routes,"'/admin/organizations/{id:\\d+}/documents'"));
    assertTrue(str_contains($routes,"'franchiseDocuments'=>\$documents->all('franchise',\$id)"));
    assertTrue(str_contains($routes,"'/admin/document-types'"));
    assertTrue(str_contains($routes,"'Cache-Control'=>'private, no-store'"));
    assertTrue(str_contains($view,'name="replace_id"'));
    assertTrue(str_contains($view,'data-document-version'));
    assertTrue(str_contains($view,'>Observação<'));
    assertTrue(!str_contains($view,'name="title"'));
    assertTrue(str_contains($form,'data-organization-tab="documentos"'));
    assertTrue(str_contains($form,'data-organization-panel="dados"'));
    assertTrue(!str_contains($view,'Gestão completa'));
    assertTrue(str_contains($navigation,'/admin/document-types'));
};

$tests['centraliza conexoes AVA por franquia sem romper a integracao Moodle atual'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260807_960000_create_central_ava_connections.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Moodle/AvaConnectionRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $form=(string)file_get_contents($rootPath.'/views/admin/organizations/form.php');
    $ava=(string)file_get_contents($rootPath.'/views/admin/organizations/ava.php');
    $navigation=(string)file_get_contents($rootPath.'/views/layouts/navigation.php');
    assertTrue(str_contains($migration,'CREATE TABLE ava_connections'));
    assertTrue(str_contains($migration,'CREATE TABLE organization_ava_settings'));
    assertTrue(str_contains($migration,"'shared:ava-cursos'"));
    assertTrue(str_contains($repository,'final readonly class AvaConnectionRepository'));
    assertTrue(str_contains($repository,"['shared','own','both']"));
    assertTrue(str_contains($repository,'SecretCipher'));
    assertTrue(str_contains($routes,"'/admin/platform/integrations/ava-cursos'"));
    assertTrue(str_contains($routes,"'/admin/platform/painel-inter'"));
    assertTrue(str_contains($routes,"'/admin/organizations/{id:\\d+}/ava'"));
    assertTrue(str_contains($form,'data-organization-tab="ava"'));
    assertTrue(str_contains($ava,'AVA Cursos compartilhado'));
    assertTrue(str_contains($ava,'AVA Cursos + Moodle exclusivo'));
    assertTrue(!str_contains($navigation,'/admin/platform/painel-inter'));
    $integrationHub=(string)file_get_contents($rootPath.'/views/admin/platform/integrations.php');
    assertTrue(str_contains($integrationHub,'/admin/platform/painel-inter'));
    assertTrue(str_contains($integrationHub,'IA - OpenAI'));
    assertTrue(str_contains($integrationHub,'Fornecedores/Catálogos'));
    assertTrue(!str_contains($integrationHub,'>Painel Inter<'));
    assertTrue(substr_count($integrationHub,'integration-card ')===5);
    assertTrue(is_file($rootPath.'/integrations/moodle/local_mundointer/version.php'));
    assertTrue(is_file($rootPath.'/integrations/moodle/local_mundointer/classes/external/ping.php'));
    $services=(string)file_get_contents($rootPath.'/integrations/moodle/local_mundointer/db/services.php');
    assertTrue(str_contains($services,'local_mundointer_ping'));
    assertTrue(str_contains($services,'core_user_create_users'));
};

$tests['direciona cada matricula ao AVA e curso corretos da franquia'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260809_993000_route_enrollments_to_ava_connections.php');
    $connections=(string)file_get_contents($rootPath.'/modules/Moodle/AvaConnectionRepository.php');
    $enrollments=(string)file_get_contents($rootPath.'/modules/Moodle/EnrollmentRepository.php');
    $releaser=(string)file_get_contents($rootPath.'/modules/Moodle/AvaEnrollmentReleaser.php');
    $notifier=(string)file_get_contents($rootPath.'/modules/Moodle/AvaAccessNotifier.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $form=(string)file_get_contents($rootPath.'/views/moodle/enrollments/form.php');
    assertTrue(str_contains($migration,'CREATE TABLE ava_course_mappings'));
    assertTrue(str_contains($migration,'ADD ava_connection_id'));
    assertTrue(str_contains($migration,'ADD ava_course_id'));
    assertTrue(str_contains($connections,'destinationsForCourse'));
    assertTrue(str_contains($connections,'synchronizeCourses'));
    assertTrue(str_contains($enrollments,"connection_type='shared' OR organization_id=:organization"));
    assertTrue(str_contains($enrollments,'student_enrollments(organization_id,finance_customer_id'));
    assertTrue(str_contains($releaser,"new MoodleClient((string)\$connection['base_url']"));
    assertTrue(str_contains($notifier,"\$context['ava_base_url']"));
    assertTrue(str_contains($routes,"'/admin/organizations/{id:\\d+}/ava/sync-courses'"));
    assertTrue(str_contains($form,'data-enrollment-ava'));
};

$tests['organiza cadastro da franquia e comunicacao do AVA'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260809_990000_add_ava_identity_to_organizations.php');
    $form=(string)file_get_contents($rootPath.'/views/admin/organizations/form.php');
    $ava=(string)file_get_contents($rootPath.'/views/admin/organizations/ava.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Organization/OrganizationRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $plugin=(string)file_get_contents($rootPath.'/integrations/moodle/local_mundointer/lib.php');
    $tabs=['dados','contatos','documentos','polos','contrato','painel','ava','site','integracoes'];
    $last=-1;
    foreach($tabs as$tab){$position=strpos($form,'data-organization-tab="'.$tab.'"');assertTrue($position!==false&&$position>$last);$last=$position;}
    assertTrue(!str_contains($form,'data-organization-tab="acesso"'));
    assertTrue(!str_contains($form,'data-organization-tab="endereco"'));
    assertTrue(!str_contains($form,'data-organization-tab="marca"'));
    assertTrue(str_contains($form,'data-organization-panel="painel"'));
    assertTrue(str_contains($form,'data-organization-panel="site"'));
    assertTrue(strpos($form,'name="postal_code"')<strpos($form,'data-organization-panel="contatos"'));
    assertTrue(str_contains($form,'name="panel_slug"'));
    assertTrue(!str_contains($form,'name="login_welcome_text"'));
    assertTrue(!str_contains($form,'name="login_title"'));
    assertTrue(str_contains($migration,'ava_polo_name'));
    assertTrue(str_contains($ava,'name="login_title"'));
    assertTrue(!str_contains($ava,'name="ava_polo_name"'));
    assertTrue(str_contains($ava,'name="login_welcome_text"'));
    assertTrue(str_contains($ava,'name="support_email"'));
    assertTrue(str_contains($ava,'name="support_phone"'));
    assertTrue(str_contains($repository,'updateAvaCommunication'));
    assertTrue(str_contains($repository,'ava_polo_name=:polo_name'));
    assertTrue(str_contains($routes,'updateAvaCommunication'));
    assertTrue(str_contains($plugin,'mundointer-login-support'));
    assertTrue(str_contains($plugin,'data-support-email'));
    assertTrue(str_contains($plugin,'data-support-phone'));
};

$tests['distribui e monitora versoes do plugin Mundo Inter'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260808_970000_create_ava_connection_checks.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Moodle/AvaConnectionRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $view=(string)file_get_contents($rootPath.'/views/admin/platform/painel-inter.php');
    assertTrue(str_contains($migration,'CREATE TABLE ava_connection_checks'));
    assertTrue(str_contains($repository,'recordHealthCheck'));
    assertTrue(str_contains($repository,'recentChecks'));
    assertTrue(str_contains($routes,"'/admin/platform/painel-inter/plugin/download'"));
    assertTrue(str_contains($view,'Baixar ZIP oficial'));
    assertTrue(str_contains($view,'Histórico de verificações'));
    $manager=new \Interferencia\Modules\Moodle\PluginReleaseManager($rootPath.'/integrations/moodle/local_mundointer');
    $metadata=$manager->metadata();
    assertSame('0.5.0',$metadata['release']);
    $package=$manager->package();
    assertTrue(str_starts_with($package['body'],'PK'));
    assertTrue($package['size']>0);
    assertSame(64,strlen($package['sha256']));
    $outdated=$manager->deploymentStatus(['configured'=>true,'is_active'=>true,'last_error'=>null,'last_tested_at'=>date('Y-m-d H:i:s'),'plugin_last_error'=>null,'plugin_status'=>'ok','plugin_version'=>'2026080600','plugin_release'=>'0.0.9']);
    assertSame('outdated',$outdated['code']);
};

$tests['personaliza o AVA compartilhado pela franquia e pelo Polo Presencial'] = static function () use ($rootPath): void {
    assertTrue(is_file($rootPath.'/modules/Moodle/AvaBrandCatalog.php'));
    assertTrue(is_file($rootPath.'/integrations/moodle/local_mundointer/classes/external/sync_brands.php'));
    assertTrue(is_file($rootPath.'/integrations/moodle/local_mundointer/classes/local/brand_resolver.php'));
    assertTrue(is_file($rootPath.'/integrations/moodle/local_mundointer/entrar.php'));
    assertTrue(is_file($rootPath.'/integrations/moodle/local_mundointer/lib.php'));
    $version=(string)file_get_contents($rootPath.'/integrations/moodle/local_mundointer/version.php');
    $services=(string)file_get_contents($rootPath.'/integrations/moodle/local_mundointer/db/services.php');
    $ping=(string)file_get_contents($rootPath.'/integrations/moodle/local_mundointer/classes/external/ping.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $view=(string)file_get_contents($rootPath.'/views/admin/platform/painel-inter.php');
    assertTrue(str_contains($version,"\$plugin->release = '0.5.0'"));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/modules/Moodle/AvaBrandCatalog.php'),'/franquia.php?slug='));
    assertTrue(str_contains($services,'local_mundointer_sync_brands'));
    assertTrue(str_contains($ping,"get_plugin_info('local_mundointer')"));
    assertTrue(str_contains($routes,"'/admin/platform/painel-inter/brands/sync'"));
    assertTrue(str_contains($view,'Identidades do AVA compartilhado'));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/integrations/moodle/local_mundointer/lib.php'),'loginContainer.querySelectorAll("#loginlogo, .login-logo")'));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/integrations/moodle/local_mundointer/lib.php'),'document.title = pageTitle'));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/integrations/moodle/local_mundointer/lib.php'),'https://wa.me/'));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/integrations/moodle/local_mundointer/lib.php'),'mundointer-support-whatsapp-icon'));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/integrations/moodle/local_mundointer/lib.php'),'querySelector("[role=\\"main\\"]")'));
    $resolver=(string)file_get_contents($rootPath.'/integrations/moodle/local_mundointer/classes/local/brand_resolver.php');
    assertTrue(str_contains($resolver,"private const COOKIE_NAME = 'MundoInterBrand'"));
    assertTrue(str_contains($resolver,"'httponly'=>true"));
    assertTrue(str_contains($resolver,"'samesite'=>'Lax'"));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/integrations/moodle/local_mundointer/entrar.php'),'brand_resolver::remember($slug)'));
};

$tests['cadastra múltiplos polos e grava a identidade estável no AVA'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260809_991000_create_organization_poles.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Organization/OrganizationPoleRepository.php');
    $releaser=(string)file_get_contents($rootPath.'/modules/Moodle/AvaEnrollmentReleaser.php');
    $catalog=(string)file_get_contents($rootPath.'/modules/Moodle/AvaBrandCatalog.php');
    $form=(string)file_get_contents($rootPath.'/views/admin/organizations/form.php');
    $poles=(string)file_get_contents($rootPath.'/views/admin/organizations/poles.php');
    $upgrade=(string)file_get_contents($rootPath.'/integrations/moodle/local_mundointer/db/upgrade.php');
    $fields=(string)file_get_contents($rootPath.'/integrations/moodle/local_mundointer/db/field_helpers.php');
    $sync=(string)file_get_contents($rootPath.'/integrations/moodle/local_mundointer/classes/external/sync_brands.php');
    $resolver=(string)file_get_contents($rootPath.'/integrations/moodle/local_mundointer/classes/local/brand_resolver.php');
    assertTrue(str_contains($migration,'CREATE TABLE organization_poles'));
    assertTrue(str_contains($migration,'organization_pole_id'));
    assertTrue(str_contains($repository,"FRANCHISE_FIELD = 'mundointer_franchise'"));
    assertTrue(str_contains($repository,"POLE_FIELD = 'mundointer_pole'"));
    assertTrue(str_contains($releaser,'identityForEnrollment'));
    assertTrue(str_contains($catalog,"'pole_records'"));
    assertTrue(str_contains($catalog,'poleKey'));
    assertTrue(str_contains($catalog,"\$record['legacy_value']=\$value"));
    assertTrue(str_contains($form,'data-organization-tab="polos"'));
    assertTrue(str_contains($poles,'Código permanente'));
    assertTrue(str_contains($upgrade,'local_mundointer_ensure_identity_fields'));
    assertTrue(str_contains($fields,"'mundointer_franchise'=>'Franquia Mundo Inter'"));
    assertTrue(str_contains($fields,"'mundointer_pole'=>'Polo Mundo Inter'"));
    assertTrue(str_contains($sync,'local_mundointer_migrate_profile_identities'));
    assertTrue(str_contains($resolver,'byCode'));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/routes/web.php'),"['migrated']"));
};

$tests['mapeia Polo Presencial para franquias com diagnóstico agregado e seguro'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260808_980000_create_ava_polo_mappings.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Moodle/AvaPoloMappingRepository.php');
    $catalog=(string)file_get_contents($rootPath.'/modules/Moodle/AvaBrandCatalog.php');
    $client=(string)file_get_contents($rootPath.'/modules/Moodle/MoodleClient.php');
    $services=(string)file_get_contents($rootPath.'/integrations/moodle/local_mundointer/db/services.php');
    $diagnostic=(string)file_get_contents($rootPath.'/integrations/moodle/local_mundointer/classes/external/diagnose_poles.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $view=(string)file_get_contents($rootPath.'/views/admin/platform/painel-inter.php');
    assertTrue(str_contains($migration,'CREATE TABLE ava_polo_mappings'));
    assertTrue(str_contains($migration,'CREATE TABLE ava_polo_diagnostics'));
    assertTrue(str_contains($repository,'final readonly class AvaPoloMappingRepository'));
    assertTrue(str_contains($repository,'recordDiagnostic'));
    assertTrue(str_contains($catalog,'ava_polo_mappings'));
    assertTrue(str_contains($client,'poloDiagnostics'));
    assertTrue(str_contains($services,'local_mundointer_diagnose_poles'));
    assertTrue(str_contains($diagnostic,"require_capability('local/mundointer:manage'"));
    assertTrue(!str_contains($diagnostic,'email'));
    assertTrue(!str_contains($diagnostic,'idnumber'));
    assertTrue(str_contains($routes,"'/admin/platform/painel-inter/poles/diagnose'"));
    assertTrue(str_contains($routes,"'/admin/platform/painel-inter/poles/{id:\\d+}/delete'"));
    assertTrue(str_contains($view,'Polo Presencial e franquias'));
    assertTrue(str_contains($view,'Atualizar diagnóstico'));
};

$tests['isola a conta Asaas exclusiva de cada franquia com fallback central seguro'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260809_992000_create_organization_finance_integrations.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Finance/OrganizationIntegrationRepository.php');
    $finance=(string)file_get_contents($rootPath.'/modules/Finance/FinanceRepository.php');
    $bootstrap=(string)file_get_contents($rootPath.'/bootstrap/app.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $form=(string)file_get_contents($rootPath.'/views/admin/organizations/form.php');
    $view=(string)file_get_contents($rootPath.'/views/admin/organizations/integrations.php');
    assertTrue(str_contains($migration,'CREATE TABLE organization_finance_integrations'));
    assertTrue(str_contains($migration,"account_mode IN ('central','exclusive')"));
    assertTrue(str_contains($migration,'finance_webhook_event_org_unique'));
    assertTrue(str_contains($migration,'PRIMARY KEY (organization_id, resource)'));
    assertTrue(str_contains($repository,'SecretCipher'));
    assertTrue(str_contains($repository,'usesExclusiveAsaas'));
    assertTrue(str_contains($repository,"last_test_status'] === 'success'"));
    assertTrue(str_contains($finance,'WHERE organization_id=:organization AND asaas_event_id=:id'));
    assertTrue(str_contains($finance,'WHERE organization_id=:organization AND resource=:resource'));
    assertTrue(str_contains($bootstrap,'!$isCentralContext&&$organizationFinanceIntegrations->usesExclusiveAsaas'));
    assertTrue(str_contains($routes,"'/admin/organizations/{id:\\d+}/integrations/asaas'"));
    assertTrue(str_contains($routes,"'/admin/organizations/{id:\\d+}/integrations/asaas/test'"));
    assertTrue(str_contains($form,'data-organization-tab="integracoes"'));
    assertTrue(str_contains($view,'name="account_mode"'));
    assertTrue(str_contains($view,'Conta central Mundo Inter'));
    assertTrue(str_contains($view,'Conta Asaas exclusiva'));
    assertTrue(str_contains($view,'Webhook exclusivo'));
};

$tests['organiza polos da franquia em linhas expansíveis'] = static function () use ($rootPath): void {
    $view=(string)file_get_contents($rootPath.'/views/admin/organizations/poles.php');
    assertTrue(str_contains($view,'class="pole-row pole-disclosure"'));
    assertTrue(str_contains($view,'Cadastrar novo polo'));
    assertTrue(str_contains($view,'Abrir cadastro'));
    assertTrue(str_contains($view,'Salvar alterações'));
    assertTrue(str_contains($view,'pole-list-head'));
};

$tests['centraliza plano e histórico na aba Contrato da franquia'] = static function () use ($rootPath): void {
    $form=(string)file_get_contents($rootPath.'/views/admin/organizations/form.php');
    $contract=(string)file_get_contents($rootPath.'/views/admin/organizations/contract.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $javascript=(string)file_get_contents($rootPath.'/public/assets/js/app.js');
    $repository=(string)file_get_contents($rootPath.'/modules/Organization/FranchiseContractRepository.php');
    assertTrue(str_contains($form,'data-organization-tab="contrato"'));
    assertTrue(str_contains($form,"require __DIR__.'/contract.php'"));
    assertTrue(str_contains($contract,'Contrato e plano'));
    assertTrue(str_contains($contract,'Alterar plano'));
    assertTrue(str_contains($contract,'Histórico de contratos'));
    assertTrue(str_contains($contract,'renew_from='));
    assertTrue(str_contains($routes,"'contracts'=>\$contracts"));
    assertTrue(str_contains($javascript,"['contrato', 'documentos'"));
    assertTrue(str_contains($repository,"SET commercial_flow_status='inactive' WHERE organization_id=:organization"));
};

$tests['carrega o Site Institucional com governança central por franquia'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260809_995000_create_institutional_sites.php');
    $expansion=(string)file_get_contents($rootPath.'/database/migrations/20260809_996000_expand_institutional_sites.php');
    $marketing=(string)file_get_contents($rootPath.'/database/migrations/20260810_999000_add_site_marketing_tools.php');
    $navigationFooter=(string)file_get_contents($rootPath.'/database/migrations/20260810_999100_add_site_navigation_footer.php');
    $visualIdentity=(string)file_get_contents($rootPath.'/database/migrations/20260810_999200_add_site_visual_identity.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Site/SiteRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $navigation=(string)file_get_contents($rootPath.'/views/layouts/navigation.php');
    $organizationForm=(string)file_get_contents($rootPath.'/views/admin/organizations/form.php');
    $tenantAdmin=(string)file_get_contents($rootPath.'/views/site/admin.php');
    $publicSite=(string)file_get_contents($rootPath.'/views/site/public.php');
    $publicJavascript=(string)file_get_contents($rootPath.'/public/assets/js/site-public.js');
    $fontAwesomeBrands=(string)file_get_contents($rootPath.'/public/assets/vendor/fontawesome/css/brands.min.css');
    $course=(string)file_get_contents($rootPath.'/views/site/course.php');
    $checkout=(string)file_get_contents($rootPath.'/views/site/checkout.php');
    $page=(string)file_get_contents($rootPath.'/views/site/page.php');

    assertTrue(str_contains($migration,'organization_sites'));
    assertTrue(str_contains($migration,'organization_site_products'));
    assertTrue(str_contains($expansion,'organization_site_banners'));
    assertTrue(str_contains($expansion,'organization_site_pages'));
    assertTrue(str_contains($expansion,'organization_site_orders'));
    assertTrue(str_contains($marketing,'scholarship_form_enabled'));
    assertTrue(str_contains($marketing,'whatsapp_button_enabled'));
    assertTrue(str_contains($marketing,'footer_text'));
    assertTrue(str_contains($navigationFooter,'classroom_url'));
    assertTrue(str_contains($navigationFooter,'webmail_url'));
    assertTrue(str_contains($navigationFooter,'site_search_enabled'));
    assertTrue(str_contains($visualIdentity,'site_primary_color'));
    assertTrue(str_contains($visualIdentity,'site_secondary_color'));
    assertTrue(str_contains($repository,'saveGovernance'));
    assertTrue(str_contains($repository,'saveContent'));
    assertTrue(str_contains($repository,'saveBanner'));
    assertTrue(str_contains($repository,'savePage'));
    assertTrue(str_contains($repository,'createOrderDraft'));
    assertTrue(str_contains($repository,'updateOrderFromWebhook'));
    assertTrue(str_contains($repository,"publication_status']??'')!=='published'"));
    assertTrue(str_contains($routes,"'/admin/site'"));
    assertTrue(str_contains($routes,"'/admin/organizations/{id:\\d+}/site-governance'"));
    assertTrue(str_contains($routes,"'/admin/organizations/{id:\\d+}/site-preview'"));
    assertTrue(str_contains($routes,"'/site'"));
    assertTrue(str_contains($routes,"'/site/bolsas'"));
    assertTrue(str_contains($routes,"'/site/contato'"));
    assertTrue(str_contains($routes,"'/site/banner/{id:\\d+}'"));
    assertTrue(str_contains($routes,"'/site/p/{slug:[a-z0-9-]+}'"));
    assertTrue(str_contains($routes,"'/site/curso/{product:\\d+}'"));
    assertTrue(str_contains($routes,"'/site/curso/{product:\\d+}/interesse'"));
    assertTrue(str_contains($routes,"'/site/checkout/{product:\\d+}'"));
    assertTrue(str_contains($navigation,'Site Institucional'));
    assertTrue(str_contains($organizationForm,"require __DIR__.'/site.php'"));
    assertTrue(str_contains($tenantAdmin,'Cursos em destaque'));
    assertTrue(str_contains($tenantAdmin,'Modelo e cores do site'));
    assertTrue(str_contains($publicSite,'<title><?= $escape($siteTitle) ?></title>'));
    assertTrue(str_contains($tenantAdmin,'Título do site'));
    assertTrue(str_contains($tenantAdmin,'site-section-nav'));
    assertTrue(str_contains($tenantAdmin,"'geral'=>['fa-palette','Geral e identidade'"));
    assertTrue(str_contains($tenantAdmin,'data-site-targets'));
    assertTrue(str_contains((string) file_get_contents($rootPath.'/views/layouts/app.php'),'app.js?v=27'));
    assertTrue(str_contains($tenantAdmin,'scholarship_form_enabled'));
    assertTrue(str_contains($tenantAdmin,'Contato e canais'));
    assertTrue(str_contains($tenantAdmin,'Conteúdo visual'));
    assertTrue(str_contains($tenantAdmin,'.site-preview-card{grid-column:span 6'));
    assertTrue(str_contains($tenantAdmin,'classroom_url'));
    assertTrue(str_contains($tenantAdmin,'youtube_url'));
    assertTrue(str_contains($tenantAdmin,'Páginas personalizadas'));
    assertTrue(str_contains($publicSite,'Ver curso e comprar'));
    assertTrue(str_contains($publicSite,'scholarship-dialog'));
    assertTrue(str_contains($publicSite,'scholarship-rail'));
    assertTrue(str_contains($publicSite,'GANHE BOLSAS DE ESTUDOS'));
    assertTrue(str_contains($publicSite,'floating-action whatsapp'));
    assertTrue(str_contains($publicSite,'fa-brands fa-whatsapp'));
    assertTrue(str_contains($publicSite,'name="full_name"'));
    assertTrue(str_contains($publicSite,'name="desired_course"'));
    assertTrue(str_contains($publicSite,'class="contact-form"'));
    assertTrue(str_contains($publicSite,'name="message"'));
    assertTrue(str_contains($publicSite,"date('Y')"));
    assertTrue(str_contains($publicSite,'.links .classroom{gap:.5rem}'));
    assertTrue(!str_contains($publicSite,'Encontre cursos e informações'));
    assertTrue(str_contains($routes,"input('desired_course'"));
    assertTrue(str_contains($fontAwesomeBrands,'fa-whatsapp'));
    assertTrue(is_file($rootPath.'/public/assets/vendor/fontawesome/webfonts/fa-brands-400.woff2'));
    assertTrue(str_contains($publicSite,'Busca universal'));
    assertTrue(str_contains($publicSite,'Acessos e suporte'));
    assertTrue(str_contains($publicSite,'classroomUrl'));
    assertTrue(str_contains($publicJavascript,'data-site-search-item'));
    assertTrue(str_contains($publicJavascript,'scholarshipPopup'));
    assertTrue(str_contains($course,'Quero receber atendimento'));
    assertTrue(str_contains($course,'Ir para o pagamento'));
    assertTrue(str_contains($checkout,'Continuar para pagamento'));
    assertTrue(str_contains($page,'Voltar ao site'));
    assertTrue(str_contains($publicSite,'Modo de pré-visualização'));
};

$tests['publica o site da franquia com versões mídia métricas SEO e LGPD'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260810_999300_create_site_publication_suite.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Site/SiteRepository.php');
    $media=(string)file_get_contents($rootPath.'/modules/Site/SiteMediaStorage.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $admin=(string)file_get_contents($rootPath.'/views/site/admin.php');
    $commandCenter=(string)file_get_contents($rootPath.'/views/site/admin-command-center.php');
    $extensions=(string)file_get_contents($rootPath.'/views/site/admin-extensions.php');
    $public=(string)file_get_contents($rootPath.'/views/site/public.php');
    $javascript=(string)file_get_contents($rootPath.'/public/assets/js/site-public.js');
    $contacts=(string)file_get_contents($rootPath.'/modules/Crm/ContactRepository.php');
    $contactView=(string)file_get_contents($rootPath.'/views/crm/contacts/show.php');

    assertTrue(str_contains($migration,'organization_site_versions'));
    assertTrue(str_contains($migration,'organization_site_media'));
    assertTrue(str_contains($migration,'organization_site_events'));
    assertTrue(str_contains($migration,'organization_site_domain_checks'));
    assertTrue(str_contains($migration,'organization_site_product_seo'));
    assertTrue(str_contains($repository,'saveRevision'));
    assertTrue(str_contains($repository,'publishVersion'));
    assertTrue(str_contains($repository,'analyticsSummary'));
    assertTrue(str_contains($repository,'recordEvent'));
    assertTrue(str_contains($repository,"['logo_path','favicon_path']"));
    assertTrue(str_contains($media,'imagewebp'));
    assertTrue(str_contains($routes,"'/admin/site/media'"));
    assertTrue(str_contains($routes,"'/admin/site/versions/{id:\\d+}/publish'"));
    assertTrue(str_contains($routes,"'/site/events'"));
    assertTrue(str_contains($admin,'publication_action'));
    assertTrue(str_contains($admin,'cookie_banner_enabled'));
    assertTrue(str_contains($commandCenter,'Ver no celular'));
    assertTrue(str_contains($commandCenter,'Histórico de versões'));
    assertTrue(str_contains($extensions,'Biblioteca de imagens da franquia'));
    assertTrue(str_contains($public,'data-cookie-banner'));
    assertTrue(str_contains($public,'Política de privacidade'));
    assertTrue(str_contains($javascript,'site-metrics-consent'));
    assertTrue(str_contains($javascript,'course_click'));
    assertTrue(str_contains($javascript,'utm_campaign'));
    assertTrue(str_contains($contacts,'landing_page'));
    assertTrue(str_contains($contacts,'utm_campaign'));
    assertTrue(str_contains($contactView,'Origem digital'));
};

$tests['automatiza pedido pago do site sem misturar cobranças das franquias'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260809_997000_automate_site_order_fulfillment.php');
    $service=(string)file_get_contents($rootPath.'/modules/Site/SiteOrderFulfillmentService.php');
    $finance=(string)file_get_contents($rootPath.'/modules/Finance/FinanceRepository.php');
    $enrollments=(string)file_get_contents($rootPath.'/modules/Moodle/EnrollmentRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $governance=(string)file_get_contents($rootPath.'/views/admin/organizations/site.php');
    $siteAdmin=(string)file_get_contents($rootPath.'/views/site/admin.php');
    $ordersPanel=(string)file_get_contents($rootPath.'/views/site/orders-panel.php');
    $ordersDashboard=(string)file_get_contents($rootPath.'/views/site/orders.php');
    $dashboard=(string)file_get_contents($rootPath.'/views/dashboard.php');
    $enrollmentIndex=(string)file_get_contents($rootPath.'/views/moodle/enrollments/index.php');
    $asaas=(string)file_get_contents($rootPath.'/modules/Finance/AsaasClient.php');
    $siteRepository=(string)file_get_contents($rootPath.'/modules/Site/SiteRepository.php');
    $javascript=(string)file_get_contents($rootPath.'/public/assets/js/app.js');

    assertTrue(str_contains($migration,'checkout_fulfillment_mode'));
    assertTrue(str_contains($migration,'student_enrollment_id'));
    assertTrue(str_contains($service,'processPayment'));
    assertTrue(str_contains($service,'organizationForPayload'));
    assertTrue(str_contains($service,'manual_review'));
    assertTrue(str_contains($finance,'isCentralFranchiseReference'));
    assertTrue(str_contains($finance,'isCentralFranchiseCustomer'));
    assertTrue(str_contains($finance,"mundo-inter:franchise-"));
    assertTrue(str_contains($enrollments,'createPaidFromSiteOrder'));
    assertTrue(str_contains($routes,"'/admin/site/orders/{id:\\d+}/release'"));
    assertTrue(str_contains($routes,"'/students/site-orders'"));
    assertTrue(str_contains($routes,"'/students/site-orders/{id:\\d+}/retry'"));
    assertTrue(str_contains($routes,'$siteOrderFulfillment->processPayment'));
    assertTrue(str_contains($governance,'Após confirmar o pagamento'));
    assertTrue(str_contains($siteAdmin,'site-section-nav'));
    assertTrue(str_contains($javascript,"querySelectorAll('[data-site-tab]')"));
    assertTrue(str_contains($ordersPanel,'Liberar no AVA'));
    assertTrue(str_contains($dashboard,'orders-panel.php'));
    assertTrue(str_contains($enrollmentIndex,'orders-panel.php'));
    assertTrue(str_contains($ordersDashboard,'Reprocessamento seguro'));
    assertTrue(str_contains($service,'retryOrder'));
    assertTrue(str_contains($siteRepository,'orderDashboard'));
    assertTrue(str_contains($siteRepository,'orderForRetry'));
    assertTrue(str_contains($asaas,'paymentsForCheckout'));
    assertTrue(str_contains($asaas,"'checkoutSession'=>"));
};

$tests['separa catálogo do AVA e cursos manuais por franquia'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260809_998000_scope_finance_products_by_franchise.php');
    $catalog=(string)file_get_contents($rootPath.'/modules/Finance/CatalogRepository.php');
    $site=(string)file_get_contents($rootPath.'/modules/Site/SiteRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $view=(string)file_get_contents($rootPath.'/views/finance/products/index.php');

    assertTrue(str_contains($migration,'organization_finance_products'));
    assertTrue(str_contains($migration,"source IN ('ava','manual')"));
    assertTrue(str_contains($catalog,'setVisible'));
    assertTrue(str_contains($catalog,'deleteManual'));
    assertTrue(str_contains($catalog,"scope.source='manual'"));
    assertTrue(str_contains($site,'organization_finance_products'));
    assertTrue(str_contains($site,'scope.is_visible=1'));
    assertTrue(str_contains($routes,"'/admin/finance/products/{id:\\d+}/visibility'"));
    assertTrue(str_contains($routes,"'/admin/finance/products/{id:\\d+}/delete'"));
    assertTrue(str_contains($view,'cursos do AVA não podem ser excluídos'));
};

$tests['amplia vitrine da franquia com catálogo páginas completas e domínio próprio'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260810_999400_expand_site_catalog_experience.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Site/SiteRepository.php');
    $organization=(string)file_get_contents($rootPath.'/modules/Organization/OrganizationRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $admin=(string)file_get_contents($rootPath.'/views/site/admin-extensions.php');
    $public=(string)file_get_contents($rootPath.'/views/site/public.php');
    $course=(string)file_get_contents($rootPath.'/views/site/course.php');
    $central=(string)file_get_contents($rootPath.'/views/admin/organizations/site.php');
    $javascript=(string)file_get_contents($rootPath.'/public/assets/js/site-public.js');

    assertTrue(str_contains($migration,'organization_site_product_details'));
    assertTrue(str_contains($migration,'organization_site_blocks'));
    assertTrue(str_contains($repository,'saveProductDetails'));
    assertTrue(str_contains($repository,'saveBlock'));
    assertTrue(str_contains($organization,'saveSiteDomain'));
    assertTrue(str_contains($routes,"'/site/sitemap.xml'"));
    assertTrue(str_contains($routes,"'/site/robots.txt'"));
    assertTrue(str_contains($admin,'Conteúdo programático'));
    assertTrue(str_contains($admin,'Editor visual'));
    assertTrue(str_contains($public,'data-catalog-search'));
    assertTrue(str_contains($course,'application/ld+json'));
    assertTrue(str_contains($course,'Perguntas frequentes'));
    assertTrue(str_contains($central,'Passo a passo no provedor de DNS'));
    assertTrue(str_contains($javascript,'site-course-favorites'));
};

$tests['acompanha o funil comercial completo do site por franquia'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260810_999500_create_site_commercial_funnel.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Site/SiteRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $view=(string)file_get_contents($rootPath.'/views/site/funnel.php');
    $admin=(string)file_get_contents($rootPath.'/views/site/admin.php');
    $javascript=(string)file_get_contents($rootPath.'/public/assets/js/site-public.js');

    assertTrue(str_contains($migration,'organization_site_orders_attribution_index'));
    assertTrue(str_contains($migration,'contact_id'));
    assertTrue(str_contains($repository,'commercialFunnel'));
    assertTrue(str_contains($repository,'lead_scholarship'));
    assertTrue(str_contains($repository,'checkout_created'));
    assertTrue(str_contains($routes,"'/admin/site/funnel'"));
    assertTrue(str_contains($routes,'$trackSiteConversion'));
    assertTrue(str_contains($view,'Funil comercial do site'));
    assertTrue(str_contains($view,'Recuperação de oportunidades'));
    assertTrue(str_contains($admin,'Funil comercial'));
    assertTrue(str_contains($javascript,'site-commercial-session'));
    assertTrue(str_contains($javascript,'utm_content'));
};

$tests['automatiza recuperação comercial de checkouts abandonados'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260810_999600_create_site_recovery_automation.php');
    $service=(string)file_get_contents($rootPath.'/modules/Site/SiteRecoveryService.php');
    $followUps=(string)file_get_contents($rootPath.'/modules/Crm/FollowUpRepository.php');
    $fulfillment=(string)file_get_contents($rootPath.'/modules/Site/SiteOrderFulfillmentService.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Site/SiteRepository.php');
    $layout=(string)file_get_contents($rootPath.'/views/layouts/app.php');
    $view=(string)file_get_contents($rootPath.'/views/site/funnel.php');
    $console=(string)file_get_contents($rootPath.'/bin/console');

    assertTrue(str_contains($migration,'organization_site_recoveries'));
    assertTrue(str_contains($migration,'crm_follow_ups_source_unique'));
    assertTrue(str_contains($service,"modify('+30 minutes')"));
    assertTrue(str_contains($service,"modify('+24 hours')"));
    assertTrue(str_contains($service,"modify('+3 days')"));
    assertTrue(str_contains($service,'markRecovered'));
    assertTrue(str_contains($followUps,'createAutomatedRecovery'));
    assertTrue(str_contains($followUps,'completeAutomated'));
    assertTrue(str_contains($fulfillment,'$this->recoveries->markRecovered'));
    assertTrue(str_contains($repository,"r.status='recovered'"));
    assertTrue(str_contains($layout,'siteRecoveryAlerts'));
    assertTrue(str_contains($view,'Enviar mensagem pronta no WhatsApp'));
    assertTrue(str_contains($view,'recuperada(s)'));
    assertTrue(str_contains($console,'site:recoveries:sync'));
};

$tests['prepara fornecedores externos e o Catalogo PRO sem misturar o financeiro'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260810_999700_create_course_provider_catalogs.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Catalog/CourseProviderRepository.php');
    $client=(string)file_get_contents($rootPath.'/modules/Catalog/EscolaAvancadaClient.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $view=(string)file_get_contents($rootPath.'/views/admin/platform/course-providers.php');
    $javascript=(string)file_get_contents($rootPath.'/public/assets/js/app.js');

    assertTrue(str_contains($migration,'course_provider_integrations'));
    assertTrue(str_contains($migration,'provider_courses_external_unique'));
    assertTrue(str_contains($migration,"'catalogo-pro','Catálogo PRO'"));
    assertTrue(str_contains($repository,'remote_reference_price'));
    assertTrue(str_contains($repository,'last_sync_status'));
    assertTrue(str_contains($client,"'cursos/listar'"));
    assertTrue(str_contains($client,'URL especial da API da Escola Avançada'));
    assertTrue(str_contains($client,'CURLINFO_CONTENT_TYPE'));
    assertTrue(!str_contains($client,'financeiro/parcelas'));
    assertTrue(str_contains($routes,"'/admin/platform/integrations/course-providers'"));
    assertTrue(str_contains($view,'data-catalog-tab'));
    assertTrue(str_contains($view,"preg_replace('/^(?:Catálogo|Formação)\\s+/u'"));
    assertTrue(str_contains($view,'.catalog-subtab{margin:0'));
    assertTrue(!str_contains($view,'<script>'));
    assertTrue(str_contains($javascript,"document.querySelectorAll('[data-catalog-tab]')"));
    assertTrue(str_contains($javascript,'showCatalog'));
    assertTrue(str_contains($javascript,"params.get('section') || 'connection'"));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/views/layouts/app.php'),'.btn-primary { border-color: var(--inter-accent);'));
    assertTrue(str_contains($view,'Conexão e API'));
    assertTrue(str_contains($view,'Cursos e curadoria'));
    assertTrue(str_contains($view,'Conexão central'));
    assertTrue(str_contains($view,'Acesso do aluno'));
    assertTrue(str_contains($view,'/admin/platform/integrations/ava-cursos/access-policy'));
    assertTrue(!str_contains($view,'Abrir integração AVA Cursos'));
    assertTrue(str_contains($routes,"'interSettings'=>\$avaConnections->shared()"));
    assertTrue(str_contains($routes,"course-providers?catalog=ava_cursos&section=access"));
    assertTrue(str_contains($view,'Não use aqui o endereço público do AVA'));
};

$tests['publica Catalogo PRO por franquia com venda assistida'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260810_999800_create_provider_course_offers.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Catalog/CourseProviderRepository.php');
    $siteRepository=(string)file_get_contents($rootPath.'/modules/Site/SiteRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $admin=(string)file_get_contents($rootPath.'/views/admin/platform/course-providers.php');
    $public=(string)file_get_contents($rootPath.'/views/site/public.php');

    assertTrue(str_contains($migration,'organization_provider_course_offers'));
    assertTrue(str_contains($migration,"sale_mode VARCHAR(30) NOT NULL DEFAULT 'assisted'"));
    assertTrue(str_contains($repository,'public function review'));
    assertTrue(str_contains($repository,'public function saveOffer'));
    assertTrue(str_contains($siteRepository,'external_products'));
    assertTrue(str_contains($routes,"'/site/catalogo-pro/{offer:\\d+}'"));
    assertTrue(str_contains($routes,"'/site/catalogo-pro/{offer:\\d+}/interesse'"));
    assertTrue(!str_contains($routes,'$sites->publicUnits($organizationId)'));
    assertTrue(str_contains($admin,'data-catalog-tab'));
    assertTrue(str_contains($admin,'Conexão e API'));
    assertTrue(!str_contains($admin,'Liberação por franquia'));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/views/admin/organizations/ava.php'),'Cursos individuais e preços da franquia'));
    assertTrue(str_contains($public,'Conhecer e solicitar matrícula'));
    assertTrue(str_contains($public,'Acesso pelo ambiente acadêmico definido para esta Formação'));
};

$tests['controla catalogos comerciais pela aba AVA da franquia'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260810_999900_create_organization_catalog_access.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Catalog/CourseProviderRepository.php');
    $site=(string)file_get_contents($rootPath.'/modules/Site/SiteRepository.php');
    $financeCatalog=(string)file_get_contents($rootPath.'/modules/Finance/CatalogRepository.php');
    $view=(string)file_get_contents($rootPath.'/views/admin/organizations/ava.php');
    assertTrue(str_contains($migration,'organization_course_catalog_access'));
    assertTrue(str_contains($migration,"'ava-cursos'"));
    assertTrue(str_contains($repository,'catalogsForOrganization'));
    assertTrue(str_contains($repository,'saveCatalogAccess'));
    assertTrue(str_contains($site,'COALESCE(access.is_enabled,1)=1'));
    assertTrue(str_contains($financeCatalog,'catalog_enabled'));
    assertTrue(str_contains($view,'Formações disponíveis'));
    assertTrue(str_contains($view,'catalog_ids[]'));
};

$tests['registra todos os catalogos e fornecedores academicos'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260810_999910_register_course_provider_catalogs.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Catalog/CourseProviderRepository.php');
    foreach(['Catálogo INTER','Catálogo PRO','Catálogo UP','Catálogo MASTER','Catálogo CEFE','Catálogo CONCLUSÃO','Catálogo PREPARA','Catálogo DRIVE'] as$catalog) assertTrue(str_contains($migration,$catalog));
    foreach(['Escola Avançada','SIE','IESDE','EJA CEFE','EJA Conclusão','Aprova Concursos','Trânsito'] as$provider) assertTrue(str_contains($migration,$provider));
    assertTrue(str_contains($repository,'providerCatalogRegistry'));
    assertTrue(str_contains($repository,'integration_active'));
    assertTrue(str_contains($repository,'settingsForProvider'));
    assertTrue(str_contains((string)file_get_contents($rootPath.'/routes/web.php'),'catalogItemsForOrganization'));
};

$tests['organiza a oferta comercial dos catalogos por franquia'] = static function () use ($rootPath): void {
    $repository=(string)file_get_contents($rootPath.'/modules/Catalog/CourseProviderRepository.php');
    $route=(string)file_get_contents($rootPath.'/routes/web.php');
    $manager=(string)file_get_contents($rootPath.'/views/admin/organizations/ava_catalog_manager.php');

    assertTrue(str_contains($repository,'catalogItemsForOrganization'));
    assertTrue(str_contains($repository,'missing_commercial_fields'));
    assertTrue(str_contains($repository,'is_commercially_ready'));
    assertTrue(str_contains($route,"'catalogItems'=>"));
    assertTrue(str_contains($manager,'Prontidão comercial'));
    assertTrue(str_contains($manager,'Bloquear nesta franquia'));
    assertTrue(str_contains($manager,'return_to'));
};

$tests['registra o Catalogo EXPERT com credenciais protegidas e destino academico explicito'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260811_000030_register_expert_catalog_execution.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Catalog/CourseProviderRepository.php');
    $catalogView=(string)file_get_contents($rootPath.'/views/admin/platform/course-providers.php');
    $organizationView=(string)file_get_contents($rootPath.'/views/admin/organizations/ava.php');

    assertTrue(str_contains($migration,'execution_environment'));
    assertTrue(str_contains($migration,"'catalogo-expert','Catálogo EXPERT'"));
    assertTrue(str_contains($migration,"'conted_tech','CONTED TECH'"));
    assertTrue(str_contains($migration,"'shared_ava'"));
    assertTrue(!str_contains($migration,'Secret Key'));
    assertTrue(str_contains($repository,"['iesde', 'conted_tech']"));
    assertTrue(str_contains($repository,'Integration Key e a Secret Key da CONTED TECH'));
    assertTrue(str_contains($catalogView,'Integration Key'));
    assertTrue(str_contains($catalogView,'Secret Key'));
    assertTrue(str_contains($catalogView,'documentação OpenAPI V2'));
    assertTrue(str_contains($organizationView,'Moodle exclusivo da franquia'));
    assertTrue(str_contains($organizationView,'Dentro do AVA Cursos'));
    assertTrue(str_contains($organizationView,'AVA do fornecedor'));
};

$tests['homologa o conector oficial JWT do Catalogo EXPERT'] = static function () use ($rootPath): void {
    $client=(string)file_get_contents($rootPath.'/modules/Catalog/ContedTechClient.php');
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260811_000040_enable_conted_tech_connector.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Catalog/CourseProviderRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');

    assertTrue(str_contains($client,"'api_key'"));
    assertTrue(str_contains($client,"'secret_key'"));
    assertTrue(str_contains($client,"'Authorization: Bearer '"));
    assertTrue(str_contains($client,"'type' => 'course'"));
    assertTrue(str_contains($client,"'limit' => \$pageSize"));
    assertTrue(str_contains($client,"'offset' => \$page * \$pageSize"));
    assertTrue(str_contains($client,"'content/link'"));
    assertTrue(str_contains($client,'CURLOPT_HTTPGET => true'));
    assertTrue(str_contains($client,"['discipline', 'unit', 'object']"));
    assertTrue(str_contains($client,"'student/inactive'"));
    assertTrue(str_contains($client,'CURLOPT_PROTOCOLS => CURLPROTO_HTTPS'));
    assertTrue(!str_contains($client,'CURLOPT_SSL_VERIFYPEER'));
    assertTrue(str_contains($migration,"delivery_mode='sso'"));
    assertTrue(str_contains($migration,'catalog_sync=1'));
    assertTrue(str_contains($repository,"['escola_avancada', 'iesde', 'conted_tech']"));
    assertTrue(str_contains($repository,"IN ('escola_avancada','iesde','conted_tech')"));
    assertTrue(str_contains($repository,"\$course['batch']"));
    assertTrue(str_contains($routes,'new ContedTechClient'));
};

$tests['prepara conector paginado e seguro do Catalogo MASTER'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260810_999920_add_portalava_provider_credentials.php');
    $client=(string)file_get_contents($rootPath.'/modules/Catalog/PortalAvaClient.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Catalog/CourseProviderRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $view=(string)file_get_contents($rootPath.'/views/admin/platform/course-providers.php');
    assertTrue(str_contains($migration,'username_encrypted'));
    assertTrue(str_contains($migration,'password_encrypted'));
    assertTrue(str_contains($migration,"https://ead.portalava.com.br"));
    assertTrue(str_contains($client,'CURLAUTH_DIGEST'));
    assertTrue(str_contains($client,'EAD-API-KEY'));
    assertTrue(str_contains($client,'web_servicePg/getCursos/format/json'));
    assertTrue(str_contains($client,'x-amzn-waf-action'));
    assertTrue(str_contains($client,'verificação CAPTCHA'));
    assertTrue(str_contains($client,'CURLOPT_NOBODY => true'));
    assertTrue(str_contains($client,"'totalPaginas'"));
    assertTrue(str_contains($client,'web_service/cadastro/format/json'));
    assertTrue(str_contains($client,'web_service/situacao/format/json'));
    assertTrue(!str_contains($client,'CURLOPT_SSL_VERIFYPEER'));
    assertTrue(str_contains($repository,"['escola_avancada', 'iesde', 'conted_tech']"));
    assertTrue(str_contains($repository,'synchronizeProvider'));
    assertTrue(str_contains($routes,'new PortalAvaClient'));
    assertTrue(str_contains($view,'Usuário HTTP Digest'));
    assertTrue(str_contains($view,'Chave EAD-API-KEY'));
};

$tests['migra o Catalogo MASTER para LTI 1.3 sem perder o legado'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260810_999930_add_lti13_course_provider_connection.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Catalog/CourseProviderRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $view=(string)file_get_contents($rootPath.'/views/admin/platform/course-providers.php');
    assertTrue(str_contains($migration,'lti_registration_url'));
    assertTrue(str_contains($migration,"integration_mode='lti13'"));
    $deepLinkMigration=(string)file_get_contents($rootPath.'/database/migrations/20260811_000010_add_lti13_deep_link_url.php');
    assertTrue(str_contains($deepLinkMigration,'lti_deep_link_url'));
    assertTrue(str_contains($repository,'saveLtiProvider'));
    assertTrue(str_contains($repository,"delivery_mode='lti'"));
    assertTrue(str_contains($routes,"No LTI 1.3 o catálogo MASTER"));
    assertTrue(str_contains($view,'Configuração LTI 1.3'));
    assertTrue(str_contains($view,'Mundo Inter — Catálogo MASTER'));
    assertTrue(str_contains($view,'/mod/lti/certs.php'));
    assertTrue(str_contains($view,'Deep Linking'));
    assertTrue(str_contains($view,'compartilhar nome e e-mail'));
    assertTrue(str_contains($view,'Client ID'));
    assertTrue(str_contains($view,'Deployment ID'));
};

$tests['governa catalogos com curadoria preservada recursos e regra por franquia'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260811_000020_expand_course_catalog_governance.php');
    $priceMigration=(string)file_get_contents($rootPath.'/database/migrations/20260811_000090_add_default_price_to_catalog_policy.php');
    $centralPolicyMigration=(string)file_get_contents($rootPath.'/database/migrations/20260811_000100_add_central_catalog_commercial_policy.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Catalog/CourseProviderRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $catalogView=(string)file_get_contents($rootPath.'/views/admin/platform/course-providers.php');
    $centralPolicyView=(string)file_get_contents($rootPath.'/views/admin/platform/_catalog-commercial-policy.php');
    $courseCuration=(string)file_get_contents($rootPath.'/views/admin/platform/_provider-course-curation.php');
    $organizationView=(string)file_get_contents($rootPath.'/views/admin/organizations/ava.php');

    foreach(['commercial_cover_url','release_status','content_hash','sync_state','course_provider_capabilities','markup_percent','default_max_installments'] as$field) assertTrue(str_contains($migration,$field));
    assertTrue(str_contains($priceMigration,'default_price'));
    assertTrue(str_contains($centralPolicyMigration,'allow_franchise_commercial_override'));
    assertTrue(str_contains($repository,'public function applyCatalogPolicy'));
    assertTrue(str_contains($repository,'public function saveCentralCatalogPolicy'));
    assertTrue(str_contains($repository,'public function applyCentralCatalogPolicy'));
    assertTrue(str_contains($repository,'public function bulkCourseAction'));
    assertTrue(str_contains($repository,'public function saveCapabilities'));
    assertTrue(str_contains($repository,"COALESCE(NULLIF(pc.commercial_name,''),pc.name) effective_name"));
    assertTrue(str_contains($repository,"course.review_status='approved' AND course.release_status IN ('released','published')"));
    assertTrue(substr_count($repository,"THEN (SELECT COUNT(*) FROM moodle_courses course WHERE course.visible=1)")>=2);
    assertTrue(str_contains($routes,"'/admin/platform/integrations/course-providers/catalog/{provider:[a-z0-9_-]+}/capabilities'"));
    assertTrue(str_contains($routes,"'/admin/organizations/{id:\\d+}/catalogs/{catalogId:\\d+}/apply-policy'"));
    assertTrue(str_contains($routes,"'/admin/platform/integrations/course-providers/catalogs/{id:\\d+}/commercial-policy'"));
    assertTrue(str_contains($routes,"'/admin/platform/integrations/course-providers/catalog/{provider:[a-z0-9_-]+}/courses/bulk'"));
    assertTrue(str_contains($catalogView,'Matriz real de recursos deste fornecedor'));
    assertTrue(str_contains($catalogView,'Aplicar em lote'));
    assertTrue(str_contains($courseCuration,'Aprovar não publica sozinho'));
    assertTrue(str_contains($organizationView,'Preço padrão para todos'));
    assertTrue(str_contains($organizationView,'Salvar regra e aplicar em lote'));
    assertTrue(str_contains($organizationView,'catalog_policy['));
    assertTrue(str_contains($organizationView,'Padrão definido pelo ADM Central'));
    assertTrue(str_contains($centralPolicyView,'Permitir personalização pela franquia'));
    assertTrue(str_contains($centralPolicyView,'Todas as franquias ativas'));
    assertTrue(str_contains($centralPolicyView,'Salvar e aplicar'));
};

$tests['decompoe cursos EXPERT em conteudos individuais vendaveis'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260811_000050_create_provider_catalog_contents.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Catalog/CourseProviderRepository.php');
    $siteRepository=(string)file_get_contents($rootPath.'/modules/Site/SiteRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $view=(string)file_get_contents($rootPath.'/views/admin/platform/course-providers.php');

    $contents=\Interferencia\Modules\Catalog\ContedTechClient::extractSellableContents([
        'semesters'=>[[
            'semester'=>1,
            'disciplines'=>[[
                'name'=>'Atendimento ao cliente',
                'classes'=>[
                    ['name'=>'Postura profissional','type'=>'unit','batch'=>'batch-a'],
                    ['name'=>'Postura profissional duplicada','type'=>'unit','batch'=>'batch-a'],
                    ['name'=>'Comunicação','type'=>'unit','batch'=>'batch-b'],
                ],
            ]],
        ]],
    ]);

    assertSame(2,count($contents));
    assertSame('batch-a',$contents[0]['batch']);
    assertSame('Atendimento ao cliente',$contents[0]['discipline']);
    assertSame(1,$contents[0]['semester']);

    $officialFormats=\Interferencia\Modules\Catalog\ContedTechClient::extractSellableContents([
        'disciplines'=>[[
            'name'=>'Políticas Educacionais',
            'type'=>'discipline',
            'batch'=>'discipline-a',
            'units'=>[[
                'name'=>'Legislação Educacional',
                'type'=>'unit',
                'batch'=>'unit-a',
                'objects'=>[[
                    'name'=>'Vídeo introdutório',
                    'type'=>'object',
                    'batch'=>'object-a',
                ]],
            ]],
        ],[
            'name'=>'Projeto Integrador',
            'type'=>'unit',
            'batch'=>'unit-b',
        ]],
    ]);

    assertSame(4,count($officialFormats));
    assertSame('discipline',$officialFormats[0]['type']);
    assertSame('unit',$officialFormats[1]['type']);
    assertSame('object',$officialFormats[2]['type']);
    assertSame('unit-b',$officialFormats[3]['batch']);
    assertTrue(str_contains($migration,'provider_catalog_contents'));
    assertTrue(str_contains($migration,'provider_course_content_links'));
    assertTrue(str_contains($migration,'organization_provider_content_offers'));
    assertTrue(str_contains($repository,'public function catalogContents'));
    assertTrue(str_contains($repository,'public function saveContentOffer'));
    assertTrue(str_contains($repository,'public function contentAccessTargetForOffer'));
    assertTrue(str_contains($repository,':first_seen,:last_seen,:changed_at'));
    assertTrue(str_contains($siteRepository,'externalContentProducts'));
    assertTrue(str_contains($routes,"'/site/conteudo/{offer:\\d+}'"));
    assertTrue(str_contains($view,'Cursos individuais'));
};

$tests['padroniza capas leves e heranca comercial em todos os catalogos'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260811_000060_create_catalog_media_assets.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Catalog/CourseProviderRepository.php');
    $mediaStorage=(string)file_get_contents($rootPath.'/modules/Catalog/CatalogMediaStorage.php');
    $generator=(string)file_get_contents($rootPath.'/modules/Catalog/CatalogImageGenerator.php');
    $siteRepository=(string)file_get_contents($rootPath.'/modules/Site/SiteRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $view=(string)file_get_contents($rootPath.'/views/admin/platform/course-providers.php');
    $courseCuration=(string)file_get_contents($rootPath.'/views/admin/platform/_provider-course-curation.php');
    $contentView=(string)file_get_contents($rootPath.'/views/admin/platform/_provider-content-panel.php');
    $javascript=(string)file_get_contents($rootPath.'/public/assets/js/app.js');

    foreach(['catalog_media_assets','generation_provider','generation_prompt','generation_status'] as$field) assertTrue(str_contains($migration,$field));
    assertTrue(str_contains($repository,'public function saveMediaAsset'));
    assertTrue(str_contains($repository,':created_user,:updated_user'));
    assertTrue(!str_contains($repository,':generated_at,:user,:user'));
    assertTrue(str_contains($repository,"NULLIF(MAX(course.commercial_description),'')"));
    assertTrue(str_contains($repository,"entity_type='course'"));
    assertTrue(str_contains($mediaStorage,'1280'));
    assertTrue(str_contains($mediaStorage,'imagewebp'));
    assertTrue(str_contains($mediaStorage,"'Catalogos/'"));
    assertTrue(str_contains($generator,'interface CatalogImageGenerator'));
    assertTrue(str_contains($siteRepository,'media_asset_id'));
    assertTrue(str_contains($routes,"'/catalog-media/{id:\\d+}'"));
    assertTrue(str_contains($courseCuration,'Capa otimizada no Spaces'));
    assertTrue(str_contains($contentView,'Capa herdada'));
    assertTrue(str_contains($javascript,"section === 'contents' && provider !== loadedContentProvider"));
};

$tests['libera catalogos por padrao e registra bloqueios como excecoes'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260811_000070_create_catalog_access_overrides.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Catalog/CourseProviderRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $catalogView=(string)file_get_contents($rootPath.'/views/admin/platform/_provider-content-panel.php');
    $organizationView=(string)file_get_contents($rootPath.'/views/admin/organizations/ava.php');
    $javascript=(string)file_get_contents($rootPath.'/public/assets/js/app.js');

    assertTrue(substr_count($migration,'is_globally_enabled')>=3);
    assertTrue(str_contains($migration,'organization_catalog_item_access'));
    assertTrue(str_contains($repository,'public function setCatalogGlobalAvailability'));
    assertTrue(str_contains($repository,'public function setItemGlobalAvailability'));
    assertTrue(str_contains($repository,'public function setOrganizationItemAvailability'));
    assertTrue(str_contains($repository,'COALESCE(item_access.is_enabled,1)'));
    assertTrue(str_contains($routes,"'/admin/platform/integrations/course-providers/catalogs/{id:\\d+}/availability'"));
    assertTrue(str_contains($routes,"'/admin/organizations/{organizationId:\\d+}/catalog-items/{type:course|content}/{itemId:\\d+}/availability'"));
    assertTrue(str_contains($catalogView,'Cursos individuais liberados por padrão'));
    assertTrue(str_contains($catalogView,'content-curation-row'));
    assertTrue(str_contains($organizationView,'Todas as Formações são liberadas por padrão'));
    assertTrue(str_contains($organizationView,'Cursos individuais'));
    assertTrue(str_contains($javascript,'data-content-curation-toggle'));
};

$tests['gera capas contextuais em fila e publica a versao final no Spaces'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260811_000080_create_catalog_image_generation.php');
    $settings=(string)file_get_contents($rootPath.'/modules/Catalog/ImageGenerationRepository.php');
    $client=(string)file_get_contents($rootPath.'/modules/Catalog/OpenAiImageClient.php');
    $generator=(string)file_get_contents($rootPath.'/modules/Catalog/CatalogCoverGenerator.php');
    $storage=(string)file_get_contents($rootPath.'/modules/Catalog/CatalogMediaStorage.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Catalog/CourseProviderRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $console=(string)file_get_contents($rootPath.'/bin/console');
    $courseView=(string)file_get_contents($rootPath.'/views/admin/platform/course-providers.php');
    $courseCuration=(string)file_get_contents($rootPath.'/views/admin/platform/_provider-course-curation.php');
    $contentView=(string)file_get_contents($rootPath.'/views/admin/platform/_provider-content-panel.php');
    $javascript=(string)file_get_contents($rootPath.'/public/assets/js/app.js');

    assertTrue(str_contains($migration,'catalog_image_generation_settings'));
    assertTrue(str_contains($migration,'catalog_image_generation_jobs'));
    assertTrue(str_contains($migration,'auto_generate_missing'));
    assertTrue(str_contains($settings,"status IN ('pending','processing')"));
    assertTrue(str_contains($client,'/v1/images/generations'));
    assertTrue(str_contains($client,"'output_format'=>'webp'"));
    assertTrue(str_contains($generator,'storeGenerated'));
    assertTrue(str_contains($generator,'queueAfterApproval'));
    assertTrue(str_contains($generator,'Não inclua palavras'));
    assertTrue(str_contains($storage,'public function storeGenerated'));
    assertTrue(str_contains($repository,'provider_course_content_links inherited_link'));
    assertTrue(!str_contains($repository,"entity.discipline_name) category"));
    assertTrue(str_contains($routes,"'/admin/platform/integrations/image-generation'"));
    assertTrue(str_contains($routes,"/generate-cover'"));
    assertTrue(str_contains($console,"catalog-images:process"));
    assertTrue(str_contains($courseView,'data-course-curation-toggle'));
    assertTrue(str_contains($courseCuration,'Gerar capa com IA'));
    assertTrue(str_contains($courseCuration,'course-curation-shell'));
    assertTrue(str_contains($contentView,'Gerar capa com IA'));
    assertTrue(str_contains($contentView,'content-ai-form'));
    assertTrue(str_contains($javascript,'data-course-curation-toggle'));
};

$tests['apresenta formacoes cursos individuais e trilhas na vitrine publica'] = static function () use ($rootPath): void {
    $repository=(string)file_get_contents($rootPath.'/modules/Site/SiteRepository.php');
    $view=(string)file_get_contents($rootPath.'/views/site/public.php');
    $javascript=(string)file_get_contents($rootPath.'/public/assets/js/site-public.js');

    assertTrue(str_contains($repository,'private function publicOffers'));
    assertTrue(str_contains($repository,"\$site['offers']=\$offers"));
    assertTrue(str_contains($repository,"\$site['formations']=\$this->publicFormations(\$offers)"));
    assertTrue(str_contains($repository,'offer.price>=5'));
    assertTrue(str_contains($repository,'catalog.central_valid_from'));
    assertTrue(str_contains($repository,'catalog.central_valid_until'));
    assertTrue(str_contains($view,"\$site['offers']"));
    assertTrue(str_contains($view,'data-catalog-formation'));
    assertTrue(str_contains($view,'Escolha uma Formação'));
    assertTrue(str_contains($view,'data-site-offer'));
    assertTrue(str_contains($javascript,'catalogFormation'));
    assertTrue(str_contains($javascript,'card.dataset.courseFormation'));
};

$tests['homologa catalogo em amostra assistida sem gerar cobranca'] = static function () use ($rootPath): void {
    $repository=(string)file_get_contents($rootPath.'/modules/Catalog/CourseProviderRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $view=(string)file_get_contents($rootPath.'/views/admin/platform/course-providers.php');
    $homologation=(string)file_get_contents($rootPath.'/views/admin/platform/_catalog-homologation.php');

    assertTrue(str_contains($repository,'public function catalogHomologationStatus'));
    assertTrue(str_contains($repository,'public function prepareCatalogHomologation'));
    assertTrue(str_contains($repository,"in_array(\$itemType, ['course', 'content'], true)"));
    assertTrue(str_contains($repository,"is_visible=1"));
    assertTrue(!str_contains($repository,':user,:user'));
    assertTrue(str_contains($repository,':created_by,:updated_by'));
    assertTrue(str_contains($routes,"/homologation'"));
    assertTrue(str_contains($routes,'confirm_no_charge'));
    assertTrue(str_contains($view,'data-catalog-subtab="homologation"'));
    assertTrue(str_contains($homologation,'Sem cobrança real'));
    assertTrue(str_contains($homologation,'não chama o Asaas'));
};

$tests['libera matricula externa EXPERT sem criar cobranca'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260812_000010_add_external_provider_enrollments.php');
    $catalog=(string)file_get_contents($rootPath.'/modules/Catalog/CourseProviderRepository.php');
    $enrollments=(string)file_get_contents($rootPath.'/modules/Moodle/EnrollmentRepository.php');
    $releaser=(string)file_get_contents($rootPath.'/modules/Moodle/AvaEnrollmentReleaser.php');
    $notifier=(string)file_get_contents($rootPath.'/modules/Moodle/AvaAccessNotifier.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $view=(string)file_get_contents($rootPath.'/views/moodle/enrollments/index.php');

    assertTrue(str_contains($migration,'provider_content_offer_id'));
    assertTrue(str_contains($migration,'provider_access_url'));
    assertTrue(str_contains($migration,'MODIFY moodle_course_id BIGINT UNSIGNED NULL'));
    assertTrue(str_contains($catalog,'public function courseAccessTargetForOffer'));
    assertTrue(str_contains($catalog,'public function contentAccessTargetForOffer'));
    assertTrue(str_contains($enrollments,'public function createProviderWaived'));
    assertTrue(str_contains($enrollments,"'payment_waived'"));
    assertTrue(str_contains($enrollments,'public function markProviderReleased'));
    assertTrue(str_contains($releaser,'contentLink($contentType, $batch, $document)'));
    assertTrue(str_contains($releaser,'providerAccessUrl'));
    assertTrue(str_contains($notifier,'Link pessoal de acesso:'));
    assertTrue(str_contains($routes,"'/students/enrollments/provider-waivers'"));
    assertTrue(str_contains($routes,'createProviderWaived'));
    assertTrue(str_contains($view,'Formação EXPERT'));
    assertTrue(str_contains($view,'não será gerada nenhuma cobrança'));
};

$tests['organiza trilhas comerciais em categorias hierarquicas'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260812_000020_create_catalog_categories_and_trails.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Catalog/LearningCatalogRepository.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $view=(string)file_get_contents($rootPath.'/views/admin/platform/catalog-trails.php');
    $integrations=(string)file_get_contents($rootPath.'/views/admin/platform/integrations.php');

    assertTrue(str_contains($migration,'CREATE TABLE catalog_categories'));
    assertTrue(str_contains($migration,'CREATE TABLE catalog_trails'));
    assertTrue(str_contains($migration,'category_id BIGINT UNSIGNED NOT NULL'));
    assertTrue(str_contains($migration,'CREATE TABLE catalog_trail_items'));
    assertTrue(str_contains($repository,'Escolha uma categoria ativa para a Trilha.'));
    assertTrue(str_contains($repository,'count($items) < 2'));
    assertTrue(str_contains($routes,"'/admin/platform/catalog-trails'"));
    assertTrue(str_contains($routes,"'/admin/platform/catalog-trails/categories'"));
    assertTrue(str_contains($view,'name="category_id"'));
    assertTrue(str_contains($view,'Categoria *'));
    assertTrue(str_contains($view,'mínimo 2'));
    assertTrue(str_contains($integrations,'Cursos individuais e Trilhas'));
};

$tests['padroniza nomenclaturas oficiais do Mundo Inter'] = static function () use ($rootPath): void {
    $standard=(string)file_get_contents($rootPath.'/docs/30-nomenclaturas-mundo-inter.md');
    $public=(string)file_get_contents($rootPath.'/views/site/public.php');
    $franchise=(string)file_get_contents($rootPath.'/views/admin/organizations/ava.php');
    foreach(['Formações','Cursos individuais','Trilhas','Categorias','Catálogos'] as$term) assertTrue(str_contains($standard,$term));
    assertTrue(str_contains($standard,'uso interno e exclusivo do ADM Central'));
    assertTrue(str_contains($public,'Cursos individuais e Trilhas'));
    assertTrue(str_contains($franchise,'Formações disponíveis'));
};

$tests['organiza novas matriculas em uma coorte por franquia e turmas no AVA'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260812_000030_create_ava_academic_organization.php');
    $consolidation=(string)file_get_contents($rootPath.'/database/migrations/20260812_000040_consolidate_ava_cohorts_by_franchise.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Moodle/AcademicOrganizationRepository.php');
    $enrollments=(string)file_get_contents($rootPath.'/modules/Moodle/EnrollmentRepository.php');
    $releaser=(string)file_get_contents($rootPath.'/modules/Moodle/AvaEnrollmentReleaser.php');
    $client=(string)file_get_contents($rootPath.'/modules/Moodle/MoodleClient.php');
    $pluginService=(string)file_get_contents($rootPath.'/integrations/moodle/local_mundointer/db/services.php');
    $pluginExternal=(string)file_get_contents($rootPath.'/integrations/moodle/local_mundointer/classes/external/organize_enrollment.php');
    $pluginVersion=(string)file_get_contents($rootPath.'/integrations/moodle/local_mundointer/version.php');
    $view=(string)file_get_contents($rootPath.'/views/admin/platform/course-providers.php');

    assertTrue(str_contains($migration,'CREATE TABLE ava_academic_cohorts'));
    assertTrue(str_contains($migration,'CREATE TABLE ava_academic_groups'));
    assertTrue(str_contains($migration,'academic_period_code'));
    assertTrue(str_contains($consolidation,'ava_academic_cohort_org_unique'));
    assertTrue(str_contains($consolidation,"scope_type='organization'"));
    assertTrue(str_contains($consolidation,'class_reference'));
    assertTrue(str_contains($repository,'public function prepareForEnrollment'));
    assertTrue(str_contains($repository,"'mi-franquia-'.\$franchiseCode"));
    assertTrue(str_contains($repository,"'trail':'course'"));
    assertTrue(str_contains($repository,'classReference'));
    assertTrue(str_contains($repository,"date('Y',\$time).'-'"));
    assertTrue(str_contains($enrollments,'recordAcademicOrganizationFailure'));
    assertTrue(str_contains($releaser,'organizeEnrollment'));
    assertTrue(str_contains($releaser,"academic_organization"));
    assertTrue(str_contains($client,'local_mundointer_organize_enrollment'));
    assertTrue(str_contains($client,"trim(\$response)==='null'"));
    assertTrue(str_contains($client,"Função Moodle: '.\$function"));
    assertTrue(str_contains($pluginService,"'local_mundointer_organize_enrollment'"));
    assertTrue(str_contains($pluginExternal,'cohort_add_member'));
    assertTrue(str_contains($pluginExternal,'groups_add_member'));
    assertTrue(str_contains($pluginVersion,"release = '0.5.0'"));
    assertTrue(str_contains($view,'Coortes e turmas'));
    assertTrue(str_contains($view,'Organização acadêmica automática'));
    assertTrue(str_contains($view,'Coortes de franquia'));
    assertTrue(str_contains($view,'Turmas lógicas'));
};

$tests['publica trilhas no AVA com curso unico e historico'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260812_000060_create_catalog_ava_publications.php');
    $publisher=(string)file_get_contents($rootPath.'/modules/Catalog/AvaCatalogPublisher.php');
    $repository=(string)file_get_contents($rootPath.'/modules/Catalog/LearningCatalogRepository.php');
    $client=(string)file_get_contents($rootPath.'/modules/Moodle/MoodleClient.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $view=(string)file_get_contents($rootPath.'/views/admin/platform/catalog-trails.php');

    assertTrue(str_contains($migration,'CREATE TABLE catalog_ava_publications'));
    assertTrue(str_contains($migration,'CREATE TABLE catalog_ava_publication_events'));
    assertTrue(str_contains($migration,"'draft','ready','published','failed'"));
    assertTrue(str_contains($publisher,'public function publishTrail'));
    assertTrue(str_contains($publisher,"'mi-trilha-'.\$trailId"));
    assertTrue(str_contains($publisher,"'mi-mundo-inter'"));
    assertTrue(str_contains($repository,'markPublicationSuccess'));
    assertTrue(str_contains($repository,'publicationHistory'));
    assertTrue(str_contains($client,'core_course_create_courses'));
    assertTrue(str_contains($client,'core_course_update_courses'));
    assertTrue(str_contains($routes,"'/admin/platform/catalog-trails/{id:\\d+}/publish'"));
    assertTrue(str_contains($view,'Publicar no AVA'));
    assertTrue(str_contains($view,'Histórico de publicação'));
};

$tests['sincroniza alunos antigos em lotes sem recriar acesso no AVA'] = static function () use ($rootPath): void {
    $migration=(string)file_get_contents($rootPath.'/database/migrations/20260812_000050_create_ava_academic_backfill.php');
    $service=(string)file_get_contents($rootPath.'/modules/Moodle/AcademicOrganizationBackfillService.php');
    $routes=(string)file_get_contents($rootPath.'/routes/web.php');
    $view=(string)file_get_contents($rootPath.'/views/admin/platform/course-providers.php');

    assertTrue(str_contains($migration,'CREATE TABLE ava_academic_backfill_runs'));
    assertTrue(str_contains($migration,'CREATE TABLE ava_academic_backfill_items'));
    assertTrue(str_contains($migration,'REFERENCES platform_users(id)'));
    assertTrue(str_contains($service,"'panel_enrollment'"));
    assertTrue(str_contains($service,"'moodle_mirror'"));
    assertTrue(str_contains($service,'processNextBatch'));
    assertTrue(str_contains($service,'Retomado após interrupção do lote.'));
    assertTrue(!str_contains($service,'createUser('));
    assertTrue(!str_contains($service,'enrolStudent('));
    assertTrue(str_contains($routes,'academic-backfill/start'));
    assertTrue(str_contains($routes,'academic-backfill/{id:\\d+}/process'));
    assertTrue(str_contains($view,'Sincronização dos alunos atuais'));
    assertTrue(str_contains($view,'Processar próximo lote'));
    assertTrue(str_contains($view,'Pendências recentes'));
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
        $caller=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,1)[0]??[];
        $location=isset($caller['line'])?' (linha '.(int)$caller['line'].')':'';
        throw new RuntimeException($message.$location);
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
