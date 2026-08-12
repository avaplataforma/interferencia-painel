<?php
declare(strict_types=1);

$activeTab = in_array(($activeTab ?? 'trails'), ['trails','categories'], true) ? $activeTab : 'trails';
$categoryEdit = is_array($categoryEdit ?? null) ? $categoryEdit : null;
$trailEdit = is_array($trailEdit ?? null) ? $trailEdit : null;
$selectedItems = array_fill_keys(array_map('strval', $trailEdit['item_keys'] ?? []), true);
$typeLabels = ['finance_product'=>'INTER','provider_course'=>'Curso individual','provider_content'=>'Curso individual'];
?>
<style>
.learning-tabs{display:flex;gap:.65rem;margin-bottom:1.2rem;padding:.5rem;border:1px solid #dfe5e9;border-radius:1rem;background:#fff}.learning-tabs a{display:flex;align-items:center;justify-content:center;gap:.5rem;min-height:3rem;padding:.65rem 1rem;border-radius:.7rem;color:#536170;font-weight:800;text-decoration:none}.learning-tabs a.is-active{color:#fff;background:var(--inter-accent);box-shadow:0 .4rem 1rem rgb(237 28 36 / 20%)}
.learning-layout{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(21rem,.75fr);gap:1rem;align-items:start}.learning-card{padding:1.2rem;border:1px solid #dfe5e9;border-radius:1rem;background:#fff;box-shadow:0 .4rem 1.2rem rgb(23 33 43 / 5%)}.learning-card h2{margin:0;font-size:1.35rem}.learning-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1rem}.learning-card-head p{margin:.2rem 0 0;color:var(--inter-muted)}
.category-form,.trail-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.85rem}.category-form label,.trail-fields label{display:grid;gap:.35rem;font-weight:750}.category-form input,.category-form textarea,.trail-fields input,.trail-fields textarea{width:100%;min-height:2.85rem;padding:.7rem .85rem;border:1px solid #bcc6ce;border-radius:.6rem;font:inherit}.category-form textarea,.trail-fields textarea{min-height:6rem;resize:vertical}.field-wide{grid-column:1/-1}.form-check-line{display:flex!important;align-items:center;gap:.55rem;min-height:2.85rem}.form-check-line input{width:auto!important;min-height:auto!important}.learning-actions{display:flex;justify-content:flex-end;gap:.55rem;grid-column:1/-1}
.category-list,.trail-list{display:grid;gap:.55rem}.category-row,.trail-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:1rem;align-items:center;padding:.8rem .9rem;border:1px solid #e1e7eb;border-radius:.75rem;background:#fbfcfd}.category-row.is-child{margin-left:1.5rem;border-left:3px solid #93c5fd}.category-row strong,.trail-row strong{display:block}.category-row small,.trail-row small{display:block;margin-top:.15rem;color:var(--inter-muted)}.category-actions,.trail-actions{display:flex;gap:.4rem;align-items:center}.status-pill{display:inline-flex!important;width:max-content;padding:.2rem .55rem;border-radius:999px;color:#087443!important;background:#e3f7ed;font-size:.74rem;font-weight:800}.status-pill.is-off{color:#8b5e00!important;background:#fff2ca}
.item-picker{grid-column:1/-1;padding:1rem;border:1px solid #dfe5e9;border-radius:.8rem;background:#f8fafb}.item-picker-head{display:flex;align-items:end;justify-content:space-between;gap:1rem;margin-bottom:.75rem}.item-picker-head label{flex:1}.item-picker-head small{color:var(--inter-muted)}.item-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.5rem;max-height:26rem;overflow:auto;padding:.15rem}.item-option{display:grid;grid-template-columns:auto minmax(0,1fr);gap:.65rem;align-items:start;padding:.7rem;border:1px solid #dfe5e9;border-radius:.65rem;background:#fff;cursor:pointer}.item-option:has(input:checked){border-color:var(--inter-accent);box-shadow:0 0 0 2px rgb(237 28 36 / 10%)}.item-option input{width:auto!important;min-height:auto!important;margin-top:.22rem}.item-option span{min-width:0}.item-option strong{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.item-option small{display:block;color:var(--inter-muted)}.selection-count{font-weight:800;color:var(--inter-accent)}.empty-note{padding:1.25rem;border:1px dashed #bdc7cf;border-radius:.75rem;color:var(--inter-muted);text-align:center}
@media(max-width:900px){.learning-layout{grid-template-columns:1fr}.category-form,.trail-fields,.item-grid{grid-template-columns:1fr}.field-wide,.learning-actions{grid-column:auto}.learning-tabs{overflow:auto;justify-content:flex-start}.learning-tabs a{white-space:nowrap}}
</style>

<div class="page-header"><div><p class="eyebrow">ADM Central · Catálogos</p><h1>Cursos individuais e Trilhas</h1><p>Organize Cursos individuais e pacotes comerciais em Categorias hierárquicas.</p></div><a class="btn btn-secondary" href="<?= $escape($basePath) ?>/admin/platform/integrations/course-providers"><i class="fa-solid fa-arrow-left"></i> Catálogos</a></div>
<?php if(!empty($message)):?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif;?>
<?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>

<nav class="learning-tabs" aria-label="Cursos individuais e Trilhas">
 <a class="<?= $activeTab==='trails'?'is-active':'' ?>" href="<?= $escape($basePath) ?>/admin/platform/catalog-trails?tab=trails"><i class="fa-solid fa-route"></i> Trilhas <span><?= count($trails) ?></span></a>
 <a class="<?= $activeTab==='categories'?'is-active':'' ?>" href="<?= $escape($basePath) ?>/admin/platform/catalog-trails?tab=categories"><i class="fa-solid fa-layer-group"></i> Categorias <span><?= count($categories) ?></span></a>
</nav>

<?php if($activeTab==='categories'):?>
<div class="learning-layout">
 <section class="learning-card">
  <div class="learning-card-head"><div><h2>Estrutura de categorias</h2><p>Categoria principal e subcategoria, com ordem e situação.</p></div></div>
  <div class="category-list">
   <?php foreach($categories as$category):?>
   <article class="category-row <?= $category['parent_id']!==null?'is-child':'' ?>"><div><strong><?= $category['parent_id']!==null?'↳ ':'' ?><?= $escape($category['name']) ?></strong><small><?= $escape($category['code']) ?> · <?= (int)$category['trail_count'] ?> Trilha(s)<?= $category['parent_name']?' · dentro de '.$escape($category['parent_name']):'' ?></small></div><div class="category-actions"><span class="status-pill <?= (int)$category['is_active']===1?'':'is-off' ?>"><?= (int)$category['is_active']===1?'Ativa':'Inativa' ?></span><a class="btn btn-secondary btn-sm" title="Editar categoria" href="<?= $escape($basePath) ?>/admin/platform/catalog-trails?tab=categories&amp;category_edit=<?= (int)$category['id'] ?>"><i class="fa-solid fa-pen"></i></a><form method="post" action="<?= $escape($basePath) ?>/admin/platform/catalog-trails/categories/<?= (int)$category['id'] ?>/delete" onsubmit="return confirm('Excluir esta categoria?')"><?= $csrfField ?><button class="btn btn-danger btn-sm" title="Excluir categoria" type="submit"><i class="fa-solid fa-trash"></i></button></form></div></article>
   <?php endforeach;?>
  </div>
 </section>
 <aside class="learning-card">
  <div class="learning-card-head"><div><h2><?= $categoryEdit?'Editar categoria':'Nova categoria' ?></h2><p>Use uma principal ou vincule como subcategoria.</p></div><?php if($categoryEdit):?><a class="btn btn-secondary btn-sm" href="<?= $escape($basePath) ?>/admin/platform/catalog-trails?tab=categories"><i class="fa-solid fa-xmark"></i></a><?php endif;?></div>
  <form class="category-form" method="post" action="<?= $escape($basePath) ?>/admin/platform/catalog-trails/categories<?= $categoryEdit?'/'.(int)$categoryEdit['id']:'' ?>"><?= $csrfField ?>
   <label class="field-wide">Nome *<input required name="name" maxlength="160" value="<?= $escape($categoryEdit['name']??'') ?>" placeholder="Ex.: Profissionalizantes"></label>
   <label>Categoria principal<select name="parent_id"><option value="">Nenhuma — nível principal</option><?php foreach($categories as$option):if($option['parent_id']!==null||($categoryEdit&&(int)$option['id']===(int)$categoryEdit['id']))continue;?><option value="<?= (int)$option['id'] ?>" <?= (int)($categoryEdit['parent_id']??0)===(int)$option['id']?'selected':'' ?>><?= $escape($option['name']) ?></option><?php endforeach;?></select></label>
   <label>Código permanente<input name="code" maxlength="120" value="<?= $escape($categoryEdit['code']??'') ?>" placeholder="Gerado pelo nome"></label>
   <label>Ordem<input type="number" min="0" max="999" name="sort_order" value="<?= (int)($categoryEdit['sort_order']??0) ?>"></label>
   <label class="form-check-line"><input type="checkbox" name="is_active" value="1" <?= !isset($categoryEdit['is_active'])||(int)$categoryEdit['is_active']===1?'checked':'' ?>> Categoria ativa</label>
   <label class="field-wide">Descrição<textarea name="description" maxlength="500"><?= $escape($categoryEdit['description']??'') ?></textarea></label>
   <div class="learning-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar categoria</button></div>
  </form>
 </aside>
</div>
<?php else:?>
<div class="learning-layout">
 <section class="learning-card">
  <div class="learning-card-head"><div><h2>Trilhas cadastradas</h2><p>Pacotes com dois ou mais cursos, classificados por categoria.</p></div><a class="btn btn-primary" href="<?= $escape($basePath) ?>/admin/platform/catalog-trails?tab=trails&amp;trail_new=1"><i class="fa-solid fa-plus"></i> Nova Trilha</a></div>
  <?php if($trails===[]):?><div class="empty-note"><i class="fa-solid fa-route"></i><br>Nenhuma Trilha cadastrada ainda.</div><?php else:?><div class="trail-list"><?php foreach($trails as$trail):?><article class="trail-row"><div><strong><?= $escape($trail['name']) ?></strong><small><i class="fa-solid fa-folder-tree"></i> <?= $escape($trail['parent_category_name']?($trail['parent_category_name'].' › '.$trail['category_name']):$trail['category_name']) ?> · <?= (int)$trail['item_count'] ?> item(ns)<?= $trail['default_price']!==null?' · R$ '.number_format((float)$trail['default_price'],2,',','.'):' · preço a definir' ?></small></div><div class="trail-actions"><span class="status-pill <?= (int)$trail['is_active']===1?'':'is-off' ?>"><?= (int)$trail['is_active']===1?'Ativa':'Inativa' ?></span><a class="btn btn-secondary btn-sm" title="Editar Trilha" href="<?= $escape($basePath) ?>/admin/platform/catalog-trails?tab=trails&amp;trail_edit=<?= (int)$trail['id'] ?>"><i class="fa-solid fa-pen"></i></a><form method="post" action="<?= $escape($basePath) ?>/admin/platform/catalog-trails/<?= (int)$trail['id'] ?>/delete" onsubmit="return confirm('Excluir esta Trilha e sua composição?')"><?= $csrfField ?><button class="btn btn-danger btn-sm" title="Excluir Trilha" type="submit"><i class="fa-solid fa-trash"></i></button></form></div></article><?php endforeach;?></div><?php endif;?>
 </section>
 <aside class="learning-card">
  <div class="learning-card-head"><div><h2><?= $trailEdit?'Editar Trilha':'Montar nova Trilha' ?></h2><p>A categoria é obrigatória e acompanha a Trilha na vitrine.</p></div><?php if($trailEdit||isset($_GET['trail_new'])):?><a class="btn btn-secondary btn-sm" href="<?= $escape($basePath) ?>/admin/platform/catalog-trails?tab=trails"><i class="fa-solid fa-xmark"></i></a><?php endif;?></div>
  <?php if(!$trailEdit&&!isset($_GET['trail_new'])):?><div class="empty-note"><i class="fa-solid fa-arrow-left"></i><br>Selecione uma Trilha para editar ou clique em <strong>Nova Trilha</strong>.</div><?php else:?>
  <form class="trail-fields" method="post" action="<?= $escape($basePath) ?>/admin/platform/catalog-trails<?= $trailEdit?'/'.(int)$trailEdit['id']:'' ?>"><?= $csrfField ?>
   <label class="field-wide">Nome da Trilha *<input required name="name" maxlength="190" value="<?= $escape($trailEdit['name']??'') ?>" placeholder="Ex.: Trilha Assistente Administrativo"></label>
   <label>Categoria *<select required name="category_id"><option value="">Selecione</option><?php foreach($categoryOptions as$option):?><option value="<?= (int)$option['id'] ?>" <?= (int)($trailEdit['category_id']??0)===(int)$option['id']?'selected':'' ?>><?= $option['parent_name']?$escape($option['parent_name']).' › ':'' ?><?= $escape($option['name']) ?></option><?php endforeach;?></select></label>
   <label>Endereço amigável<input name="slug" maxlength="160" value="<?= $escape($trailEdit['slug']??'') ?>" placeholder="Gerado pelo nome"></label>
   <label>Preço padrão (R$)<input inputmode="decimal" name="default_price" value="<?= $trailEdit&&$trailEdit['default_price']!==null?number_format((float)$trailEdit['default_price'],2,',',''):'' ?>" placeholder="Ex.: 299,90"></label>
   <label>Máximo de parcelas<input required type="number" min="1" max="60" name="max_installments" value="<?= (int)($trailEdit['max_installments']??1) ?>"></label>
   <label class="field-wide">Resumo comercial<input name="short_description" maxlength="500" value="<?= $escape($trailEdit['short_description']??'') ?>"></label>
   <label class="field-wide">Descrição completa<textarea name="description"><?= $escape($trailEdit['description']??'') ?></textarea></label>
   <label class="field-wide">Capa por URL<input type="url" name="cover_url" maxlength="1000" value="<?= $escape($trailEdit['cover_url']??'') ?>"></label>
   <label class="form-check-line field-wide"><input type="checkbox" name="is_active" value="1" <?= !isset($trailEdit['is_active'])||(int)$trailEdit['is_active']===1?'checked':'' ?>> Trilha ativa</label>
   <section class="item-picker"><div class="item-picker-head"><label>Localizar Cursos individuais<input type="search" placeholder="Nome, Formação, Catálogo interno ou tipo" data-trail-item-search></label><small><span class="selection-count" data-trail-selection-count><?= count($selectedItems) ?></span> selecionado(s) · mínimo 2</small></div><?php if($availableItems===[]):?><div class="empty-note">Aprove e libere Cursos individuais na curadoria central antes de montar uma Trilha.</div><?php else:?><div class="item-grid" data-trail-item-grid><?php foreach($availableItems as$item):$key=$item['item_type'].':'.$item['id'];?><label class="item-option" data-trail-item="<?= $escape(mb_strtolower($item['name'].' '.$item['catalog_name'].' '.$typeLabels[$item['item_type']])) ?>"><input type="checkbox" name="items[]" value="<?= $escape($key) ?>" <?= isset($selectedItems[$key])?'checked':'' ?>><span><strong><?= $escape($item['name']) ?></strong><small>Catálogo interno: <?= $escape($item['catalog_name']) ?> · <?= $escape($typeLabels[$item['item_type']]) ?></small></span></label><?php endforeach;?></div><?php endif;?></section>
   <div class="learning-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-route"></i> Salvar Trilha</button></div>
  </form>
  <?php endif;?>
 </aside>
</div>
<?php endif;?>
<script>
(()=>{const search=document.querySelector('[data-trail-item-search]');const items=[...document.querySelectorAll('[data-trail-item]')];const counter=document.querySelector('[data-trail-selection-count]');const normalize=value=>String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();const count=()=>{if(counter)counter.textContent=String(document.querySelectorAll('[data-trail-item] input:checked').length)};search?.addEventListener('input',()=>{const term=normalize(search.value);items.forEach(item=>item.hidden=term!==''&&!normalize(item.dataset.trailItem).includes(term))});items.forEach(item=>item.querySelector('input')?.addEventListener('change',count));count()})();
</script>
