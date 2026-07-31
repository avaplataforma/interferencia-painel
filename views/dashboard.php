<?php

declare(strict_types=1);

/** @var Closure(mixed): string $escape */
?>
<?php if ($message ?? null): ?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif; ?>
<?php if ($error ?? null): ?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif; ?>
<span class="text-success fw-semibold">Visão geral</span>
<h1 class="mt-2">Olá, <?= $escape($user->name) ?></h1>
<p class="text-secondary">Acesse rapidamente as áreas administrativas do PAINEL INTER.</p>
<div class="row g-3 mt-2">
  <div class="col-sm-6 col-xl-4"><div class="card h-100 border-0 shadow-sm"><div class="card-body"><div class="text-secondary small">Unidade ativa</div><h2 class="h5 mt-2 mb-0"><?= $escape($currentUnit['name'] ?? 'Nenhuma unidade') ?></h2></div></div></div>
  <div class="col-sm-6 col-xl-4"><div class="card h-100 border-0 shadow-sm"><div class="card-body"><div class="text-secondary small">Unidades permitidas</div><h2 class="display-6 mb-0"><?= $escape((string) count($unitScopes)) ?></h2></div></div></div>
  <div class="col-sm-6 col-xl-4"><div class="card h-100 border-0 shadow-sm"><div class="card-body"><div class="text-secondary small">Sessão</div><h2 class="h5 mt-2 mb-0">Protegida e ativa</h2></div></div></div>
</div>
<h2 class="h5 mt-4">Atalhos</h2>
<div class="row g-3">
  <?php if ($canManageUsers): ?><div class="col-md-4"><a class="quick-link" href="<?= $escape($basePath) ?>/users"><span class="quick-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span><strong>Usuários</strong><span>Contas, perfis e unidades permitidas</span></a></div><?php endif; ?>
  <?php if ($canManageUnits): ?><div class="col-md-4"><a class="quick-link" href="<?= $escape($basePath) ?>/units"><span class="quick-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></span><strong>Unidades</strong><span>Cadastro e situação operacional</span></a></div><?php endif; ?>
  <?php if ($canManageRoles): ?><div class="col-md-4"><a class="quick-link" href="<?= $escape($basePath) ?>/roles"><span class="quick-icon"><i class="fa-solid fa-user-shield" aria-hidden="true"></i></span><strong>Perfis</strong><span>Permissões e níveis de acesso</span></a></div><?php endif; ?>
</div>
