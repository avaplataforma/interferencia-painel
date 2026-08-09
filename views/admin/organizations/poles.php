<?php $poles=$organizationPoles??[];$poleUnits=$organizationPoleUnits??[]; ?>
<section class="card organization-section organization-poles" id="polos" data-organization-panel="polos" hidden>
 <header class="organization-section-header"><span class="organization-section-icon"><i class="fa-solid fa-location-crosshairs"></i></span><div><h2>Polos da franquia</h2><p class="meta">Cada polo possui um código permanente usado pelo Painel e pelo AVA. Ele continua válido mesmo que o nome comercial mude.</p></div></header>
 <div class="pole-summary">
  <article><i class="fa-solid fa-school"></i><div><strong><?= count($poles) ?></strong><span>polo(s) cadastrado(s)</span></div></article>
  <p><strong>Integração nova:</strong> as matrículas gravam os campos <code>Franquia Mundo Inter</code> e <code>Polo Mundo Inter</code>. O valor antigo permanece apenas durante a migração.</p>
 </div>
 <form class="pole-create" method="post" action="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/poles"><?= $csrfField ?>
  <h3><i class="fa-solid fa-circle-plus"></i> Novo polo</h3>
  <div class="pole-fields">
   <label>Nome do polo *<input required maxlength="160" name="name" placeholder="Ex.: Polo Tijucas"></label>
   <label>Código permanente *<input required maxlength="100" name="code" pattern="[a-z0-9][a-z0-9-]{1,98}[a-z0-9]" placeholder="polo-tijucas"><small>Letras minúsculas, números e hífens.</small></label>
   <label>Unidade correspondente<select name="unit_id"><option value="">Sem unidade vinculada</option><?php foreach($poleUnits as$unit):if(!empty($unit['pole_id']))continue;?><option value="<?= (int)$unit['id'] ?>"><?= $escape((string)$unit['name']) ?><?= !empty($unit['city'])?' · '.$escape((string)$unit['city']):'' ?></option><?php endforeach;?></select></label>
   <label>Valor antigo no Moodle<input maxlength="255" name="legacy_value" placeholder="Somente para migrar Polo Presencial"><small>Opcional e temporário.</small></label>
  </div>
  <div class="pole-form-footer"><label class="checkbox-row"><input type="checkbox" name="is_primary" value="1" <?= $poles===[]?'checked':'' ?>> Polo principal</label><label class="checkbox-row"><input type="checkbox" name="is_active" value="1" checked> Polo ativo</label><button class="button button-primary" type="submit"><i class="fa-solid fa-plus"></i> Cadastrar polo</button></div>
 </form>
 <div class="pole-list">
 <?php foreach($poles as$pole):?>
  <article class="pole-card">
   <form method="post" action="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/poles"><?= $csrfField ?><input type="hidden" name="pole_id" value="<?= (int)$pole['id'] ?>">
    <div class="pole-card-head"><span class="pole-marker"><i class="fa-solid fa-location-dot"></i></span><div><h3><?= $escape((string)$pole['name']) ?></h3><p class="meta"><code><?= $escape((string)$pole['code']) ?></code><?php if((int)$pole['is_primary']===1):?> · <strong>Principal</strong><?php endif;?></p></div><span class="status-badge <?= (int)$pole['is_active']===1?'status-active':'status-inactive' ?>"><?= (int)$pole['is_active']===1?'Ativo':'Inativo' ?></span></div>
    <div class="pole-fields">
     <label>Nome *<input required maxlength="160" name="name" value="<?= $escape((string)$pole['name']) ?>"></label>
     <label>Código permanente *<input required maxlength="100" name="code" pattern="[a-z0-9][a-z0-9-]{1,98}[a-z0-9]" value="<?= $escape((string)$pole['code']) ?>"></label>
     <label>Unidade<select name="unit_id"><option value="">Sem unidade vinculada</option><?php foreach($poleUnits as$unit):if(!empty($unit['pole_id'])&&(int)$unit['pole_id']!==(int)$pole['id'])continue;?><option value="<?= (int)$unit['id'] ?>" <?= (int)($pole['unit_id']??0)===(int)$unit['id']?'selected':'' ?>><?= $escape((string)$unit['name']) ?><?= !empty($unit['city'])?' · '.$escape((string)$unit['city']):'' ?></option><?php endforeach;?></select></label>
     <label>Valor antigo no Moodle<input maxlength="255" name="legacy_value" value="<?= $escape((string)($pole['legacy_value']??'')) ?>" placeholder="Compatibilidade temporária"></label>
    </div>
    <div class="pole-form-footer"><label class="checkbox-row"><input type="checkbox" name="is_primary" value="1" <?= (int)$pole['is_primary']===1?'checked':'' ?>> Polo principal</label><label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?= (int)$pole['is_active']===1?'checked':'' ?>> Polo ativo</label><span class="meta"><?= (int)$pole['enrollment_count'] ?> matrícula(s)</span><button class="button button-secondary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar</button></div>
   </form>
   <?php if((int)$pole['enrollment_count']===0):?><form class="pole-delete" method="post" action="<?= $escape($basePath) ?>/admin/organizations/<?= (int)$organization['id'] ?>/poles/<?= (int)$pole['id'] ?>/delete" onsubmit="return confirm('Excluir este polo?');"><?= $csrfField ?><button class="icon-button icon-button-danger" type="submit" title="Excluir polo" aria-label="Excluir polo"><i class="fa-solid fa-trash"></i></button></form><?php endif;?>
  </article>
 <?php endforeach;?>
 <?php if($poles===[]):?><div class="empty-state"><i class="fa-solid fa-map-location-dot"></i><strong>Nenhum polo cadastrado</strong><span>Cadastre o primeiro polo para preparar as próximas matrículas.</span></div><?php endif;?>
 </div>
