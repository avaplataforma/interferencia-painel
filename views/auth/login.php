<?php

declare(strict_types=1);
?>
<img class="login-logo" src="<?= $escape($assetBasePath.$brandLogo) ?>" alt="Logo <?= $escape($brandName) ?>">
<span class="status"><?= !empty($isCentralContext)?'ADM Central':'Acesso da organização' ?></span>
<h1><?= $escape($currentOrganization?->loginTitle ?: $brandName) ?></h1>
<p><?= $escape($currentOrganization?->loginWelcomeText ?: 'Use suas credenciais para continuar.') ?></p>
<?php if ($error !== null): ?><p class="alert" role="alert"><?= $escape($error) ?></p><?php endif; ?>
<form method="post" action="<?= $escape($basePath) ?>/login" novalidate>
  <?= $csrfField ?>
  <label for="email">E-mail</label>
  <input id="email" name="email" type="email" autocomplete="username" required value="<?= $escape($email) ?>">
  <label for="password">Senha</label>
  <input id="password" name="password" type="password" autocomplete="current-password" required>
  <button type="submit">Entrar</button>
</form>
