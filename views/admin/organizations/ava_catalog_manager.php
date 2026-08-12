<?php
$inventory = is_array($catalogItems ?? null) ? $catalogItems : ['items'=>[],'total'=>0,'page'=>1,'pages'=>1,'catalog_code'=>'','item_type'=>'course','query'=>''];
$selectedCatalogCode = (string)($inventory['catalog_code'] ?? '');
$selectedItemType = (string)($inventory['item_type'] ?? 'course');
$selectedQuery = (string)($inventory['query'] ?? '');
$selectedCatalog = null;
foreach (($catalogAccess ?? []) as $catalogRow) {
    if ((string)($catalogRow['code'] ?? '') === $selectedCatalogCode) {$selectedCatalog = $catalogRow; break;}
}
$selectedCatalog ??= [];
$formationName=static fn(string$name):string=>trim((string)(preg_replace('/^\s*(?:Cat[aá]logo|Forma[cç][aã]o)\s+/iu','',$name)??$name));
$organizationId = (int)$organization['id'];
$managerQuery = http_build_query(['ava_catalog'=>$selectedCatalogCode,'ava_type'=>$selectedItemType,'ava_q'=>$selectedQuery,'ava_page'=>(int)($inventory['page']??1)]);
$returnTo = '/admin/organizations/'.$organizationId.'/edit?'.$managerQuery.'#ava';
$pageUrl = static function(int $page) use ($basePath,$organizationId,$selectedCatalogCode,$selectedItemType,$selectedQuery): string {
    return $basePath.'/admin/organizations/'.$organizationId.'/edit?'.http_build_query(['ava_catalog'=>$selectedCatalogCode,'ava_type'=>$selectedItemType,'ava_q'=>$selectedQuery,'ava_page'=>$page]).'#ava';
};
?>
<section class="card organization-section ava-commerce-manager" data-organization-panel="ava" hidden>
 <header class="organization-section-header"><span class="organization-section-icon"><i class="fa-solid fa-store"></i></span><div><h2>Oferta comercial da franquia</h2><p class="meta">Pesquise a Formação sem carregar milhares de registros. O acesso é herdado como liberado; bloqueios e preços são exceções desta franquia.</p></div></header>

 <form class="ava-commerce-filters" method="get" action="<?= $escape($basePath) ?>/admin/organizations/<?= $organizationId ?>/edit#ava">
  <label>Formação<select name="ava_catalog" onchange="this.form.submit()">
   <?php foreach(($catalogAccess??[]) as$catalog):?><option value="<?= $escape((string)$catalog['code']) ?>" <?= (string)$catalog['code']===$selectedCatalogCode?'selected':'' ?>><?= $escape($formationName((string)$catalog['name'])) ?> · <?= (int)($catalog['inventory_count']??0) ?> curso(s) · <?= (int)($catalog['content_count']??0) ?> curso(s) individual(is)</option><?php endforeach;?>
  </select></label>
  <label>Tipo<select name="ava_type" onchange="this.form.submit()"><option value="course" <?= $selectedItemType==='course'?'selected':'' ?>>Cursos individuais</option><option value="content" <?= $selectedItemType==='content'?'selected':'' ?>>Cursos individuais por conteúdo</option></select></label>
  <label class="ava-commerce-search">Localizar<input name="ava_q" value="<?= $escape($selectedQuery) ?>" placeholder="Nome, categoria ou código"></label>
  <button class="button button-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Pesquisar</button>
  <?php if($selectedQuery!==''):?><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/organizations/<?= $organizationId ?>/edit?<?= $escape(http_build_query(['ava_catalog'=>$selectedCatalogCode,'ava_type'=>$selectedItemType])) ?>#ava">Limpar</a><?php endif;?>
 </form>

 <div class="ava-readiness-legend">
  <span><i class="fa-solid fa-unlock"></i><strong>Liberado por padrão</strong><small>Bloqueie somente exceções.</small></span>
  <span><i class="fa-solid fa-wand-magic-sparkles"></i><strong>Curadoria central</strong><small>Nome, imagem, descrição e carga horária.</small></span>
  <span><i class="fa-solid fa-tags"></i><strong>Condição da franquia</strong><small>Preço, parcelas e publicação no site.</small></span>
 </div>

 <?php if($selectedCatalogCode==='ava-cursos'):?>
  <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> A Formação INTER é nativa do AVA Cursos. A sincronização e os preços ficam em <strong>ADM → Cursos e preços</strong> da franquia; as demais Formações são administradas nesta lista.</div>
 <?php elseif((int)($inventory['total']??0)===0):?>
  <div class="empty-state"><i class="fa-solid fa-box-open"></i><strong>Nenhum item encontrado</strong><span>Sincronize este fornecedor no ADM Central ou altere a pesquisa.</span></div>
 <?php else:?>
  <div class="ava-commerce-result-header"><span><strong><?= number_format((int)$inventory['total'],0,',','.') ?></strong> curso(s) individual(is)</span><span>Página <?= (int)$inventory['page'] ?> de <?= (int)$inventory['pages'] ?></span></div>
  <div class="ava-commerce-list">
   <?php foreach(($inventory['items']??[]) as$item):
    $offerId=(int)($item['offer_id']??0);
    $itemId=(int)$item['item_id'];
    $organizationItemEnabled=(int)($item['organization_item_enabled']??1)===1;
    $globalItemEnabled=(int)($item['catalog_globally_enabled']??1)===1&&(int)($item['is_globally_enabled']??1)===1;
    $effectiveEnabled=!empty($item['is_effectively_enabled']);
    $curated=!empty($item['is_curated']);
    $ready=!empty($item['is_commercially_ready']);
    $missing=is_array($item['missing_commercial_fields']??null)?$item['missing_commercial_fields']:[];
    $itemType=(string)$item['item_type'];
    $offerPath=$itemType==='content'?'content-offers':'catalog-offers';
   ?>
    <details class="ava-commerce-item <?= $effectiveEnabled?'':'is-blocked' ?>">
     <summary>
      <span class="ava-commerce-cover"><?php if((string)($item['effective_cover_url']??'')!==''):?><img src="<?= $escape((string)$item['effective_cover_url']) ?>" alt="" loading="lazy"><?php else:?><i class="fa-solid <?= $itemType==='content'?'fa-circle-play':'fa-book-open' ?>"></i><?php endif;?></span>
      <span class="ava-commerce-title"><strong><?= $escape((string)$item['effective_name']) ?></strong><small><?= $escape((string)($item['category']?:($itemType==='content'?'Curso individual':'Sem categoria'))) ?><?= (string)($item['workload']??'')!==''?' · '.$escape((string)$item['workload']):'' ?></small></span>
      <span class="ava-commerce-state"><span class="connection-badge <?= $effectiveEnabled?'connection-approved':'connection-pending' ?>"><?= $effectiveEnabled?'Liberado':'Bloqueado' ?></span><small><?= $organizationItemEnabled?'Regra herdada':'Exceção da franquia' ?></small></span>
      <span class="ava-commerce-ready"><span class="connection-badge <?= $ready?'connection-approved':'connection-pending' ?>"><?= $ready?'Pronto para vender':($curated?'Completar oferta':'Curadoria pendente') ?></span><small><?= $offerId>0&&((int)($item['is_visible']??0)===1)?'Visível no site':($offerId>0?'Oferta configurada':'Sem preço próprio') ?></small></span>
      <i class="fa-solid fa-chevron-down ava-commerce-chevron"></i>
     </summary>
     <div class="ava-commerce-body">
      <div class="ava-commerce-diagnostics">
       <strong>Prontidão comercial</strong>
       <?php if(!$curated):?><span class="status-note is-warning"><i class="fa-solid fa-wand-magic-sparkles"></i> Aguardando aprovação e liberação na curadoria central.</span><?php endif;?>
       <?php if($missing!==[]):?><span class="status-note is-warning"><i class="fa-solid fa-triangle-exclamation"></i> Falta: <?= $escape(implode(', ',$missing)) ?>.</span><?php else:?><span class="status-note is-success"><i class="fa-solid fa-circle-check"></i> Conteúdo comercial completo.</span><?php endif;?>
       <?php if(!$globalItemEnabled):?><span class="status-note is-danger"><i class="fa-solid fa-lock"></i> Bloqueado globalmente pelo ADM Central.</span><?php endif;?>
      </div>
      <form class="ava-commerce-offer-form" method="post" action="<?= $escape($basePath) ?>/admin/organizations/<?= $organizationId ?>/<?= $offerPath ?>"><?= $csrfField ?>
       <input type="hidden" name="<?= $itemType==='content'?'content_id':'course_id' ?>" value="<?= $itemId ?>"><input type="hidden" name="return_to" value="<?= $escape($returnTo) ?>">
       <label>Nome na loja<input name="commercial_name" maxlength="500" value="<?= $escape((string)$item['effective_name']) ?>" <?= !$curated||!$effectiveEnabled?'disabled':'' ?>></label>
       <label>Preço próprio<input name="price" inputmode="decimal" value="<?= $offerId>0?number_format((float)$item['price'],2,',',''):'' ?>" placeholder="0,00" <?= !$curated||!$effectiveEnabled?'disabled':'' ?>></label>
       <label>Parcelas<input type="number" name="max_installments" min="1" max="60" value="<?= max(1,(int)($item['max_installments']??1)) ?>" <?= !$curated||!$effectiveEnabled?'disabled':'' ?>></label>
       <label class="ava-commerce-description">Descrição para esta franquia<textarea name="commercial_description" placeholder="Se vazio, usa a descrição aprovada pelo ADM Central" <?= !$curated||!$effectiveEnabled?'disabled':'' ?>><?= $escape((string)($item['offer_description']??'')) ?></textarea></label>
       <div class="ava-commerce-switches"><label><input type="checkbox" name="is_active" value="1" <?= $offerId===0||(int)($item['is_active']??0)===1?'checked':'' ?> <?= !$curated||!$effectiveEnabled?'disabled':'' ?>> Oferta ativa</label><label><input type="checkbox" name="is_visible" value="1" <?= (int)($item['is_visible']??0)===1?'checked':'' ?> <?= !$curated||!$effectiveEnabled||$missing!==[]?'disabled':'' ?>> Exibir no site</label></div>
       <button class="button button-primary" type="submit" <?= !$curated||!$effectiveEnabled?'disabled':'' ?>><i class="fa-solid fa-floppy-disk"></i> <?= $offerId>0?'Salvar oferta':'Configurar oferta' ?></button>
      </form>
      <div class="ava-commerce-actions">
       <form method="post" action="<?= $escape($basePath) ?>/admin/organizations/<?= $organizationId ?>/catalog-items/<?= $itemType ?>/<?= $itemId ?>/availability"><?= $csrfField ?><input type="hidden" name="return_to" value="<?= $escape($returnTo) ?>"><input type="hidden" name="enabled" value="<?= $organizationItemEnabled?'0':'1' ?>"><button class="button <?= $organizationItemEnabled?'button-danger':'button-secondary' ?>" type="submit" <?= !$globalItemEnabled?'disabled':'' ?>><i class="fa-solid <?= $organizationItemEnabled?'fa-ban':'fa-unlock' ?>"></i> <?= $organizationItemEnabled?'Bloquear nesta franquia':'Liberar nesta franquia' ?></button></form>
       <?php if($offerId>0):?><form method="post" action="<?= $escape($basePath) ?>/admin/organizations/<?= $organizationId ?>/<?= $offerPath ?>/<?= $offerId ?>/delete" onsubmit="return confirm('Remover a configuração comercial deste item?')"><?= $csrfField ?><input type="hidden" name="return_to" value="<?= $escape($returnTo) ?>"><button class="button button-secondary" type="submit"><i class="fa-solid fa-trash"></i> Remover oferta</button></form><?php endif;?>
       <?php if((string)($selectedCatalog['provider_code']??'')!=='ava_cursos'):?><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/platform/integrations/course-providers?catalog=<?= $escape((string)$selectedCatalog['provider_code']) ?>&section=<?= $itemType==='content'?'contents':'courses' ?>"><i class="fa-solid fa-wand-magic-sparkles"></i> Abrir curadoria central</a><?php endif;?>
      </div>
     </div>
    </details>
   <?php endforeach;?>
  </div>
  <?php if((int)$inventory['pages']>1):?><nav class="pagination ava-commerce-pagination" aria-label="Páginas da Formação"><?php if((int)$inventory['page']>1):?><a class="button button-secondary" href="<?= $escape($pageUrl((int)$inventory['page']-1)) ?>"><i class="fa-solid fa-arrow-left"></i> Anterior</a><?php endif;?><span>Página <?= (int)$inventory['page'] ?> de <?= (int)$inventory['pages'] ?></span><?php if((int)$inventory['page']<(int)$inventory['pages']):?><a class="button button-secondary" href="<?= $escape($pageUrl((int)$inventory['page']+1)) ?>">Próxima <i class="fa-solid fa-arrow-right"></i></a><?php endif;?></nav><?php endif;?>
 <?php endif;?>
