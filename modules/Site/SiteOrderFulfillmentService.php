<?php

declare(strict_types=1);

namespace Interferencia\Modules\Site;

use Interferencia\Modules\Crm\ContactRepository;
use Interferencia\Modules\Finance\AsaasClient;
use Interferencia\Modules\Finance\FinanceRepository;
use Interferencia\Modules\Moodle\AvaAccessNotifier;
use Interferencia\Modules\Moodle\AvaEnrollmentReleaser;
use Interferencia\Modules\Moodle\EnrollmentRepository;
use PDO;
use RuntimeException;
use Throwable;

final readonly class SiteOrderFulfillmentService
{
    public function __construct(
        private PDO $database,
        private SiteRepository $sites,
        private AsaasClient $asaas,
        private AvaEnrollmentReleaser $releaser,
        private AvaAccessNotifier $notifier,
    ) {}

    /** @param array<string, mixed> $payload */
    public function organizationForPayload(array $payload, int $fallbackOrganizationId): int
    {
        $checkout = is_array($payload['checkout'] ?? null) ? $payload['checkout'] : null;
        if ($checkout !== null) {
            $order = $this->sites->orderForWebhook($checkout);
            if ($order !== null) {
                return (int) $order['organization_id'];
            }
        }

        $resource = is_array($payload['payment'] ?? null)
            ? $payload['payment']
            : (is_array($payload['subscription'] ?? null) ? $payload['subscription'] : null);
        $reference = trim((string) ($resource['externalReference'] ?? ''));

        if (preg_match('/^mundo-inter:site-order:(\d+):/', $reference, $match) === 1) {
            return (int) $match[1];
        }
        if (preg_match('/^painel:site-order:\d+:unit:(\d+)$/', $reference, $match) === 1) {
            return $this->organizationForUnit((int) $match[1]);
        }
        if (preg_match('/^painel:unit:(\d+):/', $reference, $match) === 1) {
            return $this->organizationForUnit((int) $match[1]);
        }
        if (preg_match('/^painel:enrollment:(\d+)$/', $reference, $match) === 1) {
            $statement = $this->database->prepare('SELECT organization_id FROM student_enrollments WHERE id=:id');
            $statement->execute(['id' => (int) $match[1]]);
            $organizationId = (int) $statement->fetchColumn();
            if ($organizationId > 0) {
                return $organizationId;
            }
        }
        if (preg_match('/^mundo-inter:franchise-contract:(\d+)$/', $reference, $match) === 1) {
            return $this->organizationForContract((int) $match[1]);
        }
        if (preg_match('/^mundo-inter:sandbox:franchise-test:(\d+)$/', $reference, $match) === 1) {
            $statement = $this->database->prepare('SELECT organization_id FROM franchise_sandbox_billing_tests WHERE id=:id');
            $statement->execute(['id' => (int) $match[1]]);
            $organizationId = (int) $statement->fetchColumn();
            if ($organizationId > 0) {
                return $organizationId;
            }
        }
        if ($fallbackOrganizationId > 0) {
            return $fallbackOrganizationId;
        }

        $statement = $this->database->query("SELECT id FROM organizations WHERE code='interferencia' LIMIT 1");
        $organizationId = (int) $statement->fetchColumn();
        if ($organizationId < 1) {
            throw new RuntimeException('Não foi possível identificar a franquia financeira deste evento.');
        }
        return $organizationId;
    }

    /** @param array<string, mixed> $checkout */
    public function processCheckout(string $eventType, array $checkout): void
    {
        $this->sites->updateOrderFromWebhook($checkout);
        $order = $this->sites->orderForWebhook($checkout);
        if ($order === null) {
            return;
        }
        $paid = $eventType === 'CHECKOUT_PAID'
            || in_array(strtoupper((string) ($checkout['status'] ?? '')), ['PAID', 'COMPLETED'], true);
        if (!$paid || in_array((string) ($order['fulfillment_status'] ?? ''), ['released', 'releasing', 'manual_review'], true)) {
            return;
        }

        $this->fulfillOrder($order, $checkout);
    }

    /** @param array<string, mixed> $payment */
    public function processPayment(string $eventType, array $payment): void
    {
        if (!in_array(strtoupper((string) ($payment['status'] ?? '')), ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'], true)) {
            return;
        }
        $order = $this->sites->orderForWebhook($payment);
        if ($order === null || in_array((string) ($order['fulfillment_status'] ?? ''), ['released', 'releasing', 'manual_review'], true)) {
            return;
        }

        $this->fulfillOrder($order, $payment);
    }

    /** @param array<string, mixed> $order @param array<string, mixed> $resource */
    private function fulfillOrder(array $order, array $resource): void
    {
        $organizationId = (int) $order['organization_id'];
        if (!$this->sites->claimPaidOrder((int) $order['id'], $organizationId)) {
            return;
        }

        try {
            $customerValue = $resource['customer'] ?? '';
            $asaasCustomerId = is_array($customerValue)
                ? (string) ($customerValue['id'] ?? '')
                : (string) $customerValue;
            $customer = is_array($customerValue) && isset($customerValue['cpfCnpj'])
                ? $customerValue
                : $this->asaas->customer($asaasCustomerId);

            $finance = new FinanceRepository($this->database, $organizationId);
            $finance->upsertCustomer($customer);
            $customerId = $finance->customerIdByAsaas((string) ($customer['id'] ?? $asaasCustomerId));
            if ($customerId === null) {
                throw new RuntimeException('O pagamento foi confirmado, mas o aluno não pôde ser identificado no Asaas.');
            }

            $finance->reconcileCustomer($customerId, (int) $order['unit_id'], (int) $order['crm_contact_id']);
            (new ContactRepository($this->database, $organizationId))->markEnrolled((int) $order['crm_contact_id']);
            $enrollmentId = (new EnrollmentRepository($this->database, $organizationId))->createPaidFromSiteOrder(
                $customerId,
                (int) $order['finance_product_id'],
                (int) $order['unit_id'],
                (int) $order['crm_contact_id'],
            );

            $mode = (string) ($order['checkout_fulfillment_mode'] ?? 'manual_review');
            $this->sites->recordPaidOrder(
                (int) $order['id'],
                $organizationId,
                $customerId,
                $enrollmentId,
                $mode,
                isset($resource['link']) ? (string) $resource['link'] : (isset($resource['invoiceUrl']) ? (string) $resource['invoiceUrl'] : null),
            );
            if ($mode === 'automatic') {
                $this->release((int) $order['id'], $enrollmentId);
            }
        } catch (Throwable $exception) {
            $this->sites->markOrderFulfillmentFailed((int) $order['id'], $exception->getMessage());
            throw $exception;
        }
    }

    public function releaseOrder(int $organizationId, int $orderId, ?int $operatorId): void
    {
        $order = $this->sites->orderForReview($organizationId, $orderId);
        if ($order === null) {
            throw new RuntimeException('Pedido pago não encontrado ou já liberado.');
        }
        $this->release((int) $order['id'], (int) $order['student_enrollment_id'], $operatorId);
    }

    private function release(int $orderId, int $enrollmentId, ?int $operatorId = null): void
    {
        try {
            $result = $this->releaser->release($enrollmentId, $operatorId);
            if (($result['status'] ?? '') !== 'released') {
                throw new RuntimeException((string) ($result['message'] ?? 'A liberação automática ainda não está disponível.'));
            }
            $this->notifier->notify($enrollmentId);
            $this->sites->markOrderReleased($orderId);
        } catch (Throwable $exception) {
            $this->sites->markOrderFulfillmentFailed($orderId, $exception->getMessage());
            throw $exception;
        }
    }

    private function organizationForUnit(int $unitId): int
    {
        if ($unitId < 1) {
            return 0;
        }
        $statement = $this->database->prepare('SELECT organization_id FROM units WHERE id=:id');
        $statement->execute(['id' => $unitId]);
        return (int) $statement->fetchColumn();
    }

    private function organizationForContract(int $contractId): int
    {
        $statement = $this->database->prepare('SELECT organization_id FROM franchise_contracts WHERE id=:id');
        $statement->execute(['id' => $contractId]);
        $organizationId = (int) $statement->fetchColumn();
        if ($organizationId < 1) {
            throw new RuntimeException('O contrato informado pelo Asaas não pertence a uma franquia válida.');
        }
        return $organizationId;
    }
}
