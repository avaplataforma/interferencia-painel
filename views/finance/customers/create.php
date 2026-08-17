<?php declare(strict_types=1); /** @var Closure(mixed):string $escape */
$old = is_array($old ?? null) ? $old : [];
?>
<header class="section-heading"><div><span class="status">Alunos · Cadastro</span><h1>Novo aluno</h1><p class="meta">Cadastre o aluno no polo e crie o cliente financeiro correspondente no Asaas.</p></div><a class="button-secondary" href="<?= $escape($basePath) ?>/finance/customers"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Cancelar</a></header>
<?php if($error):?><p class="alert alert-danger"><?= $escape($error) ?></p><?php endif;?>
<?php if(!$writeEnabled):?><p class="alert alert-warning">O cadastro real está desativado no modo seguro.</p><?php endif;?>
<section class="card"><div class="card-body"><form id="student-create-form" method="post" action="<?= $escape($basePath) ?>/finance/customers" novalidate><?= $csrfField ?><div class="form-grid">
<div class="form-field"><label for="student-unit">Polo <span class="required">*</span></label><select id="student-unit" name="unit_id" required><option value="">Selecione</option><?php foreach($units as$unit):?><option value="<?= (int)$unit['id'] ?>" <?= (string)($old['unit_id']??'')===(string)(int)$unit['id']?'selected':'' ?>><?= $escape($unit['name']) ?></option><?php endforeach;?></select></div>
<div class="form-field"><label for="student-name">Nome completo <span class="required">*</span></label><input id="student-name" name="name" maxlength="160" value="<?= $escape((string)($old['name']??'')) ?>" required></div>
<div class="form-field"><label for="student-email">E-mail <span class="required">*</span></label><input id="student-email" name="email" type="email" maxlength="190" data-normalize="email" value="<?= $escape((string)($old['email']??'')) ?>" required></div>
<div class="form-field"><label for="student-document">CPF/CNPJ <span class="required">*</span></label><input id="student-document" name="cpf_cnpj" inputmode="numeric" maxlength="18" data-mask="document" value="<?= $escape((string)($old['cpf_cnpj']??'')) ?>" required></div>
<div class="form-field"><label for="student-mobile">Celular <span class="required">*</span></label><input id="student-mobile" name="mobile_phone" inputmode="tel" maxlength="16" data-mask="phone" value="<?= $escape((string)($old['mobile_phone']??'')) ?>" required></div>
<div class="form-field"><label for="student-phone">Telefone</label><input id="student-phone" name="phone" inputmode="tel" maxlength="16" data-mask="phone" value="<?= $escape((string)($old['phone']??'')) ?>"></div>
<div class="form-field"><label for="student-postal">CEP <span class="required">*</span></label><input id="student-postal" name="postal_code" inputmode="numeric" maxlength="9" data-mask="postal" placeholder="00000-000" value="<?= $escape((string)($old['postal_code']??'')) ?>" required></div>
<div class="form-field"><label for="student-address">Endereço <span class="required">*</span></label><input id="student-address" name="address" maxlength="255" value="<?= $escape((string)($old['address']??'')) ?>" required></div>
<div class="form-field"><label for="student-number">Número <span class="required">*</span></label><input id="student-number" name="address_number" maxlength="40" value="<?= $escape((string)($old['address_number']??'')) ?>" required></div>
<div class="form-field"><label for="student-complement">Complemento</label><input id="student-complement" name="complement" maxlength="120" value="<?= $escape((string)($old['complement']??'')) ?>"></div>
<div class="form-field"><label for="student-province">Bairro <span class="required">*</span></label><input id="student-province" name="province" maxlength="120" value="<?= $escape((string)($old['province']??'')) ?>" required></div>
</div><div class="alert alert-warning"><strong>Importante:</strong> este cadastro cria um aluno ativo e também um cliente no Asaas. Para interessados ainda em prospecção, utilize CRM · Leads.</div><p id="student-form-error" class="alert alert-danger" hidden role="alert"></p><div class="form-actions"><button class="button-primary" type="submit"<?= $writeEnabled?'':' disabled' ?>><i class="fa-solid fa-user-graduate" aria-hidden="true"></i> Cadastrar aluno</button></div></form></div></section>
<script>
(() => {
  const form = document.getElementById('student-create-form');
  const errorBox = document.getElementById('student-form-error');
  if (!(form instanceof HTMLFormElement) || !(errorBox instanceof HTMLElement)) return;
  const digits = (value) => (value || '').replace(/\D/g, '');
  const fail = (message) => {
    errorBox.textContent = message;
    errorBox.hidden = false;
    errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
  };
  form.addEventListener('submit', (event) => {
    errorBox.hidden = true;
    const data = new FormData(form);
    const unit = String(data.get('unit_id') || '');
    const name = String(data.get('name') || '').trim();
    const email = String(data.get('email') || '').trim();
    const document = digits(data.get('cpf_cnpj'));
    const mobile = digits(data.get('mobile_phone'));
    const phone = digits(data.get('phone'));
    const postal = digits(data.get('postal_code'));
    const address = String(data.get('address') || '').trim();
    const number = String(data.get('address_number') || '').trim();
    const province = String(data.get('province') || '').trim();
    if (unit === '') return fail('Selecione o polo.');
    if (name.length < 2) return fail('Informe o nome completo.');
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) return fail('Informe um e-mail válido.');
    if (document.length !== 11 && document.length !== 14) return fail('Informe um CPF ou CNPJ válido.');
    if (mobile.length < 10 || mobile.length > 11) return fail('Informe um celular válido com DDD.');
    if (phone !== '' && (phone.length < 10 || phone.length > 11)) return fail('Informe um telefone válido com DDD.');
    if (postal.length !== 8) return fail('Informe um CEP válido com 8 dígitos.');
    if (address.length < 2) return fail('Informe o endereço.');
    if (number === '') return fail('Informe o número.');
    if (province.length < 2) return fail('Informe o bairro.');
  });
})();
</script>
