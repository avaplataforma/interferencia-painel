<?php
$announcements=$announcements??[];
?>
<style>
.announcements-page{max-width:82rem;margin:auto}.announcements-layout{display:grid;grid-template-columns:minmax(18rem,.72fr) minmax(0,1.55fr);gap:1.25rem;align-items:start}.announcement-form,.announcement-list{padding:1.35rem}.announcement-form{position:sticky;top:6.5rem}.announcement-form form{display:grid;gap:1rem}.announcement-form label{margin:0}.announcement-form input:not([type=checkbox]),.announcement-form textarea{width:100%;margin-top:.35rem}.announcement-list-items{display:grid;gap:.9rem}.announcement-card{border:1px solid #dce3e8;border-radius:.85rem;padding:1rem 1.1rem;background:#fff}.announcement-card.off{opacity:.62}.announcement-card .top{display:flex;align-items:flex-start;justify-content:space-between;gap:.8rem}.announcement-card h3{margin:0;font-size:1.02rem}.announcement-card p{margin:.5rem 0 0;color:var(--inter-muted);white-space:pre-line}.announcement-card small{display:block;margin-top:.6rem;color:var(--inter-muted)}.announcement-actions{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.6rem}@media(max-width:850px){.announcements-layout{grid-template-columns:1fr}.announcement-form{position:static}}
</style>
<div class="announcements-page">
 <div class="page-header"><div><p class="eyebrow">ADM · Portal do Aluno</p><h1>Comunicados</h1><p>Publique avisos que aparecem em destaque no Portal do Aluno dos seus estudantes.</p></div></div>
 <?php if(!empty($message)):?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif;?>
 <?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>
 <div class="announcements-layout">
  <section class="card announcement-form">
   <p class="eyebrow">Novo aviso</p><h2>Publicar comunicado</h2><p class="meta">Até 190 caracteres no título e 10.000 no texto.</p>
   <form method="post" action="<?= $escape($basePath) ?>/admin/announcements"><?= $csrfField ?>
    <label>Título <span class="required-mark">*</span><input required maxlength="190" name="title" placeholder="Ex.: Aula inaugural nesta sexta-feira"></label>
    <label>Texto <span class="required-mark">*</span><textarea required maxlength="10000" name="body" rows="6" placeholder="Escreva o aviso para os alunos..."></textarea></label>
    <div class="form-actions"><button class="button button-primary" type="submit"><i class="fa-solid fa-bullhorn"></i> Publicar</button></div>
   </form>
  </section>
  <section class="card announcement-list">
   <div class="card-header"><div><h2>Publicados</h2><p class="meta"><?= count($announcements) ?> comunicado(s).</p></div></div>
   <div class="announcement-list-items">
    <?php foreach($announcements as$item):?>
    <article class="announcement-card <?= empty($item['is_active'])?'off':'' ?>">
     <div class="top"><div><h3><?= $escape((string)$item['title']) ?></h3><p><?= $escape((string)$item['body']) ?></p></div><span class="badge <?= !empty($item['is_active'])?'badge-success':'badge-warning' ?>"><?= !empty($item['is_active'])?'Visível':'Pausado' ?></span></div>
     <small><?= $escape(substr((string)$item['created_at'],0,10)) ?></small>
     <div class="announcement-actions">
      <form method="post" action="<?= $escape($basePath) ?>/admin/announcements/<?= (int)$item['id'] ?>/toggle"><?= $csrfField ?><input type="hidden" name="active" value="<?= !empty($item['is_active'])?'0':'1' ?>"><button class="button button-secondary button-sm" type="submit"><?= !empty($item['is_active'])?'Pausar':'Reativar' ?></button></form>
      <form method="post" action="<?= $escape($basePath) ?>/admin/announcements/<?= (int)$item['id'] ?>/delete" onsubmit="return confirm('Remover este comunicado?');"><?= $csrfField ?><button class="button button-danger button-sm" type="submit">Remover</button></form>
     </div>
    </article>
    <?php endforeach;?>
    <?php if($announcements===[]):?><p class="meta">Nenhum comunicado publicado ainda.</p><?php endif;?>
   </div>
  </section>
 </div>
</div>
