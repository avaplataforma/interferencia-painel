<?php
$homologationOrganization=is_array($homologation['organization']??null)?$homologation['organization']:[];
$homologationOrganizationId=(int)($homologationOrganization['id']??0);
$homologationCourses=is_array($homologation['courses']??null)?$homologation['courses']:[];
$homologationContents=is_array($homologation['contents']??null)?$homologation['contents']:[];
$homologationPublishedCourses=(int)($homologation['published_courses']??0);
$homologationPublishedContents=(int)($homologation['published_contents']??0);
$homologationPublishedTotal=$homologationPublishedCourses+$homologationPublishedContents;
$homologationPrice=(float)($catalog['central_default_price']??0);
if($homologationPrice<5)$homologationPrice=149.90;
$homologationInstallments=max(1,(int)($catalog['central_default_max_installments']??1));
$homologationSlug=trim((string)($homologationOrganization['panel_slug']??''));
$homologationConfigured=(int)($catalog['configured']??0)===1;
$homologationActive=(int)($catalog['integration_active']??0)===1;
?>
<div class="catalog-subpanel" data-catalog-subpanel="<?= $escape($provider) ?>:homologation" hidden>
 <div class="homologation-shell">
  <section class="homologation-intro">
   <div><h3>Homologação assistida do catálogo</h3><p>Publique uma amostra controlada na vitrine da franquia, confira o fluxo comercial e valide o destino acadêmico antes de liberar a operação.</p></div>
   <span class="homologation-safe"><i class="fa-solid fa-shield-halved"></i> Sem cobrança real</span>
  </section>

  <div class="homologation-status">
   <article><small>Integração</small><strong><?= $homologationConfigured&&$homologationActive?'Pronta':'Pendente' ?></strong><span class="catalog-badge <?= $homologationConfigured&&$homologationActive?'ok':'' ?>"><?= $homologationConfigured?'Credencial salva':'Configuração incompleta' ?></span></article>
   <article><small>Cursos importados</small><strong><?= (int)($homologationCourses['imported']??0) ?></strong><span><?= (int)($homologationCourses['with_cover']??0) ?> com capa pronta</span></article>
   <article><small>Cursos individuais</small><strong><?= (int)($homologationContents['imported']??0) ?></strong><span><?= (int)($homologationContents['with_cover']??0) ?> com capa pronta</span></article>
   <article><small>Publicados no piloto</small><strong><?= $homologationPublishedTotal ?></strong><span><?= $homologationPublishedCourses ?> curso(s) · <?= $homologationPublishedContents ?> conteúdo(s)</span></article>
  </div>

  <form class="homologation-form" method="post" action="<?= $escape($basePath) ?>/admin/platform/integrations/course-providers/catalog/<?= $escape($provider) ?>/homologation">
   <?= $csrfField ?>
   <label><span>Franquia do piloto *</span><select required name="organization_id"><option value="">Selecione</option><?php foreach($organizationRows as$organization):$organizationId=(int)$organization['id'];?><option value="<?= $organizationId ?>" <?= $organizationId===$homologationOrganizationId?'selected':'' ?>><?= $escape((string)($organization['display_name']?:$organization['legal_name'])) ?></option><?php endforeach;?></select><small>Somente esta franquia receberá a amostra.</small></label>
   <label><span>Tipo da amostra *</span><select required name="item_type"><option value="course">Cursos individuais</option><option value="content">Cursos individuais por conteúdo</option></select><small>Comece pelos títulos completos.</small></label>
   <label><span>Quantidade *</span><input required type="number" min="1" max="10" name="sample_size" value="3"><small>De 1 a 10 itens.</small></label>
   <label><span>Preço de teste (R$) *</span><input required inputmode="decimal" name="price" value="<?= number_format($homologationPrice,2,',','.') ?>"><small>É exibido na vitrine, sem cobrança.</small></label>
   <label><span>Parcelas *</span><input required type="number" min="1" max="60" name="installments" value="<?= $homologationInstallments ?>"><small>Condição comercial simulada.</small></label>
   <label class="homologation-confirm"><input required type="checkbox" name="confirm_no_charge" value="1"><span><strong>Confirmo a preparação do piloto sem cobrança.</strong><small>Esta ação aprova, libera e publica somente a amostra. Ela não chama o Asaas, não cria cliente financeiro e não gera cobrança.</small></span></label>
   <div class="homologation-actions"><span><i class="fa-solid fa-circle-info"></i> Itens sem capa pronta não entram na amostra.</span><div><button class="btn btn-primary" type="submit" <?= !$homologationConfigured||!$homologationActive?'disabled':'' ?>><i class="fa-solid fa-flask-vial"></i> Preparar amostra</button><?php if($homologationSlug!==''):?><a class="btn btn-secondary" href="<?= $escape($basePath.'/'.$homologationSlug.'/site?trail='.rawurlencode($tabLabel((string)$catalog['name']))) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir vitrine</a><?php endif;?></div></div>
  </form>

  <div class="homologation-checklist">
   <article><i class="fa-solid fa-store"></i><div><strong>1. Vitrine e trilha</strong><small>Confirme capa, nome, descrição, preço, filtro por trilha e página do produto.</small></div></article>
   <article><i class="fa-solid fa-graduation-cap"></i><div><strong>2. Matrícula e acesso</strong><small>Depois da vitrine, valide o destino acadêmico e o acesso do aluno com um cadastro de teste.</small></div></article>
   <article><i class="fa-solid fa-receipt"></i><div><strong>3. Financeiro protegido</strong><small>Nesta etapa nenhuma cobrança é emitida. O Asaas só será testado em uma homologação separada e confirmada.</small></div></article>
  </div>
 </div>
</div>
