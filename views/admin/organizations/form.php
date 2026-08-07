<?php
$editing=is_array($organization);$siteDomain=null;
foreach($domains as$domain){if(($domain['purpose']??'')==='site'&&($domain['is_primary']??0)){$siteDomain=$domain;break;}}
$logo=(string)($organization['logo_path']??'');$favicon=(string)($organization['favicon_path']??'');
?>
<div class="page-header"><div><p class="eyebrow">Mundo Inter · ADM Central</p><h1><?= $editing?'Personalizar franquia':'Nova franquia' ?></h1><p>Configure acesso, identidade visual, atendimento e presença pública da franquia.</p></div><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/organizations"><i class="fa-solid fa-arrow-left"></i> Voltar</a></div>
<?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>
<form class="card form-grid" method="post" enctype="multipart/form-data" action="<?= $escape($basePath) ?>/admin/organizations<?= $editing?'/'.(int)$organization['id']:'' ?>"><?= $csrfField ?>
<div class="form-section form-span-2"><h2>Dados da franquia</h2><p>Identificação jurídica e operacional usada pelo ADM Central.</p></div>
<label>Nome de exibição *<input required maxlength="160" name="display_name" value="<?= $escape($organization['display_name']??'') ?>" placeholder="Ex.: Franquia Tijucas"></label>
<label>Razão social *<input required maxlength="190" name="legal_name" value="<?= $escape($organization['legal_name']??'') ?>"></label>
<label>CNPJ *<input required name="cnpj" inputmode="numeric" maxlength="18" data-mask="document" value="<?= $escape($organization['cnpj']??'') ?>" placeholder="00.000.000/0000-00"><small>O CNPJ será validado e não poderá se repetir.</small></label>
<label>Nome fantasia <input maxlength="160" value="<?= $escape($organization['display_name']??'') ?>" disabled><small>É o mesmo nome de exibição informado acima.</small></label>
<label>Inscrição estadual<input maxlength="40" name="state_registration" value="<?= $escape($organization['state_registration']??'') ?>"></label>
<label>Inscrição municipal<input maxlength="40" name="municipal_registration" value="<?= $escape($organization['municipal_registration']??'') ?>"></label>
<label>Código interno *<input required maxlength="80" name="code" value="<?= $escape($organization['code']??'') ?>" placeholder="franquia_tijucas"><small>Visível somente nos controles internos.</small></label>
<label>Situação<select name="status"><option value="active" <?= ($organization['status']??'active')==='active'?'selected':'' ?>>Ativa</option><option value="suspended" <?= ($organization['status']??'')==='suspended'?'selected':'' ?>>Suspensa</option></select></label>

<div class="form-section form-span-2"><h2>Contato principal</h2><p>Somente nome, e-mail e telefone do gestor são obrigatórios. Os demais dados poderão ser concluídos depois.</p></div>
<label>Gestor responsável *<input required maxlength="160" name="manager_name" value="<?= $escape($organization['manager_name']??'') ?>"></label>
<label>CPF do gestor<input name="manager_document" inputmode="numeric" maxlength="14" data-mask="document" value="<?= $escape($organization['manager_document']??'') ?>" placeholder="000.000.000-00"></label>
<label>E-mail do gestor *<input required type="email" maxlength="190" name="manager_email" value="<?= $escape($organization['manager_email']??'') ?>"></label>
<label>Telefone/WhatsApp do gestor *<input required name="manager_phone" inputmode="tel" maxlength="16" data-mask="phone" value="<?= $escape($organization['manager_phone']??'') ?>" placeholder="(00) 00000-0000"></label>

<div class="form-section form-span-2"><h2>Gerente</h2><p>Cadastro opcional para contato operacional da franquia.</p></div>
<label>Nome do gerente<input maxlength="160" name="general_manager_name" value="<?= $escape($organization['general_manager_name']??'') ?>"></label>
<label>CPF do gerente<input name="general_manager_document" inputmode="numeric" maxlength="14" data-mask="document" value="<?= $escape($organization['general_manager_document']??'') ?>" placeholder="000.000.000-00"></label>
<label>E-mail do gerente<input type="email" maxlength="190" name="general_manager_email" value="<?= $escape($organization['general_manager_email']??'') ?>"></label>
<label>Telefone/WhatsApp do gerente<input name="general_manager_phone" inputmode="tel" maxlength="16" data-mask="phone" value="<?= $escape($organization['general_manager_phone']??'') ?>" placeholder="(00) 00000-0000"></label>

