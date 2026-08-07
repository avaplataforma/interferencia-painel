<?php
$editing=is_array($organization);$siteDomain=null;
foreach($domains as$domain){if(($domain['purpose']??'')==='site'&&($domain['is_primary']??0)){$siteDomain=$domain;break;}}
?>
<div class="page-header"><div><p class="eyebrow">Mundo Inter</p><h1><?= $editing?'Editar organização':'Nova organização' ?></h1><p>Defina o endereço privado da franquia e o domínio de seu site público.</p></div><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/organizations">Voltar</a></div>
<?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>
<form class="card form-grid" method="post" action="<?= $escape($basePath) ?>/admin/organizations<?= $editing?'/'.(int)$organization['id']:'' ?>"><?= $csrfField ?>
<label>Nome de exibição *<input required maxlength="160" name="display_name" value="<?= $escape($organization['display_name']??'') ?>" placeholder="Ex.: Franquia Tijucas"></label>
<label>Razão social *<input required maxlength="190" name="legal_name" value="<?= $escape($organization['legal_name']??'') ?>"></label>
<label>Código interno *<input required maxlength="80" name="code" value="<?= $escape($organization['code']??'') ?>" placeholder="franquia_tijucas"><small>Usado somente nos controles internos.</small></label>
<label>Situação<select name="status"><option value="active" <?= ($organization['status']??'active')==='active'?'selected':'' ?>>Ativa</option><option value="suspended" <?= ($organization['status']??'')==='suspended'?'selected':'' ?>>Suspensa</option></select></label>
<label class="form-span-2">Endereço interno da franquia *<div class="input-prefix"><span>mundointer.com.br/</span><input required maxlength="100" name="panel_slug" value="<?= $escape($organization['panel_slug']??'') ?>" placeholder="nome-da-franquia"></div><small>Este será o endereço exclusivo do login e do painel privado.</small></label>
<label class="form-span-2">Domínio público do site<input maxlength="253" name="site_host" value="<?= $escape($siteDomain['host']??'') ?>" placeholder="www.franquiatal.com.br"><small>O domínio permanece no provedor da franquia; somente o site será apontado para o Mundo Inter.</small></label>
<label class="checkbox-row form-span-2"><input type="checkbox" name="domain_active" value="1" <?= ($siteDomain['status']??'pending')==='active'?'checked':'' ?>> Domínio público já validado e ativo</label>
<div class="form-actions form-span-2"><button class="button button-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar organização</button></div>
</form>
