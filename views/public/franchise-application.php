<style>
  .public-intake-header{display:flex;align-items:center;gap:1.25rem;margin-bottom:1.75rem;padding-bottom:1.5rem;border-bottom:1px solid #e3e7ea}
  .public-intake-header img{flex:0 0 5.25rem;width:5.25rem;height:5.25rem;object-fit:contain}
  .public-intake-header h1{margin:.15rem 0 .35rem;font-size:clamp(1.7rem,3vw,2.45rem)}
  .public-intake-header p{margin:0;max-width:54rem}
  .public-intake-form{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:1rem}
  .public-intake-card{grid-column:1/-1;padding:1.25rem;border:1px solid #e0e5e9;border-radius:1rem;background:#f9fafb}
  .public-intake-card-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:1rem}
  .public-intake-card-heading{display:flex;align-items:center;gap:.75rem;margin-bottom:1rem}
  .public-intake-card-heading i{display:grid;place-items:center;flex:0 0 2.5rem;width:2.5rem;height:2.5rem;border-radius:.7rem;color:var(--inter-accent);background:#feecef}
  .public-intake-card-heading h2{margin:0;font-size:1.2rem}
  .public-intake-card-heading p{margin:.15rem 0 0}
  .public-intake-form label{margin:0}
  .public-intake-form input,.public-intake-form textarea{width:100%;background:#fff}
  .span-2{grid-column:span 2}.span-3{grid-column:span 3}.span-4{grid-column:span 4}.span-5{grid-column:span 5}.span-6{grid-column:span 6}.span-8{grid-column:span 8}.span-12{grid-column:1/-1}
  .public-intake-contact{grid-column:span 6}
  .public-intake-address{grid-column:1/-1}
  .public-intake-footer{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.25rem;border:1px solid #e0e5e9;border-radius:1rem;background:#fff}
  .public-intake-footer p{margin:0;max-width:46rem}
  .public-intake-footer .button{flex:0 0 auto;min-height:3rem}
  @media(max-width:950px){.public-intake-contact{grid-column:1/-1}.span-2,.span-3,.span-4,.span-5,.span-6,.span-8{grid-column:span 6}}
  @media(max-width:650px){.guest-card-wide{width:calc(100% - 1rem);max-width:82rem}.public-intake-header{align-items:flex-start;flex-direction:column}.public-intake-header img{width:4rem;height:4rem}.public-intake-card{padding:1rem}.public-intake-card-grid{grid-template-columns:1fr}.span-2,.span-3,.span-4,.span-5,.span-6,.span-8,.span-12{grid-column:1}.public-intake-footer{align-items:stretch;flex-direction:column}.public-intake-footer .button{width:100%}}
</style>
<?php $formAction=$formAction??$basePath.'/solicitacao-franquia/'.($token??''); ?>
<header class="public-intake-header">
  <img src="<?= $escape($assetBasePath.$brandFavicon) ?>" alt="Mundo Inter">
  <div><p class="eyebrow">Mundo Inter</p><h1>Cadastro de nova franquia</h1><p class="meta">Envie os dados iniciais para nossa equipe preparar a análise, o modelo de negócio, o contrato e a implantação da sua franquia.</p></div>
</header>
<?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>
<form class="public-intake-form" method="post" action="<?= $escape($formAction) ?>"><?= $csrfField ?>
  <section class="public-intake-card">
    <div class="public-intake-card-heading"><i class="fa-solid fa-building"></i><div><h2>Empresa</h2><p class="meta">Identificação principal da futura franquia.</p></div></div>
    <div class="public-intake-card-grid">
      <label class="span-4">Nome da franquia *<input required maxlength="160" name="display_name" value="<?= $escape($application['display_name']??'') ?>"></label>
      <label class="span-5">Razão social *<input required maxlength="190" name="legal_name" value="<?= $escape($application['legal_name']??'') ?>"></label>
      <label class="span-3">CNPJ *<input required name="cnpj" inputmode="numeric" maxlength="18" data-mask="document" value="<?= $escape($application['cnpj']??'') ?>" placeholder="00.000.000/0000-00"></label>
      <label class="span-6">Domínio/site atual<input maxlength="253" name="site_host" value="<?= $escape($application['site_host']??'') ?>" placeholder="www.suaempresa.com.br"></label>
      <label class="span-3">Inscrição estadual<input maxlength="40" name="state_registration" value="<?= $escape($application['state_registration']??'') ?>"></label>
      <label class="span-3">Inscrição municipal<input maxlength="40" name="municipal_registration" value="<?= $escape($application['municipal_registration']??'') ?>"></label>
    </div>
  </section>

  <section class="public-intake-card public-intake-contact">
    <div class="public-intake-card-heading"><i class="fa-solid fa-user-tie"></i><div><h2>Gestor responsável</h2><p class="meta">Contato principal para a implantação.</p></div></div>
    <div class="public-intake-card-grid">
      <label class="span-8">Nome completo *<input required maxlength="160" name="manager_name" value="<?= $escape($application['manager_name']??'') ?>"></label>
      <label class="span-4">CPF<input name="manager_document" inputmode="numeric" maxlength="14" data-mask="document" value="<?= $escape($application['manager_document']??'') ?>"></label>
      <label class="span-7">E-mail *<input required type="email" maxlength="190" name="manager_email" value="<?= $escape($application['manager_email']??'') ?>"></label>
      <label class="span-5">Telefone/WhatsApp *<input required name="manager_phone" inputmode="tel" maxlength="16" data-mask="phone" value="<?= $escape($application['manager_phone']??'') ?>"></label>
    </div>
  </section>

  <section class="public-intake-card public-intake-contact">
    <div class="public-intake-card-heading"><i class="fa-solid fa-user-gear"></i><div><h2>Gerente operacional</h2><p class="meta">Preenchimento opcional.</p></div></div>
    <div class="public-intake-card-grid">
      <label class="span-12">Nome<input maxlength="160" name="general_manager_name" value="<?= $escape($application['general_manager_name']??'') ?>"></label>
      <label class="span-7">E-mail<input type="email" maxlength="190" name="general_manager_email" value="<?= $escape($application['general_manager_email']??'') ?>"></label>
      <label class="span-5">Telefone/WhatsApp<input maxlength="30" inputmode="tel" name="general_manager_phone" value="<?= $escape($application['general_manager_phone']??'') ?>"></label>
    </div>
  </section>

  <section class="public-intake-card public-intake-address">
    <div class="public-intake-card-heading"><i class="fa-solid fa-location-dot"></i><div><h2>Endereço</h2><p class="meta">Opcional nesta primeira etapa.</p></div></div>
    <div class="public-intake-card-grid">
      <label class="span-2">CEP<input maxlength="12" name="postal_code" value="<?= $escape($application['postal_code']??'') ?>"></label>
      <label class="span-5">Endereço<input maxlength="190" name="address" value="<?= $escape($application['address']??'') ?>"></label>
      <label class="span-2">Número<input maxlength="30" name="address_number" value="<?= $escape($application['address_number']??'') ?>"></label>
      <label class="span-3">Complemento<input maxlength="120" name="address_complement" value="<?= $escape($application['address_complement']??'') ?>"></label>
      <label class="span-4">Bairro<input maxlength="120" name="neighborhood" value="<?= $escape($application['neighborhood']??'') ?>"></label>
      <label class="span-6">Cidade<input maxlength="120" name="city" value="<?= $escape($application['city']??'') ?>"></label>
      <label class="span-2">UF<input maxlength="2" name="state" value="<?= $escape($application['state']??'') ?>"></label>
    </div>
  </section>

  <section class="public-intake-card">
    <div class="public-intake-card-heading"><i class="fa-solid fa-message"></i><div><h2>Informações adicionais</h2><p class="meta">Conte o que considera importante para a futura parceria.</p></div></div>
    <label class="span-12">Observações<textarea name="negotiation_notes" rows="4" maxlength="3000" placeholder="Informações sobre a empresa, operação atual ou expectativas para a franquia."><?= $escape($application['negotiation_notes']??'') ?></textarea></label>
  </section>

  <footer class="public-intake-footer">
    <p class="meta"><i class="fa-solid fa-shield-halved"></i> O envio não ativa a franquia automaticamente. O ADM Central fará a conferência e entrará em contato.</p>
    <button class="button button-primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Enviar cadastro para análise</button>
  </footer>
</form>
