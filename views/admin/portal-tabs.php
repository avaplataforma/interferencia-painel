<?php
$tabs=$tabs??[];
?>
<style>
.portal-tabs-page{max-width:82rem;margin:auto}.portal-tabs-card{padding:1.35rem;max-width:56rem}.portal-tabs-card form{display:grid;gap:1rem}.portal-tabs-card label{margin:0}.tab-options{display:grid;gap:.7rem;margin-top:.6rem}.tab-option{display:flex!important;align-items:center;gap:.7rem;padding:.85rem;border:1px solid #dce3e8;border-radius:.75rem;background:#f8fafb;margin:0}.tab-option input{width:auto!important;margin:0!important}.tab-option span strong,.tab-option span small{display:block}.tab-option span small{color:var(--inter-muted)}
</style>
<div class="portal-tabs-page">
 <div class="page-header"><div><p class="eyebrow">ADM · Portal do Aluno · Abas e seções</p><h1>O que o aluno vê</h1><p>Desmarque as seções que não devem aparecer no portal desta franquia.</p></div></div>
 <?php if(!empty($message)):?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif;?>
 <?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>
 <section class="card portal-tabs-card">
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
</div>
