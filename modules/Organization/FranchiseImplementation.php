<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

final class FranchiseImplementation
{
    /**
     * @param array<string,mixed> $organization
     * @param list<array<string,mixed>> $domains
     * @param array<string,mixed>|null $contract
     * @param array<string,mixed> $facts
     * @return array<string,mixed>
     */
    public static function evaluate(array $organization, array $domains, ?array $contract, array $facts): array
    {
        $registrationComplete = self::filled($organization, [
            'legal_name', 'display_name', 'cnpj', 'manager_name', 'manager_email', 'manager_phone', 'panel_slug',
        ]);
        $addressComplete = self::filled($organization, ['postal_code', 'address', 'address_number', 'city', 'state']);
        $managerComplete = self::filled($organization, ['general_manager_name', 'general_manager_email', 'general_manager_phone']);
        $contractSigned = $contract !== null && ($contract['status'] ?? '') === 'signed';
        $commercialModel = $contract === null ? '' : (string) ($contract['commercial_model'] ?? '');
        $commercialDefined = $contractSigned && in_array($commercialModel, ['fixed_plus_percentage', 'split_only'], true);
        $percentage = $contract === null ? 0.0 : (float) ($contract['sales_fee_percentage'] ?? 0);
        $monthly = $contract === null ? 0.0 : (float) ($contract['monthly_fixed_amount'] ?? 0);
        $needsSplit = $percentage > 0;
        $needsMonthly = $commercialModel === 'fixed_plus_percentage' && $monthly > 0;
        $splitReady = !$needsSplit || (
            ($organization['asaas_wallet_status'] ?? '') === 'validated'
            && trim((string) ($organization['asaas_wallet_id'] ?? '')) !== ''
            && (int) ($organization['split_enabled'] ?? 0) === 1
        );
        $monthlyReady = !$needsMonthly || trim((string) ($contract['asaas_payment_link_url'] ?? '')) !== '';
        $paymentProviderReady = (int) ($facts['finance_account_ready'] ?? 0) === 1;
        $financeReady = $commercialDefined && $splitReady && $monthlyReady && $paymentProviderReady;
        $brandingReady = trim((string) ($organization['logo_path'] ?? '')) !== ''
            && trim((string) ($organization['favicon_path'] ?? '')) !== '';
        $domainReady = false;
        foreach ($domains as $domain) {
            if (($domain['purpose'] ?? '') === 'site' && ($domain['status'] ?? '') === 'active' && (int) ($domain['is_primary'] ?? 0) === 1) {
                $domainReady = true;
                break;
            }
        }
        $adminReady = (int) ($facts['active_admins'] ?? 0) > 0;
        $avaReady = (int) ($facts['active_ava_integrations'] ?? 0) > 0;
        $polesReady = (int) ($facts['active_poles'] ?? 0) > 0 && (int) ($facts['primary_poles'] ?? 0) > 0;
        $documentsReady = (int) ($facts['franchise_documents'] ?? 0) > 0;
        $active = ($organization['status'] ?? '') === 'active';
        $slug = (string) ($organization['panel_slug'] ?? '');

        $steps = [
            self::step('cadastro', 'Cadastro conferido', 'Dados jurídicos, gestor e acesso exclusivo preenchidos.', $registrationComplete, 'required', 'Cadastro', 'fa-building', '/edit#dados'),
            self::step('contrato', 'Contrato assinado', 'O contrato vigente precisa estar assinado.', $contractSigned, 'required', 'Legal', 'fa-file-signature', '/contracts'),
            self::step('comercial', 'Modelo comercial definido', 'Mensalidade e/ou percentual definidos no contrato.', $commercialDefined, 'required', 'Comercial', 'fa-scale-balanced', '/contracts'),
            self::step('financeiro', 'Operação financeira pronta', self::financeDetail($needsMonthly, $needsSplit, $monthlyReady, $splitReady, $paymentProviderReady, (string) ($facts['finance_account_mode'] ?? 'central')), $financeReady, 'required', 'Financeiro', 'fa-wallet', $paymentProviderReady ? '#operacao-comercial' : '/edit#integracoes'),
            self::step('administrador', 'Administrador da franquia criado', 'Ao menos um administrador ativo deve acessar o painel exclusivo.', $adminReady, 'required', 'Acesso', 'fa-user-shield', '/' . $slug . '/users'),
            self::step('ava', 'AVA conectado e testado', 'A modalidade escolhida precisa ter todas as conexões ativas e testadas.', $avaReady, 'required', 'Acadêmico', 'fa-graduation-cap', '/edit#ava'),
            self::step('polos', 'Polo operacional definido', 'Cadastre ao menos um polo ativo e marque o polo principal.', $polesReady, 'required', 'Acadêmico', 'fa-location-dot', '/edit#polos'),
            self::step('identidade', 'Identidade visual configurada', 'Logo e favicon próprios cadastrados.', $brandingReady, 'required', 'Experiência', 'fa-palette', '/edit#marca'),
            self::step('dominio', 'Domínio público validado', 'O domínio principal do site precisa estar ativo.', $domainReady, 'required', 'Tecnologia', 'fa-globe', '/edit#dados'),
            self::step('endereco', 'Endereço completo', 'Facilita contratos, documentos fiscais e atendimento.', $addressComplete, 'recommended', 'Cadastro', 'fa-map-location-dot', '/edit#endereco'),
            self::step('gerente', 'Gerente operacional informado', 'Mantém um segundo responsável para a operação diária.', $managerComplete, 'recommended', 'Cadastro', 'fa-user-tie', '/edit#contatos'),
            self::step('documentos', 'Documentos da franquia anexados', 'Centralize contrato social, documento do gestor e comprovantes.', $documentsReady, 'recommended', 'Legal', 'fa-folder-open', '/edit#documentos'),
            self::step('ativacao', 'Franquia ativada', 'Libera o login e a operação da franquia.', $active, 'activation', 'Conclusão', 'fa-circle-check', ''),
        ];
        $required = self::ofKind($steps, 'required');
        $recommended = self::ofKind($steps, 'recommended');
        $requiredDone = self::doneCount($required);
        $recommendedDone = self::doneCount($recommended);
        $missing = array_values(array_map(
            static fn(array $step): string => (string) $step['label'],
            array_filter($required, static fn(array $step): bool => !$step['done'])
        ));

        return [
            'steps' => $steps,
            'required_steps' => $required,
            'recommended_steps' => $recommended,
            'required_total' => count($required),
            'required_done' => $requiredDone,
            'recommended_total' => count($recommended),
            'recommended_done' => $recommendedDone,
            'progress' => (int) round(($requiredDone / max(1, count($required))) * 100),
            'ready_to_activate' => $missing === [],
            'missing' => $missing,
        ];
    }

