<?php
$announcements=$announcements??[];
?>
<style>
.portal-page{max-width:96rem;margin:auto}
.portal-layout{display:grid;grid-template-columns:minmax(17rem,340px) minmax(0,1fr);gap:1.4rem;align-items:start}
.portal-card{padding:1.4rem;border-radius:1rem}
.portal-form-card{position:sticky;top:6.5rem}
.portal-form-card form{display:grid;gap:1rem}
.portal-form-card label{margin:0}
.portal-form-card input:not([type=checkbox]),.portal-form-card textarea{width:100%;margin-top:.35rem}
.portal-form-card .field-hint{margin-top:.3rem;font-size:.8rem;color:var(--inter-muted)}
.portal-list-head{display:flex;align-items:center;justify-content:space-between;gap:.8rem;margin-bottom:1rem}
.portal-announcements{display:grid;grid-template-columns:repeat(auto-fill,minmax(19rem,1fr));gap:1rem}
.announcement-card{display:flex;flex-direction:column;gap:.7rem;border:1px solid #e2e8ee;border-radius:1rem;padding:1.1rem 1.15rem;background:#fff;box-shadow:0 .15rem .5rem rgb(20 40 70 / 4%);transition:box-shadow .15s ease,transform .15s ease}
.announcement-card:hover{box-shadow:0 .5rem 1.4rem rgb(20 40 70 / 10%);transform:translateY(-2px)}
.announcement-card.off{opacity:.6}
.announcement-top{display:flex;align-items:flex-start;gap:.75rem}
.announcement-icon{display:grid;place-items:center;width:2.4rem;height:2.4rem;flex:0 0 2.4rem;border-radius:.75rem;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;font-size:.95rem}
.announcement-top h3{margin:0;font-size:1rem;line-height:1.35}
.announcement-top .badge{margin-left:auto;white-space:nowrap}
.announcement-body{margin:0;color:var(--inter-muted);font-size:.92rem;line-height:1.5;white-space:pre-line;display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden}
.announcement-meta{display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;color:var(--inter-muted);font-size:.78rem;margin-top:auto;padding-top:.35rem}
.announcement-meta i{color:#c4cdd5}
.announcement-actions{display:flex;gap:.5rem;flex-wrap:wrap;padding-top:.7rem;border-top:1px solid #f0f4f7}
.announcement-actions form{display:flex;margin:0;flex:1 1 8rem;max-width:8.5rem}
.announcement-actions .button{display:inline-flex!important;align-items:center;width:100%;min-height:2.6rem!important;padding:.45rem .7rem!important;justify-content:center}
@media(max-width:1000px){.portal-layout{grid-template-columns:1fr}.portal-form-card{position:static}}
</style>
<div class="portal-page">
 <div class="page-header"><div><p class="eyebrow">ADM · Portal do Aluno · Comunicados</p><h1>Comunicados</h1><p>Publique avisos que aparecem em destaque no Portal do Aluno dos seus estudantes.</p></div></div>
 <?php if(!empty($message)):?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif;?>
 <?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>
 <div class="portal-layout">
  <section class="card portal-card portal-form-card">
   <p class="eyebrow">Novo aviso</p><h2>Publicar comunicado</h2><p class="meta">Aparece em destaque no Portal do Aluno até a data escolhida.</p>
   <form method="post" action="<?= $escape($basePath) ?>/admin/portal"><?= $csrfField ?>
    <label>Título <span class="required-mark">*</span><input required maxlength="190" name="title" placeholder="Ex.: Aula inaugural nesta sexta-feira"></label>
    <label>Texto <span class="required-mark">*</span><textarea required maxlength="10000" name="body" rows="6" placeholder="Escreva o aviso para os alunos..."></textarea></label>
    <label>Desaparecer em (opcional)<input type="date" name="expires_at"><small class="field-hint">Sem data, o aviso fica visível até você pausá-lo.</small></label>
    <div class="form-actions"><button class="button button-primary" type="submit"><i class="fa-solid fa-bullhorn"></i> Publicar</button></div>
   </form>
  </section>
  <section class="card portal-card">
   <div class="portal-list-head"><div><h2>Publicados</h2><p class="meta"><?= count($announcements) ?> comunicado(s).</p></div><span class="badge badge-neutral">Portal do Aluno</span></div>
   <div class="portal-announcements">
    <?php foreach($announcements as$item):?>
    <article class="announcement-card <?= empty($item['is_active'])?'off':'' ?>">
     <div class="announcement-top"><span class="announcement-icon"><i class="fa-solid fa-bullhorn"></i></span><h3><?= $escape((string)$item['title']) ?></h3><span class="badge <?= !empty($item['is_active'])?'badge-success':'badge-warning' ?>"><?= !empty($item['is_active'])?'Visível':'Pausado' ?></span></div>
     <p class="announcement-body"><?= $escape((string)$item['body']) ?></p>
     <div class="announcement-meta"><i class="fa-regular fa-calendar"></i> Criado em <?= $escape(substr((string)$item['created_at'],0,10)) ?><?= !empty($item['expires_at'])?' · <i class="fa-regular fa-clock"></i> expira em '.$escape((string)$item['expires_at']):'' ?></div>
     <div class="announcement-actions">
      <form method="post" action="<?= $escape($basePath) ?>/admin/portal/<?= (int)$item['id'] ?>/toggle"><?= $csrfField ?><input type="hidden" name="active" value="<?= !empty($item['is_active'])?'0':'1' ?>"><button class="button button-secondary button-sm" type="submit"><?= !empty($item['is_active'])?'Pausar':'Reativar' ?></button></form>
      <form method="post" action="<?= $escape($basePath) ?>/admin/portal/<?= (int)$item['id'] ?>/delete" onsubmit="return confirm('Remover este comunicado?');"><?= $csrfField ?><button class="button button-danger button-sm" type="submit">Remover</button></form>
     </div>
    </article>
    <?php endforeach;?>
    <?php if($announcements===[]):?><p class="meta">Nenhum comunicado publicado ainda.</p><?php endif;?>
   </div>
  </section>
 </div>
</div>
