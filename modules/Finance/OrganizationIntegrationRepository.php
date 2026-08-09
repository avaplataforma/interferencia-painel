<?php

declare(strict_types=1);

namespace Interferencia\Modules\Finance;

use Interferencia\Kernel\Security\SecretCipher;
use PDO;
use RuntimeException;

final readonly class OrganizationIntegrationRepository
{
    public function __construct(private PDO $database, private SecretCipher $cipher) {}

    public function encryptionReady(): bool
    {
        return $this->cipher->ready();
    }

    /** @return array<string,mixed> */
    public function asaas(int $organizationId): array
    {
        $statement = $this->database->prepare("SELECT * FROM organization_finance_integrations WHERE organization_id=:organization AND provider='asaas' LIMIT 1");
        $statement->execute(['organization' => $organizationId]);
        $row = $statement->fetch() ?: [];
        $apiKey = $this->cipher->decrypt(isset($row['api_key_encrypted']) ? (string) $row['api_key_encrypted'] : null);
        $webhookToken = $this->cipher->decrypt(isset($row['webhook_token_encrypted']) ? (string) $row['webhook_token_encrypted'] : null);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'organization_id' => $organizationId,
            'account_mode' => (string) ($row['account_mode'] ?? 'central'),
            'environment' => (string) ($row['environment'] ?? 'production'),
            'api_key' => $apiKey,
            'api_key_last4' => (string) ($row['api_key_last4'] ?? ''),
            'webhook_token' => $webhookToken,
            'is_active' => (int) ($row['is_active'] ?? 0) === 1,
            'configured' => $apiKey !== '',
            'last_test_status' => (string) ($row['last_test_status'] ?? 'not_tested'),
            'last_tested_at' => isset($row['last_tested_at']) ? (string) $row['last_tested_at'] : null,
            'last_test_error' => isset($row['last_test_error']) ? (string) $row['last_test_error'] : null,
        ];
    }

    public function usesExclusiveAsaas(int $organizationId): bool
    {
        $settings = $this->asaas($organizationId);
        return $settings['account_mode'] === 'exclusive'
            && $settings['configured']
            && $settings['is_active']
            && $settings['last_test_status'] === 'success';
    }

    public function saveAsaas(int $organizationId, string $mode, string $environment, string $apiKey, bool $active, ?int $userId): void
    {
        if (!in_array($mode, ['central', 'exclusive'], true)) {
            throw new RuntimeException('Selecione como a franquia utilizará o Asaas.');
        }
        if (!in_array($environment, ['production', 'sandbox'], true)) {
            throw new RuntimeException('Selecione um ambiente válido do Asaas.');
        }

        $current = $this->asaas($organizationId);
        if ($mode === 'central') {
            $this->upsert($organizationId, $mode, $environment, null, null, null, false, 'not_tested', $userId);
            return;
        }
        if (!$this->cipher->ready()) {
            throw new RuntimeException('A chave-mestra da plataforma precisa estar configurada antes de salvar credenciais da franquia.');
        }

        $apiKey = trim($apiKey);
        $expectedPrefix = $environment === 'production' ? '$aact_prod_' : '$aact_hmlg_';
        if ($apiKey === '' && !$current['configured']) {
            throw new RuntimeException('Informe a chave da conta Asaas exclusiva.');
        }
        if ($apiKey !== '' && !str_starts_with($apiKey, $expectedPrefix)) {
            throw new RuntimeException('A chave não corresponde ao ambiente selecionado.');
        }

        $keyChanged = $apiKey !== '';
        $effectiveKey = $keyChanged ? $apiKey : (string) $current['api_key'];
        $webhookToken = $keyChanged || (string) $current['webhook_token'] === ''
            ? bin2hex(random_bytes(32))
            : (string) $current['webhook_token'];
        $testStatus = $keyChanged || $environment !== $current['environment'] ? 'pending' : (string) $current['last_test_status'];

        $this->upsert(
            $organizationId,
            $mode,
            $environment,
            $this->cipher->encrypt($effectiveKey),
            substr($effectiveKey, -4),
            $this->cipher->encrypt($webhookToken),
            $active,
            $testStatus,
            $userId
        );
    }

    public function recordTest(int $organizationId, ?string $error): void
    {
        $statement = $this->database->prepare("UPDATE organization_finance_integrations SET last_test_status=:status,last_tested_at=NOW(),last_test_error=:error WHERE organization_id=:organization AND provider='asaas'");
        $statement->execute([
            'status' => $error === null ? 'success' : 'failed',
            'error' => $error === null ? null : mb_substr($error, 0, 500),
            'organization' => $organizationId,
        ]);
    }

    private function upsert(int $organizationId, string $mode, string $environment, ?string $encryptedKey, ?string $last4, ?string $encryptedWebhook, bool $active, string $testStatus, ?int $userId): void
    {
        $sql = "INSERT INTO organization_finance_integrations(organization_id,provider,account_mode,environment,api_key_encrypted,api_key_last4,webhook_token_encrypted,is_active,last_test_status,updated_by) VALUES(:organization,'asaas',:mode,:environment,:api,:last4,:webhook,:active,:test_status,:user) ON DUPLICATE KEY UPDATE account_mode=VALUES(account_mode),environment=VALUES(environment),api_key_encrypted=COALESCE(VALUES(api_key_encrypted),api_key_encrypted),api_key_last4=COALESCE(VALUES(api_key_last4),api_key_last4),webhook_token_encrypted=COALESCE(VALUES(webhook_token_encrypted),webhook_token_encrypted),is_active=VALUES(is_active),last_test_status=VALUES(last_test_status),last_test_error=NULL,updated_by=VALUES(updated_by)";
        $this->database->prepare($sql)->execute([
            'organization' => $organizationId,
            'mode' => $mode,
            'environment' => $environment,
            'api' => $encryptedKey,
            'last4' => $last4,
            'webhook' => $encryptedWebhook,
            'active' => (int) $active,
            'test_status' => $testStatus,
            'user' => $userId,
        ]);
    }
}