<div class="form-section form-span-2"><h2>Endereço físico</h2><p>Opcional nesta etapa; poderá ser preenchido ou atualizado posteriormente.</p></div>
<label>CEP<input name="postal_code" inputmode="numeric" maxlength="9" value="<?= $escape($organization['postal_code']??'') ?>" placeholder="00000-000"></label>
<label>Endereço<input maxlength="190" name="address" value="<?= $escape($organization['address']??'') ?>"></label>
<label>Número<input maxlength="30" name="address_number" value="<?= $escape($organization['address_number']??'') ?>"></label>
<label>Complemento<input maxlength="120" name="address_complement" value="<?= $escape($organization['address_complement']??'') ?>"></label>
<label>Bairro<input maxlength="120" name="neighborhood" value="<?= $escape($organization['neighborhood']??'') ?>"></label>
<label>Cidade<input maxlength="120" name="city" value="<?= $escape($organization['city']??'') ?>"></label>
<label>UF<input maxlength="2" name="state" value="<?= $escape($organization['state']??'') ?>" placeholder="SC" style="text-transform:uppercase"></label>

<div class="form-section form-span-2"><h2>Endereços</h2><p>O painel é hospedado pelo Mundo Inter; o site público pode continuar usando o domínio e o e-mail atuais da franquia.</p></div>
<label class="form-span-2">Endereço privado da franquia *<div class="input-prefix"><span>mundointer.com.br/</span><input required maxlength="100" name="panel_slug" value="<?= $escape($organization['panel_slug']??'') ?>" placeholder="nome-da-franquia"></div><small>Exclusivo para login e uso do painel privado.</small></label>
<label class="form-span-2">Domínio público do site<input maxlength="253" name="site_host" value="<?= $escape($siteDomain['host']??'') ?>" placeholder="www.franquiatal.com.br"><small>No DNS, somente o site será apontado para a infraestrutura Mundo Inter.</small></label>
<label class="checkbox-row form-span-2"><input type="checkbox" name="domain_active" value="1" <?= ($siteDomain['status']??'pending')==='active'?'checked':'' ?>> Domínio público já validado e ativo</label>

<div class="form-section form-span-2"><h2>Identidade visual</h2><p>Aplicada na tela de acesso e, progressivamente, em toda a experiência da franquia.</p></div>
<label>Cor principal *<div class="color-field"><input type="color" name="primary_color" value="<?= $escape($organization['primary_color']??'#ed1c24') ?>"><input aria-label="Cor principal em hexadecimal" maxlength="7" value="<?= $escape($organization['primary_color']??'#ed1c24') ?>" data-color-text readonly></div></label>
<label>Cor secundária<div class="color-field"><input type="color" name="secondary_color" value="<?= $escape($organization['secondary_color']??'#102a56') ?>"><input aria-label="Cor secundária em hexadecimal" maxlength="7" value="<?= $escape($organization['secondary_color']??'#102a56') ?>" data-color-text readonly></div></label>
<label>Logo da franquia<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"><small>PNG, JPG ou WebP, até 3 MB.</small><?php if($logo!==''):?><span class="brand-preview"><img src="<?= $escape($basePath.$logo) ?>" alt="Logo atual"><label class="checkbox-row"><input type="checkbox" name="remove_logo" value="1"> Remover logo atual</label></span><?php endif;?></label>
<label>Favicon<input type="file" name="favicon" accept="image/png,image/jpeg,image/webp"><small>Preferencialmente quadrado.</small><?php if($favicon!==''):?><span class="brand-preview brand-preview-icon"><img src="<?= $escape($basePath.$favicon) ?>" alt="Favicon atual"><label class="checkbox-row"><input type="checkbox" name="remove_favicon" value="1"> Remover favicon atual</label></span><?php endif;?></label>

<div class="form-section form-span-2"><h2>Tela de acesso</h2><p>Textos exibidos antes de o usuário entrar no painel da franquia.</p></div>
<label>Título do login<input maxlength="160" name="login_title" value="<?= $escape($organization['login_title']??'') ?>" placeholder="Ex.: Portal da Franquia"></label>
<label>Mensagem de boas-vindas<input maxlength="500" name="login_welcome_text" value="<?= $escape($organization['login_welcome_text']??'') ?>" placeholder="Use suas credenciais para continuar."></label>
<label>E-mail de suporte<input type="email" maxlength="190" name="support_email" value="<?= $escape($organization['support_email']??'') ?>" placeholder="suporte@franquia.com.br"></label>
<label>Telefone de suporte<input maxlength="30" name="support_phone" value="<?= $escape($organization['support_phone']??'') ?>" placeholder="(00) 00000-0000"></label>
<div class="form-actions form-span-2"><button class="button button-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar franquia e marca</button></div>
</form>
<script>document.querySelectorAll('.color-field').forEach(function(group){var picker=group.querySelector('input[type=color]'),text=group.querySelector('[data-color-text]');picker.addEventListener('input',function(){text.value=picker.value;});});</script>
