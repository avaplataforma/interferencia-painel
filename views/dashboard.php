<?php

declare(strict_types=1);

/** @var Closure(mixed): string $escape */
/** @var string $csrfField */
/** @var Interferencia\Modules\Identity\User $user */
/** @var list<string> $unitScopes */
/** @var string $basePath */
/** @var bool $canManageUsers */
/** @var bool $canManageUnits */
/** @var bool $canManageRoles */
?>
<span class="status">Sessão autenticada</span>
<h1>Olá, <?= $escape($user->name) ?></h1>
<p>Esta é a primeira área protegida do PAINEL INTER 📊.</p>
<dl>
  <dt>E-mail</dt><dd><?= $escape($user->email) ?></dd>
  <dt>Unidades</dt><dd><?= $escape((string) count($unitScopes)) ?></dd>
</dl>
<?php if ($canManageUsers): ?><p><a href="<?= $escape($basePath) ?>/users">Gerenciar usuários</a></p><?php endif; ?>
<?php if ($canManageUnits): ?><p><a href="<?= $escape($basePath) ?>/units">Gerenciar unidades</a></p><?php endif; ?>
<?php if ($canManageRoles): ?><p><a href="<?= $escape($basePath) ?>/roles">Gerenciar perfis e permissões</a></p><?php endif; ?>
<form method="post" action="<?= $escape($basePath) ?>/logout">
  <?= $csrfField ?>
  <button type="submit">Sair</button>
</form>
