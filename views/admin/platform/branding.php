<div class="page-header">
  <div><p class="eyebrow">ADM</p><h1>Personalização</h1><p>Identidade visual e comunicação do ADM Central Mundo Inter.</p></div>
</div>

<?php if (!empty($message)): ?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif; ?>

<form method="post" action="<?= $escape($basePath) ?>/admin/platform/branding" enctype="multipart/form-data" class="dashboard-section">
  <?= $csrfField ?>
  <div class="dashboard-section-heading"><div><span class="section-eyebrow">Marca central</span><h2>Identidade do Mundo Inter</h2><p>Estas alterações não modificam logos, cores ou logins das franquias.</p></div></div>

  <div class="row g-4 mt-1">
    <div class="col-lg-6">
      <label>Nome de exibição *</label>
      <input name="display_name" maxlength="120" required value="<?= $escape($settings['display_name']) ?>">
    </div>
    <div class="col-md-6 col-lg-3">
      <label>Cor principal *</label>
      <input name="primary_color" type="color" required value="<?= $escape($settings['primary_color']) ?>" style="min-height:3rem">
    </div>
    <div class="col-md-6 col-lg-3">
      <label>Cor secundária *</label>
      <input name="secondary_color" type="color" required value="<?= $escape($settings['secondary_color']) ?>" style="min-height:3rem">
    </div>
  </div>

  <div class="row g-4 mt-1">
    <div class="col-md-6">
      <div class="border rounded-4 bg-light p-4 h-100">
        <strong>Logo principal</strong>
        <div class="my-3"><img src="<?= $escape($assetBasePath.$settings['logo_path']) ?>" alt="Logo atual" style="max-width:18rem;max-height:9rem;object-fit:contain"></div>
        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
        <label class="d-flex align-items-center gap-2 mt-3"><input type="checkbox" name="remove_logo" value="1" style="width:auto;margin:0"> Restaurar logo padrão</label>
      </div>
    </div>
    <div class="col-md-6">
      <div class="border rounded-4 bg-light p-4 h-100">
        <strong>Favicon</strong>
        <div class="my-3"><img src="<?= $escape($assetBasePath.$settings['favicon_path']) ?>" alt="Favicon atual" style="width:6rem;height:6rem;object-fit:contain"></div>
        <input type="file" name="favicon" accept="image/png,image/jpeg,image/webp,image/svg+xml">
        <label class="d-flex align-items-center gap-2 mt-3"><input type="checkbox" name="remove_favicon" value="1" style="width:auto;margin:0"> Restaurar favicon padrão</label>
      </div>
    </div>
  </div>

  <hr class="my-4">
  <div class="dashboard-section-heading"><div><span class="section-eyebrow">Tela de entrada</span><h2>Comunicação do login</h2></div></div>
  <div class="row g-4 mt-1">
    <div class="col-md-6"><label>Título do login</label><input name="login_title" maxlength="160" value="<?= $escape($settings['login_title'] ?? '') ?>"></div>
    <div class="col-md-6"><label>Mensagem de boas-vindas</label><input name="login_welcome_text" maxlength="500" value="<?= $escape($settings['login_welcome_text'] ?? '') ?>"></div>
    <div class="col-md-6"><label>E-mail de suporte</label><input name="support_email" type="email" maxlength="190" value="<?= $escape($settings['support_email'] ?? '') ?>"></div>
    <div class="col-md-6"><label>Telefone de suporte</label><input name="support_phone" maxlength="30" value="<?= $escape($settings['support_phone'] ?? '') ?>"></div>
  </div>
  <p class="text-muted mt-3 mb-0">Imagens aceitas: PNG, JPG ou WebP, com até 3 MB.</p>
  <button type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar personalização</button>
</form>
