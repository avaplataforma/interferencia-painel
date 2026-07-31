<?php

declare(strict_types=1);

/** @var Closure(mixed): string $escape */
/** @var string $csrfField */
/** @var string|null $error */
/** @var string $email */
/** @var string $basePath */
?>
<span class="status">Acesso seguro</span>
<h1>Entrar no PAINEL INTER 📊</h1>
<p>Use suas credenciais para continuar.</p>
<?php if ($error !== null): ?>
  <p class="alert" role="alert"><?= $escape($error) ?></p>
<?php endif; ?>
<form method="post" action="<?= $escape($basePath) ?>/login" novalidate>
  <?= $csrfField ?>
  <label for="email">E-mail</label>
  <input id="email" name="email" type="email" autocomplete="username" required value="<?= $escape($email) ?>">
  <label for="password">Senha</label>
  <input id="password" name="password" type="password" autocomplete="current-password" required>
  <button type="submit">Entrar</button>
</form>
