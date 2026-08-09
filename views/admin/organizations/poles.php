<?php $poles=$organizationPoles??[];$poleUnits=$organizationPoleUnits??[]; ?>
<section class="card organization-section organization-poles" id="polos" data-organization-panel="polos" hidden>
 <header class="organization-section-header"><span class="organization-section-icon"><i class="fa-solid fa-location-crosshairs"></i></span><div><h2>Polos da franquia</h2><p class="meta">Gerencie os polos em uma lista compacta. Abra somente a linha que deseja editar.</p></div></header>

 <div class="pole-toolbar">
  <div class="pole-total"><span class="pole-total-icon"><i class="fa-solid fa-school"></i></span><div><strong><?= count($poles) ?></strong><span>polo(s) cadastrado(s)</span></div></div>
  <p class="meta"><i class="fa-solid fa-circle-info"></i> O código permanente identifica o polo no Painel e no AVA, mesmo quando seu nome comercial mudar.</p>
 </div>

 <details class="pole-create pole-disclosure">
  <summary><span><i class="fa-solid fa-circle-plus"></i><strong>Cadastrar novo polo</strong></span><span class="pole-summary-action">Abrir cadastro <i class="fa-solid fa-chevron-down"></i></span></summary>
  <form method="post" action="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/poles"><?= $csrfField ?>
   <div class="pole-fields">
    <label>Nome do polo *<input required maxlength="160" name="name" placeholder="Ex.: Polo Tijucas"></label>
    <label>Código permanente *<input required maxlength="100" name="code" pattern="[a-z0-9][a-z0-9-]{1,98}[a-z0-9]" placeholder="polo-tijucas"><small>Letras minúsculas, números e hífens.</small></label>
    <label>Unidade correspondente<select name="unit_id"><option value="">Sem unidade vinculada</option><?php foreach($poleUnits as$unit):if(!empty($unit['pole_id']))continue;?><option value="<?= (int)$unit['id'] ?>"><?= $escape((string)$unit['name']) ?><?= !empty($unit['city'])?' · '.$escape((string)$unit['city']):'' ?></option><?php endforeach;?></select></label>
    <label>Valor antigo no Moodle<input maxlength="255" name="legacy_value" placeholder="Somente para migrar Polo Presencial"><small>Opcional e temporário.</small></label>
   </div>
   <div class="pole-form-footer"><label class="checkbox-row"><input type="checkbox" name="is_primary" value="1" <?= $poles===[]?'checked':'' ?>> Polo principal</label><label class="checkbox-row"><input type="checkbox" name="is_active" value="1" checked> Polo ativo</label><button class="button button-primary" type="submit"><i class="fa-solid fa-plus"></i> Cadastrar polo</button></div>
  </form>
 </details>

 <?php if($poles!==[]):?>
 <div class="pole-list-head" aria-hidden="true"><span>Polo</span><span>Unidade</span><span>Código</span><span>Matrículas</span><span>Situação</span><span></span></div>
 <?php endif;?>
 <div class="pole-list">
 <?php foreach($poles as$pole):
  $unitName=trim((string)($pole['unit_name']??''));
  $isPrimary=(int)$pole['is_primary']===1;
  $isActive=(int)$pole['is_active']===1;
 ?>
  <details class="pole-row pole-disclosure">
   <summary>
    <span class="pole-name"><span class="pole-marker"><i class="fa-solid fa-location-dot"></i></span><span><strong><?= $escape((string)$pole['name']) ?></strong><?php if($isPrimary):?><small><i class="fa-solid fa-star"></i> Principal</small><?php endif;?></span></span>
    <span class="pole-unit"><small>Unidade</small><?= $unitName!==''?$escape($unitName):'<span class="meta">Sem vínculo</span>' ?></span>
    <code><?= $escape((string)$pole['code']) ?></code>
    <span class="pole-enrollments"><i class="fa-solid fa-user-graduate"></i> <?= (int)$pole['enrollment_count'] ?></span>
    <span class="status-badge <?= $isActive?'status-active':'status-inactive' ?>"><?= $isActive?'Ativo':'Inativo' ?></span>
    <span class="pole-expand" aria-label="Editar polo"><i class="fa-solid fa-chevron-down"></i></span>
   </summary>
   <div class="pole-row-editor">
    <form class="pole-edit-form" method="post" action="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/poles"><?= $csrfField ?><input type="hidden" name="pole_id" value="<?= (int)$pole['id'] ?>">
     <div class="pole-fields">
      <label>Nome *<input required maxlength="160" name="name" value="<?= $escape((string)$pole['name']) ?>"></label>
      <label>Código permanente *<input required maxlength="100" name="code" pattern="[a-z0-9][a-z0-9-]{1,98}[a-z0-9]" value="<?= $escape((string)$pole['code']) ?>"></label>
      <label>Unidade<select name="unit_id"><option value="">Sem unidade vinculada</option><?php foreach($poleUnits as$unit):if(!empty($unit['pole_id'])&&(int)$unit['pole_id']!==(int)$pole['id'])continue;?><option value="<?= (int)$unit['id'] ?>" <?= (int)($pole['unit_id']??0)===(int)$unit['id']?'selected':'' ?>><?= $escape((string)$unit['name']) ?><?= !empty($unit['city'])?' · '.$escape((string)$unit['city']):'' ?></option><?php endforeach;?></select></label>
      <label>Valor antigo no Moodle<input maxlength="255" name="legacy_value" value="<?= $escape((string)($pole['legacy_value']??'')) ?>" placeholder="Compatibilidade temporária"></label>
     </div>
     <div class="pole-form-footer"><label class="checkbox-row"><input type="checkbox" name="is_primary" value="1" <?= $isPrimary?'checked':'' ?>> Polo principal</label><label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?= $isActive?'checked':'' ?>> Polo ativo</label><span class="meta"><i class="fa-solid fa-graduation-cap"></i> <?= (int)$pole['enrollment_count'] ?> matrícula(s)</span><button class="button button-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar alterações</button></div>
    </form>
    <?php if((int)$pole['enrollment_count']===0):?><form class="pole-delete" method="post" action="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/poles/<?= (int)$pole['id'] ?>/delete" onsubmit="return confirm('Excluir este polo?');"><?= $csrfField ?><button class="button button-danger" type="submit"><i class="fa-solid fa-trash"></i> Excluir polo</button></form><?php endif;?>
   </div>
  </details>
 <?php endforeach;?>
 <?php if($poles===[]):?><div class="empty-state"><i class="fa-solid fa-map-location-dot"></i><strong>Nenhum polo cadastrado</strong><span>Abra o cadastro acima para adicionar o primeiro polo.</span></div><?php endif;?>
 </div>
