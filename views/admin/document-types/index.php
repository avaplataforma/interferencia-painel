<?php
$types=$types??[];$editingType=$editingType??null;$editing=is_array($editingType);
?>
<style>
.document-types-page{max-width:82rem;margin:auto}.document-types-layout{display:grid;grid-template-columns:minmax(18rem,.72fr) minmax(0,1.55fr);gap:1.25rem;align-items:start}.document-type-form,.document-type-list{padding:1.35rem}.document-type-form{position:sticky;top:6.5rem}.document-type-form form{display:grid;gap:1rem}.document-type-form label{margin:0}.document-type-form input:not([type=checkbox]){width:100%;margin-top:.35rem}.document-type-options{display:grid;grid-template-columns:1fr 1fr;gap:.7rem}.document-type-option{display:flex!important;align-items:center;gap:.6rem;padding:.85rem;border:1px solid #dce3e8;border-radius:.75rem;background:#f8fafb}.document-type-option input{width:auto!important;margin:0!important}.document-type-table{overflow:hidden;border:1px solid #dce3e8;border-radius:.85rem}.document-type-row{display:grid;grid-template-columns:minmax(0,1.5fr) 7rem 7rem 5rem 3rem;gap:1rem;align-items:center;padding:.9rem 1rem;border-bottom:1px solid #e5eaee}.document-type-row:last-child{border-bottom:0}.document-type-row.header{background:#f7f9fa;color:#526172;font-size:.8rem;font-weight:750;text-transform:uppercase}.document-type-name strong,.document-type-name small{display:block}.document-type-name small{color:var(--inter-muted)}.document-type-row .status{display:inline-flex;width:max-content;padding:.25rem .55rem;border-radius:999px;font-size:.78rem}.document-type-row .status-success{color:#087a39;background:#eaf8ef}.document-type-row .status-danger{color:#a33a00;background:#fff0d2}.document-type-row .status-neutral{color:#596773;background:#edf1f4}@media(max-width:850px){.document-types-layout{grid-template-columns:1fr}.document-type-form{position:static}.document-type-row{grid-template-columns:minmax(0,1fr) auto auto}.document-type-row.header span:nth-child(2),.document-type-row.header span:nth-child(4),.document-type-row>:nth-child(2),.document-type-row>:nth-child(4){display:none}}@media(max-width:520px){.document-type-options{grid-template-columns:1fr}.document-type-row{gap:.55rem;padding:.8rem}.document-type-row.header span:nth-child(3),.document-type-row>:nth-child(3){display:none}}
</style>
<div class="document-types-page">
 <div class="page-header"><div><p class="eyebrow">ADM · Documentos</p><h1>Tipos de documentos</h1><p>Defina quais documentos podem ser anexados às franquias e quais fazem parte da conferência obrigatória.</p></div></div>
 <?php if(!empty($message)):?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif;?>
 <?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>
 <div class="document-types-layout">
  <section class="card document-type-form">
   <p class="eyebrow"><?= $editing?'Edição':'Novo item' ?></p><h2><?= $editing?'Editar tipo':'Cadastrar tipo' ?></h2><p class="meta">O nome aparecerá na seleção de documentos de todas as franquias.</p>
   <form method="post" action="<?= $escape($basePath) ?>/admin/document-types<?= $editing?'/'.(int)$editingType['id']:'' ?>"><?= $csrfField ?>
    <label>Nome do documento <span class="required-mark">*</span><input required maxlength="120" name="name" value="<?= $escape($editingType['name']??'') ?>" placeholder="Ex.: Alvará de funcionamento"></label>
    <label>Ordem de exibição<input type="number" min="0" max="9999" name="sort_order" value="<?= (int)($editingType['sort_order']??100) ?>"><small>Itens com números menores aparecem primeiro.</small></label>
    <div class="document-type-options">
     <label class="document-type-option"><input type="checkbox" name="is_required" value="1" <?= $editing&&!empty($editingType['is_required'])?'checked':'' ?>><span><strong>Obrigatório</strong><small>Entra na conferência documental.</small></span></label>
     <label class="document-type-option"><input type="checkbox" name="is_active" value="1" <?= !$editing||!empty($editingType['is_active'])?'checked':'' ?>><span><strong>Ativo</strong><small>Disponível para novos anexos.</small></span></label>
    </div>
    <div class="form-actions"><button class="button button-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> <?= $editing?'Salvar alterações':'Cadastrar tipo' ?></button><?php if($editing):?><a class="button button-secondary" href="<?= $escape($basePath) ?>/admin/document-types">Cancelar</a><?php endif;?></div>
   </form>
  </section>
  <section class="card document-type-list">
   <div class="card-header"><div><h2>Lista configurada</h2><p class="meta"><?= count($types) ?> tipo(s) cadastrado(s).</p></div></div>
   <div class="document-type-table">
    <div class="document-type-row header"><span>Documento</span><span>Obrigatório</span><span>Situação</span><span>Ordem</span><span>Ação</span></div>
    <?php foreach($types as$type):?><div class="document-type-row">
     <div class="document-type-name"><strong><?= $escape((string)$type['name']) ?></strong><small><?= $escape((string)$type['code']) ?></small></div>
     <span class="status <?= !empty($type['is_required'])?'status-danger':'status-neutral' ?>"><?= !empty($type['is_required'])?'Sim':'Não' ?></span>
     <span class="status <?= !empty($type['is_active'])?'status-success':'status-neutral' ?>"><?= !empty($type['is_active'])?'Ativo':'Inativo' ?></span>
     <span><?= (int)$type['sort_order'] ?></span>
     <a class="button button-secondary button-icon" title="Editar tipo" href="<?= $escape($basePath) ?>/admin/document-types?edit=<?= (int)$type['id'] ?>"><i class="fa-solid fa-pen"></i></a>
    </div><?php endforeach;?>
   </div>
  </section>
 </div>
</div>
