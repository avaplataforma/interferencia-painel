<?php

declare(strict_types=1);

$money=static fn(float $value):string=>'R$ '.number_format($value,2,',','.');
$visibleCount=count(array_filter($products,static fn(array $product):bool=>(int)($product['catalog_visible']??0)===1));
$avaCount=count(array_filter($products,static fn(array $product):bool=>($product['catalog_source']??'ava')==='ava'));
$manualCount=count($products)-$avaCount;
?>
<style>
.catalog-admin{max-width:96rem;margin:0 auto}.catalog-header{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:1.2rem}.catalog-header h1,.catalog-header p{margin:.2rem 0}.catalog-actions{display:flex;align-items:center;gap:.65rem;flex-wrap:wrap}.catalog-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.8rem;margin-bottom:1rem}.catalog-stat{display:flex;align-items:center;gap:.8rem;padding:1rem 1.15rem}.catalog-stat i{display:grid;place-items:center;width:2.5rem;height:2.5rem;border-radius:.7rem;background:#fff0f1;color:var(--inter-accent)}.catalog-stat span{display:grid}.catalog-stat small{color:var(--inter-muted)}.catalog-course{display:grid;gap:.2rem}.catalog-course small{color:var(--inter-muted)}.catalog-source{display:inline-flex;align-items:center;gap:.4rem;width:max-content;padding:.25rem .55rem;border-radius:999px;font-size:.78rem;font-weight:800}.catalog-source.ava{background:#e8f7ee;color:#067340}.catalog-source.manual{background:#eef2f6;color:#475569}.catalog-visibility{display:grid;gap:.35rem}.catalog-row-hidden{opacity:.62;background:#f8fafb}.catalog-actions-cell{display:flex;align-items:center;justify-content:flex-end;gap:.45rem;white-space:nowrap}.catalog-actions-cell form{margin:0}.catalog-empty{display:grid;place-items:center;gap:.45rem;min-height:12rem;color:var(--inter-muted)}@media(max-width:800px){.catalog-header{align-items:stretch;flex-direction:column}.catalog-summary{grid-template-columns:1fr}.catalog-actions .button-primary,.catalog-actions .button-secondary{flex:1}.catalog-admin table{min-width:68rem}}
</style>
<div class="catalog-admin">
 <header class="catalog-header">
  <div><p class="eyebrow">ADM · Formação INTER</p><h1>Cursos e preços</h1><p class="meta">Gerencie preços e decida quais Cursos individuais ficam disponíveis nesta franquia.</p></div>
  <div class="catalog-actions">
   <a class="button-secondary" href="<?= $escape($basePath) ?>/admin/finance/products/create"><i class="fa-solid fa-plus"></i> Curso manual</a>
   <form method="post" action="<?= $escape($basePath) ?>/admin/finance/products/sync"><?= $csrfField ?><button class="button-primary" type="submit"><i class="fa-solid fa-rotate"></i> Sincronizar com o AVA</button></form>
  </div>
 </header>
 <?php if($message):?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif;?>
 <?php if($error):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>

 <div class="catalog-summary">
  <article class="card catalog-stat"><i class="fa-solid fa-graduation-cap"></i><span><strong><?= $avaCount ?></strong><small>sincronizados do AVA</small></span></article>
  <article class="card catalog-stat"><i class="fa-solid fa-pen-ruler"></i><span><strong><?= $manualCount ?></strong><small>cadastrados manualmente</small></span></article>
  <article class="card catalog-stat"><i class="fa-solid fa-eye"></i><span><strong><?= $visibleCount ?></strong><small>visíveis nesta franquia</small></span></article>
 </div>

 <div class="alert alert-warning"><strong>Regra segura:</strong> cursos do AVA não podem ser excluídos. Cursos manuais sem histórico podem ser removidos; qualquer curso pode ser ocultado somente desta franquia.</div>
 <section class="card"><div class="table-responsive"><table><thead><tr><th>Curso</th><th>Origem</th><th>Unidade</th><th>Valor</th><th>Parcelas</th><th>Disponibilidade</th><th>Ações</th></tr></thead><tbody>
 <?php if($products===[]):?><tr><td colspan="7"><div class="catalog-empty"><i class="fa-solid fa-book-open"></i><strong>Nenhum Curso individual disponível</strong><span>Sincronize os cursos do AVA ou cadastre um curso manual.</span></div></td></tr><?php endif;?>
 <?php foreach($products as$product):$pending=(float)$product['value']<5;$source=(string)($product['catalog_source']??($product['moodle_course_id']===null?'manual':'ava'));$visible=(int)($product['catalog_visible']??1)===1;?>
  <tr class="<?= $visible?'':'catalog-row-hidden' ?>">
   <td><span class="catalog-course"><strong><?= $escape($product['name']) ?></strong><?php if($product['moodle_shortname']):?><small><?= $escape($product['moodle_shortname']) ?></small><?php endif;?></span></td>
   <td><span class="catalog-source <?= $source==='ava'?'ava':'manual' ?>"><i class="fa-solid <?= $source==='ava'?'fa-cloud-arrow-down':'fa-pen' ?>"></i> <?= $source==='ava'?'AVA':'Manual' ?></span></td>
   <td><?= $escape($product['unit_name']?:'Todas as unidades') ?></td>
   <td><?= $pending?'—':$escape($money((float)$product['value'])) ?></td>
   <td><?= $pending?'—':'Até '.(int)$product['max_installments'].'x' ?></td>
   <td><div class="catalog-visibility"><span class="badge <?= $visible?'badge-success':'badge-warning' ?>"><?= $visible?'Visível':'Oculto nesta franquia' ?></span><small><?= (int)$product['is_active']===1?'Curso ativo':'Curso inativo' ?></small></div></td>
   <td><div class="catalog-actions-cell">
    <a class="button-secondary button-icon" title="Editar curso e preço" href="<?= $escape($basePath) ?>/admin/finance/products/<?= (int)$product['id'] ?>/edit"><i class="fa-solid fa-pen"></i></a>
    <form method="post" action="<?= $escape($basePath) ?>/admin/finance/products/<?= (int)$product['id'] ?>/visibility"><?= $csrfField ?><input type="hidden" name="is_visible" value="<?= $visible?'0':'1' ?>"><button class="button-secondary button-icon" type="submit" title="<?= $visible?'Ocultar desta franquia':'Exibir nesta franquia' ?>"><i class="fa-solid <?= $visible?'fa-eye-slash':'fa-eye' ?>"></i></button></form>
    <?php if($source==='manual'):?><form method="post" action="<?= $escape($basePath) ?>/admin/finance/products/<?= (int)$product['id'] ?>/delete" onsubmit="return confirm('Excluir este curso manual? A exclusão só será concluída se não houver histórico vinculado.')"><?= $csrfField ?><input type="hidden" name="confirm_delete" value="1"><button class="button-danger button-icon" type="submit" title="Excluir curso manual"><i class="fa-solid fa-trash"></i></button></form><?php endif;?>
   </div></td>
  </tr>
 <?php endforeach;?>
 </tbody></table></div></section>
</div>