</section>
<style>
.organization-poles{display:grid;gap:1rem}.pole-toolbar{display:flex;align-items:center;gap:1rem;padding:.85rem 1rem;border:1px solid #dce3e8;border-radius:.85rem;background:#f8fafb}.pole-toolbar>p{margin:0;margin-left:auto}.pole-total{display:flex;align-items:center;gap:.75rem;white-space:nowrap}.pole-total-icon,.pole-marker{display:grid;place-items:center;width:2.35rem;height:2.35rem;flex:0 0 2.35rem;border-radius:.7rem;background:#fff0f1;color:var(--inter-accent)}.pole-total div{display:grid}.pole-total strong{font-size:1.2rem;line-height:1}.pole-total span{color:#657483;font-size:.82rem}.pole-disclosure{border:1px solid #dce3e8;border-radius:.9rem;background:#fff;overflow:hidden}.pole-disclosure>summary{list-style:none;cursor:pointer}.pole-disclosure>summary::-webkit-details-marker{display:none}.pole-create>summary{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.1rem;color:#17212b}.pole-create>summary>span:first-child{display:flex;align-items:center;gap:.65rem}.pole-create>summary i{color:var(--inter-accent)}.pole-summary-action{font-size:.88rem;color:#657483}.pole-summary-action i,.pole-expand i{transition:transform .2s ease}.pole-disclosure[open] .pole-summary-action i,.pole-disclosure[open]>.pole-row-editor~* i,.pole-row[open]>summary .pole-expand i{transform:rotate(180deg)}.pole-create>form,.pole-row-editor{padding:.85rem 1.1rem;border-top:1px solid #e5eaee;background:#fbfcfd}.pole-fields{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.85rem;align-items:start}.pole-fields label{display:grid;grid-template-rows:auto 2.45rem 1.15rem;align-content:start;gap:.22rem;margin:0;min-width:0}.pole-fields input,.pole-fields select{width:100%;height:2.45rem;min-height:2.45rem;margin:0;padding:.38rem .65rem;line-height:1.15}.pole-fields small{min-height:1.15rem;margin:0;line-height:1.15}.pole-form-footer{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-top:.65rem}.pole-form-footer .button{margin-left:auto}.pole-list-head,.pole-row>summary{display:grid;grid-template-columns:minmax(12rem,1.5fr) minmax(10rem,1.2fr) minmax(8rem,1fr) 7rem 6rem 2rem;align-items:center;gap:1rem}.pole-list-head{padding:0 .95rem;color:#657483;font-size:.78rem;font-weight:750;text-transform:uppercase;letter-spacing:.035em}.pole-list{display:grid;gap:.55rem}.pole-row>summary{min-height:4.15rem;padding:.55rem .85rem}.pole-row>summary:hover{background:#f8fafb}.pole-name{display:flex;align-items:center;gap:.75rem;min-width:0}.pole-name>span:last-child{display:grid;gap:.15rem;min-width:0}.pole-name strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.pole-name small{color:#a26800}.pole-unit{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.pole-unit>small{display:none}.pole-row code{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#3b4a57}.pole-enrollments{color:#526270}.pole-expand{display:grid;place-items:center;width:2rem;height:2rem;border-radius:.55rem;background:#f1f4f6;color:#526270}.pole-row-editor{position:relative}.pole-edit-form{display:grid}.pole-delete{position:absolute;right:1.1rem;bottom:1rem}.pole-row-editor:has(.pole-delete) .pole-form-footer{padding-right:8.5rem}.status-badge{display:inline-flex;justify-content:center;padding:.35rem .65rem;border-radius:999px;font-size:.78rem;font-weight:750}.status-active{background:#e8f7ef;color:#08723d}.status-inactive{background:#eef1f4;color:#657483}.button-danger{background:#b4232b;border-color:#b4232b;color:#fff}.empty-state{display:grid;place-items:center;gap:.5rem;padding:2.5rem;border:1px dashed #cbd5dc;border-radius:1rem;color:#657483}.empty-state i{font-size:1.6rem}@media(max-width:1050px){.pole-fields{grid-template-columns:repeat(2,minmax(0,1fr))}.pole-list-head{display:none}.pole-row>summary{grid-template-columns:minmax(11rem,1.5fr) minmax(9rem,1fr) 7rem 6rem 2rem}.pole-row>summary code{display:none}}@media(max-width:760px){.pole-toolbar{align-items:flex-start;flex-direction:column}.pole-toolbar>p{margin-left:0}.pole-fields{grid-template-columns:1fr}.pole-row>summary{grid-template-columns:1fr auto auto;gap:.65rem}.pole-unit{grid-column:1/2;padding-left:3.1rem;font-size:.88rem}.pole-unit>small{display:inline;margin-right:.35rem}.pole-row>summary code,.pole-enrollments{display:none}.pole-row>summary .status-badge{grid-column:2;grid-row:1}.pole-expand{grid-column:3;grid-row:1}.pole-form-footer .button{width:100%;margin-left:0}.pole-delete{position:static;margin-top:.75rem}.pole-delete .button{width:100%}.pole-row-editor:has(.pole-delete) .pole-form-footer{padding-right:0}}
</style>
