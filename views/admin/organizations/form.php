<?php
$editing=is_array($organization);$siteDomain=null;
foreach($domains as$domain){if(($domain['purpose']??'')==='site'&&($domain['is_primary']??0)){$siteDomain=$domain;break;}}
$logo=(string)($organization['logo_path']??'');$favicon=(string)($organization['favicon_path']??'');
?>
<style>
.organization-editor{width:100%;max-width:82rem;margin:0 auto}.organization-editor-form{display:grid;gap:1rem}.organization-section{padding:1.5rem}.organization-section-header{display:flex;gap:1rem;align-items:flex-start;margin-bottom:1.25rem;padding-bottom:1rem;border-bottom:1px solid #e3e8ec}.organization-section-icon{display:grid;place-items:center;flex:0 0 2.6rem;height:2.6rem;border-radius:.75rem;background:#fff0f1;color:var(--inter-accent)}.organization-section-header h2,.organization-section-header p{margin:0}.organization-fields{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:1rem}.organization-fields>label{grid-column:span 6;margin:0}.organization-fields>.field-third{grid-column:span 4}.organization-fields>.field-quarter{grid-column:span 3}.organization-fields>.field-full{grid-column:1/-1}.organization-fields input,.organization-fields select{width:100%}.organization-savebar{position:sticky;z-index:8;bottom:1rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:1rem 1.25rem;border:1px solid #dce3e8;border-radius:1rem;background:rgba(255,255,255,.96);box-shadow:0 .65rem 1.8rem rgba(14,35,55,.12);backdrop-filter:blur(8px)}.organization-savebar p{margin:0}.organization-savebar .button{min-width:13rem}.organization-brand-preview{display:flex;align-items:center;gap:1rem;margin-top:.75rem;padding:.75rem;border:1px solid #e0e6ea;border-radius:.75rem}.organization-brand-preview img{width:5rem;height:3.5rem;object-fit:contain}.organization-brand-preview.icon img{width:3.5rem}.organization-brand-preview .checkbox-row{margin:0}.organization-help{display:flex;align-items:center;gap:.5rem;padding:.8rem 1rem;border-radius:.75rem;background:#f6f8fa;color:#506274}.organization-help i{color:var(--inter-accent)}@media(max-width:800px){.organization-fields>label,.organization-fields>.field-third,.organization-fields>.field-quarter{grid-column:1/-1}.organization-savebar{align-items:stretch;flex-direction:column}.organization-savebar .button{width:100%}}
.organization-tabs{display:flex;gap:.35rem;margin:0 0 1rem;padding:.35rem;border:1px solid #dce3e8;border-radius:1rem;background:#fff;overflow-x:auto;box-shadow:0 .3rem 1rem rgb(23 33 43 / 5%)}.organization-tab{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;min-width:max-content;padding:.72rem .95rem;border:0;border-radius:.72rem;background:transparent;color:#536170;font:inherit;font-weight:750;cursor:pointer}.organization-tab:hover{background:#f5f7f9;color:var(--inter-ink)}.organization-tab[aria-selected=true]{background:#fff0f1;color:var(--inter-accent);box-shadow:inset 0 0 0 1px rgb(237 28 36 / 16%)}[data-organization-panel][hidden]{display:none!important}.organization-section{min-height:25rem}.organization-savebar[hidden]{display:none!important}@media(max-width:800px){.organization-tabs{border-radius:.75rem}.organization-tab{padding:.65rem .8rem}.organization-section{min-height:0}}
</style>

<div class="organization-editor">
  <div class="page-header">
    <div><p class="eyebrow">Mundo Inter · ADM Central</p><h1><?= $editing?'Editar franquia':'Cadastrar franquia' ?></h1><p>Dados, acesso e identidade visual organizados em uma única jornada.</p></div>
    <div class="page-actions"><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/organizations<?= $editing?'/'.(int)$organization['id']:'' ?>"><i class="fa-solid fa-arrow-left"></i> Voltar</a></div>
  </div>
  <?php if(!empty($message)):?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif;?>
  <?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>

  <nav class="organization-tabs" aria-label="Etapas do cadastro" role="tablist">
   <button class="organization-tab" type="button" role="tab" data-organization-tab="dados"><i class="fa-solid fa-building"></i> Dados</button>
   <button class="organization-tab" type="button" role="tab" data-organization-tab="endereco"><i class="fa-solid fa-location-dot"></i> Endereço</button>
   <button class="organization-tab" type="button" role="tab" data-organization-tab="contatos"><i class="fa-solid fa-address-book"></i> Responsáveis</button>
   <?php if($editing):?><button class="organization-tab" type="button" role="tab" data-organization-tab="documentos"><i class="fa-solid fa-folder-open"></i> Documentos</button><?php endif;?>
   <button class="organization-tab" type="button" role="tab" data-organization-tab="marca"><i class="fa-solid fa-palette"></i> Personalização</button>
   <?php if($editing):?><button class="organization-tab" type="button" role="tab" data-organization-tab="ava"><i class="fa-solid fa-graduation-cap"></i> AVA</button><?php endif;?>
   <?php if($editing):?><button class="organization-tab" type="button" role="tab" data-organization-tab="polos"><i class="fa-solid fa-location-crosshairs"></i> Polos</button><?php endif;?>
   <?php if($editing):?><button class="organization-tab" type="button" role="tab" data-organization-tab="integracoes"><i class="fa-solid fa-plug-circle-bolt"></i> Integrações</button><?php endif;?>
  </nav>

  <form class="organization-editor-form" method="post" enctype="multipart/form-data" action="<?= $escape($basePath) ?>/admin/organizations<?= $editing?'/'.(int)$organization['id']:'' ?>"><?= $csrfField ?>
    <section class="card organization-section" id="dados" data-organization-panel="dados">
      <header class="organization-section-header"><span class="organization-section-icon"><i class="fa-solid fa-building"></i></span><div><h2>Dados da franquia</h2><p class="meta">Identificação jurídica e situação operacional. Somente os campos com * são obrigatórios.</p></div></header>
      <div class="organization-fields">
        <label>Nome de exibição *<input required maxlength="160" name="display_name" value="<?= $escape($organization['display_name']??'') ?>" placeholder="Ex.: Interferência Tijucas"></label>
        <label>Razão social *<input required maxlength="190" name="legal_name" value="<?= $escape($organization['legal_name']??'') ?>"></label>
        <label class="field-third">CNPJ *<input required name="cnpj" inputmode="numeric" maxlength="18" data-mask="document" value="<?= $escape($organization['cnpj']??'') ?>" placeholder="00.000.000/0000-00"><small>Validado e exclusivo na rede.</small></label>
        <label class="field-third">Código interno *<input required maxlength="80" name="code" value="<?= $escape($organization['code']??'') ?>" placeholder="franquia_tijucas"></label>
        <?php if(($organization['status']??'suspended')==='active'):?><label class="field-third">Situação<select name="status"><option value="active" selected>Ativa</option><option value="suspended">Suspensa</option></select><small>A suspensão interrompe o acesso da franquia.</small></label><?php else:?><label class="field-third">Situação<input value="Suspensa — aguardando implantação" readonly><input type="hidden" name="status" value="suspended"><small>A ativação é liberada somente pelo fluxo guiado.</small></label><?php endif;?>
        <label>Inscrição estadual<input maxlength="40" name="state_registration" value="<?= $escape($organization['state_registration']??'') ?>"></label>
        <label>Inscrição municipal<input maxlength="40" name="municipal_registration" value="<?= $escape($organization['municipal_registration']??'') ?>"></label>
        <div class="field-full organization-help"><i class="fa-solid fa-globe"></i><span>Acesso ao painel e site público</span></div>
        <label class="field-full">Login exclusivo da franquia *<div class="input-prefix"><span>mundointer.com.br/</span><input required maxlength="100" name="panel_slug" value="<?= $escape($organization['panel_slug']??'') ?>" placeholder="nome-da-franquia"></div><small>Endereço usado pela equipe da franquia para entrar no painel.</small></label>
        <label class="field-full">Domínio público do site<input maxlength="253" name="site_host" value="<?= $escape($siteDomain['host']??'') ?>" placeholder="www.franquiatal.com.br"><small>Informe somente o domínio, sem caminhos internos.</small></label>
        <label class="checkbox-row field-full"><input type="checkbox" name="domain_active" value="1" <?= ($siteDomain['status']??'pending')==='active'?'checked':'' ?>> Domínio público já validado e ativo</label>
      </div>
    </section>

    <section class="card organization-section" id="contatos" data-organization-panel="contatos" hidden>
      <header class="organization-section-header"><span class="organization-section-icon"><i class="fa-solid fa-address-book"></i></span><div><h2>Responsáveis</h2><p class="meta">O gestor é o contato principal. O gerente é opcional e pode ser incluído depois.</p></div></header>
      <div class="organization-fields">
        <label>Gestor responsável *<input required maxlength="160" name="manager_name" value="<?= $escape($organization['manager_name']??'') ?>"></label>
        <label>CPF do gestor<input name="manager_document" inputmode="numeric" maxlength="14" data-mask="document" value="<?= $escape($organization['manager_document']??'') ?>" placeholder="000.000.000-00"></label>
        <label>E-mail do gestor *<input required type="email" maxlength="190" name="manager_email" value="<?= $escape($organization['manager_email']??'') ?>"></label>
        <label>Telefone/WhatsApp do gestor *<input required name="manager_phone" inputmode="tel" maxlength="16" data-mask="phone" value="<?= $escape($organization['manager_phone']??'') ?>" placeholder="(00) 00000-0000"></label>
        <div class="field-full organization-help"><i class="fa-solid fa-user-tie"></i><span>Gerente operacional (opcional)</span></div>
        <label>Nome do gerente<input maxlength="160" name="general_manager_name" value="<?= $escape($organization['general_manager_name']??'') ?>"></label>
        <label>CPF do gerente<input name="general_manager_document" inputmode="numeric" maxlength="14" data-mask="document" value="<?= $escape($organization['general_manager_document']??'') ?>" placeholder="000.000.000-00"></label>
        <label>E-mail do gerente<input type="email" maxlength="190" name="general_manager_email" value="<?= $escape($organization['general_manager_email']??'') ?>"></label>
        <label>Telefone/WhatsApp do gerente<input name="general_manager_phone" inputmode="tel" maxlength="16" data-mask="phone" value="<?= $escape($organization['general_manager_phone']??'') ?>" placeholder="(00) 00000-0000"></label>
      </div>
    </section>

    <section class="card organization-section" id="endereco" data-organization-panel="endereco" hidden>
      <header class="organization-section-header"><span class="organization-section-icon"><i class="fa-solid fa-location-dot"></i></span><div><h2>Endereço físico</h2><p class="meta">Opcional nesta etapa e editável a qualquer momento.</p></div></header>
      <div class="organization-fields">
        <label class="field-quarter">CEP<input name="postal_code" inputmode="numeric" maxlength="9" value="<?= $escape($organization['postal_code']??'') ?>" placeholder="00000-000"></label>
        <label>Endereço<input maxlength="190" name="address" value="<?= $escape($organization['address']??'') ?>"></label>
        <label class="field-quarter">Número<input maxlength="30" name="address_number" value="<?= $escape($organization['address_number']??'') ?>"></label>
        <label class="field-third">Complemento<input maxlength="120" name="address_complement" value="<?= $escape($organization['address_complement']??'') ?>"></label>
        <label class="field-third">Bairro<input maxlength="120" name="neighborhood" value="<?= $escape($organization['neighborhood']??'') ?>"></label>
        <label class="field-third">Cidade<input maxlength="120" name="city" value="<?= $escape($organization['city']??'') ?>"></label>
        <label class="field-quarter">UF<input maxlength="2" name="state" value="<?= $escape($organization['state']??'') ?>" placeholder="SC" style="text-transform:uppercase"></label>
      </div>
    </section>

    <section class="card organization-section" id="marca" data-organization-panel="marca" hidden>
      <header class="organization-section-header"><span class="organization-section-icon"><i class="fa-solid fa-palette"></i></span><div><h2>Marca e tela de acesso</h2><p class="meta">Personalização exibida no login exclusivo e na experiência da franquia.</p></div></header>
      <div class="organization-fields">
        <label class="field-third">Cor principal *<div class="color-field"><input type="color" name="primary_color" value="<?= $escape($organization['primary_color']??'#ed1c24') ?>"><input aria-label="Cor principal em hexadecimal" maxlength="7" value="<?= $escape($organization['primary_color']??'#ed1c24') ?>" data-color-text readonly></div></label>
        <label class="field-third">Cor secundária<div class="color-field"><input type="color" name="secondary_color" value="<?= $escape($organization['secondary_color']??'#102a56') ?>"><input aria-label="Cor secundária em hexadecimal" maxlength="7" value="<?= $escape($organization['secondary_color']??'#102a56') ?>" data-color-text readonly></div></label>
        <label>Logo da franquia<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"><small>PNG, JPG ou WebP, até 3 MB.</small><?php if($logo!==''):?><span class="organization-brand-preview"><img src="<?= $escape($basePath.$logo) ?>" alt="Logo atual"><label class="checkbox-row"><input type="checkbox" name="remove_logo" value="1"> Remover logo atual</label></span><?php endif;?></label>
        <label>Favicon<input type="file" name="favicon" accept="image/png,image/jpeg,image/webp"><small>Use uma imagem quadrada.</small><?php if($favicon!==''):?><span class="organization-brand-preview icon"><img src="<?= $escape($basePath.$favicon) ?>" alt="Favicon atual"><label class="checkbox-row"><input type="checkbox" name="remove_favicon" value="1"> Remover favicon atual</label></span><?php endif;?></label>
      </div>
    </section>

    <footer class="organization-savebar" data-organization-savebar><p class="meta"><i class="fa-solid fa-shield-halved"></i> O financeiro e o modelo comercial são concluídos no fluxo de implantação, após a conferência.</p><button class="button button-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar franquia</button></footer>
  </form>
  <?php if($editing):?><?php require __DIR__.'/documents.php'; ?><?php endif;?>
  <?php if($editing):?><?php require __DIR__.'/ava.php'; ?><?php endif;?>
  <?php if($editing):?><?php require __DIR__.'/poles.php'; ?><?php endif;?>
  <?php if($editing):?><?php require __DIR__.'/integrations.php'; ?><?php endif;?>
</div>
