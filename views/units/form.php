<?php
declare(strict_types=1);
/** @var Closure(mixed): string $escape */
$editing = $unit !== null;
?>
<span class="status"><?= $editing ? 'Edição' : 'Cadastro' ?></span>
<h1><?= $editing ? 'Editar unidade' : 'Nova unidade' ?></h1>
<?php if ($error): ?><p class="alert" role="alert"><?= $escape($error) ?></p><?php endif; ?>
<form method="post" action="<?= $escape($basePath) ?>/units<?= $editing ? '/' . $escape($unit['id']) : '' ?>" novalidate>
  <?= $csrfField ?>
  <label for="name">Nome</label><input id="name" name="name" required minlength="2" maxlength="120" value="<?= $escape($unit['name'] ?? '') ?>">
  <label for="city">Cidade</label><input id="city" name="city" required minlength="2" maxlength="120" value="<?= $escape($unit['city'] ?? '') ?>">
  <?php if ($editing): ?><p><span class="meta">Código interno permanente: <?= $escape($unit['code']) ?></span></p><?php endif; ?>
  <div class="checks"><label><input type="checkbox" name="is_active" value="1" <?= !$editing || (int) $unit['is_active'] === 1 ? 'checked' : '' ?>>Unidade ativa</label></div>
  <button type="submit">Salvar unidade</button>
</form>
<p><a href="<?= $escape($basePath) ?>/units">Cancelar</a></p>