    /** @param array<string,mixed> $data @param list<string> $keys */
    private static function filled(array $data, array $keys): bool
    {
        foreach ($keys as $key) {
            if (trim((string) ($data[$key] ?? '')) === '') {
                return false;
            }
        }
        return true;
    }

    /** @return array<string,mixed> */
    private static function step(string $id, string $label, string $detail, bool $done, string $kind, string $group, string $icon, string $action): array
    {
        return compact('id', 'label', 'detail', 'done', 'kind', 'group', 'icon', 'action');
    }

    /** @param list<array<string,mixed>> $steps @return list<array<string,mixed>> */
    private static function ofKind(array $steps, string $kind): array
    {
        return array_values(array_filter($steps, static fn(array $step): bool => $step['kind'] === $kind));
    }

    /** @param list<array<string,mixed>> $steps */
    private static function doneCount(array $steps): int
    {
        return count(array_filter($steps, static fn(array $step): bool => $step['done']));
    }

    private static function financeDetail(bool $needsMonthly, bool $needsSplit, bool $monthlyReady, bool $splitReady, bool $providerReady, string $mode): string
    {
        $pending = [];
        if (!$providerReady) {
            $pending[] = $mode === 'exclusive' ? 'testar e ativar o Asaas exclusivo' : 'ativar a integração Asaas central';
        }
        if ($needsMonthly && !$monthlyReady) {
            $pending[] = 'gerar o link mensal';
        }
        if ($needsSplit && !$splitReady) {
            $pending[] = 'validar a Wallet e o split';
        }
        return $pending === [] ? 'Gateway, cobrança e repasse prontos para novas vendas.' : 'Pendente: ' . implode('; ', $pending) . '.';
    }
}
