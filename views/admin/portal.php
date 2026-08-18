<?php
$announcements=$announcements??[];$tabs=$tabs??[];
?>
<style>
.portal-page{max-width:82rem;margin:auto}.portal-layout{display:grid;grid-template-columns:minmax(18rem,.72fr) minmax(0,1.55fr);gap:1.25rem;align-items:start}.portal-card{padding:1.35rem}.portal-card form{display:grid;gap:1rem}.portal-card label{margin:0}.portal-card input:not([type=checkbox]),.portal-card textarea{width:100%;margin-top:.35rem}.portal-announcements{display:grid;gap:.9rem}.announcement-card{border:1px solid #dce3e8;border-radius:.85rem;padding:1rem 1.1rem;background:#fff}.announcement-card.off{opacity:.62}.announcement-card .top{display:flex;align-items:flex-start;justify-content:space-between;gap:.8rem}.announcement-card h3{margin:0;font-size:1.02rem}.announcement-card p{margin:.5rem 0 0;color:var(--inter-muted);white-space:pre-line}.announcement-card small{display:block;margin-top:.6rem;color:var(--inter-muted)}.announcement-actions{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.6rem}
.announcement-actions form{display:flex;margin:0}
.announcement-actions .button{width:7.5rem;justify-content:center}.tab-options{display:grid;gap:.7rem;margin-top:.6rem}.tab-option{display:flex!important;align-items:center;gap:.7rem;padding:.85rem;border:1px solid #dce3e8;border-radius:.75rem;background:#f8fafb;margin:0}.tab-option input{width:auto!important;margin:0!important}.tab-option span strong,.tab-option span small{display:block}.tab-option span small{color:var(--inter-muted)}@media(max-width:850px){.portal-layout{grid-template-columns:1fr}}
</style>
<div class="portal-page">
 <div class="page-header"><div><p class="eyebrow">ADM · Portal do Aluno</p><h1>Portal do Aluno</h1><p>Controle os comunicados e as seções que os estudantes da sua franquia enxergam no AVA.</p></div></div>
 <?php if(!empty($message)):?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif;?>
 <?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>
 <div class="portal-layout">
  <section class="card portal-card" id="comunicados">
   <p class="eyebrow">Comunicados</p><h2>Publicar comunicado</h2><p class="meta">Aparece em destaque no Portal do Aluno até a data escolhida.</p>
   <form method="post" action="<?= $escape($basePath) ?>/admin/portal"><?= $csrfField ?>
    <label>Título <span class="required-mark">*</span><input required maxlength="190" name="title" placeholder="Ex.: Aula inaugural nesta sexta-feira"></label>
    <label>Texto <span class="required-mark">*</span><textarea required maxlength="10000" name="body" rows="5" placeholder="Escreva o aviso para os alunos..."></textarea></label>
    <label>Desaparecer em (opcional)<input type="date" name="expires_at"></label>
    <div class="form-actions"><button class="button button-primary" type="submit"><i class="fa-solid fa-bullhorn"></i> Publicar</button></div>
   </form>
  </section>
  <section class="card portal-card" id="abas">
   <p class="eyebrow">Abas e seções</p><h2>O que o aluno vê</h2><p class="meta">Desmarque as seções que não devem aparecer no portal desta franquia.</p>
   <form method="post" action="<?= $escape($basePath) ?>/admin/portal/tabs"><?= $csrfField ?>
    <div class="tab-options">
     <label class="tab-option"><input type="checkbox" name="tab_journey" value="1" <?= !empty($tabs['journey'])?'checked':'' ?>><span><strong>Jornada</strong><small>Progresso dos cursos, nota e continuar de onde parou.</small></span></label>
     <label class="tab-option"><input type="checkbox" name="tab_enrollments" value="1" <?= !empty($tabs['enrollments'])?'checked':'' ?>><span><strong>Matrículas</strong><small>Histórico das matrículas do aluno.</small></span></label>
     <label class="tab-option"><input type="checkbox" name="tab_finance" value="1" <?= !empty($tabs['finance'])?'checked':'' ?>><span><strong>Financeiro</strong><small>Cobranças, 2ª via, PIX e parcelas futuras.</small></span></label>
     <label class="tab-option"><input type="checkbox" name="tab_tickets" value="1" <?= !empty($tabs['tickets'])?'checked':'' ?>><span><strong>Tickets</strong><small>Abertura e acompanhamento de chamados.</small></span></label>
     <label class="tab-option"><input type="checkbox" name="tab_documents" value="1" <?= !empty($tabs['documents'])?'checked':'' ?>><span><strong>Documentos</strong><small>Envio de documentos para a franquia.</small></span></label>
    </div>
    <div class="form-actions"><button class="button button-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar seções</button></div>
   </form>
  </section>
  <section class="card portal-card" style="grid-column:1/-1">
   <div class="card-header"><div><h2>Publicados</h2><p class="meta"><?= count($announcements) ?> comunicado(s).</p></div></div>
   <div class="portal-announcements">
    <?php foreach($announcements as$item):?>
    <article class="announcement-card <?= empty($item['is_active'])?'off':'' ?>">
     <div class="top"><div><h3><?= $escape((string)$item['title']) ?></h3><p><?= $escape((string)$item['body']) ?></p></div><span class="badge <?= !empty($item['is_active'])?'badge-success':'badge-warning' ?>"><?= !empty($item['is_active'])?'Visível':'Pausado' ?></span></div>
     <small>Criado em <?= $escape(substr((string)$item['created_at'],0,10)) ?><?= !empty($item['expires_at'])?' · expira em '.$escape((string)$item['expires_at']):'' ?></small>
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
