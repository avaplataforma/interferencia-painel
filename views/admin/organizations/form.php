<?php
$editing=is_array($organization);$siteDomain=null;
foreach($domains as$domain){if(($domain['purpose']??'')==='site'&&($domain['is_primary']??0)){$siteDomain=$domain;break;}}
$logo=(string)($organization['logo_path']??'');$favicon=(string)($organization['favicon_path']??'');
?>
<div class="page-header"><div><p class="eyebrow">Mundo Inter · ADM Central</p><h1><?= $editing?'Personalizar organização':'Nova organização' ?></h1><p>Configure acesso, identidade visual, atendimento e presença pública da franquia.</p></div><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/organizations"><i class="fa-solid fa-arrow-left"></i> Voltar</a></div>
<?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>
<form class="card form-grid" method="post" enctype="multipart/form-data" action="<?= $escape($basePath) ?>/admin/organizations<?= $editing?'/'.(int)$organization['id']:'' ?>"><?= $csrfField ?>
<div class="form-section form-span-2"><h2>Dados da organização</h2><p>Identificação jurídica e operacional usada pelo ADM Central.</p></div>
<label>Nome de exibição *<input required maxlength="160" name="display_name" value="<?= $escape($organization['display_name']??'') ?>" placeholder="Ex.: Franquia Tijucas"></label>
<label>Razão social *<input required maxlength="190" name="legal_name" value="<?= $escape($organization['legal_name']??'') ?>"></label>
<label>Código interno *<input required maxlength="80" name="code" value="<?= $escape($organization['code']??'') ?>" placeholder="franquia_tijucas"><small>Visível somente nos controles internos.</small></label>
<label>Situação<select name="status"><option value="active" <?= ($organization['status']??'active')==='active'?'selected':'' ?>>Ativa</option><option value="suspended" <?= ($organization['status']??'')==='suspended'?'selected':'' ?>>Suspensa</option></select></label>

<div class="form-section form-span-2"><h2>Endereços</h2><p>O painel é hospedado pelo Mundo Inter; o site público pode continuar usando o domínio e o e-mail atuais da franquia.</p></div>
<label class="form-span-2">Endereço privado da franquia *<div class="input-prefix"><span>mundointer.com.br/</span><input required maxlength="100" name="panel_slug" value="<?= $escape($organization['panel_slug']??'') ?>" placeholder="nome-da-franquia"></div><small>Exclusivo para login e uso do painel privado.</small></label>
<label class="form-span-2">Domínio público do site<input maxlength="253" name="site_host" value="<?= $escape($siteDomain['host']??'') ?>" placeholder="www.franquiatal.com.br"><small>No DNS, somente o site será apontado para a infraestrutura Mundo Inter.</small></label>
<label class="checkbox-row form-span-2"><input type="checkbox" name="domain_active" value="1" <?= ($siteDomain['status']??'pending')==='active'?'checked':'' ?>> Domínio público já validado e ativo</label>

<div class="form-section form-span-2"><h2>Identidade visual</h2><p>Aplicada na tela de acesso e, progressivamente, em toda a experiência da organização.</p></div>
<label>Cor principal *<div class="color-field"><input type="color" name="primary_color" value="<?= $escape($organization['primary_color']??'#ed1c24') ?>"><input aria-label="Cor principal em hexadecimal" maxlength="7" value="<?= $escape($organization['primary_color']??'#ed1c24') ?>" data-color-text readonly></div></label>
<label>Cor secundária<div class="color-field"><input type="color" name="secondary_color" value="<?= $escape($organization['secondary_color']??'#102a56') ?>"><input aria-label="Cor secundária em hexadecimal" maxlength="7" value="<?= $escape($organization['secondary_color']??'#102a56') ?>" data-color-text readonly></div></label>
<label>Logo da franquia<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"><small>PNG, JPG ou WebP, até 3 MB.</small><?php if($logo!==''):?><span class="brand-preview"><img src="<?= $escape($basePath.$logo) ?>" alt="Logo atual"><label class="checkbox-row"><input type="checkbox" name="remove_logo" value="1"> Remover logo atual</label></span><?php endif;?></label>
<label>Favicon<input type="file" name="favicon" accept="image/png,image/jpeg,image/webp"><small>Preferencialmente quadrado.</small><?php if($favicon!==''):?><span class="brand-preview brand-preview-icon"><img src="<?= $escape($basePath.$favicon) ?>" alt="Favicon atual"><label class="checkbox-row"><input type="checkbox" name="remove_favicon" value="1"> Remover favicon atual</label></span><?php endif;?></label>

<div class="form-section form-span-2"><h2>Tela de acesso</h2><p>Textos exibidos antes de o usuário entrar no painel da franquia.</p></div>
<label>Título do login<input maxlength="160" name="login_title" value="<?= $escape($organization['login_title']??'') ?>" placeholder="Ex.: Portal da Franquia"></label>
<label>Mensagem de boas-vindas<input maxlength="500" name="login_welcome_text" value="<?= $escape($organization['login_welcome_text']??'') ?>" placeholder="Use suas credenciais para continuar."></label>
<label>E-mail de suporte<input type="email" maxlength="190" name="support_email" value="<?= $escape($organization['support_email']??'') ?>" placeholder="suporte@franquia.com.br"></label>
<label>Telefone de suporte<input maxlength="30" name="support_phone" value="<?= $escape($organization['support_phone']??'') ?>" placeholder="(00) 00000-0000"></label>
<div class="form-actions form-span-2"><button class="button button-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar organização e marca</button></div>
</form>
<script>document.querySelectorAll('.color-field').forEach(function(group){var picker=group.querySelector('input[type=color]'),text=group.querySelector('[data-color-text]');picker.addEventListener('input',function(){text.value=picker.value;});});</script>
