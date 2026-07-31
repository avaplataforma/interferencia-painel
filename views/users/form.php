<?php
declare(strict_types=1);
/** @var Closure(mixed): string $escape */
$editing = $user !== null;
?>
<span class="status"><?= $editing ? 'Edição' : 'Cadastro' ?></span>
<h1><?= $editing ? 'Editar usuário' : 'Novo usuário' ?></h1>
<?php if ($error): ?><p class="alert" role="alert"><?= $escape($error) ?></p><?php endif; ?>
<form method="post" action="<?= $escape($basePath) ?>/users<?= $editing ? '/' . $escape($user->id) : '' ?>" novalidate>
  <?= $csrfField ?>
  <label for="name">Nome</label><input id="name" name="name" required maxlength="120" value="<?= $escape($user?->name ?? '') ?>">
  <label for="email">E-mail</label><input id="email" name="email" type="email" required maxlength="190" value="<?= $escape($user?->email ?? '') ?>">
  <label for="password">Senha <?= $editing ? '(deixe vazia para manter)' : '' ?></label><input id="password" name="password" type="password" autocomplete="new-password" <?= $editing ? '' : 'required' ?>>
  <label for="password_confirmation">Confirmar senha</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" <?= $editing ? '' : 'required' ?>>
  <h2>Papel</h2><div class="checks"><?php foreach ($roles as $role): ?><label><input type="checkbox" name="roles[]" value="<?= $escape($role['id']) ?>" <?= in_array((int) $role['id'], $selectedRoles, true) ? 'checked' : '' ?>><?= $escape($role['name']) ?></label><?php endforeach; ?></div>
  <h2>Unidades permitidas</h2><div class="checks"><?php foreach ($units as $unit): ?><label><input type="checkbox" name="units[]" value="<?= $escape($unit['id']) ?>" <?= in_array((int) $unit['id'], $selectedUnits, true) ? 'checked' : '' ?>><?= $escape($unit['name']) ?></label><?php endforeach; ?></div>
  <div class="checks"><label><input type="checkbox" name="is_active" value="1" <?= !$editing || $user->active ? 'checked' : '' ?>>Usuário ativo</label></div>
  <button type="submit">Salvar usuário</button>
</form>
<p><a href="<?= $escape($basePath) ?>/users">Cancelar</a></p>
