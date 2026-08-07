<div class="page-header"><div><p class="eyebrow">Franquias · <?= $escape($application['display_name']) ?></p><h1>Gerar contrato</h1><p>O texto será congelado no momento da geração para preservar o histórico.</p></div><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/franchise-applications/<?= (int)$application['id'] ?>"><i class="fa-solid fa-arrow-left"></i> Voltar</a></div>
<?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>
<form class="card form-grid" method="post" action="<?= $escape($basePath) ?>/admin/franchise-applications/<?= (int)$application['id'] ?>/contracts"><?= $csrfField ?>
  <label class="form-span-2">Modelo *<select name="template_id" required><option value="">Selecione</option><?php foreach($templates as$item):?><option value="<?= (int)$item['id'] ?>"><?= $escape($item['title']) ?> · v<?= $escape($item['version']) ?></option><?php endforeach;?></select></label>
  <label class="form-span-2">Título do contrato<input name="title" maxlength="180" placeholder="Preenchido automaticamente se ficar vazio"></label>
  <label class="form-span-2">Condições comerciais *<textarea name="commercial_terms" rows="6" required placeholder="Implantação, mensalidade, serviços incluídos, reajuste e demais condições acordadas."></textarea></label>
  <label class="form-span-2">Vigência *<input name="term" required placeholder="Ex.: 12 meses, com renovação automática"></label>
  <label class="checkbox-row form-span-2"><input type="checkbox" name="billing_required" value="1" data-contract-billing-toggle> Haverá cobrança vinculada a esta negociação</label>
  <label>Valor previsto<input name="billing_amount" inputmode="decimal" placeholder="0,00"></label>
  <label>Descrição da cobrança<input name="billing_description" maxlength="190" placeholder="Contrato de franquia"></label>
  <div class="form-span-2 alert alert-warning"><strong>Importante:</strong> gerar o contrato não o envia. Primeiro ele ficará como rascunho para conferência.</div>
  <div class="form-span-2"><button class="button button-primary" type="submit"><i class="fa-solid fa-file-circle-plus"></i> Gerar rascunho</button></div>
</form>
