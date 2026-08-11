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
    private const READY_PROVIDERS = ['escola_avancada', 'iesde'];
    private const DELIVERY_MODES = ['external_link', 'iframe', 'sso'];

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
        $configured = trim((string)($row['base_url'] ?? '')) !== '' && (string)($row['token_encrypted'] ?? '') !== '';
        if ($providerCode === 'iesde') {
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

                $exists = $this->database->prepare('SELECT id FROM provider_courses WHERE provider_id=:provider AND external_key=:external LIMIT 1');
                $exists->execute(['provider' => $providerId, 'external' => $externalKey]);
                $id = (int)($exists->fetchColumn() ?: 0);

                $payload = [
                    'provider' => $providerId, 'catalog' => $catalogId, 'external' => $externalKey,
                    'remote_id' => $normalized['remote_id'] ?: null, 'name' => $normalized['name'],
                    'description' => $normalized['description'] ?: null, 'category' => $normalized['category'] ?: null,
                    'workload' => $normalized['workload'] ?: null, 'lessons' => $normalized['lesson_count'],
                    'cover' => $normalized['cover_url'] ?: null, 'price' => $normalized['price'],
                    'promotional' => $normalized['promotional_price'], 'installments' => $normalized['installments'],
                    'remote_status' => $normalized['status'] ?: null,
                    'raw' => json_encode($course, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'seen' => $now,
                ];

                if ($id > 0) {
                    $updatePayload = $payload;
                    unset($updatePayload['provider'], $updatePayload['external']);
                    $updatePayload['id'] = $id;
                    $this->database->prepare('UPDATE provider_courses SET catalog_id=:catalog,remote_id=:remote_id,name=:name,description=:description,category=:category,workload=:workload,lesson_count=:lessons,cover_url=:cover,remote_reference_price=:price,remote_promotional_price=:promotional,remote_installments=:installments,remote_status=:remote_status,is_available=1,raw_payload=:raw,last_seen_at=:seen WHERE id=:id')->execute($updatePayload);
                    $updated++;
                } else {
                    $this->database->prepare('INSERT INTO provider_courses(provider_id,catalog_id,external_key,remote_id,name,description,category,workload,lesson_count,cover_url,remote_reference_price,remote_promotional_price,remote_installments,remote_status,is_available,raw_payload,first_seen_at,last_seen_at) VALUES(:provider,:catalog,:external,:remote_id,:name,:description,:category,:workload,:lessons,:cover,:price,:promotional,:installments,:remote_status,1,:raw,:seen,:seen)')->execute($payload);
                    $created++;
                }
            }

            $unavailable = 0;
            if ($seen !== []) {
                $placeholders = implode(',', array_fill(0, count($seen), '?'));
                $statement = $this->database->prepare("UPDATE provider_courses SET is_available=0 WHERE provider_id=? AND external_key NOT IN ($placeholders) AND is_available=1");
                $statement->execute(array_merge([$providerId], $seen));
                $unavailable = $statement->rowCount();
            }

            $this->database->prepare("UPDATE course_provider_integrations SET last_sync_status='success',last_synced_at=NOW(),last_error=NULL WHERE id=:id")->execute(['id' => $providerId]);
            $this->database->commit();
            return ['received' => count($courses), 'created' => $created, 'updated' => $updated, 'unavailable' => $unavailable];
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            $this->recordSyncFailure($exception->getMessage(), $providerCode);
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
        $statement = $this->database->query("SELECT catalog.id,catalog.code,catalog.name,catalog.description,
            CASE WHEN catalog.code='ava-cursos' THEN 'AVA Cursos' ELSE COALESCE(provider.name,'Fornecedor a definir') END provider_name,
            COALESCE(provider.provider_code,'ava_cursos') provider_code,
            COALESCE(provider.base_url,'') base_url,COALESCE(provider.token_last4,'') token_last4,
            COALESCE(provider.username_last4,'') username_last4,COALESCE(provider.password_last4,'') password_last4,
            COALESCE(provider.delivery_mode,'external_link') delivery_mode,
            CASE WHEN catalog.code='ava-cursos' THEN 'https://avacursos.com.br/{franquia}' ELSE COALESCE(provider.launch_url_template,'') END ava_url,
            CASE WHEN catalog.code='ava-cursos' THEN 1 ELSE COALESCE(provider.is_active,0) END integration_active,
            CASE WHEN catalog.code='ava-cursos' THEN 1
                WHEN provider.provider_code='iesde' AND provider.base_url IS NOT NULL AND provider.base_url<>'' AND provider.token_encrypted IS NOT NULL AND provider.token_encrypted<>'' AND provider.username_encrypted IS NOT NULL AND provider.username_encrypted<>'' AND provider.password_encrypted IS NOT NULL AND provider.password_encrypted<>'' THEN 1
                WHEN provider.provider_code<>'iesde' AND provider.base_url IS NOT NULL AND provider.base_url<>'' AND provider.token_encrypted IS NOT NULL AND provider.token_encrypted<>'' THEN 1 ELSE 0 END configured,
            CASE WHEN provider.provider_code IN ('escola_avancada','iesde') THEN 1 WHEN catalog.code='ava-cursos' THEN 1 ELSE 0 END adapter_ready,
            CASE WHEN catalog.code='ava-cursos' THEN 'success' ELSE COALESCE(provider.last_test_status,'not_tested') END integration_status,
            CASE WHEN catalog.code='ava-cursos' THEN 'success' ELSE COALESCE(provider.last_sync_status,'never') END sync_status,
            provider.last_tested_at,provider.last_synced_at,COALESCE(provider.last_error,'') last_error,
            (SELECT COUNT(*) FROM provider_courses course WHERE course.catalog_id=catalog.id AND course.is_available=1) course_count,
            (SELECT COUNT(*) FROM provider_courses course WHERE course.catalog_id=catalog.id AND course.review_status='approved' AND course.is_available=1) approved_count,
            (SELECT COUNT(*) FROM organization_course_catalog_access access WHERE access.course_catalog_id=catalog.id AND access.is_enabled=1) organization_count
            FROM course_catalogs catalog
            LEFT JOIN course_provider_integrations provider ON provider.catalog_id=catalog.id
            WHERE catalog.is_active=1
            ORDER BY FIELD(catalog.code,'ava-cursos','catalogo-pro','catalogo-up','catalogo-master','catalogo-cefe','catalogo-conclusao','catalogo-prepara','catalogo-drive'),catalog.name");
        return $statement->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function allCourses(): array
    {
        $statement = $this->database->query("SELECT pc.*,c.name catalog_name,c.code catalog_code,p.provider_code,p.name provider_name FROM provider_courses pc INNER JOIN course_catalogs c ON c.id=pc.catalog_id INNER JOIN course_provider_integrations p ON p.id=pc.provider_id ORDER BY c.name,pc.is_available DESC,pc.category,pc.name");
        return $statement->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function catalogCourseOffersForOrganization(int $organizationId): array
    {
        $statement = $this->database->prepare("SELECT pc.id course_id,pc.catalog_id,pc.name source_name,pc.commercial_name approved_name,pc.commercial_description approved_description,pc.category,pc.workload,pc.cover_url,pc.review_status,pc.is_available,c.code catalog_code,c.name catalog_name,p.name provider_name,o.id offer_id,o.commercial_name offer_name,o.commercial_description offer_description,o.price,o.max_installments,o.is_visible,o.is_active FROM provider_courses pc INNER JOIN course_catalogs c ON c.id=pc.catalog_id INNER JOIN course_provider_integrations p ON p.id=pc.provider_id LEFT JOIN organization_provider_course_offers o ON o.provider_course_id=pc.id AND o.organization_id=:organization WHERE pc.review_status='approved' AND pc.is_available=1 ORDER BY c.name,COALESCE(NULLIF(pc.commercial_name,''),pc.name)");
        $statement->execute(['organization' => $organizationId]);
        return $statement->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function catalogsForOrganization(int $organizationId): array
    {
        $statement = $this->database->prepare("SELECT catalog.id,catalog.code,catalog.name,catalog.description,
            COALESCE(access.is_enabled,CASE WHEN catalog.code='ava-cursos' THEN 1 ELSE 0 END) is_enabled,
            CASE WHEN catalog.code='ava-cursos' THEN 'AVA Cursos' ELSE COALESCE(provider.name,'Fornecedor externo') END provider_name,
            CASE WHEN catalog.code='ava-cursos' THEN 'https://avacursos.com.br/{franquia}' ELSE COALESCE(provider.launch_url_template,'') END ava_url,
            CASE WHEN catalog.code='ava-cursos' THEN 1 ELSE COALESCE(provider.is_active,0) END integration_active,
            CASE WHEN catalog.code='ava-cursos'
                THEN (SELECT COUNT(*) FROM moodle_courses course WHERE course.visible=1)
                ELSE (SELECT COUNT(*) FROM provider_courses course WHERE course.catalog_id=catalog.id AND course.review_status='approved' AND course.is_available=1)
            END course_count,
            (SELECT COUNT(*) FROM organization_provider_course_offers offer INNER JOIN provider_courses course ON course.id=offer.provider_course_id WHERE offer.organization_id=:offer_organization AND course.catalog_id=catalog.id AND offer.is_active=1) offer_count
            FROM course_catalogs catalog
            LEFT JOIN organization_course_catalog_access access ON access.course_catalog_id=catalog.id AND access.organization_id=:organization
            LEFT JOIN course_provider_integrations provider ON provider.catalog_id=catalog.id
            WHERE catalog.is_active=1
            GROUP BY catalog.id,catalog.code,catalog.name,catalog.description,access.is_enabled,provider.name,provider.launch_url_template,provider.is_active
            ORDER BY FIELD(catalog.code,'ava-cursos','catalogo-pro','catalogo-up','catalogo-master','catalogo-cefe','catalogo-conclusao','catalogo-prepara','catalogo-drive'),catalog.name");
        $statement->execute(['organization' => $organizationId, 'offer_organization' => $organizationId]);
        return $statement->fetchAll() ?: [];
    }

    /** @param list<int|string> $enabledCatalogIds */
    public function saveCatalogAccess(int $organizationId, array $enabledCatalogIds, ?int $userId): void
    {
        $organization = $this->database->prepare('SELECT 1 FROM organizations WHERE id=:id');
        $organization->execute(['id' => $organizationId]);
        if ($organization->fetchColumn() === false) throw new RuntimeException('Franquia não encontrada.');

        $enabled = array_values(array_unique(array_filter(array_map('intval', $enabledCatalogIds), static fn(int $id): bool => $id > 0)));
        $catalogs = $this->database->query('SELECT id FROM course_catalogs WHERE is_active=1')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $statement = $this->database->prepare('INSERT INTO organization_course_catalog_access(organization_id,course_catalog_id,is_enabled,updated_by) VALUES(:organization,:catalog,:enabled,:user) ON DUPLICATE KEY UPDATE is_enabled=VALUES(is_enabled),updated_by=VALUES(updated_by)');

        $this->database->beginTransaction();
        try {
            foreach ($catalogs as $catalogId) {
                $id = (int)$catalogId;
                $statement->execute(['organization' => $organizationId, 'catalog' => $id, 'enabled' => (int)in_array($id, $enabled, true), 'user' => $userId]);
            }
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw $exception;
        }
    }

    public function review(int $courseId, string $status, string $commercialName, string $commercialDescription, string $notes, ?int $userId): void
    {
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) throw new RuntimeException('Situação da curadoria inválida.');
        $commercialName = trim($commercialName);
        $commercialDescription = trim($commercialDescription);
        $notes = trim($notes);
        if ($status === 'approved' && $commercialName === '') throw new RuntimeException('Informe o nome comercial antes de aprovar o curso.');
        if (mb_strlen($commercialName) > 500) throw new RuntimeException('O nome comercial é muito extenso.');

        $statement = $this->database->prepare('UPDATE provider_courses SET review_status=:status,commercial_name=:name,commercial_description=:description,review_notes=:notes,reviewed_by=:user,reviewed_at=NOW() WHERE id=:id');
        $statement->execute([
            'status' => $status,
            'name' => $commercialName !== '' ? $commercialName : null,
            'description' => $commercialDescription !== '' ? $commercialDescription : null,
            'notes' => $notes !== '' ? $notes : null,
            'user' => $userId,
            'id' => $courseId,
        ]);
        if ($statement->rowCount() !== 1) {
            $exists = $this->database->prepare('SELECT 1 FROM provider_courses WHERE id=:id');
            $exists->execute(['id' => $courseId]);
            if ($exists->fetchColumn() === false) throw new RuntimeException('Curso externo não encontrado.');
        }

        if ($status !== 'approved') {
            $this->database->prepare('UPDATE organization_provider_course_offers SET is_visible=0,updated_by=:user WHERE provider_course_id=:course')->execute(['user' => $userId, 'course' => $courseId]);
        }
    }

    public function saveOffer(int $courseId, int $organizationId, string $name, string $description, float $price, int $installments, bool $visible, bool $active, ?int $userId): int
    {
        if ($organizationId < 1) throw new RuntimeException('Selecione a franquia.');
        $course = $this->database->prepare("SELECT id,commercial_name,name,review_status,is_available FROM provider_courses WHERE id=:id LIMIT 1");
        $course->execute(['id' => $courseId]);
        $row = $course->fetch();
        if (!is_array($row)) throw new RuntimeException('Curso externo não encontrado.');
        if (($row['review_status'] ?? '') !== 'approved' || (int)($row['is_available'] ?? 0) !== 1) throw new RuntimeException('Apenas cursos disponíveis e aprovados podem ser liberados.');

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

    private function recordSyncFailure(string $error, string $providerCode = self::PROVIDER): void
    {
        $this->database->prepare("UPDATE course_provider_integrations SET last_sync_status='failed',last_synced_at=NOW(),last_error=:error WHERE provider_code=:code")->execute(['error' => mb_substr($error, 0, 2000), 'code' => $providerCode]);
    }

    /** @param array<string,mixed> $course @return array<string,mixed> */
    private function normalizeCourse(array $course): array
    {
        return [
            'remote_id' => trim((string)($course['id'] ?? $course['ID'] ?? $course['CursoID'] ?? $course['curso_id'] ?? $course['codigo'] ?? $course['Codigo'] ?? '')),
            'name' => trim((string)($course['nome'] ?? $course['Nome'] ?? $course['Curso'] ?? $course['curso'] ?? $course['titulo'] ?? $course['Titulo'] ?? '')),
            'description' => trim((string)($course['obs'] ?? $course['Obs'] ?? $course['descricao'] ?? $course['Descricao'] ?? $course['Descrição'] ?? '')),
            'category' => trim((string)($course['categoria_loja'] ?? $course['categoria_interna'] ?? $course['categoria'] ?? $course['Categoria'] ?? $course['CategoriaNome'] ?? '')),
            'workload' => trim((string)($course['carga_horaria'] ?? $course['CargaHoraria'] ?? $course['Carga_Horaria'] ?? '')),
            'lesson_count' => max(0, (int)($course['aulas'] ?? $course['Aulas'] ?? $course['QtdAulas'] ?? 0)),
            'cover_url' => trim((string)($course['capa_image'] ?? $course['capa'] ?? $course['Capa'] ?? $course['Imagem'] ?? $course['imagem'] ?? '')),
            'price' => $this->money($course['preco'] ?? $course['Preco'] ?? null),
            'promotional_price' => $this->money($course['preco_promocional'] ?? $course['PrecoPromocional'] ?? null),
            'installments' => isset($course['parcelas']) && is_numeric($course['parcelas']) ? max(0, (int)$course['parcelas']) : (isset($course['Parcelas']) && is_numeric($course['Parcelas']) ? max(0, (int)$course['Parcelas']) : null),
            'status' => trim((string)($course['status'] ?? $course['Status'] ?? $course['Situacao'] ?? $course['Situação'] ?? '')),
        ];
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
}
