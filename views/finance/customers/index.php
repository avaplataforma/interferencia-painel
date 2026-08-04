<?php
declare(strict_types=1);
/** @var Closure(mixed):string $escape */
$money = static fn(float $value): string => 'R$ '.number_format($value, 2, ',', '.');
$query = static function (array $changes) use ($search, $scope, $order): string {
    $values = array_merge(['q' => $search, 'scope' => $scope, 'order' => $order], $changes);
    return http_build_query(array_filter($values, static fn($value): bool => $value !== ''));
};
$firstItem = $result['total'] === 0 ? 0 : (($result['page'] - 1) * 50) + 1;
$lastItem = min($result['total'], $result['page'] * 50);
$pageStart = max(1, $result['page'] - 2);
$pageEnd = min($result['pages'], $result['page'] + 2);
?>
<nav class="finance-section-tabs" aria-label="Navegação do Financeiro"><a class="active" href="<?= $escape($basePath) ?>/finance/customers">Clientes</a><a href="<?= $escape($basePath) ?>/finance/payments">Cobranças</a><a href="<?= $escape($basePath) ?>/finance/subscriptions">Assinaturas</a></nav>
<div class="finance-customer-heading">
  <div>
    <span class="status">Financeiro</span>
    <h1>Clientes</h1>
    <p class="meta">Consulte clientes, cobranças e vínculos respeitando a unidade ativa.</p>
  </div>
  <div class="finance-customer-total"><i class="fa-solid fa-users" aria-hidden="true"></i><span><strong><?= number_format((int)$result['total'], 0, ',', '.') ?></strong> clientes encontrados</span></div>
