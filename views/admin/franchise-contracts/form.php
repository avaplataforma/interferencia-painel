<?php $renewing=is_array($renewFrom); ?>
<div class="page-header"><div><p class="eyebrow">Franquias · <?= $escape($application['display_name']) ?></p><h1><?= $renewing?'Renovar contrato':'Novo contrato' ?></h1><p>Cada documento será preservado no histórico da franquia.</p></div><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/franchise-applications/<?= (int)$application['id'] ?>"><i class="fa-solid fa-arrow-left"></i> Voltar</a></div>
<?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>
<form class="card form-grid" method="post" action="<?= $escape($basePath) ?>/admin/franchise-applications/<?= (int)$application['id'] ?>/contracts"><?= $csrfField ?>
  <label>Tipo *<select name="contract_type" required><option value="new" <?= !$renewing?'selected':'' ?>>Novo contrato</option><option value="renewal" <?= $renewing?'selected':'' ?>>Renovação</option></select></label>
  <label>Contrato anterior<select name="parent_contract_id"><option value="">Não se aplica</option><?php foreach($previousContracts as$item):?><option value="<?= (int)$item['id'] ?>" <?= $renewing&&(int)$renewFrom['id']===(int)$item['id']?'selected':'' ?>>#<?= (int)$item['contract_number'] ?> · <?= $escape($item['title']) ?></option><?php endforeach;?></select></label>
  <label class="form-span-2">Modelo *<select name="template_id" required><option value="">Selecione</option><?php foreach($templates as$item):?><option value="<?= (int)$item['id'] ?>"><?= $escape($item['title']) ?> · v<?= $escape($item['version']) ?></option><?php endforeach;?></select></label>
  <label class="form-span-2">Título<input name="title" maxlength="180" value="<?= $renewing?$escape('Renovação — '.$renewFrom['title']):'' ?>" placeholder="Preenchido automaticamente se ficar vazio"></label>
  <label class="form-span-2">Condições comerciais *<textarea name="commercial_terms" rows="5" required placeholder="Serviços incluídos, reajuste e demais condições acordadas."><?= $renewing?$escape('Renovação das condições do contrato #'.$renewFrom['contract_number'].'.'):'' ?></textarea></label>
  <label>Início da vigência<input type="date" name="valid_from" value="<?= $escape(date('Y-m-d')) ?>"></label>
  <label>Fim da vigência<input type="date" name="valid_until"></label>
  <label class="form-span-2">Vigência por extenso *<input name="term" required placeholder="Ex.: 12 meses, com renovação mediante novo instrumento"></label>
  <div class="form-section form-span-2"><h2>Modelo de cobrança</h2><p>O contrato pode combinar mensalidade e comissão, ou funcionar somente por split.</p></div>
  <label class="form-span-2">Modelo comercial *<select name="commercial_model" required><option value="">Selecione</option><option value="fixed_plus_percentage">Assinatura mensal fixa + percentual por curso vendido</option><option value="split_only">Somente split percentual por venda</option></select></label>
  <label>Assinatura mensal fixa<input name="monthly_fixed_amount" inputmode="decimal" placeholder="0,00"><small>Obrigatória no primeiro modelo.</small></label>
  <label>Percentual Mundo Inter por venda *<input name="sales_fee_percentage" type="number" min="0" max="100" step="0.0001" required placeholder="0,0000"><small>Percentual retido pelo Mundo Inter em cada curso vendido.</small></label>
  <label class="form-span-2">Descrição da assinatura<input name="billing_description" maxlength="190" placeholder="Licenciamento mensal Mundo Inter"></label>
  <div class="form-span-2 alert alert-info"><strong>Fluxo manual:</strong> o contrato ficará em rascunho. Depois de conferir, você libera e envia o link de assinatura. O link de pagamento recorrente também será gerado e enviado separadamente.</div>
  <div class="form-span-2"><button class="button button-primary" type="submit"><i class="fa-solid fa-file-circle-plus"></i> Gerar rascunho</button></div>
</form>
