<?php declare(strict_types=1); /** @var Closure(mixed):string $escape */ ?>
<div class="actions">
 <div><span class="status">Alunos · Cadastro</span><h1>Editar aluno</h1><p class="meta">Mantenha os dados cadastrais usados pelo Painel e pelas integrações.</p></div>
 <a href="<?= $escape($cancelHref??($basePath.'/students/'.(int)$customer['id'].'?tab=overview')) ?>">Cancelar</a>
</div>
<?php if($error):?><p class="alert alert-danger"><?= $escape($error) ?></p><?php endif;?>
<?php if(!$writeEnabled):?><p class="alert alert-warning"><strong>Modo seguro:</strong> as alterações serão salvas no Painel, sem alterar o cadastro no Asaas.</p><?php endif;?>
<section class="card"><div class="card-body">
 <form method="post" action="<?= $escape($basePath) ?>/finance/customers/<?= (int)$customer['id'] ?>/edit">
  <?= $csrfField ?>
  <div class="form-grid">
   <div class="form-field"><label for="customer-name">Nome <span class="required">*</span></label><input id="customer-name" name="name" maxlength="160" value="<?= $escape($customer['name']) ?>" required></div>
   <div class="form-field"><label for="customer-email">E-mail <span class="required">*</span></label><input id="customer-email" name="email" type="email" maxlength="190" data-normalize="email" value="<?= $escape($customer['email']??'') ?>" placeholder="nome@exemplo.com.br" required></div>
   <div class="form-field"><label for="customer-document">CPF/CNPJ</label><input id="customer-document" name="cpf_cnpj" inputmode="numeric" maxlength="18" data-mask="document" value="<?= $escape($customer['cpf_cnpj']??'') ?>" placeholder="000.000.000-00"></div>
   <div class="form-field"><label for="customer-mobile">Celular</label><input id="customer-mobile" name="mobile_phone" inputmode="tel" maxlength="16" data-mask="phone" value="<?= $escape($customer['mobile_phone']??'') ?>" placeholder="(00) 00000-0000"></div>
   <div class="form-field"><label for="customer-phone">Telefone</label><input id="customer-phone" name="phone" inputmode="tel" maxlength="16" data-mask="phone" value="<?= $escape($customer['phone']??'') ?>" placeholder="(00) 0000-0000"></div>
   <div class="form-field"><label for="customer-postal">CEP <span class="required">*</span></label><input id="customer-postal" name="postal_code" inputmode="numeric" maxlength="9" data-mask="postal" placeholder="00000-000" value="<?= $escape($customer['postal_code']??'') ?>" required></div>
   <div class="form-field"><label for="customer-address">Endereço <span class="required">*</span></label><input id="customer-address" name="address" maxlength="255" value="<?= $escape($customer['address']??'') ?>" required></div>
   <div class="form-field"><label for="customer-number">Número <span class="required">*</span></label><input id="customer-number" name="address_number" maxlength="40" value="<?= $escape($customer['address_number']??'') ?>" required></div>
   <div class="form-field"><label for="customer-complement">Complemento</label><input id="customer-complement" name="complement" maxlength="120" value="<?= $escape($customer['complement']??'') ?>"></div>
   <div class="form-field"><label for="customer-province">Bairro <span class="required">*</span></label><input id="customer-province" name="province" maxlength="120" value="<?= $escape($customer['province']??'') ?>" required></div>
  </div>
  <div class="alert alert-warning"><strong>Atenção:</strong> estes dados serão usados nos próximos checkouts, cobranças e comunicações.</div>
  <div class="form-actions"><button class="button-primary" type="submit">Salvar aluno</button></div>
 </form>
</div></section>
