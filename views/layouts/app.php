<?php

declare(strict_types=1);

/** @var Closure(mixed): string $escape */
$authenticated = ($currentUser ?? null) !== null;
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title><?= $escape($title) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <style>
    :root { --inter-green: #087443; --inter-green-dark: #075c37; --inter-ink: #17212b; --inter-muted: #647383; --inter-bg: #f3f6f8; }
    body { background: var(--inter-bg); color: var(--inter-ink); }
    .app-shell { min-height: 100vh; }
    .min-width-0 { min-width: 0; }
    .sidebar { width: 17rem; background: #fff; border-right: 1px solid #e2e8ec; }
    .brand { color: var(--inter-ink); text-decoration: none; font-size: 1.12rem; font-weight: 800; letter-spacing: -.02em; }
    .brand-mark { display: inline-grid; place-items: center; width: 2.2rem; height: 2.2rem; border-radius: .7rem; background: var(--inter-green); color: #fff; }
    .nav-link { color: #536170; border-radius: .65rem; padding: .7rem .8rem; font-weight: 600; }
    .nav-link:hover, .nav-link:focus { color: var(--inter-green-dark); background: #eaf7f0; }
    .topbar { min-height: 4.5rem; background: rgb(255 255 255 / 92%); border-bottom: 1px solid #e2e8ec; backdrop-filter: blur(8px); }
    .content-wrap { max-width: 80rem; margin-inline: auto; padding: 2rem; }
    .quick-link { display: flex; flex-direction: column; gap: .35rem; height: 100%; padding: 1.1rem; color: var(--inter-ink); text-decoration: none; background: #fff; border: 1px solid #e1e7eb; border-radius: .8rem; }
    .quick-link:hover { color: var(--inter-green-dark); border-color: #9dcdb4; box-shadow: 0 .5rem 1.5rem rgb(24 45 34 / 7%); }
    .quick-link span, .meta { color: var(--inter-muted); font-size: .9rem; }
    .status { color: var(--inter-green); font-weight: 700; }
    .actions { display: flex; gap: .75rem; align-items: center; justify-content: space-between; flex-wrap: wrap; }
    .actions > a { color: #fff; background: var(--inter-green); padding: .65rem .9rem; border-radius: .55rem; text-decoration: none; }
    main a { color: var(--inter-green-dark); font-weight: 600; }
    main > form:not(.unit-switcher) { max-width: 46rem; }
    main label { display: block; margin-top: 1rem; font-weight: 650; }
    main input:not([type=checkbox]) { box-sizing: border-box; width: 100%; margin-top: .4rem; padding: .75rem; border: 1px solid #bcc6ce; border-radius: .55rem; }
    main button { margin-top: 1.25rem; padding: .75rem 1rem; border: 0; border-radius: .55rem; background: var(--inter-green); color: #fff; font-weight: 700; }
    .checks { display: grid; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); gap: .5rem; margin-top: .6rem; }
    .checks label { margin: 0; padding: .65rem; border: 1px solid #dfe4e8; border-radius: .5rem; background: #fff; }
    .checks input { margin-right: .45rem; }
    main { overflow-x: auto; }
    table { width: 100%; min-width: 42rem; margin-top: 1rem; background: #fff; }
    th, td { padding: .75rem; border-bottom: 1px solid #e3e7ea; vertical-align: top; }
    .guest-card { width: min(30rem, calc(100% - 2rem)); }
    @media (max-width: 991.98px) { .desktop-sidebar { display: none; } .content-wrap { padding: 1.25rem; } }
  </style>
</head>
<body>
<?php if (!$authenticated): ?>
  <div class="min-vh-100 d-grid align-items-center py-4"><main class="guest-card mx-auto bg-white border rounded-4 shadow-sm p-4 p-md-5"><?= $content ?></main></div>
<?php else: ?>
  <div class="app-shell d-flex">
    <aside class="sidebar desktop-sidebar flex-shrink-0 p-3">
      <a class="brand d-flex align-items-center gap-2 mb-4" href="<?= $escape($basePath) ?>/"><span class="brand-mark">📊</span><span>PAINEL INTER</span></a>
      <nav class="nav flex-column gap-1">
        <a class="nav-link" href="<?= $escape($basePath) ?>/">Visão geral</a>
        <?php if ($navigation['users'] ?? false): ?><a class="nav-link" href="<?= $escape($basePath) ?>/users">Usuários</a><?php endif; ?>
        <?php if ($navigation['units'] ?? false): ?><a class="nav-link" href="<?= $escape($basePath) ?>/units">Unidades</a><?php endif; ?>
        <?php if ($navigation['roles'] ?? false): ?><a class="nav-link" href="<?= $escape($basePath) ?>/roles">Perfis e permissões</a><?php endif; ?>
      </nav>
    </aside>
    <div class="flex-grow-1 min-width-0">
      <header class="topbar sticky-top d-flex align-items-center px-3 px-lg-4 gap-3">
        <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu" aria-label="Abrir menu">☰</button>
        <form class="unit-switcher d-flex align-items-center gap-2 me-auto" method="post" action="<?= $escape($basePath) ?>/context/unit">
          <?= $csrfField ?>
          <label class="visually-hidden" for="active-unit">Unidade ativa</label>
          <select class="form-select form-select-sm" id="active-unit" name="unit_code" onchange="this.form.submit()" aria-label="Unidade ativa">
            <?php foreach ($availableUnits as $unit): ?><option value="<?= $escape($unit['code']) ?>" <?= ($currentUnit['code'] ?? null) === $unit['code'] ? 'selected' : '' ?>><?= $escape($unit['name']) ?></option><?php endforeach; ?>
          </select>
          <noscript><button class="btn btn-sm btn-success m-0" type="submit">Alterar</button></noscript>
        </form>
        <div class="d-none d-sm-block text-end"><strong><?= $escape($currentUser->name) ?></strong><div class="meta"><?= $escape($currentUser->email) ?></div></div>
        <form method="post" action="<?= $escape($basePath) ?>/logout"><?= $csrfField ?><button class="btn btn-sm btn-outline-secondary m-0" type="submit">Sair</button></form>
      </header>
      <main class="content-wrap"><?= $content ?></main>
    </div>
  </div>
  <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileMenu" aria-labelledby="mobileMenuLabel">
    <div class="offcanvas-header"><h2 class="offcanvas-title h5" id="mobileMenuLabel">PAINEL INTER 📊</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button></div>
    <div class="offcanvas-body"><nav class="nav flex-column gap-1"><a class="nav-link" href="<?= $escape($basePath) ?>/">Visão geral</a><?php if ($navigation['users'] ?? false): ?><a class="nav-link" href="<?= $escape($basePath) ?>/users">Usuários</a><?php endif; ?><?php if ($navigation['units'] ?? false): ?><a class="nav-link" href="<?= $escape($basePath) ?>/units">Unidades</a><?php endif; ?><?php if ($navigation['roles'] ?? false): ?><a class="nav-link" href="<?= $escape($basePath) ?>/roles">Perfis e permissões</a><?php endif; ?></nav></div>
  </div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
