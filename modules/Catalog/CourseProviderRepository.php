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

    /** @param list<array<string,mixed>> $courses @return array{received:int,created:int,updated:int,unavailable:int,contents:int,content_created:int,content_updated:int,content_unavailable:int} */
    public function synchronize(array $courses): array
    {
        return $this->synchronizeProvider(self::PROVIDER, $courses);
    }

    /** @param list<array<string,mixed>> $courses @return array{received:int,created:int,updated:int,unavailable:int,contents:int,content_created:int,content_updated:int,content_unavailable:int} */
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
        $contentCount = 0;
        $contentCreated = 0;
        $contentUpdated = 0;
        $contentUnavailable = 0;

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
                    $id = (int)$this->database->lastInsertId();
                    $created++;
                }

                if ($providerCode === 'conted_tech') {
                    $contentResult = $this->synchronizeCourseContents(
                        $providerId,
                        $catalogId,
                        $id,
                        is_array($course['conteudos'] ?? null) ? $course['conteudos'] : [],
                        $now,
                    );
                    $contentCount += $contentResult['received'];
                    $contentCreated += $contentResult['created'];
                    $contentUpdated += $contentResult['updated'];
                }
            }

            $unavailable = 0;
            if ($seen !== []) {
                $placeholders = implode(',', array_fill(0, count($seen), '?'));
                $statement = $this->database->prepare("UPDATE provider_courses SET is_available=0,sync_state='removed',last_changed_at=NOW() WHERE provider_id=? AND external_key NOT IN ($placeholders) AND is_available=1");
                $statement->execute(array_merge([$providerId], $seen));
                $unavailable = $statement->rowCount();
            }

            if ($providerCode === 'conted_tech') {
                $contentsRemoved = $this->database->prepare("UPDATE provider_catalog_contents SET is_available=0,sync_state='removed',last_changed_at=NOW() WHERE provider_id=:provider AND last_seen_at<:seen AND is_available=1");
                $contentsRemoved->execute(['provider' => $providerId, 'seen' => $now]);
                $contentUnavailable = $contentsRemoved->rowCount();
            }

            $this->database->prepare("UPDATE course_provider_integrations SET last_sync_status='success',last_synced_at=NOW(),last_error=NULL,consecutive_failures=0,last_created_count=:created,last_updated_count=:updated,last_unavailable_count=:unavailable,next_retry_at=NULL WHERE id=:id")->execute(['id' => $providerId, 'created' => $created, 'updated' => $updated, 'unavailable' => $unavailable]);
            $this->database->commit();
            return ['received' => count($courses), 'created' => $created, 'updated' => $updated, 'unavailable' => $unavailable, 'contents' => $contentCount, 'content_created' => $contentCreated, 'content_updated' => $contentUpdated, 'content_unavailable' => $contentUnavailable];
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw $exception;
        }
    }

    /**
     * @param list<array<string,mixed>> $contents
     * @return array{received:int,created:int,updated:int}
     */
    private function synchronizeCourseContents(int $providerId, int $catalogId, int $courseId, array $contents, string $seenAt): array
    {
        if ($courseId < 1) return ['received' => 0, 'created' => 0, 'updated' => 0];
        $linked = [];
        $created = 0;
        $updated = 0;

        foreach ($contents as $content) {
            if (!is_array($content)) continue;
            $external = trim((string)($content['batch'] ?? ''));
            $type = trim((string)($content['type'] ?? 'unit')) ?: 'unit';
            $name = trim((string)($content['name'] ?? ''));
            if ($external === '' || $name === '') continue;
            $raw = is_array($content['raw'] ?? null) ? $content['raw'] : $content;
            $hash = hash('sha256', json_encode(['name' => $name, 'type' => $type, 'batch' => $external, 'raw' => $raw], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            $exists = $this->database->prepare('SELECT id,content_hash FROM provider_catalog_contents WHERE provider_id=:provider AND content_type=:type AND external_key=:external LIMIT 1');
            $exists->execute(['provider' => $providerId, 'type' => $type, 'external' => $external]);
            $row = $exists->fetch();
            $contentId = is_array($row) ? (int)$row['id'] : 0;
            $syncState = $contentId < 1 ? 'new' : (hash_equals((string)($row['content_hash'] ?? ''), $hash) ? 'unchanged' : 'changed');
            $rawJson = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            if ($contentId > 0) {
                $this->database->prepare("UPDATE provider_catalog_contents SET catalog_id=:catalog,name=:name,is_available=1,raw_payload=:raw,content_hash=:hash,sync_state=:state,last_changed_at=IF(:changed='changed',:changed_at,last_changed_at),last_seen_at=:seen WHERE id=:id")->execute([
                    'catalog' => $catalogId, 'name' => $name, 'raw' => $rawJson, 'hash' => $hash,
                    'state' => $syncState, 'changed' => $syncState, 'changed_at' => $seenAt,
                    'seen' => $seenAt, 'id' => $contentId,
                ]);
                if ($syncState === 'changed') $updated++;
            } else {
                $this->database->prepare("INSERT INTO provider_catalog_contents(provider_id,catalog_id,external_key,content_type,name,is_available,raw_payload,content_hash,sync_state,first_seen_at,last_seen_at,last_changed_at) VALUES(:provider,:catalog,:external,:type,:name,1,:raw,:hash,'new',:first_seen,:last_seen,:changed_at)")->execute([
                    'provider' => $providerId, 'catalog' => $catalogId, 'external' => $external,
                    'type' => $type, 'name' => $name, 'raw' => $rawJson, 'hash' => $hash,
                    'first_seen' => $seenAt, 'last_seen' => $seenAt, 'changed_at' => $seenAt,
                ]);
                $contentId = (int)$this->database->lastInsertId();
                $created++;
            }

            if ($contentId < 1) continue;
            $linked[] = $contentId;
            $semester = isset($content['semester']) && is_numeric($content['semester']) ? max(1, (int)$content['semester']) : null;
            $discipline = trim((string)($content['discipline'] ?? ''));
            $this->database->prepare('INSERT INTO provider_course_content_links(provider_course_id,provider_content_id,semester_number,discipline_name,position) VALUES(:course,:content,:semester,:discipline,:position) ON DUPLICATE KEY UPDATE semester_number=VALUES(semester_number),discipline_name=VALUES(discipline_name),position=VALUES(position)')->execute([
                'course' => $courseId, 'content' => $contentId, 'semester' => $semester,
                'discipline' => $discipline !== '' ? $discipline : null,
                'position' => max(0, (int)($content['position'] ?? 0)),
            ]);
        }

        if ($linked === []) {
            $this->database->prepare('DELETE FROM provider_course_content_links WHERE provider_course_id=:course')->execute(['course' => $courseId]);
        } else {
            $marks = implode(',', array_fill(0, count($linked), '?'));
            $delete = $this->database->prepare("DELETE FROM provider_course_content_links WHERE provider_course_id=? AND provider_content_id NOT IN ($marks)");
            $delete->execute(array_merge([$courseId], $linked));
        }

        return ['received' => count($linked), 'created' => $created, 'updated' => $updated];
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
        $statement = $this->database->query("SELECT catalog.id,catalog.code,catalog.name,catalog.description,catalog.execution_environment,catalog.is_globally_enabled,
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
            CASE WHEN provider.provider_code IN ('escola_avancada','iesde','conted_tech') THEN 1 WHEN catalog.code='ava-cursos' THEN 1 ELSE 0 END adapter_ready,
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
                ELSE (SELECT COUNT(*) FROM provider_courses course WHERE course.catalog_id=catalog.id AND course.is_available=1 AND course.is_globally_enabled=1)
            END course_count,
            CASE WHEN catalog.code='ava-cursos'
                THEN (SELECT COUNT(*) FROM moodle_courses course WHERE course.visible=1)
                ELSE (SELECT COUNT(*) FROM provider_courses course WHERE course.catalog_id=catalog.id AND course.review_status='approved' AND course.release_status IN ('released','published') AND course.is_available=1 AND course.is_globally_enabled=1)
            END approved_count,
            (SELECT COUNT(*) FROM provider_catalog_contents content WHERE content.catalog_id=catalog.id AND content.is_available=1 AND content.is_globally_enabled=1) content_count,
            (SELECT COUNT(*) FROM organizations organization LEFT JOIN organization_course_catalog_access access ON access.organization_id=organization.id AND access.course_catalog_id=catalog.id WHERE organization.status='active' AND COALESCE(access.is_enabled,1)=1) organization_count
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
        $statement = $this->database->query("SELECT pc.*,COALESCE(NULLIF(pc.commercial_name,''),pc.name) effective_name,COALESCE(NULLIF(pc.commercial_description,''),pc.description) effective_description,COALESCE(NULLIF(pc.commercial_cover_url,''),pc.cover_url) effective_cover_url,COALESCE(NULLIF(pc.commercial_category,''),pc.category) effective_category,COALESCE(NULLIF(pc.commercial_workload,''),pc.workload) effective_workload,COALESCE(NULLIF(pc.commercial_certificate,''),pc.certificate) effective_certificate,c.name catalog_name,c.code catalog_code,c.is_globally_enabled catalog_globally_enabled,p.provider_code,p.name provider_name,asset.id media_asset_id,asset.source media_source FROM provider_courses pc INNER JOIN course_catalogs c ON c.id=pc.catalog_id INNER JOIN course_provider_integrations p ON p.id=pc.provider_id LEFT JOIN catalog_media_assets asset ON asset.entity_type='course' AND asset.entity_id=pc.id AND asset.purpose='cover' AND asset.generation_status='ready' ORDER BY c.name,pc.is_available DESC,pc.category,pc.name");
        return $statement->fetchAll() ?: [];
    }

    /** @return array<string,mixed>|null */
    public function catalogEntity(string $entityType, int $entityId): ?array
    {
        if (!in_array($entityType, ['course', 'content'], true) || $entityId < 1) return null;
        if($entityType==='course')$sql="SELECT entity.id,catalog.code catalog_code,COALESCE(NULLIF(entity.commercial_name,''),entity.name) name,COALESCE(NULLIF(entity.commercial_description,''),entity.description) description,COALESCE(NULLIF(entity.commercial_category,''),entity.category) category FROM provider_courses entity INNER JOIN course_catalogs catalog ON catalog.id=entity.catalog_id WHERE entity.id=:id LIMIT 1";
        else$sql="SELECT entity.id,catalog.code catalog_code,
            COALESCE(NULLIF(entity.commercial_name,''),entity.name) name,
            COALESCE(NULLIF(entity.commercial_description,''),(SELECT COALESCE(NULLIF(parent.commercial_description,''),NULLIF(parent.description,'')) FROM provider_course_content_links inherited_link INNER JOIN provider_courses parent ON parent.id=inherited_link.provider_course_id WHERE inherited_link.provider_content_id=entity.id ORDER BY inherited_link.position,inherited_link.provider_course_id LIMIT 1),'') description,
            COALESCE(NULLIF(entity.commercial_category,''),(SELECT COALESCE(NULLIF(parent.commercial_category,''),NULLIF(parent.category,''),NULLIF(inherited_link.discipline_name,'')) FROM provider_course_content_links inherited_link INNER JOIN provider_courses parent ON parent.id=inherited_link.provider_course_id WHERE inherited_link.provider_content_id=entity.id ORDER BY inherited_link.position,inherited_link.provider_course_id LIMIT 1),'Conteúdo individual') category
            FROM provider_catalog_contents entity INNER JOIN course_catalogs catalog ON catalog.id=entity.catalog_id WHERE entity.id=:id LIMIT 1";
        $statement = $this->database->prepare($sql);
        $statement->execute(['id' => $entityId]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function setCatalogGlobalAvailability(int $catalogId, bool $enabled, ?int $userId): void
    {
        $statement = $this->database->prepare('UPDATE course_catalogs SET is_globally_enabled=:enabled WHERE id=:id');
        $statement->execute(['enabled' => (int)$enabled, 'id' => $catalogId]);
        if ($statement->rowCount() !== 1) {
            $exists = $this->database->prepare('SELECT 1 FROM course_catalogs WHERE id=:id');
            $exists->execute(['id' => $catalogId]);
            if ($exists->fetchColumn() === false) throw new RuntimeException('Catálogo não encontrado.');
        }
        if (!$enabled) {
            $this->database->prepare('UPDATE organization_provider_course_offers offer INNER JOIN provider_courses course ON course.id=offer.provider_course_id SET offer.is_visible=0,offer.updated_by=:user WHERE course.catalog_id=:catalog')->execute(['user' => $userId, 'catalog' => $catalogId]);
            $this->database->prepare('UPDATE organization_provider_content_offers offer INNER JOIN provider_catalog_contents content ON content.id=offer.provider_content_id SET offer.is_visible=0,offer.updated_by=:user WHERE content.catalog_id=:catalog')->execute(['user' => $userId, 'catalog' => $catalogId]);
        }
    }

    public function setItemGlobalAvailability(string $itemType, int $itemId, bool $enabled, ?int $userId): void
    {
        if (!in_array($itemType, ['course', 'content'], true)) throw new RuntimeException('Tipo de item do catálogo inválido.');
        $table = $itemType === 'course' ? 'provider_courses' : 'provider_catalog_contents';
        $offerTable = $itemType === 'course' ? 'organization_provider_course_offers' : 'organization_provider_content_offers';
        $offerColumn = $itemType === 'course' ? 'provider_course_id' : 'provider_content_id';
        $statement = $this->database->prepare("UPDATE {$table} SET is_globally_enabled=:enabled WHERE id=:id");
        $statement->execute(['enabled' => (int)$enabled, 'id' => $itemId]);
        if ($statement->rowCount() !== 1) {
            $exists = $this->database->prepare("SELECT 1 FROM {$table} WHERE id=:id");
            $exists->execute(['id' => $itemId]);
            if ($exists->fetchColumn() === false) throw new RuntimeException('Curso ou conteúdo não encontrado.');
        }
        if (!$enabled) $this->database->prepare("UPDATE {$offerTable} SET is_visible=0,updated_by=:user WHERE {$offerColumn}=:item")->execute(['user' => $userId, 'item' => $itemId]);
    }

    public function setOrganizationItemAvailability(int $organizationId, string $itemType, int $itemId, bool $enabled, ?int $userId): void
    {
        if (!in_array($itemType, ['course', 'content'], true)) throw new RuntimeException('Tipo de item do catálogo inválido.');
        if ($this->catalogEntity($itemType, $itemId) === null) throw new RuntimeException('Curso ou conteúdo não encontrado.');
        $organization = $this->database->prepare('SELECT 1 FROM organizations WHERE id=:id');
        $organization->execute(['id' => $organizationId]);
        if ($organization->fetchColumn() === false) throw new RuntimeException('Franquia não encontrada.');
        $statement = $this->database->prepare('INSERT INTO organization_catalog_item_access(organization_id,item_type,item_id,is_enabled,updated_by) VALUES(:organization,:type,:item,:enabled,:user) ON DUPLICATE KEY UPDATE is_enabled=VALUES(is_enabled),updated_by=VALUES(updated_by)');
        $statement->execute(['organization' => $organizationId, 'type' => $itemType, 'item' => $itemId, 'enabled' => (int)$enabled, 'user' => $userId]);
        if (!$enabled) {
            $offerTable = $itemType === 'course' ? 'organization_provider_course_offers' : 'organization_provider_content_offers';
            $offerColumn = $itemType === 'course' ? 'provider_course_id' : 'provider_content_id';
            $this->database->prepare("UPDATE {$offerTable} SET is_visible=0,updated_by=:user WHERE organization_id=:organization AND {$offerColumn}=:item")->execute(['user' => $userId, 'organization' => $organizationId, 'item' => $itemId]);
        }
    }

    /** @param array<string,mixed> $data */
    public function saveMediaAsset(string $entityType, int $entityId, array $data, ?int $userId): int
    {
        if ($this->catalogEntity($entityType, $entityId) === null) throw new RuntimeException('Curso ou conteúdo do catálogo não encontrado.');
        $statement = $this->database->prepare("INSERT INTO catalog_media_assets(entity_type,entity_id,purpose,storage_path,mime_type,width,height,file_size,source,generation_provider,generation_prompt,generation_status,generation_error,generated_at,created_by,updated_by) VALUES(:type,:entity,'cover',:path,:mime,:width,:height,:size,:source,:provider,:prompt,:status,:error,:generated_at,:created_user,:updated_user) ON DUPLICATE KEY UPDATE storage_path=VALUES(storage_path),mime_type=VALUES(mime_type),width=VALUES(width),height=VALUES(height),file_size=VALUES(file_size),source=VALUES(source),generation_provider=VALUES(generation_provider),generation_prompt=VALUES(generation_prompt),generation_status=VALUES(generation_status),generation_error=VALUES(generation_error),generated_at=VALUES(generated_at),updated_by=VALUES(updated_by)");
        $statement->execute([
            'type' => $entityType, 'entity' => $entityId,
            'path' => $data['storage_path'] ?? null, 'mime' => $data['mime_type'] ?? null,
            'width' => $data['width'] ?? null, 'height' => $data['height'] ?? null,
            'size' => $data['file_size'] ?? null, 'source' => $data['source'] ?? 'upload',
            'provider' => $data['generation_provider'] ?? null, 'prompt' => $data['generation_prompt'] ?? null,
            'status' => $data['generation_status'] ?? 'ready', 'error' => $data['generation_error'] ?? null,
            'generated_at' => $data['generated_at'] ?? null,
            'created_user' => $userId, 'updated_user' => $userId,
        ]);
        $id = (int)$this->database->lastInsertId();
        if ($id > 0) return $id;
        $current = $this->entityMediaAsset($entityType, $entityId);
        return (int)($current['id'] ?? 0);
    }

    /** @return array<string,mixed>|null */
    public function mediaAsset(int $id): ?array
    {
        $statement = $this->database->prepare("SELECT * FROM catalog_media_assets WHERE id=:id AND purpose='cover' LIMIT 1");
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function entityMediaAsset(string $entityType, int $entityId): ?array
    {
        if (!in_array($entityType, ['course', 'content'], true) || $entityId < 1) return null;
        $statement = $this->database->prepare("SELECT * FROM catalog_media_assets WHERE entity_type=:type AND entity_id=:entity AND purpose='cover' LIMIT 1");
        $statement->execute(['type' => $entityType, 'entity' => $entityId]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function deleteMediaAsset(string $entityType, int $entityId): ?array
    {
        $current = $this->entityMediaAsset($entityType, $entityId);
        if ($current === null) return null;
        $statement = $this->database->prepare('DELETE FROM catalog_media_assets WHERE id=:id');
        $statement->execute(['id' => (int)$current['id']]);
        return $current;
    }

    /**
     * @return array{items:list<array<string,mixed>>,total:int,page:int,pages:int,per_page:int}
     */
    public function catalogContents(string $providerCode, string $query = '', int $page = 1, int $perPage = 50): array
    {
        $providerCode = trim($providerCode);
        $query = trim($query);
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        $where = 'provider.provider_code=?';
        $params = [$providerCode];
        if ($query !== '') {
            $where .= " AND (content.name LIKE ? OR content.commercial_name LIKE ? OR content.external_key LIKE ? OR EXISTS(SELECT 1 FROM provider_course_content_links search_link INNER JOIN provider_courses search_course ON search_course.id=search_link.provider_course_id WHERE search_link.provider_content_id=content.id AND (search_link.discipline_name LIKE ? OR search_course.name LIKE ?)))";
            $needle = '%' . $query . '%';
            array_push($params, $needle, $needle, $needle, $needle, $needle);
        }

        $count = $this->database->prepare("SELECT COUNT(*) FROM provider_catalog_contents content INNER JOIN course_provider_integrations provider ON provider.id=content.provider_id WHERE $where");
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT content.*,
            COALESCE(NULLIF(content.commercial_name,''),content.name) effective_name,
            COALESCE(NULLIF(content.commercial_description,''),NULLIF(MAX(course.commercial_description),''),NULLIF(MAX(course.description),''),'') effective_description,
            COALESCE(NULLIF(content.commercial_category,''),NULLIF(MAX(course.commercial_category),''),NULLIF(MAX(course.category),''),MIN(link.discipline_name),'Conteúdo individual') effective_category,
            COALESCE(NULLIF(content.commercial_workload,''),NULLIF(MAX(course.commercial_workload),''),NULLIF(MAX(course.workload),''),'') effective_workload,
            COALESCE(NULLIF(content.commercial_cover_url,''),NULLIF(MAX(course.commercial_cover_url),''),NULLIF(MAX(course.cover_url),''),'') effective_cover_url,
            COALESCE(
                (SELECT media.id FROM catalog_media_assets media WHERE media.entity_type='content' AND media.entity_id=content.id AND media.purpose='cover' AND media.generation_status='ready' LIMIT 1),
                (SELECT media.id FROM provider_course_content_links inherited_link INNER JOIN catalog_media_assets media ON media.entity_type='course' AND media.entity_id=inherited_link.provider_course_id AND media.purpose='cover' AND media.generation_status='ready' WHERE inherited_link.provider_content_id=content.id ORDER BY inherited_link.position,inherited_link.provider_course_id LIMIT 1)
            ) media_asset_id,
            CASE WHEN EXISTS(SELECT 1 FROM catalog_media_assets own_media WHERE own_media.entity_type='content' AND own_media.entity_id=content.id AND own_media.purpose='cover' AND own_media.generation_status='ready') THEN 'own' ELSE 'inherited' END media_inheritance,
            catalog.name catalog_name,catalog.code catalog_code,catalog.is_globally_enabled catalog_globally_enabled,provider.provider_code,provider.name provider_name,
            MIN(link.semester_number) semester_number,MIN(link.discipline_name) discipline_name,
            COUNT(DISTINCT link.provider_course_id) course_count,
            GROUP_CONCAT(DISTINCT course.name ORDER BY course.name SEPARATOR '||') course_names,
            (SELECT COUNT(*) FROM organization_provider_content_offers offer WHERE offer.provider_content_id=content.id AND offer.is_active=1) offer_count
            FROM provider_catalog_contents content
            INNER JOIN course_provider_integrations provider ON provider.id=content.provider_id
            INNER JOIN course_catalogs catalog ON catalog.id=content.catalog_id
            LEFT JOIN provider_course_content_links link ON link.provider_content_id=content.id
            LEFT JOIN provider_courses course ON course.id=link.provider_course_id
            WHERE $where
            GROUP BY content.id,catalog.id,provider.id
            ORDER BY content.is_available DESC,effective_name
            LIMIT $perPage OFFSET $offset";
        $statement = $this->database->prepare($sql);
        $statement->execute($params);

        return [
            'items' => $statement->fetchAll() ?: [],
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    /** @param array<string,mixed> $metadata */
    public function reviewContent(int $contentId, string $status, string $releaseStatus, array $metadata, ?int $userId): void
    {
        if (!in_array($status, ['imported', 'reviewing', 'approved', 'rejected'], true)) throw new RuntimeException('Situação da curadoria do conteúdo inválida.');
        if (!in_array($releaseStatus, ['private', 'released', 'published'], true)) throw new RuntimeException('Liberação comercial do conteúdo inválida.');
        if ($status !== 'approved') $releaseStatus = 'private';
        $name = trim((string)($metadata['commercial_name'] ?? ''));
        if ($status === 'approved' && $name === '') throw new RuntimeException('Informe o nome comercial antes de aprovar o conteúdo.');
        if (mb_strlen($name) > 500) throw new RuntimeException('O nome comercial do conteúdo é muito extenso.');
        $description = trim((string)($metadata['commercial_description'] ?? ''));
        $category = trim((string)($metadata['commercial_category'] ?? ''));
        $workload = trim((string)($metadata['commercial_workload'] ?? ''));
        $cover = trim((string)($metadata['commercial_cover_url'] ?? ''));
        $notes = trim((string)($metadata['review_notes'] ?? ''));
        if ($cover !== '' && filter_var($cover, FILTER_VALIDATE_URL) === false) throw new RuntimeException('Informe uma URL válida para a imagem do conteúdo.');

        $statement = $this->database->prepare('UPDATE provider_catalog_contents SET review_status=:status,release_status=:release,commercial_name=:name,commercial_description=:description,commercial_category=:category,commercial_workload=:workload,commercial_cover_url=:cover,review_notes=:notes,reviewed_by=:user,reviewed_at=NOW() WHERE id=:id');
        $statement->execute([
            'status' => $status, 'release' => $releaseStatus,
            'name' => $name !== '' ? $name : null,
            'description' => $description !== '' ? $description : null,
            'category' => $category !== '' ? $category : null,
            'workload' => $workload !== '' ? $workload : null,
            'cover' => $cover !== '' ? $cover : null,
            'notes' => $notes !== '' ? $notes : null,
            'user' => $userId, 'id' => $contentId,
        ]);
        if ($statement->rowCount() !== 1) {
            $exists = $this->database->prepare('SELECT 1 FROM provider_catalog_contents WHERE id=:id');
            $exists->execute(['id' => $contentId]);
            if ($exists->fetchColumn() === false) throw new RuntimeException('Conteúdo externo não encontrado.');
        }
        if ($status !== 'approved' || $releaseStatus === 'private') {
            $this->database->prepare('UPDATE organization_provider_content_offers SET is_visible=0,updated_by=:user WHERE provider_content_id=:content')->execute(['user' => $userId, 'content' => $contentId]);
        }
    }

    public function saveContentOffer(int $contentId, int $organizationId, string $name, string $description, float $price, int $installments, bool $visible, bool $active, ?int $userId): int
    {
        if ($organizationId < 1) throw new RuntimeException('Selecione a franquia.');
        $content = $this->database->prepare("SELECT content.id,content.name,content.commercial_name,content.commercial_description,
            COALESCE(NULLIF(content.commercial_description,''),(SELECT COALESCE(NULLIF(parent.commercial_description,''),NULLIF(parent.description,'')) FROM provider_course_content_links link INNER JOIN provider_courses parent ON parent.id=link.provider_course_id WHERE link.provider_content_id=content.id ORDER BY link.position,parent.id LIMIT 1)) effective_description,
            COALESCE(NULLIF(content.commercial_workload,''),(SELECT COALESCE(NULLIF(parent.commercial_workload,''),NULLIF(parent.workload,'')) FROM provider_course_content_links link INNER JOIN provider_courses parent ON parent.id=link.provider_course_id WHERE link.provider_content_id=content.id ORDER BY link.position,parent.id LIMIT 1)) effective_workload,
            COALESCE(NULLIF(content.commercial_cover_url,''),(SELECT COALESCE(NULLIF(parent.commercial_cover_url,''),NULLIF(parent.cover_url,'')) FROM provider_course_content_links link INNER JOIN provider_courses parent ON parent.id=link.provider_course_id WHERE link.provider_content_id=content.id ORDER BY link.position,parent.id LIMIT 1)) effective_cover_url,
            content.review_status,content.release_status,content.is_available,content.is_globally_enabled,catalog.is_globally_enabled catalog_globally_enabled,COALESCE(catalog_access.is_enabled,1) organization_catalog_enabled,COALESCE(item_access.is_enabled,1) organization_item_enabled FROM provider_catalog_contents content INNER JOIN course_catalogs catalog ON catalog.id=content.catalog_id LEFT JOIN organization_course_catalog_access catalog_access ON catalog_access.organization_id=:organization AND catalog_access.course_catalog_id=catalog.id LEFT JOIN organization_catalog_item_access item_access ON item_access.organization_id=:item_organization AND item_access.item_type='content' AND item_access.item_id=content.id WHERE content.id=:id LIMIT 1");
        $content->execute(['organization' => $organizationId, 'item_organization' => $organizationId, 'id' => $contentId]);
        $row = $content->fetch();
        if (!is_array($row)) throw new RuntimeException('Conteúdo externo não encontrado.');
        if (($row['review_status'] ?? '') !== 'approved' || !in_array((string)($row['release_status'] ?? ''), ['released', 'published'], true) || (int)($row['is_available'] ?? 0) !== 1) {
            throw new RuntimeException('Apenas conteúdos disponíveis, aprovados e liberados podem ser vendidos individualmente.');
        }
        if ((int)($row['catalog_globally_enabled'] ?? 0) !== 1 || (int)($row['is_globally_enabled'] ?? 0) !== 1 || (int)($row['organization_catalog_enabled'] ?? 0) !== 1 || (int)($row['organization_item_enabled'] ?? 0) !== 1) throw new RuntimeException('Este conteúdo está bloqueado globalmente ou para esta franquia.');
        $organization = $this->database->prepare("SELECT 1 FROM organizations WHERE id=:id AND status='active'");
        $organization->execute(['id' => $organizationId]);
        if ($organization->fetchColumn() === false) throw new RuntimeException('Franquia ativa não encontrada.');

        $name = trim($name) ?: trim((string)($row['commercial_name'] ?: $row['name']));
        $description = trim($description);
        $price = round($price, 2);
        $installments = max(1, min(60, $installments));
        if ($name === '' || mb_strlen($name) > 500) throw new RuntimeException('Informe um nome comercial válido para o conteúdo.');
        if ($price < 0) throw new RuntimeException('O preço não pode ser negativo.');
        if ($visible && $price < 5) throw new RuntimeException('Informe um preço de pelo menos R$ 5,00 antes de publicar o conteúdo.');

        if ($visible && $description === '' && trim((string)($row['effective_description'] ?? '')) === '') throw new RuntimeException('Complete a descrição comercial antes de exibir este conteúdo no site.');
        if ($visible && trim((string)($row['effective_cover_url'] ?? '')) === '') throw new RuntimeException('Cadastre uma capa comercial antes de exibir este conteúdo no site.');
        if ($visible && trim((string)($row['effective_workload'] ?? '')) === '') throw new RuntimeException('Informe a carga horária na curadoria antes de exibir este conteúdo no site.');

        $statement = $this->database->prepare("INSERT INTO organization_provider_content_offers(organization_id,provider_content_id,commercial_name,commercial_description,price,max_installments,sale_mode,is_visible,is_active,created_by,updated_by) VALUES(:organization,:content,:name,:description,:price,:installments,'assisted',:visible,:active,:user,:user) ON DUPLICATE KEY UPDATE commercial_name=VALUES(commercial_name),commercial_description=VALUES(commercial_description),price=VALUES(price),max_installments=VALUES(max_installments),sale_mode='assisted',is_visible=VALUES(is_visible),is_active=VALUES(is_active),updated_by=VALUES(updated_by)");
        $statement->execute(['organization' => $organizationId, 'content' => $contentId, 'name' => $name, 'description' => $description !== '' ? $description : null, 'price' => $price, 'installments' => $installments, 'visible' => (int)$visible, 'active' => (int)$active, 'user' => $userId]);
        $id = (int)$this->database->lastInsertId();
        if ($id > 0) return $id;
        $current = $this->database->prepare('SELECT id FROM organization_provider_content_offers WHERE organization_id=:organization AND provider_content_id=:content');
        $current->execute(['organization' => $organizationId, 'content' => $contentId]);
        return (int)$current->fetchColumn();
    }

    /** @return array<string,mixed> */
    public function contentAccessTargetForOffer(int $offerId): array
    {
        $statement = $this->database->prepare("SELECT offer.id offer_id,offer.organization_id,content.id content_id,content.content_type,content.external_key batch,COALESCE(NULLIF(offer.commercial_name,''),NULLIF(content.commercial_name,''),content.name) name,provider.provider_code FROM organization_provider_content_offers offer INNER JOIN provider_catalog_contents content ON content.id=offer.provider_content_id INNER JOIN course_catalogs catalog ON catalog.id=content.catalog_id INNER JOIN course_provider_integrations provider ON provider.id=content.provider_id LEFT JOIN organization_course_catalog_access catalog_access ON catalog_access.organization_id=offer.organization_id AND catalog_access.course_catalog_id=catalog.id LEFT JOIN organization_catalog_item_access item_access ON item_access.organization_id=offer.organization_id AND item_access.item_type='content' AND item_access.item_id=content.id WHERE offer.id=:id AND offer.is_active=1 AND content.is_available=1 AND content.is_globally_enabled=1 AND catalog.is_globally_enabled=1 AND COALESCE(catalog_access.is_enabled,1)=1 AND COALESCE(item_access.is_enabled,1)=1 AND content.review_status='approved' AND content.release_status IN ('released','published') LIMIT 1");
        $statement->execute(['id' => $offerId]);
        $row = $statement->fetch();
        if (!is_array($row)) throw new RuntimeException('Oferta de conteúdo individual não encontrada ou não liberada.');
        return $row;
    }

    /** @return list<array<string,mixed>> */
    public function catalogCourseOffersForOrganization(int $organizationId): array
    {
        $statement = $this->database->prepare("SELECT pc.id course_id,pc.catalog_id,pc.name source_name,pc.commercial_name approved_name,pc.commercial_description approved_description,COALESCE(NULLIF(pc.commercial_category,''),pc.category) category,COALESCE(NULLIF(pc.commercial_workload,''),pc.workload) workload,COALESCE(NULLIF(pc.commercial_cover_url,''),pc.cover_url) cover_url,pc.review_status,pc.release_status,pc.is_available,pc.is_globally_enabled,c.code catalog_code,c.name catalog_name,c.is_globally_enabled catalog_globally_enabled,p.name provider_name,COALESCE(item_access.is_enabled,1) organization_item_enabled,o.id offer_id,o.commercial_name offer_name,o.commercial_description offer_description,o.price,o.max_installments,o.is_visible,o.is_active FROM provider_courses pc INNER JOIN course_catalogs c ON c.id=pc.catalog_id INNER JOIN course_provider_integrations p ON p.id=pc.provider_id LEFT JOIN organization_catalog_item_access item_access ON item_access.organization_id=:item_organization AND item_access.item_type='course' AND item_access.item_id=pc.id LEFT JOIN organization_provider_course_offers o ON o.provider_course_id=pc.id AND o.organization_id=:offer_organization WHERE pc.review_status='approved' AND pc.release_status IN ('released','published') AND pc.is_available=1 ORDER BY c.name,COALESCE(NULLIF(pc.commercial_name,''),pc.name)");
        $statement->execute(['item_organization' => $organizationId, 'offer_organization' => $organizationId]);
        return $statement->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function catalogContentOffersForOrganization(int $organizationId): array
    {
        $statement = $this->database->prepare("SELECT content.id content_id,content.catalog_id,content.name source_name,content.commercial_name approved_name,content.commercial_description approved_description,COALESCE(NULLIF(content.commercial_category,''),'Conteúdo individual') category,content.commercial_workload workload,content.commercial_cover_url cover_url,content.review_status,content.release_status,content.is_available,content.is_globally_enabled,catalog.code catalog_code,catalog.name catalog_name,catalog.is_globally_enabled catalog_globally_enabled,provider.name provider_name,COALESCE(item_access.is_enabled,1) organization_item_enabled,offer.id offer_id,offer.commercial_name offer_name,offer.commercial_description offer_description,offer.price,offer.max_installments,offer.is_visible,offer.is_active FROM provider_catalog_contents content INNER JOIN course_catalogs catalog ON catalog.id=content.catalog_id INNER JOIN course_provider_integrations provider ON provider.id=content.provider_id LEFT JOIN organization_catalog_item_access item_access ON item_access.organization_id=:item_organization AND item_access.item_type='content' AND item_access.item_id=content.id LEFT JOIN organization_provider_content_offers offer ON offer.provider_content_id=content.id AND offer.organization_id=:offer_organization WHERE content.review_status='approved' AND content.release_status IN ('released','published') AND content.is_available=1 ORDER BY catalog.name,COALESCE(NULLIF(content.commercial_name,''),content.name)");
        $statement->execute(['item_organization' => $organizationId, 'offer_organization' => $organizationId]);
        return $statement->fetchAll() ?: [];
    }

    /**
     * Returns one compact, searchable page of the academic inventory for a franchise.
     * Access is inherited as enabled unless a global or franchise override blocks it.
     *
     * @return array{items:list<array<string,mixed>>,total:int,page:int,pages:int,per_page:int,catalog_code:string,item_type:string,query:string}
     */
    public function catalogItemsForOrganization(
        int $organizationId,
        string $catalogCode,
        string $itemType = 'course',
        string $query = '',
        int $page = 1,
        int $perPage = 20,
    ): array {
        $itemType = $itemType === 'content' ? 'content' : 'course';
        $catalogCode = trim($catalogCode);
        $query = trim($query);
        $page = max(1, $page);
        $perPage = max(10, min(50, $perPage));

        $catalog = $this->database->prepare('SELECT id,code FROM course_catalogs WHERE code=:code AND is_active=1 LIMIT 1');
        $catalog->execute(['code' => $catalogCode]);
        $catalogRow = $catalog->fetch();
        if (!is_array($catalogRow)) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => $perPage, 'catalog_code' => $catalogCode, 'item_type' => $itemType, 'query' => $query];
        }

        $catalogId = (int)$catalogRow['id'];
        $search = $query !== '' ? '%'.$query.'%' : null;
        $searchSql = $itemType === 'course'
            ? " AND CONCAT_WS(' ',course.name,course.commercial_name,course.category,course.remote_id) LIKE :search"
            : " AND CONCAT_WS(' ',content.name,content.commercial_name,content.commercial_category,content.external_key) LIKE :search";
        $whereSearch = $search === null ? '' : $searchSql;
        $countSql = $itemType === 'course'
            ? "SELECT COUNT(*) FROM provider_courses course WHERE course.catalog_id=:catalog AND course.is_available=1{$whereSearch}"
            : "SELECT COUNT(*) FROM provider_catalog_contents content WHERE content.catalog_id=:catalog AND content.is_available=1{$whereSearch}";
        $count = $this->database->prepare($countSql);
        $countParams = ['catalog' => $catalogId];
        if ($search !== null) $countParams['search'] = $search;
        $count->execute($countParams);
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        if ($itemType === 'course') {
            $sql = "SELECT course.id item_id,'course' item_type,course.name source_name,
                COALESCE(NULLIF(offer.commercial_name,''),NULLIF(course.commercial_name,''),course.name) effective_name,
                COALESCE(NULLIF(offer.commercial_description,''),NULLIF(course.commercial_description,''),NULLIF(course.description,'')) effective_description,
                COALESCE(NULLIF(course.commercial_cover_url,''),NULLIF(course.cover_url,'')) effective_cover_url,
                COALESCE(NULLIF(course.commercial_category,''),NULLIF(course.category,'')) category,
                COALESCE(NULLIF(course.commercial_workload,''),NULLIF(course.workload,'')) workload,
                course.review_status,course.release_status,course.is_globally_enabled,
                catalog.name catalog_name,catalog.code catalog_code,catalog.is_globally_enabled catalog_globally_enabled,
                COALESCE(catalog_access.is_enabled,1) organization_catalog_enabled,
                COALESCE(item_access.is_enabled,1) organization_item_enabled,
                offer.id offer_id,offer.commercial_name offer_name,offer.commercial_description offer_description,
                offer.price,offer.max_installments,offer.is_visible,offer.is_active
                FROM provider_courses course
                INNER JOIN course_catalogs catalog ON catalog.id=course.catalog_id
                LEFT JOIN organization_course_catalog_access catalog_access ON catalog_access.organization_id=:catalog_organization AND catalog_access.course_catalog_id=catalog.id
                LEFT JOIN organization_catalog_item_access item_access ON item_access.organization_id=:item_organization AND item_access.item_type='course' AND item_access.item_id=course.id
                LEFT JOIN organization_provider_course_offers offer ON offer.organization_id=:offer_organization AND offer.provider_course_id=course.id
                WHERE course.catalog_id=:catalog AND course.is_available=1{$whereSearch}
                ORDER BY COALESCE(NULLIF(offer.commercial_name,''),NULLIF(course.commercial_name,''),course.name)
                LIMIT {$perPage} OFFSET {$offset}";
        } else {
            $sql = "SELECT content.id item_id,'content' item_type,content.name source_name,
                COALESCE(NULLIF(offer.commercial_name,''),NULLIF(content.commercial_name,''),content.name) effective_name,
                COALESCE(NULLIF(offer.commercial_description,''),NULLIF(content.commercial_description,''),
                    (SELECT COALESCE(NULLIF(parent.commercial_description,''),NULLIF(parent.description,'')) FROM provider_course_content_links link INNER JOIN provider_courses parent ON parent.id=link.provider_course_id WHERE link.provider_content_id=content.id ORDER BY link.position,parent.id LIMIT 1)) effective_description,
                COALESCE(NULLIF(content.commercial_cover_url,''),
                    (SELECT COALESCE(NULLIF(parent.commercial_cover_url,''),NULLIF(parent.cover_url,'')) FROM provider_course_content_links link INNER JOIN provider_courses parent ON parent.id=link.provider_course_id WHERE link.provider_content_id=content.id ORDER BY link.position,parent.id LIMIT 1)) effective_cover_url,
                COALESCE(NULLIF(content.commercial_category,''),'Conteúdo individual') category,
                COALESCE(NULLIF(content.commercial_workload,''),
                    (SELECT COALESCE(NULLIF(parent.commercial_workload,''),NULLIF(parent.workload,'')) FROM provider_course_content_links link INNER JOIN provider_courses parent ON parent.id=link.provider_course_id WHERE link.provider_content_id=content.id ORDER BY link.position,parent.id LIMIT 1)) workload,
                content.review_status,content.release_status,content.is_globally_enabled,
                catalog.name catalog_name,catalog.code catalog_code,catalog.is_globally_enabled catalog_globally_enabled,
                COALESCE(catalog_access.is_enabled,1) organization_catalog_enabled,
                COALESCE(item_access.is_enabled,1) organization_item_enabled,
                offer.id offer_id,offer.commercial_name offer_name,offer.commercial_description offer_description,
                offer.price,offer.max_installments,offer.is_visible,offer.is_active
                FROM provider_catalog_contents content
                INNER JOIN course_catalogs catalog ON catalog.id=content.catalog_id
                LEFT JOIN organization_course_catalog_access catalog_access ON catalog_access.organization_id=:catalog_organization AND catalog_access.course_catalog_id=catalog.id
                LEFT JOIN organization_catalog_item_access item_access ON item_access.organization_id=:item_organization AND item_access.item_type='content' AND item_access.item_id=content.id
                LEFT JOIN organization_provider_content_offers offer ON offer.organization_id=:offer_organization AND offer.provider_content_id=content.id
                WHERE content.catalog_id=:catalog AND content.is_available=1{$whereSearch}
                ORDER BY COALESCE(NULLIF(offer.commercial_name,''),NULLIF(content.commercial_name,''),content.name)
                LIMIT {$perPage} OFFSET {$offset}";
        }

        $statement = $this->database->prepare($sql);
        $params = ['catalog_organization' => $organizationId, 'item_organization' => $organizationId, 'offer_organization' => $organizationId, 'catalog' => $catalogId];
        if ($search !== null) $params['search'] = $search;
        $statement->execute($params);
        $items = $statement->fetchAll() ?: [];

        foreach ($items as &$item) {
            $missing = [];
            if (trim((string)($item['effective_description'] ?? '')) === '') $missing[] = 'descrição';
            if (trim((string)($item['effective_cover_url'] ?? '')) === '') $missing[] = 'imagem';
            if (trim((string)($item['workload'] ?? '')) === '') $missing[] = 'carga horária';
            if ((float)($item['price'] ?? 0) < 5) $missing[] = 'preço';
            $curated = ($item['review_status'] ?? '') === 'approved' && in_array((string)($item['release_status'] ?? ''), ['released', 'published'], true);
            $available = (int)($item['catalog_globally_enabled'] ?? 1) === 1
                && (int)($item['organization_catalog_enabled'] ?? 1) === 1
                && (int)($item['is_globally_enabled'] ?? 1) === 1
                && (int)($item['organization_item_enabled'] ?? 1) === 1;
            $item['missing_commercial_fields'] = $missing;
            $item['is_curated'] = $curated;
            $item['is_effectively_enabled'] = $available;
            $item['is_commercially_ready'] = $curated && $missing === [] && $available;
        }
        unset($item);

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage, 'catalog_code' => $catalogCode, 'item_type' => $itemType, 'query' => $query];
    }

    /** @return list<array<string,mixed>> */
    public function catalogsForOrganization(int $organizationId): array
    {
        $statement = $this->database->prepare("SELECT catalog.id,catalog.code,catalog.name,catalog.description,catalog.execution_environment,catalog.is_globally_enabled,
            COALESCE(access.is_enabled,1) is_enabled,
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
                ELSE (SELECT COUNT(*) FROM provider_courses course WHERE course.catalog_id=catalog.id AND course.review_status='approved' AND course.release_status IN ('released','published') AND course.is_available=1 AND course.is_globally_enabled=1)
            END course_count,
            CASE WHEN catalog.code='ava-cursos'
                THEN (SELECT COUNT(*) FROM moodle_courses course WHERE course.visible=1)
                ELSE (SELECT COUNT(*) FROM provider_courses course WHERE course.catalog_id=catalog.id AND course.is_available=1)
            END inventory_count,
            (SELECT COUNT(*) FROM provider_catalog_contents content WHERE content.catalog_id=catalog.id AND content.is_available=1) content_count,
            (SELECT COUNT(*) FROM organization_provider_course_offers offer INNER JOIN provider_courses course ON course.id=offer.provider_course_id WHERE offer.organization_id=:offer_organization AND course.catalog_id=catalog.id AND offer.is_active=1) offer_count
            FROM course_catalogs catalog
            LEFT JOIN organization_course_catalog_access access ON access.course_catalog_id=catalog.id AND access.organization_id=:organization
            LEFT JOIN course_provider_integrations provider ON provider.catalog_id=catalog.id
            LEFT JOIN course_provider_capabilities capability ON capability.provider_id=provider.id
            WHERE catalog.is_active=1
            GROUP BY catalog.id,catalog.code,catalog.name,catalog.description,catalog.execution_environment,catalog.is_globally_enabled,access.is_enabled,access.markup_percent,access.default_max_installments,access.valid_from,access.valid_until,provider.name,provider.provider_code,provider.launch_url_template,provider.is_active,capability.automatic_enrollment,capability.single_sign_on,capability.progress_tracking,capability.grade_tracking,capability.certificate_access
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
        $policy = $this->database->prepare('SELECT access.* FROM organization_course_catalog_access access INNER JOIN course_catalogs catalog ON catalog.id=access.course_catalog_id AND catalog.is_globally_enabled=1 WHERE access.organization_id=:organization AND access.course_catalog_id=:catalog AND access.is_enabled=1 LIMIT 1');
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
            WHERE course.catalog_id=:catalog AND course.review_status='approved' AND course.release_status IN ('released','published') AND course.is_available=1 AND course.is_globally_enabled=1 AND NOT EXISTS(SELECT 1 FROM organization_catalog_item_access item_access WHERE item_access.organization_id=:item_organization AND item_access.item_type='course' AND item_access.item_id=course.id AND item_access.is_enabled=0)
            ON DUPLICATE KEY UPDATE price=VALUES(price),max_installments=VALUES(max_installments),is_active=1,updated_by=VALUES(updated_by)");
        $statement->execute(['organization' => $organizationId, 'catalog' => $catalogId, 'markup' => $markup, 'installments' => $installments, 'created_by' => $userId, 'updated_by' => $userId, 'item_organization' => $organizationId]);
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
        $course = $this->database->prepare("SELECT course.id,course.commercial_name,course.name,course.commercial_description,course.description,COALESCE(NULLIF(course.commercial_cover_url,''),NULLIF(course.cover_url,'')) effective_cover_url,COALESCE(NULLIF(course.commercial_workload,''),NULLIF(course.workload,'')) effective_workload,course.review_status,course.release_status,course.is_available,course.is_globally_enabled,catalog.is_globally_enabled catalog_globally_enabled,COALESCE(catalog_access.is_enabled,1) organization_catalog_enabled,COALESCE(item_access.is_enabled,1) organization_item_enabled FROM provider_courses course INNER JOIN course_catalogs catalog ON catalog.id=course.catalog_id LEFT JOIN organization_course_catalog_access catalog_access ON catalog_access.organization_id=:organization AND catalog_access.course_catalog_id=catalog.id LEFT JOIN organization_catalog_item_access item_access ON item_access.organization_id=:item_organization AND item_access.item_type='course' AND item_access.item_id=course.id WHERE course.id=:id LIMIT 1");
        $course->execute(['organization' => $organizationId, 'item_organization' => $organizationId, 'id' => $courseId]);
        $row = $course->fetch();
        if (!is_array($row)) throw new RuntimeException('Curso externo não encontrado.');
        if (($row['review_status'] ?? '') !== 'approved' || !in_array((string)($row['release_status'] ?? ''), ['released','published'], true) || (int)($row['is_available'] ?? 0) !== 1) throw new RuntimeException('Apenas cursos disponíveis, aprovados e liberados pelo ADM Central podem ser ofertados.');
        if ((int)($row['catalog_globally_enabled'] ?? 0) !== 1 || (int)($row['is_globally_enabled'] ?? 0) !== 1 || (int)($row['organization_catalog_enabled'] ?? 0) !== 1 || (int)($row['organization_item_enabled'] ?? 0) !== 1) throw new RuntimeException('Este curso está bloqueado globalmente ou para esta franquia.');

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

        $sourceDescription = (string)(($row['commercial_description'] ?? '') ?: ($row['description'] ?? ''));
        if ($visible && $description === '' && trim($sourceDescription) === '') throw new RuntimeException('Complete a descrição comercial antes de exibir este curso no site.');
        if ($visible && trim((string)($row['effective_cover_url'] ?? '')) === '') throw new RuntimeException('Cadastre uma capa comercial antes de exibir este curso no site.');
        if ($visible && trim((string)($row['effective_workload'] ?? '')) === '') throw new RuntimeException('Informe a carga horária na curadoria antes de exibir este curso no site.');

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

    public function deleteOrganizationContentOffer(int $offerId, int $organizationId): void
    {
        $statement = $this->database->prepare('DELETE FROM organization_provider_content_offers WHERE id=:id AND organization_id=:organization');
        $statement->execute(['id' => $offerId, 'organization' => $organizationId]);
        if ($statement->rowCount() !== 1) throw new RuntimeException('Oferta de conteúdo da franquia não encontrada.');
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
