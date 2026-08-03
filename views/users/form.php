<?php
declare(strict_types=1);
/** @var Closure(mixed): string $escape */
$editing = $user !== null;
$old = is_array($old ?? null) ? $old : [];
$name = (string) ($old['name'] ?? $user?->name ?? '');
$email = (string) ($old['email'] ?? $user?->email ?? '');
$active = array_key_exists('is_active', $old) ? (bool) $old['is_active'] : (!$editing || $user->active);
$required = '<span class="required-mark">*</span>';
?>
<span class="status"><?= $editing ? 'Edição' : 'Cadastro' ?></span>
<h1><?= $editing ? 'Editar usuário' : 'Novo usuário' ?></h1>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><strong>Não foi possível salvar.</strong><br><?= $escape($error) ?></div><?php endif; ?>
<p>Os campos marcados com <?= $required ?> são obrigatórios.</p>
<form method="post" action="<?= $escape($basePath) ?>/users<?= $editing ? '/' . $escape($user->id) : '' ?>">
  <?= $csrfField ?>
  <label for="name">Nome <?= $required ?></label><input id="name" name="name" required minlength="3" maxlength="120" value="<?= $escape($name) ?>">
  <label for="email">E-mail <?= $required ?></label><input id="email" name="email" type="email" required maxlength="190" value="<?= $escape($email) ?>">
  <label for="password">Senha <?= $editing ? '(deixe vazia para manter)' : $required ?></label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" <?= $editing ? '' : 'required' ?>><small>Use no mínimo 12 caracteres.</small>
  <label for="password_confirmation">Confirmar senha <?= $editing ? '' : $required ?></label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" <?= $editing ? '' : 'required' ?>>
  <h2>Papel <?= $required ?></h2><p>Selecione pelo menos um papel.</p><div class="checks"><?php foreach ($roles as $role): ?><label><input type="checkbox" name="roles[]" value="<?= $escape($role['id']) ?>" <?= in_array((int) $role['id'], $selectedRoles, true) ? 'checked' : '' ?>><?= $escape($role['name']) ?></label><?php endforeach; ?></div>
  <h2>Unidades permitidas <?= $required ?></h2><p>Selecione pelo menos uma unidade.</p><div class="checks"><?php foreach ($units as $unit): ?><label><input type="checkbox" name="units[]" value="<?= $escape($unit['id']) ?>" <?= in_array((int) $unit['id'], $selectedUnits, true) ? 'checked' : '' ?>><?= $escape($unit['name']) ?></label><?php endforeach; ?></div>
  <div class="checks"><label><input type="checkbox" name="is_active" value="1" <?= $active ? 'checked' : '' ?>>Usuário ativo</label></div>
  <button type="submit">Salvar usuário</button>
</form>
<p><a href="<?= $escape($basePath) ?>/users">Cancelar</a></p>
