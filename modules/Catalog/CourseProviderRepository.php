<?php

declare(strict_types=1);

namespace Interferencia\Modules\Catalog;

use Interferencia\Kernel\Security\SecretCipher;
use JsonException;
use PDO;
use RuntimeException;
use Throwable;

final readonly class CourseProviderRepository
{
    private const PROVIDER = 'escola_avancada';
    private const READY_PROVIDERS = ['escola_avancada', 'iesde', 'conted_tech'];
    private const DELIVERY_MODES = ['external_link', 'iframe', 'sso', 'lti'];

    public function __construct(private PDO $database, private SecretCipher $cipher) {}

    public function encryptionReady(): bool { return $this->cipher->ready(); }

    /** @return array<string,mixed> */
    public function settings(): array
    {
        return $this->settingsForProvider(self::PROVIDER, true);
    }

    /** @return array<string,mixed> */
    public function settingsForProvider(string $providerCode, bool $includeSecret = false): array
    {
        $statement = $this->database->prepare("SELECT p.*,c.name catalog_name,c.code catalog_code FROM course_provider_integrations p LEFT JOIN course_catalogs c ON c.id=p.catalog_id WHERE p.provider_code=:code LIMIT 1");
        $statement->execute(['code' => $providerCode]);
        $row = $statement->fetch();
        if (!is_array($row)) throw new RuntimeException('Fornecedor de cursos não encontrado.');
        $token = $includeSecret ? $this->cipher->decrypt(isset($row['token_encrypted']) ? (string)$row['token_encrypted'] : null) : '';
        $username = $includeSecret ? $this->cipher->decrypt(isset($row['username_encrypted']) ? (string)$row['username_encrypted'] : null) : '';
        $password = $includeSecret ? $this->cipher->decrypt(isset($row['password_encrypted']) ? (string)$row['password_encrypted'] : null) : '';
        $providerCode = (string)$row['provider_code'];
        $integrationMode = (string)($row['integration_mode'] ?? 'api');
        $configured = trim((string)($row['base_url'] ?? '')) !== '' && (string)($row['token_encrypted'] ?? '') !== '';
        if ($providerCode === 'iesde' && $integrationMode === 'lti13') {
            $configured = trim((string)($row['lti_platform_url'] ?? '')) !== '';
        } elseif (in_array($providerCode, ['iesde', 'conted_tech'], true)) {
            $configured = $configured && (string)($row['username_encrypted'] ?? '') !== '' && (string)($row['password_encrypted'] ?? '') !== '';
        }

        return [
            'id' => (int)$row['id'],
            'provider_code' => (string)$row['provider_code'],
            'name' => (string)$row['name'],
            'base_url' => (string)($row['base_url'] ?? ''),
            'token' => $token,
            'token_last4' => (string)($row['token_last4'] ?? ''),
            'username' => $username,
            'username_last4' => (string)($row['username_last4'] ?? ''),
            'password' => $password,
            'password_last4' => (string)($row['password_last4'] ?? ''),
            'integration_mode' => $integrationMode,
            'lti_integration_name' => (string)($row['lti_integration_name'] ?? ''),
            'lti_platform_url' => (string)($row['lti_platform_url'] ?? ''),
            'lti_registration_url' => (string)($row['lti_registration_url'] ?? ''),
            'lti_tool_url' => (string)($row['lti_tool_url'] ?? ''),
            'lti_deep_link_url' => (string)($row['lti_deep_link_url'] ?? ''),
            'lti_login_url' => (string)($row['lti_login_url'] ?? ''),
            'lti_jwks_url' => (string)($row['lti_jwks_url'] ?? ''),
            'lti_redirect_uris' => (string)($row['lti_redirect_uris'] ?? ''),
            'lti_client_id' => (string)($row['lti_client_id'] ?? ''),
            'lti_deployment_id' => (string)($row['lti_deployment_id'] ?? ''),
            'lti_status' => (string)($row['lti_status'] ?? 'draft'),
            'catalog_id' => (int)($row['catalog_id'] ?? 0),
            'catalog_name' => (string)($row['catalog_name'] ?? ''),
            'catalog_code' => (string)($row['catalog_code'] ?? ''),
            'delivery_mode' => (string)($row['delivery_mode'] ?? 'external_link'),
            'launch_url_template' => (string)($row['launch_url_template'] ?? ''),
            'is_active' => (int)($row['is_active'] ?? 0) === 1,
            'configured' => $configured,
            'adapter_ready' => in_array($providerCode, self::READY_PROVIDERS, true),
            'last_test_status' => (string)($row['last_test_status'] ?? 'not_tested'),
            'last_tested_at' => $row['last_tested_at'] ?? null,
            'last_sync_status' => (string)($row['last_sync_status'] ?? 'never'),
            'last_synced_at' => $row['last_synced_at'] ?? null,
            'last_error' => (string)($row['last_error'] ?? ''),
        ];
    }

    public function saveProvider(string $providerCode, array $input, ?int $userId): void
    {
        if ($providerCode === self::PROVIDER) {
            $this->save($input, $userId);
            return;
        }

        $current = $this->settingsForProvider($providerCode);
        if ($providerCode === 'iesde' && (string)($input['integration_mode'] ?? $current['integration_mode']) === 'lti13') {
            $this->saveLtiProvider($providerCode, $input, $userId);
            return;
        }
        $baseUrl = trim((string)($input['base_url'] ?? ''));
        $token = trim((string)($input['token'] ?? ''));
        $username = trim((string)($input['username'] ?? ''));
        $password = (string)($input['password'] ?? '');
        $delivery = trim((string)($input['delivery_mode'] ?? 'external_link'));
        $launch = trim((string)($input['launch_url_template'] ?? ''));
        $adapterReady = in_array($providerCode, self::READY_PROVIDERS, true);
        $active = $adapterReady && (bool)($input['is_active'] ?? false);
        if ($baseUrl === '' || filter_var($baseUrl, FILTER_VALIDATE_URL) === false || strtolower((string)parse_url($baseUrl, PHP_URL_SCHEME)) !== 'https') throw new RuntimeException('Informe a URL HTTPS oficial da API do fornecedor.');
        if (!in_array($delivery, self::DELIVERY_MODES, true)) throw new RuntimeException('Forma de acesso ao curso inválida.');
        if ($launch !== '' && (filter_var(str_replace(['{curso}', '{id}'], ['1', '1'], $launch), FILTER_VALIDATE_URL) === false || strtolower((string)parse_url($launch, PHP_URL_SCHEME)) !== 'https')) throw new RuntimeException('O endereço do AVA deve usar HTTPS.');
        if ($token === '' && !$current['configured']) throw new RuntimeException('Informe a chave ou token da API.');
        if ($providerCode === 'iesde' && !$current['configured'] && ($username === '' || trim($password) === '')) throw new RuntimeException('Informe o usuário e a senha HTTP Digest do Portal AVA.');
        if ($providerCode === 'conted_tech' && !$current['configured'] && ($username === '' || trim($password) === '')) throw new RuntimeException('Informe a Integration Key e a Secret Key da CONTED TECH.');
        if (($token !== '' || $username !== '' || trim($password) !== '') && !$this->cipher->ready()) throw new RuntimeException('A chave-mestra de criptografia ainda não está disponível.');

        $sql = 'UPDATE course_provider_integrations SET base_url=:base,delivery_mode=:delivery,launch_url_template=:launch,is_active=:active,updated_by=:user';
        $params = ['base' => rtrim($baseUrl, '/'), 'delivery' => $delivery, 'launch' => $launch !== '' ? $launch : null, 'active' => (int)$active, 'user' => $userId, 'code' => $providerCode];
        if ($token !== '') {
            $sql .= ',token_encrypted=:token,token_last4=:last4';
            $params['token'] = $this->cipher->encrypt($token);
            $params['last4'] = substr($token, -4);
        }
        if ($username !== '') {
            $sql .= ',username_encrypted=:username,username_last4=:username_last4';
            $params['username'] = $this->cipher->encrypt($username);
            $params['username_last4'] = substr($username, -4);
        }
        if (trim($password) !== '') {
            $sql .= ',password_encrypted=:password,password_last4=:password_last4';
            $params['password'] = $this->cipher->encrypt($password);
            $params['password_last4'] = substr($password, -4);
        }
        $sql .= ' WHERE provider_code=:code';
        $this->database->prepare($sql)->execute($params);
    }

    public function saveLtiProvider(string $providerCode, array $input, ?int $userId): void
    {
        $name = trim((string)($input['lti_integration_name'] ?? ''));
        $platform = rtrim(trim((string)($input['lti_platform_url'] ?? '')), '/');
        $registration = trim((string)($input['lti_registration_url'] ?? ''));
        $tool = trim((string)($input['lti_tool_url'] ?? ''));
        $deepLink = trim((string)($input['lti_deep_link_url'] ?? ''));
        $login = trim((string)($input['lti_login_url'] ?? ''));
        $jwks = trim((string)($input['lti_jwks_url'] ?? ''));
        $redirects = trim((string)($input['lti_redirect_uris'] ?? ''));
        $clientId = trim((string)($input['lti_client_id'] ?? ''));
        $deploymentId = trim((string)($input['lti_deployment_id'] ?? ''));

        if ($name === '') throw new RuntimeException('Informe o nome da integração LTI.');
        $this->assertHttpsUrl($platform, 'Informe a URL HTTPS do LMS.');
        foreach ([[$registration, 'URL de registro dinâmico'], [$tool, 'URL da ferramenta'], [$deepLink, 'URL de seleção de conteúdo'], [$login, 'URL de início de login'], [$jwks, 'URL do conjunto de chaves']] as [$url, $label]) {
            if ($url !== '') $this->assertHttpsUrl($url, $label.' deve usar HTTPS.');
        }
        foreach (preg_split('/\R+/', $redirects) ?: [] as $redirect) {
            if (trim($redirect) !== '') $this->assertHttpsUrl(trim($redirect), 'Toda URI de redirecionamento deve usar HTTPS.');
        }

        $toolConfigured = $registration !== '' || ($tool !== '' && $deepLink !== '' && $login !== '' && $jwks !== '');
        $moodleConfigured = $clientId !== '' && $deploymentId !== '';
        $complete = $toolConfigured && $moodleConfigured;
        $active = $complete && (bool)($input['is_active'] ?? false);
        $status = $active ? 'active' : ($moodleConfigured ? 'moodle_configured' : ($toolConfigured ? 'tool_received' : 'provider_started'));

        $this->database->prepare("UPDATE course_provider_integrations SET
            integration_mode='lti13',lti_integration_name=:name,lti_platform_url=:platform,
            lti_registration_url=:registration,lti_tool_url=:tool,lti_deep_link_url=:deep_link,lti_login_url=:login,
            lti_jwks_url=:jwks,lti_redirect_uris=:redirects,lti_client_id=:client,
            lti_deployment_id=:deployment,lti_status=:status,delivery_mode='lti',
            is_active=:active,updated_by=:user,last_error=NULL WHERE provider_code=:code")->execute([
                'name' => $name, 'platform' => $platform,
                'registration' => $registration !== '' ? $registration : null,
                'tool' => $tool !== '' ? $tool : null, 'login' => $login !== '' ? $login : null,
                'deep_link' => $deepLink !== '' ? $deepLink : null,
                'jwks' => $jwks !== '' ? $jwks : null, 'redirects' => $redirects !== '' ? $redirects : null,
                'client' => $clientId !== '' ? $clientId : null,
                'deployment' => $deploymentId !== '' ? $deploymentId : null,
                'status' => $status, 'active' => (int)$active, 'user' => $userId, 'code' => $providerCode,
            ]);
    }

    private function assertHttpsUrl(string $url, string $message): void
    {
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false || strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            throw new RuntimeException($message);
        }
    }

    public function save(array $input, ?int $userId): void
    {
        $baseUrl = trim((string)($input['base_url'] ?? ''));
        $token = trim((string)($input['token'] ?? ''));
        $catalogName = trim((string)($input['catalog_name'] ?? ''));
        $delivery = trim((string)($input['delivery_mode'] ?? 'external_link'));
        $launch = trim((string)($input['launch_url_template'] ?? ''));
        $active = (bool)($input['is_active'] ?? false);

        if ($baseUrl === '' || filter_var($baseUrl, FILTER_VALIDATE_URL) === false || strtolower((string)parse_url($baseUrl, PHP_URL_SCHEME)) !== 'https') throw new RuntimeException('Informe a URL HTTPS especial fornecida pela Escola Avançada.');
        if ($catalogName === '') throw new RuntimeException('Informe o nome do catálogo.');
        if (!in_array($delivery, self::DELIVERY_MODES, true)) throw new RuntimeException('Forma de acesso ao curso inválida.');
        if ($launch !== '' && (filter_var(str_replace(['{curso}', '{id}'], ['1', '1'], $launch), FILTER_VALIDATE_URL) === false || strtolower((string)parse_url($launch, PHP_URL_SCHEME)) !== 'https')) throw new RuntimeException('O modelo do link de acesso deve usar HTTPS.');

        $current = $this->settings();
        if ($token === '' && !$current['configured']) throw new RuntimeException('Informe o token da Escola Avançada.');
        if ($token !== '' && !$this->cipher->ready()) throw new RuntimeException('A chave-mestra de criptografia ainda não está disponível.');

        $this->database->beginTransaction();
        try {
            $catalog = $this->database->prepare("INSERT INTO course_catalogs(code,name,description,is_active) VALUES('catalogo-pro',:name,'Cursos de fornecedores externos, com comercialização controlada pelo Mundo Inter.',1) ON DUPLICATE KEY UPDATE name=VALUES(name),is_active=1");
            $catalog->execute(['name' => $catalogName]);
            $catalogId = (int)$this->database->query("SELECT id FROM course_catalogs WHERE code='catalogo-pro'")->fetchColumn();

            $encrypted = $token !== '' ? $this->cipher->encrypt($token) : null;
            $last4 = $token !== '' ? substr($token, -4) : null;
            $sql = "UPDATE course_provider_integrations SET name='Escola Avançada',base_url=:base,catalog_id=:catalog,delivery_mode=:delivery,launch_url_template=:launch,is_active=:active,updated_by=:user";
            $params = ['base' => rtrim($baseUrl, '/'), 'catalog' => $catalogId, 'delivery' => $delivery, 'launch' => $launch !== '' ? $launch : null, 'active' => (int)$active, 'user' => $userId, 'code' => self::PROVIDER];
            if ($token !== '') { $sql .= ',token_encrypted=:token,token_last4=:last4'; $params['token'] = $encrypted; $params['last4'] = $last4; }
            $sql .= ' WHERE provider_code=:code';
            $this->database->prepare($sql)->execute($params);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw $exception;
        }
    }

    public function recordTest(?string $error, string $providerCode = self::PROVIDER): void
    {
        $this->database->prepare("UPDATE course_provider_integrations SET last_test_status=:status,last_tested_at=NOW(),last_error=:error WHERE provider_code=:code")->execute([
            'status' => $error === null ? 'success' : 'failed',
            'error' => $error,
            'code' => $providerCode,
        ]);
    }

    /** @param list<array<string,mixed>> $courses @return array{received:int,created:int,updated:int,unavailable:int} */
    public function synchronize(array $courses): array
    {
        return $this->synchronizeProvider(self::PROVIDER, $courses);
    }

    /** @param list<array<string,mixed>> $courses @return array{received:int,created:int,updated:int,unavailable:int} */
    public function synchronizeProvider(string $providerCode, array $courses): array
    {
        $settings = $this->settingsForProvider($providerCode, true);
        if ((int)$settings['id'] < 1) throw new RuntimeException('Integração do fornecedor não encontrada.');
        $catalogId = (int)$settings['catalog_id'];
        if ($catalogId < 1) throw new RuntimeException('Catálogo do fornecedor não encontrado.');

        $providerId = (int)$settings['id'];
        $now = date('Y-m-d H:i:s');
        $seen = [];
        $created = 0;
        $updated = 0;

        $this->database->beginTransaction();
        try {
            foreach ($courses as $course) {
                $normalized = $this->normalizeCourse($course);
                if ($normalized['name'] === '') continue;
                $externalKey = $this->externalKey($course, $normalized);
                $seen[] = $externalKey;

                $exists = $this->database->prepare('SELECT id,content_hash FROM provider_courses WHERE provider_id=:provider AND external_key=:external LIMIT 1');
                $exists->execute(['provider' => $providerId, 'external' => $externalKey]);
                $existing = $exists->fetch();
                $id = is_array($existing) ? (int)$existing['id'] : 0;
                $contentHash = hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                $syncState = $id < 1 ? 'new' : (hash_equals((string)($existing['content_hash'] ?? ''), $contentHash) ? 'unchanged' : 'changed');

                $payload = [
                    'provider' => $providerId, 'catalog' => $catalogId, 'external' => $externalKey,
                    'remote_id' => $normalized['remote_id'] ?: null, 'name' => $normalized['name'], 'slug' => $normalized['slug'] ?: null,
                    'summary' => $normalized['short_description'] ?: null, 'description' => $normalized['description'] ?: null, 'category' => $normalized['category'] ?: null,
                    'workload' => $normalized['workload'] ?: null, 'certificate' => $normalized['certificate'] ?: null,
                    'access_type' => $normalized['access_type'] ?: null, 'supplier_updated' => $normalized['supplier_updated_at'] ?: null, 'lessons' => $normalized['lesson_count'],
                    'cover' => $normalized['cover_url'] ?: null, 'price' => $normalized['price'],
                    'promotional' => $normalized['promotional_price'], 'installments' => $normalized['installments'],
                    'remote_status' => $normalized['status'] ?: null,
                    'raw' => json_encode($course, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'content_hash' => $contentHash,
                    'sync_state' => $syncState,
                    'seen' => $now,
                ];

                if ($id > 0) {
                    $updatePayload = $payload;
                    unset($updatePayload['provider'], $updatePayload['external']);
                    $updatePayload['id'] = $id;
                    $this->database->prepare("UPDATE provider_courses SET catalog_id=:catalog,remote_id=:remote_id,name=:name,slug=:slug,short_description=:summary,description=:description,category=:category,workload=:workload,certificate=:certificate,access_type=:access_type,supplier_updated_at=:supplier_updated,lesson_count=:lessons,cover_url=:cover,remote_reference_price=:price,remote_promotional_price=:promotional,remote_installments=:installments,remote_status=:remote_status,is_available=1,raw_payload=:raw,content_hash=:content_hash,sync_state=:sync_state,last_changed_at=IF(:sync_state_change='changed',:seen_change,last_changed_at),last_seen_at=:seen WHERE id=:id")->execute($updatePayload + ['sync_state_change' => $syncState, 'seen_change' => $now]);
                    if ($syncState === 'changed') $updated++;
                } else {
                    $insertPayload = $payload;
                    unset($insertPayload['seen']);
                    $insertPayload += ['changed_at' => $now, 'first_seen' => $now, 'last_seen' => $now];
                    $this->database->prepare("INSERT INTO provider_courses(provider_id,catalog_id,external_key,remote_id,name,slug,short_description,description,category,workload,certificate,access_type,supplier_updated_at,lesson_count,cover_url,remote_reference_price,remote_promotional_price,remote_installments,remote_status,is_available,raw_payload,content_hash,sync_state,last_changed_at,first_seen_at,last_seen_at) VALUES(:provider,:catalog,:external,:remote_id,:name,:slug,:summary,:description,:category,:workload,:certificate,:access_type,:supplier_updated,:lessons,:cover,:price,:promotional,:installments,:remote_status,1,:raw,:content_hash,:sync_state,:changed_at,:first_seen,:last_seen)")->execute($insertPayload);
                    $created++;
                }
            }

            $unavailable = 0;
            if ($seen !== []) {
                $placeholders = implode(',', array_fill(0, count($seen), '?'));
                $statement = $this->database->prepare("UPDATE provider_courses SET is_available=0,sync_state='removed',last_changed_at=NOW() WHERE provider_id=? AND external_key NOT IN ($placeholders) AND is_available=1");
                $statement->execute(array_merge([$providerId], $seen));
                $unavailable = $statement->rowCount();
            }

            $this->database->prepare("UPDATE course_provider_integrations SET last_sync_status='success',last_synced_at=NOW(),last_error=NULL,consecutive_failures=0,last_created_count=:created,last_updated_count=:updated,last_unavailable_count=:unavailable,next_retry_at=NULL WHERE id=:id")->execute(['id' => $providerId, 'created' => $created, 'updated' => $updated, 'unavailable' => $unavailable]);
            $this->database->commit();
            return ['received' => count($courses), 'created' => $created, 'updated' => $updated, 'unavailable' => $unavailable];
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw $exception;
        }
    }

    /** @return list<array<string,mixed>> */
    public function courses(): array
    {
        $statement = $this->database->query("SELECT pc.*,c.name catalog_name FROM provider_courses pc INNER JOIN course_catalogs c ON c.id=pc.catalog_id INNER JOIN course_provider_integrations p ON p.id=pc.provider_id WHERE p.provider_code='escola_avancada' ORDER BY pc.is_available DESC,pc.category,pc.name");
        return $statement->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function organizations(): array
    {
        $statement = $this->database->query("SELECT id,display_name,legal_name FROM organizations WHERE status='active' ORDER BY display_name,legal_name");
        return $statement->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function providerCatalogRegistry(): array
    {
        $statement = $this->database->query("SELECT catalog.id,catalog.code,catalog.name,catalog.description,catalog.execution_environment,
            CASE WHEN catalog.code='ava-cursos' THEN 'AVA Cursos' ELSE COALESCE(provider.name,'Fornecedor a definir') END provider_name,
            COALESCE(provider.provider_code,'ava_cursos') provider_code,
            COALESCE(provider.base_url,'') base_url,COALESCE(provider.token_last4,'') token_last4,
            COALESCE(provider.username_last4,'') username_last4,COALESCE(provider.password_last4,'') password_last4,
            COALESCE(provider.delivery_mode,'external_link') delivery_mode,
            COALESCE(provider.integration_mode,'api') integration_mode,
            COALESCE(provider.lti_integration_name,'') lti_integration_name,
            COALESCE(provider.lti_platform_url,'') lti_platform_url,
            COALESCE(provider.lti_registration_url,'') lti_registration_url,
            COALESCE(provider.lti_tool_url,'') lti_tool_url,
            COALESCE(provider.lti_deep_link_url,'') lti_deep_link_url,
            COALESCE(provider.lti_login_url,'') lti_login_url,
            COALESCE(provider.lti_jwks_url,'') lti_jwks_url,
            COALESCE(provider.lti_redirect_uris,'') lti_redirect_uris,
            COALESCE(provider.lti_client_id,'') lti_client_id,
            COALESCE(provider.lti_deployment_id,'') lti_deployment_id,
            COALESCE(provider.lti_status,'draft') lti_status,
            CASE WHEN catalog.code='ava-cursos' THEN 'https://avacursos.com.br/{franquia}' ELSE COALESCE(provider.launch_url_template,'') END ava_url,
            CASE WHEN catalog.code='ava-cursos' THEN 1 ELSE COALESCE(provider.is_active,0) END integration_active,
            CASE WHEN catalog.code='ava-cursos' THEN 1
                WHEN provider.provider_code='iesde' AND provider.integration_mode='lti13' AND provider.lti_platform_url IS NOT NULL AND provider.lti_platform_url<>'' THEN 1
                WHEN provider.provider_code='iesde' AND provider.base_url IS NOT NULL AND provider.base_url<>'' AND provider.token_encrypted IS NOT NULL AND provider.token_encrypted<>'' AND provider.username_encrypted IS NOT NULL AND provider.username_encrypted<>'' AND provider.password_encrypted IS NOT NULL AND provider.password_encrypted<>'' THEN 1
                WHEN provider.provider_code='conted_tech' AND provider.base_url IS NOT NULL AND provider.base_url<>'' AND provider.token_encrypted IS NOT NULL AND provider.token_encrypted<>'' AND provider.username_encrypted IS NOT NULL AND provider.username_encrypted<>'' AND provider.password_encrypted IS NOT NULL AND provider.password_encrypted<>'' THEN 1
                WHEN provider.provider_code NOT IN ('iesde','conted_tech') AND provider.base_url IS NOT NULL AND provider.base_url<>'' AND provider.token_encrypted IS NOT NULL AND provider.token_encrypted<>'' THEN 1 ELSE 0 END configured,
            CASE WHEN provider.provider_code IN ('escola_avancada','iesde') THEN 1 WHEN catalog.code='ava-cursos' THEN 1 ELSE 0 END adapter_ready,
            CASE WHEN catalog.code='ava-cursos' THEN 'success' ELSE COALESCE(provider.last_test_status,'not_tested') END integration_status,
            CASE WHEN catalog.code='ava-cursos' THEN 'success' ELSE COALESCE(provider.last_sync_status,'never') END sync_status,
            provider.last_tested_at,provider.last_synced_at,COALESCE(provider.last_error,'') last_error,
            COALESCE(provider.consecutive_failures,0) consecutive_failures,
            COALESCE(provider.last_created_count,0) last_created_count,
            COALESCE(provider.last_updated_count,0) last_updated_count,
            COALESCE(provider.last_unavailable_count,0) last_unavailable_count,
            provider.next_retry_at,
            CASE WHEN catalog.code='ava-cursos' THEN 1 ELSE COALESCE(capability.catalog_sync,0) END capability_catalog_sync,
            CASE WHEN catalog.code='ava-cursos' THEN 1 ELSE COALESCE(capability.automatic_enrollment,0) END capability_automatic_enrollment,
            CASE WHEN catalog.code='ava-cursos' THEN 1 ELSE COALESCE(capability.single_sign_on,0) END capability_single_sign_on,
            CASE WHEN catalog.code='ava-cursos' THEN 1 ELSE COALESCE(capability.progress_tracking,0) END capability_progress_tracking,
            CASE WHEN catalog.code='ava-cursos' THEN 1 ELSE COALESCE(capability.grade_tracking,0) END capability_grade_tracking,
            CASE WHEN catalog.code='ava-cursos' THEN 1 ELSE COALESCE(capability.certificate_access,0) END capability_certificate_access,
            CASE WHEN catalog.code='ava-cursos' THEN 1 ELSE COALESCE(capability.suspend_access,0) END capability_suspend_access,
            CASE WHEN catalog.code='ava-cursos' THEN 1 ELSE COALESCE(capability.send_access,0) END capability_send_access,
            CASE WHEN catalog.code='ava-cursos'
                THEN (SELECT COUNT(*) FROM moodle_courses course WHERE course.visible=1)
                ELSE (SELECT COUNT(*) FROM provider_courses course WHERE course.catalog_id=catalog.id AND course.is_available=1)
            END course_count,
            CASE WHEN catalog.code='ava-cursos'
                THEN (SELECT COUNT(*) FROM moodle_courses course WHERE course.visible=1)
                ELSE (SELECT COUNT(*) FROM provider_courses course WHERE course.catalog_id=catalog.id AND course.review_status='approved' AND course.release_status IN ('released','published') AND course.is_available=1)
            END approved_count,
            (SELECT COUNT(*) FROM organization_course_catalog_access access WHERE access.course_catalog_id=catalog.id AND access.is_enabled=1) organization_count
            FROM course_catalogs catalog
            LEFT JOIN course_provider_integrations provider ON provider.catalog_id=catalog.id
            LEFT JOIN course_provider_capabilities capability ON capability.provider_id=provider.id
            WHERE catalog.is_active=1
            ORDER BY FIELD(catalog.code,'ava-cursos','catalogo-pro','catalogo-up','catalogo-master','catalogo-expert','catalogo-cefe','catalogo-conclusao','catalogo-prepara','catalogo-drive'),catalog.name");
        return $statement->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function allCourses(): array
    {
        $statement = $this->database->query("SELECT pc.*,COALESCE(NULLIF(pc.commercial_name,''),pc.name) effective_name,COALESCE(NULLIF(pc.commercial_description,''),pc.description) effective_description,COALESCE(NULLIF(pc.commercial_cover_url,''),pc.cover_url) effective_cover_url,COALESCE(NULLIF(pc.commercial_category,''),pc.category) effective_category,COALESCE(NULLIF(pc.commercial_workload,''),pc.workload) effective_workload,COALESCE(NULLIF(pc.commercial_certificate,''),pc.certificate) effective_certificate,c.name catalog_name,c.code catalog_code,p.provider_code,p.name provider_name FROM provider_courses pc INNER JOIN course_catalogs c ON c.id=pc.catalog_id INNER JOIN course_provider_integrations p ON p.id=pc.provider_id ORDER BY c.name,pc.is_available DESC,pc.category,pc.name");
        return $statement->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function catalogCourseOffersForOrganization(int $organizationId): array
    {
        $statement = $this->database->prepare("SELECT pc.id course_id,pc.catalog_id,pc.name source_name,pc.commercial_name approved_name,pc.commercial_description approved_description,COALESCE(NULLIF(pc.commercial_category,''),pc.category) category,COALESCE(NULLIF(pc.commercial_workload,''),pc.workload) workload,COALESCE(NULLIF(pc.commercial_cover_url,''),pc.cover_url) cover_url,pc.review_status,pc.release_status,pc.is_available,c.code catalog_code,c.name catalog_name,p.name provider_name,o.id offer_id,o.commercial_name offer_name,o.commercial_description offer_description,o.price,o.max_installments,o.is_visible,o.is_active FROM provider_courses pc INNER JOIN course_catalogs c ON c.id=pc.catalog_id INNER JOIN course_provider_integrations p ON p.id=pc.provider_id LEFT JOIN organization_provider_course_offers o ON o.provider_course_id=pc.id AND o.organization_id=:organization WHERE pc.review_status='approved' AND pc.release_status IN ('released','published') AND pc.is_available=1 ORDER BY c.name,COALESCE(NULLIF(pc.commercial_name,''),pc.name)");
        $statement->execute(['organization' => $organizationId]);
        return $statement->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function catalogsForOrganization(int $organizationId): array
    {
        $statement = $this->database->prepare("SELECT catalog.id,catalog.code,catalog.name,catalog.description,catalog.execution_environment,
            COALESCE(access.is_enabled,CASE WHEN catalog.code='ava-cursos' THEN 1 ELSE 0 END) is_enabled,
            COALESCE(access.markup_percent,0) markup_percent,COALESCE(access.default_max_installments,1) default_max_installments,
            access.valid_from,access.valid_until,
            CASE WHEN catalog.code='ava-cursos' THEN 'AVA Cursos' ELSE COALESCE(provider.name,'Fornecedor externo') END provider_name,
            COALESCE(provider.provider_code,'ava_cursos') provider_code,
            CASE WHEN catalog.code='ava-cursos' THEN 'https://avacursos.com.br/{franquia}' ELSE COALESCE(provider.launch_url_template,'') END ava_url,
            CASE WHEN catalog.code='ava-cursos' THEN 1 ELSE COALESCE(provider.is_active,0) END integration_active,
            CASE WHEN catalog.code='ava-cursos' THEN 1 ELSE COALESCE(capability.automatic_enrollment,0) END automatic_enrollment,
            CASE WHEN catalog.code='ava-cursos' THEN 1 ELSE COALESCE(capability.single_sign_on,0) END single_sign_on,
            CASE WHEN catalog.code='ava-cursos' THEN 1 ELSE COALESCE(capability.progress_tracking,0) END progress_tracking,
            CASE WHEN catalog.code='ava-cursos' THEN 1 ELSE COALESCE(capability.grade_tracking,0) END grade_tracking,
            CASE WHEN catalog.code='ava-cursos' THEN 1 ELSE COALESCE(capability.certificate_access,0) END certificate_access,
            CASE WHEN catalog.code='ava-cursos'
                THEN (SELECT COUNT(*) FROM moodle_courses course WHERE course.visible=1)
                ELSE (SELECT COUNT(*) FROM provider_courses course WHERE course.catalog_id=catalog.id AND course.review_status='approved' AND course.release_status IN ('released','published') AND course.is_available=1)
            END course_count,
            (SELECT COUNT(*) FROM organization_provider_course_offers offer INNER JOIN provider_courses course ON course.id=offer.provider_course_id WHERE offer.organization_id=:offer_organization AND course.catalog_id=catalog.id AND offer.is_active=1) offer_count
            FROM course_catalogs catalog
            LEFT JOIN organization_course_catalog_access access ON access.course_catalog_id=catalog.id AND access.organization_id=:organization
            LEFT JOIN course_provider_integrations provider ON provider.catalog_id=catalog.id
            LEFT JOIN course_provider_capabilities capability ON capability.provider_id=provider.id
            WHERE catalog.is_active=1
            GROUP BY catalog.id,catalog.code,catalog.name,catalog.description,catalog.execution_environment,access.is_enabled,access.markup_percent,access.default_max_installments,access.valid_from,access.valid_until,provider.name,provider.provider_code,provider.launch_url_template,provider.is_active,capability.automatic_enrollment,capability.single_sign_on,capability.progress_tracking,capability.grade_tracking,capability.certificate_access
            ORDER BY FIELD(catalog.code,'ava-cursos','catalogo-pro','catalogo-up','catalogo-master','catalogo-expert','catalogo-cefe','catalogo-conclusao','catalogo-prepara','catalogo-drive'),catalog.name");
        $statement->execute(['organization' => $organizationId, 'offer_organization' => $organizationId]);
        return $statement->fetchAll() ?: [];
    }

    /** @param list<int|string> $enabledCatalogIds @param array<int|string,array<string,mixed>> $policies */
    public function saveCatalogAccess(int $organizationId, array $enabledCatalogIds, ?int $userId, array $policies = []): void
    {
        $organization = $this->database->prepare('SELECT 1 FROM organizations WHERE id=:id');
        $organization->execute(['id' => $organizationId]);
        if ($organization->fetchColumn() === false) throw new RuntimeException('Franquia não encontrada.');

        $enabled = array_values(array_unique(array_filter(array_map('intval', $enabledCatalogIds), static fn(int $id): bool => $id > 0)));
        $catalogs = $this->database->query('SELECT id FROM course_catalogs WHERE is_active=1')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $statement = $this->database->prepare('INSERT INTO organization_course_catalog_access(organization_id,course_catalog_id,is_enabled,markup_percent,default_max_installments,valid_from,valid_until,updated_by) VALUES(:organization,:catalog,:enabled,:markup,:installments,:valid_from,:valid_until,:user) ON DUPLICATE KEY UPDATE is_enabled=VALUES(is_enabled),markup_percent=VALUES(markup_percent),default_max_installments=VALUES(default_max_installments),valid_from=VALUES(valid_from),valid_until=VALUES(valid_until),updated_by=VALUES(updated_by)');

        $this->database->beginTransaction();
        try {
            foreach ($catalogs as $catalogId) {
                $id = (int)$catalogId;
                $policy = is_array($policies[$id] ?? null) ? $policies[$id] : (is_array($policies[(string)$id] ?? null) ? $policies[(string)$id] : []);
                $markup = round((float)str_replace(',', '.', (string)($policy['markup_percent'] ?? '0')), 4);
                $installments = max(1, min(60, (int)($policy['default_max_installments'] ?? 1)));
                $validFrom = $this->dateOrNull((string)($policy['valid_from'] ?? ''));
                $validUntil = $this->dateOrNull((string)($policy['valid_until'] ?? ''));
                if ($markup < -100 || $markup > 1000) throw new RuntimeException('O ajuste em lote deve ficar entre -100% e 1.000%.');
                if ($validFrom !== null && $validUntil !== null && $validUntil < $validFrom) throw new RuntimeException('A validade final da regra não pode ser anterior ao início.');
                $statement->execute(['organization' => $organizationId, 'catalog' => $id, 'enabled' => (int)in_array($id, $enabled, true), 'markup' => $markup, 'installments' => $installments, 'valid_from' => $validFrom, 'valid_until' => $validUntil, 'user' => $userId]);
            }
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw $exception;
        }
    }

    public function applyCatalogPolicy(int $organizationId, int $catalogId, ?int $userId): int
    {
        $policy = $this->database->prepare('SELECT * FROM organization_course_catalog_access WHERE organization_id=:organization AND course_catalog_id=:catalog AND is_enabled=1 LIMIT 1');
        $policy->execute(['organization' => $organizationId, 'catalog' => $catalogId]);
        $row = $policy->fetch();
        if (!is_array($row)) throw new RuntimeException('Ative e salve primeiro este catálogo para a franquia.');
        $today = date('Y-m-d');
        if (($row['valid_from'] ?? null) !== null && (string)$row['valid_from'] > $today) throw new RuntimeException('Esta regra comercial ainda não entrou em vigor.');
        if (($row['valid_until'] ?? null) !== null && (string)$row['valid_until'] < $today) throw new RuntimeException('Esta regra comercial está vencida.');

        $markup = (float)$row['markup_percent'];
        $installments = max(1, min(60, (int)$row['default_max_installments']));
        $statement = $this->database->prepare("INSERT INTO organization_provider_course_offers(organization_id,provider_course_id,commercial_name,commercial_description,price,max_installments,sale_mode,is_visible,is_active,created_by,updated_by)
            SELECT :organization,course.id,COALESCE(NULLIF(course.commercial_name,''),course.name),course.commercial_description,
                ROUND(COALESCE(course.remote_promotional_price,course.remote_reference_price,0)*(1+(:markup/100)),2),:installments,'assisted',0,1,:created_by,:updated_by
            FROM provider_courses course
            WHERE course.catalog_id=:catalog AND course.review_status='approved' AND course.release_status IN ('released','published') AND course.is_available=1
            ON DUPLICATE KEY UPDATE price=VALUES(price),max_installments=VALUES(max_installments),is_active=1,updated_by=VALUES(updated_by)");
        $statement->execute(['organization' => $organizationId, 'catalog' => $catalogId, 'markup' => $markup, 'installments' => $installments, 'created_by' => $userId, 'updated_by' => $userId]);
        return $statement->rowCount();
    }

    /** @param array<string,mixed> $metadata */
    public function review(int $courseId, string $status, string $commercialName, string $commercialDescription, string $notes, ?int $userId, array $metadata = [], string $releaseStatus = 'private'): void
    {
        if ($status === 'pending') $status = 'imported';
        if (!in_array($status, ['imported', 'reviewing', 'approved', 'rejected'], true)) throw new RuntimeException('Situação da curadoria inválida.');
        if (!in_array($releaseStatus, ['private', 'released', 'published'], true)) throw new RuntimeException('Liberação comercial inválida.');
        if ($status !== 'approved') $releaseStatus = 'private';
        $commercialName = trim($commercialName);
        $commercialDescription = trim($commercialDescription);
        $notes = trim($notes);
        if ($status === 'approved' && $commercialName === '') throw new RuntimeException('Informe o nome comercial antes de aprovar o curso.');
        if (mb_strlen($commercialName) > 500) throw new RuntimeException('O nome comercial é muito extenso.');

        $cover = trim((string)($metadata['commercial_cover_url'] ?? ''));
        if ($cover !== '' && filter_var($cover, FILTER_VALIDATE_URL) === false) throw new RuntimeException('Informe uma URL válida para a imagem comercial.');
        $category = trim((string)($metadata['commercial_category'] ?? ''));
        $workload = trim((string)($metadata['commercial_workload'] ?? ''));
        $certificate = trim((string)($metadata['commercial_certificate'] ?? ''));

        $statement = $this->database->prepare('UPDATE provider_courses SET review_status=:status,release_status=:release_status,commercial_name=:name,commercial_description=:description,commercial_cover_url=:cover,commercial_category=:category,commercial_workload=:workload,commercial_certificate=:certificate,review_notes=:notes,reviewed_by=:user,reviewed_at=NOW() WHERE id=:id');
        $statement->execute([
            'status' => $status,
            'release_status' => $releaseStatus,
            'name' => $commercialName !== '' ? $commercialName : null,
            'description' => $commercialDescription !== '' ? $commercialDescription : null,
            'cover' => $cover !== '' ? $cover : null,
            'category' => $category !== '' ? $category : null,
            'workload' => $workload !== '' ? $workload : null,
            'certificate' => $certificate !== '' ? $certificate : null,
            'notes' => $notes !== '' ? $notes : null,
            'user' => $userId,
            'id' => $courseId,
        ]);
        if ($statement->rowCount() !== 1) {
            $exists = $this->database->prepare('SELECT 1 FROM provider_courses WHERE id=:id');
            $exists->execute(['id' => $courseId]);
            if ($exists->fetchColumn() === false) throw new RuntimeException('Curso externo não encontrado.');
        }

        if ($status !== 'approved' || $releaseStatus === 'private') {
            $this->database->prepare('UPDATE organization_provider_course_offers SET is_visible=0,updated_by=:user WHERE provider_course_id=:course')->execute(['user' => $userId, 'course' => $courseId]);
        }
    }

    /** @param array<string,mixed> $input */
    public function saveCapabilities(string $providerCode, array $input, ?int $userId): void
    {
        $settings = $this->settingsForProvider($providerCode);
        $statement = $this->database->prepare("INSERT INTO course_provider_capabilities(provider_id,catalog_sync,automatic_enrollment,single_sign_on,progress_tracking,grade_tracking,certificate_access,suspend_access,send_access,updated_by)
            VALUES(:provider,:catalog_sync,:automatic_enrollment,:single_sign_on,:progress_tracking,:grade_tracking,:certificate_access,:suspend_access,:send_access,:user)
            ON DUPLICATE KEY UPDATE catalog_sync=VALUES(catalog_sync),automatic_enrollment=VALUES(automatic_enrollment),single_sign_on=VALUES(single_sign_on),progress_tracking=VALUES(progress_tracking),grade_tracking=VALUES(grade_tracking),certificate_access=VALUES(certificate_access),suspend_access=VALUES(suspend_access),send_access=VALUES(send_access),updated_by=VALUES(updated_by)");
        $payload = ['provider' => (int)$settings['id'], 'user' => $userId];
        foreach (['catalog_sync','automatic_enrollment','single_sign_on','progress_tracking','grade_tracking','certificate_access','suspend_access','send_access'] as $capability) {
            $payload[$capability] = (int)(($input[$capability] ?? false) === true || (string)($input[$capability] ?? '') === '1');
        }
        $statement->execute($payload);
    }

    public function saveOffer(int $courseId, int $organizationId, string $name, string $description, float $price, int $installments, bool $visible, bool $active, ?int $userId): int
    {
        if ($organizationId < 1) throw new RuntimeException('Selecione a franquia.');
        $course = $this->database->prepare("SELECT id,commercial_name,name,review_status,release_status,is_available FROM provider_courses WHERE id=:id LIMIT 1");
        $course->execute(['id' => $courseId]);
        $row = $course->fetch();
        if (!is_array($row)) throw new RuntimeException('Curso externo não encontrado.');
        if (($row['review_status'] ?? '') !== 'approved' || !in_array((string)($row['release_status'] ?? ''), ['released','published'], true) || (int)($row['is_available'] ?? 0) !== 1) throw new RuntimeException('Apenas cursos disponíveis, aprovados e liberados pelo ADM Central podem ser ofertados.');

        $organization = $this->database->prepare("SELECT 1 FROM organizations WHERE id=:id AND status='active'");
        $organization->execute(['id' => $organizationId]);
        if ($organization->fetchColumn() === false) throw new RuntimeException('Franquia ativa não encontrada.');

        $name = trim($name) ?: trim((string)($row['commercial_name'] ?: $row['name']));
        $description = trim($description);
        $price = round($price, 2);
        $installments = max(1, min(60, $installments));
        if ($name === '' || mb_strlen($name) > 500) throw new RuntimeException('Informe um nome comercial válido.');
        if ($price < 0) throw new RuntimeException('O preço não pode ser negativo.');
        if ($visible && $price < 5) throw new RuntimeException('Informe um preço comercial de pelo menos R$ 5,00 antes de publicar.');

        $statement = $this->database->prepare("INSERT INTO organization_provider_course_offers(organization_id,provider_course_id,commercial_name,commercial_description,price,max_installments,sale_mode,is_visible,is_active,created_by,updated_by) VALUES(:organization,:course,:name,:description,:price,:installments,'assisted',:visible,:active,:user,:user) ON DUPLICATE KEY UPDATE commercial_name=VALUES(commercial_name),commercial_description=VALUES(commercial_description),price=VALUES(price),max_installments=VALUES(max_installments),sale_mode='assisted',is_visible=VALUES(is_visible),is_active=VALUES(is_active),updated_by=VALUES(updated_by)");
        $statement->execute(['organization' => $organizationId, 'course' => $courseId, 'name' => $name, 'description' => $description !== '' ? $description : null, 'price' => $price, 'installments' => $installments, 'visible' => (int)$visible, 'active' => (int)$active, 'user' => $userId]);

        $id = (int)$this->database->lastInsertId();
        if ($id > 0) return $id;
        $current = $this->database->prepare('SELECT id FROM organization_provider_course_offers WHERE organization_id=:organization AND provider_course_id=:course');
        $current->execute(['organization' => $organizationId, 'course' => $courseId]);
        return (int)$current->fetchColumn();
    }

    /** @return list<array<string,mixed>> */
    public function offers(): array
    {
        $statement = $this->database->query("SELECT o.*,org.display_name organization_name,COALESCE(NULLIF(o.commercial_name,''),NULLIF(pc.commercial_name,''),pc.name) course_name,c.name catalog_name,pc.review_status,pc.is_available FROM organization_provider_course_offers o INNER JOIN organizations org ON org.id=o.organization_id INNER JOIN provider_courses pc ON pc.id=o.provider_course_id INNER JOIN course_catalogs c ON c.id=pc.catalog_id ORDER BY org.display_name,course_name");
        return $statement->fetchAll() ?: [];
    }

    public function deleteOffer(int $offerId): void
    {
        $statement = $this->database->prepare('DELETE FROM organization_provider_course_offers WHERE id=:id');
        $statement->execute(['id' => $offerId]);
        if ($statement->rowCount() !== 1) throw new RuntimeException('Liberação de catálogo não encontrada.');
    }

    public function deleteOrganizationOffer(int $offerId, int $organizationId): void
    {
        $statement = $this->database->prepare('DELETE FROM organization_provider_course_offers WHERE id=:id AND organization_id=:organization');
        $statement->execute(['id' => $offerId, 'organization' => $organizationId]);
        if ($statement->rowCount() !== 1) throw new RuntimeException('Oferta da franquia não encontrada.');
    }

    /** @return array{total:int,available:int,categories:int,approved:int,offers:int} */
    public function summary(): array
    {
        $row = $this->database->query("SELECT COUNT(*) total,SUM(is_available=1) available,COUNT(DISTINCT NULLIF(category,'')) categories,SUM(review_status='approved') approved FROM provider_courses pc INNER JOIN course_provider_integrations p ON p.id=pc.provider_id WHERE p.provider_code='escola_avancada'")->fetch() ?: [];
        $offers = (int)$this->database->query('SELECT COUNT(*) FROM organization_provider_course_offers WHERE is_active=1')->fetchColumn();
        return ['total' => (int)($row['total'] ?? 0), 'available' => (int)($row['available'] ?? 0), 'categories' => (int)($row['categories'] ?? 0), 'approved' => (int)($row['approved'] ?? 0), 'offers' => $offers];
    }

    public function recordSyncFailure(string $error, string $providerCode = self::PROVIDER): void
    {
        $this->database->prepare("UPDATE course_provider_integrations SET last_sync_status='failed',last_synced_at=NOW(),last_error=:error,consecutive_failures=consecutive_failures+1,next_retry_at=DATE_ADD(NOW(),INTERVAL 15 MINUTE) WHERE provider_code=:code")->execute(['error' => mb_substr($error, 0, 2000), 'code' => $providerCode]);
    }

    /** @param array<string,mixed> $course @return array<string,mixed> */
    private function normalizeCourse(array $course): array
    {
        $name = trim((string)($course['nome'] ?? $course['Nome'] ?? $course['Curso'] ?? $course['curso'] ?? $course['titulo'] ?? $course['Titulo'] ?? ''));
        $supplierUpdated = trim((string)($course['updated_at'] ?? $course['AtualizadoEm'] ?? $course['data_atualizacao'] ?? $course['DataAtualizacao'] ?? ''));
        if ($supplierUpdated !== '') {
            $timestamp = strtotime($supplierUpdated);
            $supplierUpdated = $timestamp === false ? '' : date('Y-m-d H:i:s', $timestamp);
        }
        return [
            'remote_id' => trim((string)($course['id'] ?? $course['batch'] ?? $course['ID'] ?? $course['CursoID'] ?? $course['curso_id'] ?? $course['codigo'] ?? $course['Codigo'] ?? '')),
            'name' => $name,
            'slug' => trim((string)($course['slug'] ?? $course['Slug'] ?? $this->slug($name))),
            'short_description' => trim((string)($course['resumo'] ?? $course['Resumo'] ?? $course['ementa'] ?? $course['Ementa'] ?? '')),
            'description' => trim((string)($course['obs'] ?? $course['Obs'] ?? $course['descricao'] ?? $course['Descricao'] ?? $course['Descrição'] ?? '')),
            'category' => trim((string)($course['categoria_loja'] ?? $course['categoria_interna'] ?? $course['categoria'] ?? $course['Categoria'] ?? $course['CategoriaNome'] ?? '')),
            'workload' => trim((string)($course['carga_horaria'] ?? $course['CargaHoraria'] ?? $course['Carga_Horaria'] ?? '')),
            'certificate' => trim((string)($course['certificado'] ?? $course['Certificado'] ?? $course['tipo_certificado'] ?? '')),
            'access_type' => trim((string)($course['tipo_acesso'] ?? $course['TipoAcesso'] ?? $course['modalidade'] ?? $course['Modalidade'] ?? '')),
            'supplier_updated_at' => $supplierUpdated,
            'lesson_count' => max(0, (int)($course['aulas'] ?? $course['Aulas'] ?? $course['QtdAulas'] ?? 0)),
            'cover_url' => trim((string)($course['capa_image'] ?? $course['capa'] ?? $course['Capa'] ?? $course['Imagem'] ?? $course['imagem'] ?? '')),
            'price' => $this->money($course['preco'] ?? $course['Preco'] ?? null),
            'promotional_price' => $this->money($course['preco_promocional'] ?? $course['PrecoPromocional'] ?? null),
            'installments' => isset($course['parcelas']) && is_numeric($course['parcelas']) ? max(0, (int)$course['parcelas']) : (isset($course['Parcelas']) && is_numeric($course['Parcelas']) ? max(0, (int)$course['Parcelas']) : null),
            'status' => trim((string)($course['status'] ?? $course['Status'] ?? $course['Situacao'] ?? $course['Situação'] ?? '')),
        ];
    }

    private function slug(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $slug = strtolower(is_string($ascii) ? $ascii : $value);
        return trim((string)preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
    }

    /** @param array<string,mixed> $course @param array<string,mixed> $normalized */
    private function externalKey(array $course, array $normalized): string
    {
        $remote = (string)$normalized['remote_id'];
        if ($remote !== '') return hash('sha256', 'id:' . $remote);
        return hash('sha256', mb_strtolower((string)$normalized['name']) . '|' . (string)$normalized['cover_url']);
    }

    private function money(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        if (is_int($value) || is_float($value)) return round((float)$value, 2);
        $text = preg_replace('/[^0-9,.-]/', '', (string)$value) ?? '';
        if (str_contains($text, ',') && str_contains($text, '.')) $text = str_replace('.', '', $text);
        $text = str_replace(',', '.', $text);
        return is_numeric($text) ? round((float)$text, 2) : null;
    }

    private function dateOrNull(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return null;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date instanceof \DateTimeImmutable || $date->format('Y-m-d') !== $value) throw new RuntimeException('Informe as datas da regra no formato válido.');
        return $value;
    }
}