</div>
<?php if ($message): ?><p class="alert alert-success"><?= $escape($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert alert-danger"><?= $escape($error) ?></p><?php endif; ?>

<section class="finance-customer-toolbar" aria-label="Busca e filtros de clientes">
  <form method="get" action="<?= $escape($basePath) ?>/finance/customers">
    <div class="finance-search-row">
      <label class="finance-search-box" for="finance-search">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input id="finance-search" name="q" value="<?= $escape($search) ?>" placeholder="Nome, e-mail, telefone, CPF/CNPJ ou ID Asaas">
      </label>
      <button class="button-primary finance-search-button" type="submit">Pesquisar</button>
      <details class="finance-filter-panel" <?= ($scope !== 'all' || $order !== 'name') ? 'open' : '' ?>>
        <summary><i class="fa-solid fa-filter" aria-hidden="true"></i> Filtros<?php if ($scope !== 'all' || $order !== 'name'): ?><span class="filter-active-dot" aria-label="Filtros ativos"></span><?php endif; ?></summary>
        <div class="finance-filter-content">
          <?php if ($canViewLegacy): ?>
            <label for="finance-scope">Vínculo
              <select id="finance-scope" name="scope">
                <option value="all" <?= $scope === 'all' ? 'selected' : '' ?>>Todos do meu acesso</option>
                <option value="legacy" <?= $scope === 'legacy' ? 'selected' : '' ?>>Legado / sem unidade</option>
                <option value="units" <?= $scope === 'units' ? 'selected' : '' ?>>Vinculados a unidades</option>
              </select>
            </label>
          <?php endif; ?>
          <label for="finance-order">Ordenar por
            <select id="finance-order" name="order">
              <option value="name" <?= $order === 'name' ? 'selected' : '' ?>>Nome</option>
              <option value="recent" <?= $order === 'recent' ? 'selected' : '' ?>>Mais recentes</option>
              <option value="open" <?= $order === 'open' ? 'selected' : '' ?>>Maior valor em aberto</option>
              <option value="charges" <?= $order === 'charges' ? 'selected' : '' ?>>Mais cobranças</option>
            </select>
          </label>
          <div class="finance-filter-actions"><button class="button-primary" type="submit">Aplicar filtros</button><a class="button-secondary" href="<?= $escape($basePath) ?>/finance/customers">Limpar</a></div>
        </div>
      </details>
    </div>
  </form>
</section>

<div class="finance-result-bar"><span>Exibindo <strong><?= $firstItem ?>–<?= $lastItem ?></strong> de <?= number_format((int)$result['total'], 0, ',', '.') ?></span><span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Dados conforme suas permissões</span></div>
<div class="table-responsive finance-customer-table"><table><thead><tr><th>Cliente</th><th>Documento</th><th>Unidade</th><th class="text-center">Cobranças</th><th class="text-end">Em aberto</th><th>Ações</th></tr></thead><tbody>
<?php if ($result['items'] === []): ?><tr><td colspan="6"><div class="finance-empty"><i class="fa-solid fa-user-slash" aria-hidden="true"></i><strong>Nenhum cliente encontrado</strong><span>Tente alterar a busca ou limpar os filtros.</span></div></td></tr><?php endif; ?>
<?php foreach ($result['items'] as $item): ?>
  <tr>
    <td><a class="finance-customer-name" href="<?= $escape($basePath) ?>/finance/customers/<?= (int)$item['id'] ?>"><span class="finance-avatar"><?= $escape(mb_strtoupper(mb_substr(trim((string)$item['name']), 0, 1))) ?></span><span><strong><?= $escape($item['name']) ?></strong><small><?php if ($item['email']): ?><i class="fa-regular fa-envelope" aria-hidden="true"></i> <?= $escape($item['email']) ?><?php elseif ($item['mobile_phone'] || $item['phone']): ?><i class="fa-solid fa-phone" aria-hidden="true"></i> <?= $escape($item['mobile_phone'] ?: $item['phone']) ?><?php else: ?>Sem contato informado<?php endif; ?></small></span></a></td>
    <td><?= $escape($item['cpf_cnpj'] ?: '—') ?></td>
    <td><?php if ($item['unit_id'] === null): ?><span class="connection-badge connection-pending">Sem unidade</span><?php else: ?><span class="finance-unit"><i class="fa-solid fa-building" aria-hidden="true"></i><?= $escape($item['unit_name']) ?></span><?php endif; ?></td>
    <td class="text-center"><span class="finance-count-badge"><?= (int)$item['payment_count'] ?></span></td>
    <td class="text-end"><strong class="<?= (float)$item['open_value'] > 0 ? 'finance-open-value' : 'finance-zero-value' ?>"><?= $escape($money((float)$item['open_value'])) ?></strong></td>
    <td><div class="finance-action-buttons"><a class="button-secondary button-small finance-icon-button" href="<?= $escape($basePath) ?>/finance/customers/<?= (int)$item['id'] ?>" title="Abrir cliente" aria-label="Abrir cliente"><i class="fa-solid fa-eye" aria-hidden="true"></i></a><?php if($canManage):?><a class="button-secondary button-small finance-icon-button" href="<?= $escape($basePath) ?>/finance/customers/<?= (int)$item['id'] ?>/edit" title="Editar cliente" aria-label="Editar cliente"><i class="fa-solid fa-pen" aria-hidden="true"></i></a><?php endif;?><?php if($canDelete):?><form class="inline-form" method="post" action="<?= $escape($basePath) ?>/finance/customers/<?= (int)$item['id'] ?>/delete" data-confirm-submit="Excluir <?= $escape($item['name']) ?> no Painel e no Asaas? Esta ação só será permitida se não houver registros financeiros vinculados."><?= $csrfField ?><input type="hidden" name="confirm_delete" value="1"><button class="button-danger button-small finance-icon-button" type="submit" title="Excluir cliente" aria-label="Excluir cliente"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></form><?php endif;?><?php if(($navigation['finance_issue']??false)&&$item['unit_id']!==null):?><a class="button-primary button-small finance-create-charge" href="<?= $escape($basePath) ?>/finance/customers/<?= (int)$item['id'] ?>/payments/create">Cobrança <i class="fa-solid fa-plus" aria-hidden="true"></i></a><?php endif;?></div></td>
  </tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if ($result['pages'] > 1): ?><nav class="finance-pagination" aria-label="Paginação de clientes"><span>Página <?= (int)$result['page'] ?> de <?= (int)$result['pages'] ?></span><div><?php if ($result['page'] > 1): ?><a href="?<?= $escape($query(['page' => $result['page'] - 1])) ?>" aria-label="Página anterior"><i class="fa-solid fa-chevron-left"></i></a><?php endif; ?><?php if ($pageStart > 1): ?><a href="?<?= $escape($query(['page' => 1])) ?>">1</a><?php if ($pageStart > 2): ?><span>…</span><?php endif; ?><?php endif; ?><?php for ($pageNumber = $pageStart; $pageNumber <= $pageEnd; $pageNumber++): ?><a href="?<?= $escape($query(['page' => $pageNumber])) ?>" <?= $pageNumber === $result['page'] ? 'class="active" aria-current="page"' : '' ?>><?= $pageNumber ?></a><?php endfor; ?><?php if ($pageEnd < $result['pages']): ?><?php if ($pageEnd < $result['pages'] - 1): ?><span>…</span><?php endif; ?><a href="?<?= $escape($query(['page' => $result['pages']])) ?>"><?= (int)$result['pages'] ?></a><?php endif; ?><?php if ($result['page'] < $result['pages']): ?><a href="?<?= $escape($query(['page' => $result['page'] + 1])) ?>" aria-label="Próxima página"><i class="fa-solid fa-chevron-right"></i></a><?php endif; ?></div></nav><?php endif; ?>