</section>
<style>
.ava-commerce-manager{min-height:0}.ava-commerce-filters{display:grid;grid-template-columns:minmax(13rem,1.1fr) minmax(10rem,.55fr) minmax(15rem,1.4fr) auto auto;gap:.75rem;align-items:end;padding:1rem;border:1px solid #dce3e8;border-radius:1rem;background:#f8fafb}.ava-commerce-filters label{display:grid;gap:.35rem;margin:0}.ava-commerce-filters select,.ava-commerce-filters input{width:100%;height:2.8rem}.ava-readiness-legend{display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;margin:1rem 0}.ava-readiness-legend>span{display:grid;grid-template-columns:auto 1fr;column-gap:.65rem;align-items:center;padding:.85rem;border:1px solid #dce3e8;border-radius:.85rem;background:#fff}.ava-readiness-legend i{grid-row:1/3;color:var(--inter-accent)}.ava-readiness-legend small{color:var(--inter-muted)}.ava-commerce-result-header{display:flex;justify-content:space-between;gap:1rem;padding:.75rem .2rem;color:var(--inter-muted)}.ava-commerce-list{display:grid;gap:.65rem}.ava-commerce-item{border:1px solid #dce3e8;border-radius:.9rem;background:#fff;overflow:hidden}.ava-commerce-item.is-blocked{border-color:#efb9be;background:#fff8f8}.ava-commerce-item>summary{display:grid;grid-template-columns:auto minmax(14rem,1.5fr) minmax(8rem,.55fr) minmax(9rem,.65fr) auto;gap:.8rem;align-items:center;padding:.85rem 1rem;cursor:pointer;list-style:none}.ava-commerce-item>summary::-webkit-details-marker{display:none}.ava-commerce-item[open] .ava-commerce-chevron{transform:rotate(180deg)}.ava-commerce-cover{display:grid;place-items:center;width:3.8rem;height:3rem;border-radius:.65rem;background:#fff0f1;color:var(--inter-accent);overflow:hidden}.ava-commerce-cover img{width:100%;height:100%;object-fit:cover}.ava-commerce-title,.ava-commerce-state,.ava-commerce-ready{display:grid;gap:.2rem;min-width:0}.ava-commerce-title strong{overflow-wrap:anywhere}.ava-commerce-title small,.ava-commerce-state small,.ava-commerce-ready small{color:var(--inter-muted)}.ava-commerce-state .connection-badge,.ava-commerce-ready .connection-badge{width:max-content}.ava-commerce-body{display:grid;grid-template-columns:minmax(13rem,.7fr) minmax(0,2fr);gap:1rem;padding:1rem;border-top:1px solid #e5eaee;background:#fafcfd}.ava-commerce-diagnostics{display:grid;align-content:start;gap:.55rem}.status-note{display:flex;gap:.4rem;padding:.55rem .65rem;border-radius:.65rem;background:#eef2f5;color:#53636f;font-size:.8rem}.status-note.is-warning{background:#fff4d6;color:#7a5700}.status-note.is-danger{background:#ffe8ea;color:#a31320}.status-note.is-success{background:#e5f8ed;color:#087542}.ava-commerce-offer-form{display:grid;grid-template-columns:minmax(12rem,1fr) minmax(7rem,.45fr) minmax(5rem,.32fr);gap:.7rem;align-items:end}.ava-commerce-offer-form label{display:grid;gap:.3rem;margin:0}.ava-commerce-description{grid-column:1/3}.ava-commerce-description textarea{min-height:4.6rem}.ava-commerce-switches{display:flex;gap:1rem;align-items:center}.ava-commerce-switches label{display:flex;align-items:center;gap:.35rem}.ava-commerce-actions{display:flex;flex-wrap:wrap;gap:.6rem;grid-column:1/-1;justify-content:flex-end;padding-top:.8rem;border-top:1px dashed #d5dee4}.ava-commerce-actions form{margin:0}.ava-commerce-pagination{display:flex;justify-content:center;align-items:center;gap:1rem;margin-top:1rem}.button-danger{background:#bd1f2d;color:#fff;border-color:#bd1f2d}@media(max-width:1100px){.ava-commerce-filters{grid-template-columns:repeat(2,minmax(0,1fr))}.ava-commerce-search{grid-column:1/-1}.ava-commerce-item>summary{grid-template-columns:auto 1fr auto}.ava-commerce-state,.ava-commerce-ready{grid-row:2;grid-column:auto}.ava-commerce-body{grid-template-columns:1fr}}@media(max-width:700px){.ava-commerce-filters,.ava-readiness-legend,.ava-commerce-offer-form{grid-template-columns:1fr}.ava-commerce-search,.ava-commerce-description{grid-column:auto}.ava-commerce-item>summary{grid-template-columns:auto 1fr auto}.ava-commerce-state,.ava-commerce-ready{grid-column:1/-1}.ava-commerce-switches{align-items:flex-start;flex-direction:column}.ava-commerce-actions{justify-content:stretch}.ava-commerce-actions .button{width:100%}}
</style>