</section>
<style>
.organization-poles{display:grid;gap:1rem}.pole-summary{display:grid;grid-template-columns:minmax(13rem,18rem) 1fr;align-items:stretch;gap:1rem}.pole-summary article,.pole-summary>p{margin:0;padding:1rem;border:1px solid #dce3e8;border-radius:1rem;background:#f8fafb}.pole-summary article{display:flex;align-items:center;gap:.85rem}.pole-summary article>i,.pole-marker{display:grid;place-items:center;width:2.7rem;height:2.7rem;border-radius:.8rem;background:#fff0f1;color:var(--inter-accent)}.pole-summary article div{display:grid}.pole-summary article strong{font-size:1.4rem}.pole-summary article span{color:#657483}.pole-create,.pole-card{position:relative;display:grid;gap:1rem;padding:1.2rem;border:1px solid #dce3e8;border-radius:1rem;background:#fff}.pole-create h3,.pole-card h3,.pole-card p{margin:0}.pole-fields{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem}.pole-fields label{margin:0}.pole-fields input,.pole-fields select{width:100%}.pole-form-footer{display:flex;align-items:center;gap:1rem;flex-wrap:wrap}.pole-form-footer .button{margin-left:auto}.pole-list{display:grid;gap:1rem}.pole-card-head{display:flex;align-items:center;gap:.8rem}.pole-card-head .status-badge{margin-left:auto}.pole-delete{position:absolute;right:.8rem;bottom:.8rem}.pole-card:has(.pole-delete) .pole-form-footer{padding-right:3rem}.status-badge{padding:.35rem .65rem;border-radius:999px;font-size:.82rem;font-weight:750}.status-active{background:#e8f7ef;color:#08723d}.status-inactive{background:#eef1f4;color:#657483}.empty-state{display:grid;place-items:center;gap:.5rem;padding:2.5rem;border:1px dashed #cbd5dc;border-radius:1rem;color:#657483}.empty-state i{font-size:1.6rem}.icon-button-danger{color:#b4232b}@media(max-width:1000px){.pole-fields{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:700px){.pole-summary,.pole-fields{grid-template-columns:1fr}.pole-form-footer .button{width:100%;margin-left:0}}
</style>
