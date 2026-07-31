<?php

declare(strict_types=1);

/** @var Closure(mixed): string $escape */
$authenticated = ($currentUser ?? null) !== null;
$currentPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$crmOpen = str_contains($currentPath, '/crm');
$admOpen = preg_match('#/(users|units|roles|tags|statuses)(?:/|$)#', $currentPath) === 1;
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title><?= $escape($title) ?></title>
  <link rel="icon" type="image/png" href="<?= $escape($basePath) ?>/assets/media/painel-inter-icon.png">
  <link rel="apple-touch-icon" href="<?= $escape($basePath) ?>/assets/media/painel-inter-icon.png">
  <link href="<?= $escape($basePath) ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
  <link href="<?= $escape($basePath) ?>/assets/vendor/fontawesome/css/fontawesome.min.css" rel="stylesheet">
  <link href="<?= $escape($basePath) ?>/assets/vendor/fontawesome/css/solid.min.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    html { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; line-height: 1.5; }
    body { margin: 0; }
    .d-flex { display: flex; } .d-grid { display: grid; } .d-none { display: none; }
    .flex-column { flex-direction: column; } .flex-grow-1 { flex-grow: 1; } .flex-shrink-0 { flex-shrink: 0; }
    .align-items-center { align-items: center; } .gap-1 { gap: .25rem; } .gap-2 { gap: .5rem; } .gap-3 { gap: 1rem; }
    .me-auto { margin-right: auto; } .mx-auto { margin-inline: auto; } .mb-0 { margin-bottom: 0; } .mb-4 { margin-bottom: 1.5rem; }
    .mt-2 { margin-top: .5rem; } .mt-4 { margin-top: 1.5rem; } .p-3 { padding: 1rem; } .p-4 { padding: 1.5rem; }
    .px-3 { padding-inline: 1rem; } .py-4 { padding-block: 1.5rem; } .min-vh-100 { min-height: 100vh; }
    .text-end { text-align: right; } .text-secondary { color: #647383; } .text-success { color: #ed1c24; }
    .fw-semibold { font-weight: 600; } .small { font-size: .875rem; } .h5 { font-size: 1.25rem; } .display-6 { font-size: 2.25rem; }
    .bg-white { background: #fff; } .border { border: 1px solid #dee2e6; } .border-0 { border: 0; }
    .rounded-4 { border-radius: 1rem; } .shadow-sm { box-shadow: 0 .4rem 1.2rem rgb(23 33 43 / 8%); }
    .btn { display: inline-block; padding: .45rem .7rem; border: 1px solid transparent; border-radius: .45rem; background: transparent; font: inherit; cursor: pointer; }
    .btn-sm { padding: .3rem .55rem; font-size: .875rem; } .btn-outline-secondary { color: #536170; border-color: #aeb8c1; background: #fff; }
    .btn-success { color: #fff; background: #ed1c24; } .m-0 { margin: 0; }
    .form-select { min-width: 10rem; padding: .45rem 2rem .45rem .65rem; border: 1px solid #b8c2ca; border-radius: .45rem; background: #fff; font: inherit; }
    .form-select-sm { font-size: .875rem; }
    .visually-hidden { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
    .row { display: flex; flex-wrap: wrap; margin: -.5rem; } .row > * { padding: .5rem; width: 100%; }
    .card { background: #fff; border-radius: .8rem; } .card-body { padding: 1rem; } .h-100 { height: 100%; }
    .alert { padding: .8rem 1rem; margin-bottom: 1rem; border-radius: .55rem; }
    .alert-success { color: #176b3a; background: #eaf8ef; } .alert-danger { color: #8c2020; background: #fff0f0; }
    .mobile-menu { position: relative; }
    .mobile-menu > summary { display: grid; place-items: center; width: 2.4rem; height: 2.4rem; border: 1px solid #aeb8c1; border-radius: .45rem; background: #fff; color: #536170; cursor: pointer; list-style: none; font-size: 1.2rem; }
    .mobile-menu > summary::-webkit-details-marker { display: none; }
    .mobile-menu-panel { position: absolute; z-index: 1060; top: calc(100% + .7rem); left: 0; width: min(19rem, calc(100vw - 2rem)); padding: .75rem; background: #fff; border: 1px solid #dfe5e9; border-radius: .75rem; box-shadow: 0 1rem 2.5rem rgb(23 33 43 / 16%); }
    :root { --inter-accent: #ed1c24; --inter-accent-dark: #c81018; --inter-ink: #17212b; --inter-muted: #647383; --inter-bg: #f3f6f8; }
    body { background: var(--inter-bg); color: var(--inter-ink); }
    .app-shell { min-height: 100vh; }
    .min-width-0 { min-width: 0; }
    .sidebar { width: 17rem; background: #fff; border-right: 1px solid #e2e8ec; }
    .brand { color: var(--inter-ink); text-decoration: none; font-size: 1.12rem; font-weight: 800; letter-spacing: -.02em; }
    .brand-logo { display: block; width: 2.35rem; height: 2.35rem; border-radius: 50%; object-fit: cover; }
    .login-logo { display: block; width: 6rem; height: 6rem; margin: 0 auto 1.25rem; border-radius: 50%; object-fit: cover; }
    .sidebar .nav, .mobile-menu .nav { display: flex; flex-direction: column; width: 100%; }
    .sidebar a.nav-link, .mobile-menu a.nav-link { display: block; width: 100%; color: #536170; border-radius: .65rem; padding: .72rem .8rem; font-weight: 650; text-decoration: none; line-height: 1.25; }
    .sidebar a.nav-link:hover, .sidebar a.nav-link:focus, .mobile-menu a.nav-link:hover, .mobile-menu a.nav-link:focus { color: var(--inter-accent-dark); background: #fff0f1; text-decoration: none; }
    .nav-group { width: 100%; }
    .nav-group > summary { display: flex; align-items: center; justify-content: space-between; width: 100%; padding: .72rem .8rem; border-radius: .65rem; color: #536170; font-weight: 700; cursor: pointer; list-style: none; }
    .nav-group > summary::-webkit-details-marker { display: none; }
    .nav-group > summary::after { content: "›"; font-size: 1.25rem; transition: transform .15s ease; }
    .nav-group[open] > summary::after { transform: rotate(90deg); }
    .nav-group > summary:hover, .nav-group > summary:focus { color: var(--inter-accent-dark); background: #fff0f1; }
    .nav-submenu { display: grid; gap: .2rem; margin: .2rem 0 .35rem .75rem; padding-left: .55rem; border-left: 2px solid #f4c2c4; }
    .sidebar .nav-submenu a.nav-link, .mobile-menu .nav-submenu a.nav-link { padding: .55rem .7rem; font-size: .94rem; }
    .nav-link .fa-fw, .nav-group summary .fa-fw { margin-right: .55rem; color: var(--inter-accent); }
    .quick-icon { display: grid; place-items: center; width: 2.3rem; height: 2.3rem; margin-bottom: .35rem; border-radius: .65rem; color: var(--inter-accent); background: #fff0f1; font-size: 1.05rem; }
    .topbar { position: sticky; z-index: 1020; top: 0; min-height: 4.5rem; background: rgb(255 255 255 / 92%); border-bottom: 1px solid #e2e8ec; backdrop-filter: blur(8px); }
    .content-wrap { max-width: 80rem; margin-inline: auto; padding: 2rem; }
    .quick-link { display: flex; flex-direction: column; gap: .35rem; height: 100%; padding: 1.1rem; color: var(--inter-ink); text-decoration: none; background: #fff; border: 1px solid #e1e7eb; border-radius: .8rem; }
    .quick-link:hover { color: var(--inter-accent-dark); border-color: #f3a4a7; box-shadow: 0 .5rem 1.5rem rgb(90 15 19 / 8%); }
    .quick-link span, .meta { color: var(--inter-muted); font-size: .9rem; }
    .status { color: var(--inter-accent); font-weight: 700; }
    .required-mark { color: var(--inter-accent); font-weight: 800; }
    main select:required:invalid { color: var(--inter-accent); }
    main select option { color: var(--inter-ink); }
    .tag-dropdown > summary { color: var(--inter-accent); }
    .tag-dropdown > summary .tag-badge { color: var(--tag-color); }
    .followup-status { display: inline-flex; padding: .2rem .55rem; border-radius: 999px; font-size: .8rem; font-weight: 700; }
    .followup-pending { color: #9a6700; background: #fff4cc; }
    .followup-completed { color: #176b3a; background: #eaf8ef; }
    .followup-cancelled { color: #7a2630; background: #fff0f1; }
    .actions { display: flex; gap: .75rem; align-items: center; justify-content: space-between; flex-wrap: wrap; }
    .actions > a { color: #fff; background: var(--inter-accent); padding: .65rem .9rem; border-radius: .55rem; text-decoration: none; }
    main a { color: var(--inter-accent-dark); font-weight: 600; }
    main > form:not(.unit-switcher) { max-width: 46rem; }
    main label { display: block; margin-top: 1rem; font-weight: 650; }
    main input, main select, main textarea, .tag-dropdown > summary { font-weight: 400; }
    main input:not([type=checkbox]) { box-sizing: border-box; width: 100%; margin-top: .4rem; padding: .75rem; border: 1px solid #bcc6ce; border-radius: .55rem; }
    main textarea { box-sizing: border-box; width: 100%; margin-top: .4rem; padding: .75rem; border: 1px solid #bcc6ce; border-radius: .55rem; font: inherit; }
    main button { margin-top: 1.25rem; padding: .75rem 1rem; border: 0; border-radius: .55rem; background: var(--inter-accent); color: #fff; font-weight: 700; }
    .checks { display: grid; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); gap: .5rem; margin-top: .6rem; }
    .checks label { margin: 0; padding: .65rem; border: 1px solid #dfe4e8; border-radius: .5rem; background: #fff; }
    .checks input { margin-right: .45rem; }
    .contact-filters { display: grid; grid-template-columns: minmax(15rem, 1fr) minmax(12rem, 18rem) auto auto; gap: .55rem; align-items: end; max-width: none !important; }
    .contact-filters input, .contact-filters select, .contact-filters button { margin-top: 0 !important; min-height: 3rem; }
    .button-secondary { display: grid; place-items: center; min-height: 3rem; padding: .6rem .8rem; border: 1px solid #bcc6ce; border-radius: .55rem; background: #fff; text-decoration: none; }
    .tag-list { display: flex; flex-wrap: wrap; gap: .35rem; min-width: 9rem; }
    .tag-badge { display: inline-flex; align-items: center; width: fit-content; padding: .2rem .55rem; border: 1px solid var(--tag-color); border-radius: 999px; color: var(--tag-color); background: #fff; font-size: .8rem; font-weight: 750; white-space: nowrap; }
    .tag-picker { display: flex; flex-wrap: wrap; gap: .55rem; margin: 1.2rem 0; padding: 1rem; border: 1px solid #dfe4e8; border-radius: .6rem; background: #fff; }
    .tag-picker legend { padding: 0 .3rem; font-weight: 700; }
    .tag-picker label { display: flex; align-items: center; gap: .35rem; margin: 0; cursor: pointer; }
    .tag-dropdown { position: relative; margin-top: .4rem; max-width: 100%; }
    .tag-dropdown > summary { min-height: 3rem; padding: .72rem; border: 1px solid #bcc6ce; border-radius: .55rem; background: #fff; cursor: pointer; list-style-position: inside; }
    .tags-dropdown > summary .tag-badge { margin: 0 .2rem; }
    .tag-dropdown-menu { position: absolute; z-index: 30; display: grid; gap: .45rem; width: 100%; max-height: 16rem; overflow-y: auto; padding: .75rem; border: 1px solid #dfe4e8; border-radius: .55rem; background: #fff; box-shadow: 0 .7rem 1.8rem rgb(23 33 43 / 14%); }
    .tag-dropdown-menu label { display: flex; align-items: center; gap: .45rem; margin: 0; padding: .25rem; cursor: pointer; }
    .status-options label { justify-content: flex-start; }
    .status-options input[type=radio] { appearance: none; box-sizing: border-box; flex: 0 0 .85rem; width: .85rem !important; height: .85rem; margin: 0 !important; padding: 0 !important; border: 1px solid #8c98a4; border-radius: .18rem; background: #fff; }
    .status-options input[type=radio]:checked { border-color: var(--inter-accent); background: var(--inter-accent); box-shadow: inset 0 0 0 2px #fff; }
    main { overflow-x: auto; }
    table { width: 100%; min-width: 42rem; margin-top: 1rem; background: #fff; }
    th, td { padding: .75rem; border-bottom: 1px solid #e3e7ea; vertical-align: top; }
    .guest-card { width: min(30rem, calc(100% - 2rem)); }
    @media (min-width: 576px) { .d-sm-block { display: block; } .col-sm-6 { width: 50%; } }
    @media (min-width: 768px) { .col-md-4 { width: 33.333%; } .p-md-5 { padding: 3rem; } }
    @media (min-width: 992px) { .d-lg-none { display: none; } .px-lg-4 { padding-inline: 1.5rem; } }
    @media (min-width: 1200px) { .col-xl-4 { width: 33.333%; } }
    @media (max-width: 991.98px) { .desktop-sidebar { display: none; } .content-wrap { padding: 1.25rem; } .contact-filters { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
<?php if (!$authenticated): ?>
  <div class="min-vh-100 d-grid align-items-center py-4"><main class="guest-card mx-auto bg-white border rounded-4 shadow-sm p-4 p-md-5"><?= $content ?></main></div>
<?php else: ?>
  <div class="app-shell d-flex">
    <aside class="sidebar desktop-sidebar flex-shrink-0 p-3">
      <a class="brand d-flex align-items-center gap-2 mb-4" href="<?= $escape($basePath) ?>/"><img class="brand-logo" src="<?= $escape($basePath) ?>/assets/media/painel-inter.png" alt=""><span>PAINEL INTER</span></a>
      <nav class="nav flex-column gap-1">
        <a class="nav-link" href="<?= $escape($basePath) ?>/"><i class="fa-solid fa-house fa-fw" aria-hidden="true"></i>Visão geral</a>
        <?php if ($navigation['crm'] ?? false): ?><details class="nav-group" <?= $crmOpen ? 'open' : '' ?>><summary><span><i class="fa-solid fa-chart-line fa-fw" aria-hidden="true"></i>CRM</span></summary><div class="nav-submenu"><a class="nav-link" href="<?= $escape($basePath) ?>/crm/contacts"><i class="fa-solid fa-address-book fa-fw" aria-hidden="true"></i>Contatos</a><a class="nav-link" href="<?= $escape($basePath) ?>/crm/follow-ups"><i class="fa-solid fa-calendar-check fa-fw" aria-hidden="true"></i>Follow-ups</a></div></details><?php endif; ?>
        <?php if (($navigation['users'] ?? false) || ($navigation['units'] ?? false) || ($navigation['roles'] ?? false) || ($navigation['tags'] ?? false) || ($navigation['statuses'] ?? false)): ?><details class="nav-group" <?= $admOpen ? 'open' : '' ?>><summary><span><i class="fa-solid fa-gear fa-fw" aria-hidden="true"></i>ADM</span></summary><div class="nav-submenu"><?php if ($navigation['units'] ?? false): ?><a class="nav-link" href="<?= $escape($basePath) ?>/units"><i class="fa-solid fa-building fa-fw" aria-hidden="true"></i>Unidades</a><?php endif; ?><?php if ($navigation['users'] ?? false): ?><a class="nav-link" href="<?= $escape($basePath) ?>/users"><i class="fa-solid fa-users fa-fw" aria-hidden="true"></i>Usuários</a><?php endif; ?><?php if ($navigation['roles'] ?? false): ?><a class="nav-link" href="<?= $escape($basePath) ?>/roles"><i class="fa-solid fa-user-shield fa-fw" aria-hidden="true"></i>Perfis e permissões</a><?php endif; ?><?php if ($navigation['tags'] ?? false): ?><a class="nav-link" href="<?= $escape($basePath) ?>/tags"><i class="fa-solid fa-tags fa-fw" aria-hidden="true"></i>Etiquetas</a><?php endif; ?><?php if ($navigation['statuses'] ?? false): ?><a class="nav-link" href="<?= $escape($basePath) ?>/statuses"><i class="fa-solid fa-list-check fa-fw" aria-hidden="true"></i>Status do CRM</a><?php endif; ?></div></details><?php endif; ?>
      </nav>
    </aside>
    <div class="flex-grow-1 min-width-0">
      <header class="topbar sticky-top d-flex align-items-center px-3 px-lg-4 gap-3">
        <details class="mobile-menu d-lg-none"><summary aria-label="Abrir menu">☰</summary><div class="mobile-menu-panel"><nav class="nav flex-column gap-1"><a class="nav-link" href="<?= $escape($basePath) ?>/"><i class="fa-solid fa-house fa-fw" aria-hidden="true"></i>Visão geral</a><?php if ($navigation['crm'] ?? false): ?><details class="nav-group" <?= $crmOpen ? 'open' : '' ?>><summary><span><i class="fa-solid fa-chart-line fa-fw" aria-hidden="true"></i>CRM</span></summary><div class="nav-submenu"><a class="nav-link" href="<?= $escape($basePath) ?>/crm/contacts"><i class="fa-solid fa-address-book fa-fw" aria-hidden="true"></i>Contatos</a><a class="nav-link" href="<?= $escape($basePath) ?>/crm/follow-ups"><i class="fa-solid fa-calendar-check fa-fw" aria-hidden="true"></i>Follow-ups</a></div></details><?php endif; ?><?php if (($navigation['users'] ?? false) || ($navigation['units'] ?? false) || ($navigation['roles'] ?? false) || ($navigation['tags'] ?? false) || ($navigation['statuses'] ?? false)): ?><details class="nav-group" <?= $admOpen ? 'open' : '' ?>><summary><span><i class="fa-solid fa-gear fa-fw" aria-hidden="true"></i>ADM</span></summary><div class="nav-submenu"><?php if ($navigation['units'] ?? false): ?><a class="nav-link" href="<?= $escape($basePath) ?>/units"><i class="fa-solid fa-building fa-fw" aria-hidden="true"></i>Unidades</a><?php endif; ?><?php if ($navigation['users'] ?? false): ?><a class="nav-link" href="<?= $escape($basePath) ?>/users"><i class="fa-solid fa-users fa-fw" aria-hidden="true"></i>Usuários</a><?php endif; ?><?php if ($navigation['roles'] ?? false): ?><a class="nav-link" href="<?= $escape($basePath) ?>/roles"><i class="fa-solid fa-user-shield fa-fw" aria-hidden="true"></i>Perfis e permissões</a><?php endif; ?><?php if ($navigation['tags'] ?? false): ?><a class="nav-link" href="<?= $escape($basePath) ?>/tags"><i class="fa-solid fa-tags fa-fw" aria-hidden="true"></i>Etiquetas</a><?php endif; ?><?php if ($navigation['statuses'] ?? false): ?><a class="nav-link" href="<?= $escape($basePath) ?>/statuses"><i class="fa-solid fa-list-check fa-fw" aria-hidden="true"></i>Status do CRM</a><?php endif; ?></div></details><?php endif; ?></nav></div></details>
        <form class="unit-switcher d-flex align-items-center gap-2 me-auto" method="post" action="<?= $escape($basePath) ?>/context/unit">
          <?= $csrfField ?>
          <label class="visually-hidden" for="active-unit">Unidade ativa</label>
          <select class="form-select form-select-sm" id="active-unit" name="unit_code" aria-label="Unidade ativa">
            <?php if (count($availableUnits) > 1): ?><option value="__all__" <?= ($currentUnit['code'] ?? null) === '__all__' ? 'selected' : '' ?>>Todas as unidades</option><?php endif; ?>
            <?php foreach ($availableUnits as $unit): ?><option value="<?= $escape($unit['code']) ?>" <?= ($currentUnit['code'] ?? null) === $unit['code'] ? 'selected' : '' ?>><?= $escape($unit['name']) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-success m-0" type="submit">Aplicar</button>
        </form>
        <div class="d-none d-sm-block text-end"><strong><?= $escape($currentUser->name) ?></strong><div class="meta"><?= $escape($currentUser->email) ?></div></div>
        <form method="post" action="<?= $escape($basePath) ?>/logout"><?= $csrfField ?><button class="btn btn-sm btn-outline-secondary m-0" type="submit">Sair</button></form>
      </header>
      <main class="content-wrap"><?= $content ?></main>
    </div>
  </div>
<?php endif; ?>
<script src="<?= $escape($basePath) ?>/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="<?= $escape($basePath) ?>/assets/js/app.js?v=4"></script>
</body>
</html>
