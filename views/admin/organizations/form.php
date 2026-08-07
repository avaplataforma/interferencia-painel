<?php $editing=is_array($organization);$primary=$domains[0]??null; ?>
<div class="page-header"><div><p class="eyebrow">Mundo Inter</p><h1><?= $editing?'Editar organização':'Nova organização' ?></h1><p>O domínio será o endereço exclusivo de entrada da franquia.</p></div><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/organizations">Voltar</a></div>
<?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>
<form class="card form-grid" method="post" action="<?= $escape($basePath) ?>/admin/organizations<?= $editing?'/'.(int)$organization['id']:'' ?>"><?= $csrfField ?>
<label>Nome de exibição *<input required maxlength="160" name="display_name" value="<?= $escape($organization['display_name']??'') ?>" placeholder="Ex.: Franquia Tijucas"></label>
<label>Razão social *<input required maxlength="190" name="legal_name" value="<?= $escape($organization['legal_name']??'') ?>"></label>
<label>Código interno *<input required maxlength="80" name="code" value="<?= $escape($organization['code']??'') ?>" placeholder="franquia_tijucas"><small>Não aparece para o cliente.</small></label>
<label>Situação<select name="status"><option value="active" <?= ($organization['status']??'active')==='active'?'selected':'' ?>>Ativa</option><option value="suspended" <?= ($organization['status']??'')==='suspended'?'selected':'' ?>>Suspensa</option></select></label>
<label class="form-span-2">Endereço de entrada do painel *<input required maxlength="253" name="primary_host" value="<?= $escape($primary['host']??'') ?>" placeholder="painel.franquia.com.br"><small>Informe somente o domínio, sem https:// ou caminhos.</small></label>
<label class="checkbox-row form-span-2"><input type="checkbox" name="domain_active" value="1" <?= ($primary['status']??'pending')==='active'?'checked':'' ?>> Domínio já apontado e pronto para receber acessos</label>
<div class="form-actions form-span-2"><button class="button button-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar organização</button></div>
</form>
