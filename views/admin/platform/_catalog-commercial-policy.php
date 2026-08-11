<?php
$centralPrice=$catalog['central_default_price']??null;
$centralMarkup=(float)($catalog['central_markup_percent']??0);
$centralInstallments=max(1,(int)($catalog['central_default_max_installments']??1));
$franchiseOverrideAllowed=(int)($catalog['allow_franchise_commercial_override']??1)===1;
?>
<div class="catalog-subpanel" data-catalog-subpanel="<?= $escape($provider) ?>:policy" hidden>
 <section class="central-policy-shell">
  <header>
   <div><p class="eyebrow">Governança comercial</p><h3>Padrão para todas as franquias</h3><p>Defina a regra herdada deste catálogo. Salvar não altera ofertas existentes; a aplicação em lote é sempre uma ação separada.</p></div>
   <span class="catalog-badge <?= $franchiseOverrideAllowed?'ok':'private' ?>"><i class="fa-solid <?= $franchiseOverrideAllowed?'fa-pen':'fa-lock' ?>"></i> <?= $franchiseOverrideAllowed?'Personalização liberada':'Regra central bloqueada' ?></span>
  </header>
  <form method="post" action="<?= $escape($basePath) ?>/admin/platform/integrations/course-providers/catalogs/<?= (int)$catalog['id'] ?>/commercial-policy">
   <?= $csrfField ?>
   <input type="hidden" name="provider" value="<?= $escape($provider) ?>">
   <div class="central-policy-grid">
    <label><span>Preço padrão para todos (R$)</span><input inputmode="decimal" name="default_price" value="<?= $centralPrice!==null?$escape(number_format((float)$centralPrice,2,',','')):'' ?>" placeholder="Ex.: 149,90"><small>Se preenchido, substitui o preço do fornecedor.</small></label>
    <label><span>Ajuste sobre o fornecedor (%)</span><input inputmode="decimal" name="markup_percent" value="<?= $escape(number_format($centralMarkup,4,',','')) ?>"><small>Usado quando o preço padrão ficar vazio.</small></label>
    <label><span>Parcelamento padrão</span><input type="number" min="1" max="60" name="default_max_installments" value="<?= $centralInstallments ?>"><small>Quantidade máxima oferecida neste catálogo.</small></label>
    <label><span>Válida a partir de</span><input type="date" name="valid_from" value="<?= $escape((string)($catalog['central_valid_from']??'')) ?>"><small>Vazio aplica imediatamente.</small></label>
    <label><span>Válida até</span><input type="date" name="valid_until" value="<?= $escape((string)($catalog['central_valid_until']??'')) ?>"><small>Vazio mantém a regra sem prazo.</small></label>
   </div>
   <div class="central-policy-control">
    <label class="central-policy-toggle"><input type="checkbox" name="allow_franchise_override" value="1" <?= $franchiseOverrideAllowed?'checked':'' ?>><span><strong>Permitir personalização pela franquia</strong><small>Quando desativado, preço, ajuste, parcelas e vigência ficam bloqueados no ADM da Franquia.</small></span></label>
    <label class="central-policy-target"><span>Aplicar o padrão em</span><select name="organization_id"><option value="0">Todas as franquias ativas</option><?php foreach($organizationRows as$organization):?><option value="<?= (int)$organization['id'] ?>"><?= $escape((string)($organization['display_name']?:$organization['legal_name'])) ?></option><?php endforeach;?></select><small>A aplicação preserva bloqueios de catálogo e curso.</small></label>
   </div>
   <?php if($isInter):?><div class="catalog-note"><i class="fa-solid fa-circle-info"></i><div><strong>Catálogo INTER.</strong><br>Esta regra governa a herança entre franquias. Os valores individuais dos cursos nativos continuam no cadastro de Cursos e preços.</div></div><?php endif;?>
   <footer class="central-policy-actions">
    <span><i class="fa-solid fa-shield-halved"></i> Exceções atuais só mudam quando você escolher aplicar.</span>
    <div><button class="btn btn-secondary" type="submit" name="policy_action" value="save"><i class="fa-solid fa-floppy-disk"></i> Salvar padrão central</button><button class="btn btn-primary" type="submit" name="policy_action" value="apply" onclick="return confirm('Aplicar este padrão comercial ao destino selecionado?')"><i class="fa-solid fa-wand-magic-sparkles"></i> Salvar e aplicar</button></div>
   </footer>
  </form>
 </section>
</div>
