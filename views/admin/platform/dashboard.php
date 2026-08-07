<header class="dashboard-hero platform-hero">
  <div>
    <span class="status">ADM Central</span>
    <h1>Mundo Inter</h1>
    <p>Administre a rede, acompanhe as franquias e mantenha a estrutura central organizada.</p>
  </div>
  <a class="button button-primary" href="<?= $escape($basePath) ?>/admin/organizations/create"><i class="fa-solid fa-plus" aria-hidden="true"></i> Nova franquia</a>
</header>

<section class="platform-summary" aria-label="Resumo da rede">
  <article><span class="platform-summary-icon"><i class="fa-solid fa-network-wired" aria-hidden="true"></i></span><div><small>Franquias cadastradas</small><strong><?= (int) $summary['organizations'] ?></strong></div></article>
  <article><span class="platform-summary-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span><div><small>Franquias ativas</small><strong><?= (int) $summary['active'] ?></strong></div></article>
  <article><span class="platform-summary-icon"><i class="fa-solid fa-globe" aria-hidden="true"></i></span><div><small>Domínios ativos</small><strong><?= (int) $summary['domains'] ?></strong></div></article>
  <article><span class="platform-summary-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span><div><small>Usuários da rede</small><strong><?= (int) $summary['users'] ?></strong></div></article>
</section>

<section class="dashboard-section platform-organizations">
  <div class="dashboard-section-heading">
    <div><span class="section-eyebrow">Rede</span><h2>Franquias</h2><p>Acessos recentes à estrutura organizacional da plataforma.</p></div>
    <a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/organizations">Ver todas</a>
  </div>
  <div class="table-responsive mt-4">
    <table>
      <thead><tr><th>Franquia</th><th>Login exclusivo</th><th>Domínio público</th><th>Unidades</th><th>Situação</th><th>Ação</th></tr></thead>
      <tbody>
      <?php foreach ($organizations as $organization): ?>
        <tr>
          <td><strong><?= $escape($organization['display_name']) ?></strong><br><small><?= $escape($organization['legal_name']) ?></small></td>
          <td><a href="<?= $escape($basePath) ?>/<?= $escape($organization['panel_slug']) ?>">/<?= $escape($organization['panel_slug']) ?></a></td>
          <td><?= !empty($organization['primary_host']) ? $escape($organization['primary_host']) : 'Não configurado' ?></td>
          <td><?= (int) $organization['unit_count'] ?></td>
          <td><span class="status <?= ($organization['status'] ?? '') === 'active' ? '' : 'status-muted' ?>"><?= ($organization['status'] ?? '') === 'active' ? 'Ativa' : 'Suspensa' ?></span></td>
          <td><a class="button button-secondary button-icon" title="Editar franquia" href="<?= $escape($basePath) ?>/admin/organizations/<?= (int) $organization['id'] ?>/edit"><i class="fa-solid fa-pen" aria-hidden="true"></i></a></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($organizations === []): ?><tr><td colspan="6">Nenhuma franquia cadastrada.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
