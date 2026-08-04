<?php
declare(strict_types=1);
/** @var Closure(mixed): string $escape */
$editing = $role !== null;
$protected = $editing && $role['code'] === 'super_admin';
?>
<span class="status"><?= $editing ? 'Edição' : 'Cadastro' ?></span>
<h1><?= $editing ? 'Editar perfil' : 'Novo perfil' ?></h1>
<?php if ($error): ?><p class="alert" role="alert"><?= $escape($error) ?></p><?php endif; ?>
<?php if ($protected): ?><p class="alert" style="background:#fff8e6;color:#765300">Por segurança, o Admin System sempre mantém todas as permissões.</p><?php endif; ?>
<form method="post" action="<?= $escape($basePath) ?>/roles<?= $editing ? '/' . $escape($role['id']) : '' ?>" novalidate>
  <?= $csrfField ?>
  <label for="name">Nome do perfil</label><input id="name" name="name" required minlength="2" maxlength="120" value="<?= $escape($role['name'] ?? '') ?>">
  <?php if ($editing): ?><p><span class="meta">Código interno permanente: <?= $escape($role['code']) ?></span></p><?php endif; ?>
  <h2>Permissões</h2><div class="checks"><?php foreach ($permissions as $permission): ?><label><input type="checkbox" name="permissions[]" value="<?= $escape($permission['id']) ?>" <?= $protected || in_array((int) $permission['id'], $selectedPermissions, true) ? 'checked' : '' ?> <?= $protected ? 'disabled' : '' ?>><?= $escape($permission['name']) ?><br><span class="meta"><?= $escape($permission['code']) ?></span></label><?php endforeach; ?></div>
  <button type="submit">Salvar perfil</button>
</form>
<p><a href="<?= $escape($basePath) ?>/roles">Cancelar</a></p>
