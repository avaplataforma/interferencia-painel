<style>.guest-card{width:min(68rem,calc(100% - 2rem))}.public-intake-header{text-align:center;margin-bottom:1.5rem}.public-intake-header img{width:5rem;height:5rem;object-fit:contain}.public-intake-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.public-intake-form .full{grid-column:1/-1}.public-intake-section{grid-column:1/-1;padding-top:.75rem;border-top:1px solid #e3e7ea}.public-intake-section h2{margin:0 0 .25rem}.public-intake-form label{margin:0}.public-intake-form input,.public-intake-form textarea{width:100%}.public-intake-actions{grid-column:1/-1;display:flex;justify-content:flex-end}@media(max-width:700px){.public-intake-form{grid-template-columns:1fr}.public-intake-form .full,.public-intake-section,.public-intake-actions{grid-column:1}}</style>
<header class="public-intake-header"><img src="<?= $escape($assetBasePath.$brandFavicon) ?>" alt=""><p class="eyebrow">Mundo Inter</p><h1>Cadastro da futura franquia</h1><p class="meta">Preencha os dados para iniciarmos a análise, o contrato e a configuração da parceria.</p></header>
<?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>
<form class="public-intake-form" method="post" action="<?= $escape($basePath) ?>/solicitacao-franquia/<?= $escape($token) ?>"><?= $csrfField ?>
  <div class="public-intake-section"><h2>Empresa</h2><p class="meta">Os campos com * são necessários para enviar.</p></div>
  <label>Nome da franquia *<input required maxlength="160" name="display_name" value="<?= $escape($application['display_name']??'') ?>"></label>
  <label>Razão social *<input required maxlength="190" name="legal_name" value="<?= $escape($application['legal_name']??'') ?>"></label>
  <label>CNPJ *<input required name="cnpj" inputmode="numeric" maxlength="18" data-mask="document" value="<?= $escape($application['cnpj']??'') ?>" placeholder="00.000.000/0000-00"></label>
  <label>Domínio/site atual<input maxlength="253" name="site_host" value="<?= $escape($application['site_host']??'') ?>" placeholder="www.suaempresa.com.br"></label>
  <label>Inscrição estadual<input maxlength="40" name="state_registration" value="<?= $escape($application['state_registration']??'') ?>"></label>
  <label>Inscrição municipal<input maxlength="40" name="municipal_registration" value="<?= $escape($application['municipal_registration']??'') ?>"></label>
  <div class="public-intake-section"><h2>Gestor responsável</h2></div>
  <label>Nome completo *<input required maxlength="160" name="manager_name" value="<?= $escape($application['manager_name']??'') ?>"></label>
  <label>CPF<input name="manager_document" inputmode="numeric" maxlength="14" data-mask="document" value="<?= $escape($application['manager_document']??'') ?>"></label>
  <label>E-mail *<input required type="email" maxlength="190" name="manager_email" value="<?= $escape($application['manager_email']??'') ?>"></label>
  <label>Telefone/WhatsApp *<input required name="manager_phone" inputmode="tel" maxlength="16" data-mask="phone" value="<?= $escape($application['manager_phone']??'') ?>"></label>
  <div class="public-intake-section"><h2>Gerente operacional</h2><p class="meta">Opcional.</p></div>
  <label>Nome<input maxlength="160" name="general_manager_name" value="<?= $escape($application['general_manager_name']??'') ?>"></label>
  <label>E-mail<input type="email" maxlength="190" name="general_manager_email" value="<?= $escape($application['general_manager_email']??'') ?>"></label>
  <label>Telefone/WhatsApp<input maxlength="30" name="general_manager_phone" value="<?= $escape($application['general_manager_phone']??'') ?>"></label>
  <div class="public-intake-section"><h2>Endereço</h2><p class="meta">Opcional nesta primeira etapa.</p></div>
  <label>CEP<input maxlength="12" name="postal_code" value="<?= $escape($application['postal_code']??'') ?>"></label>
  <label>Endereço<input maxlength="190" name="address" value="<?= $escape($application['address']??'') ?>"></label>
  <label>Número<input maxlength="30" name="address_number" value="<?= $escape($application['address_number']??'') ?>"></label>
  <label>Complemento<input maxlength="120" name="address_complement" value="<?= $escape($application['address_complement']??'') ?>"></label>
  <label>Bairro<input maxlength="120" name="neighborhood" value="<?= $escape($application['neighborhood']??'') ?>"></label>
  <label>Cidade<input maxlength="120" name="city" value="<?= $escape($application['city']??'') ?>"></label>
  <label>UF<input maxlength="2" name="state" value="<?= $escape($application['state']??'') ?>"></label>
  <div class="public-intake-section"><h2>Negociação</h2></div>
  <label class="full">Observações<textarea name="negotiation_notes" rows="4" maxlength="3000" placeholder="Conte-nos informações importantes sobre a parceria."><?= $escape($application['negotiation_notes']??'') ?></textarea></label>
  <label class="checkbox-row full"><input type="checkbox" name="billing_required" value="1" <?= (int)($application['billing_required']??0)===1?'checked':'' ?>> A negociação prevê cobrança recorrente ou pontual pelo Mundo Inter.</label>
  <p class="meta full"><i class="fa-solid fa-shield-halved"></i> O envio não ativa a franquia automaticamente. O ADM Central fará a conferência e entrará em contato.</p>
  <div class="public-intake-actions"><button class="button button-primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Enviar cadastro para análise</button></div>
</form>
