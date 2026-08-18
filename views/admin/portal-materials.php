<?php
$materials=$materials??[];
?>
<style>
.materials-page{max-width:82rem;margin:auto}.materials-layout{display:grid;grid-template-columns:minmax(17rem,340px) minmax(0,1fr);gap:1.4rem;align-items:start}.materials-card{padding:1.4rem;border-radius:1rem}.materials-form{position:sticky;top:6.5rem}.materials-form form{display:grid;gap:1rem}.materials-form label{margin:0}.materials-form input{margin-top:.35rem}.materials-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(19rem,1fr));gap:1rem}.material-card{display:flex;gap:.8rem;align-items:flex-start;border:1px solid #e2e8ee;border-radius:1rem;padding:1rem 1.1rem;background:#fff}.material-icon{display:grid;place-items:center;width:2.6rem;height:2.6rem;flex:0 0 2.6rem;border-radius:.75rem;background:#eef4ff;color:#2563eb;font-size:1rem}.material-card strong{display:block;font-size:.98rem}.material-card small{display:block;color:var(--inter-muted);margin-top:.25rem}.material-actions{display:flex;gap:.45rem;margin-left:auto}.material-actions form{display:flex;margin:0}.material-actions .button{width:auto!important;min-width:4.6rem;justify-content:center}@media(max-width:900px){.materials-layout{grid-template-columns:1fr}.materials-form{position:static}}
</style>
<div class="materials-page">
 <div class="page-header"><div><p class="eyebrow">ADM · Portal do Aluno · Materiais</p><h1>Materiais da franquia</h1><p>Publique manuais, regulamentos e PDFs que os alunos baixam no Portal do Aluno.</p></div></div>
 <?php if(!empty($message)):?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif;?>
 <?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>
 <div class="materials-layout">
  <section class="card materials-card materials-form">
   <p class="eyebrow">Novo material</p><h2>Publicar material</h2><p class="meta">PDF, imagem, Word ou áudio de até 16 MB.</p>
   <form method="post" action="<?= $escape($basePath) ?>/admin/portal/materials" enctype="multipart/form-data"><?= $csrfField ?>
    <label>Título <span class="required-mark">*</span><input required maxlength="190" name="title" placeholder="Ex.: Manual do aluno 2026"></label>
    <label>Arquivo <span class="required-mark">*</span><input type="file" name="file" required></label>
    <div class="form-actions"><button class="button button-primary" type="submit"><i class="fa-solid fa-cloud-arrow-up"></i> Publicar</button></div>
   </form>
  </section>
  <section class="card materials-card">
   <div class="card-header"><div><h2>Publicados</h2><p class="meta"><?= count($materials) ?> material(is).</p></div></div>
   <div class="materials-grid">
    <?php foreach($materials as$item):?>
    <article class="material-card">
     <span class="material-icon"><i class="fa-solid fa-file-pdf"></i></span>
     <div><strong><?= $escape((string)$item['title']) ?></strong><small><?= $escape((string)$item['file_name']) ?> · <?= number_format((int)$item['file_size']/1024,0,',','.') ?> KB · <?= $escape(substr((string)$item['created_at'],0,10)) ?></small></div>
     <div class="material-actions"><form method="post" action="<?= $escape($basePath) ?>/admin/portal/materials/<?= (int)$item['id'] ?>/delete" onsubmit="return confirm('Remover este material?');"><?= $csrfField ?><button class="button button-danger button-sm" type="submit">Remover</button></form></div>
    </article>
    <?php endforeach;?>
    <?php if($materials===[]):?><p class="meta">Nenhum material publicado ainda.</p><?php endif;?>
   </div>
  </section>
 </div>
</div>
