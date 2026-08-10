<?php
$formatDate=static fn(mixed$value):string=>is_string($value)&&$value!==''?date('d/m/Y H:i',strtotime($value)):'Ainda não executado';
$modeLabels=['external_link'=>'Abrir no AVA do fornecedor','iframe'=>'Iframe (experimental)','sso'=>'Login integrado / SSO'];
?>
<div class="page-header">
  <div><p class="eyebrow">ADM Central · Integrações</p><h1>Fornecedores de cursos</h1><p>Importe catálogos externos sem misturar conteúdo acadêmico com preços, cobranças ou matrículas.</p></div>
  <a class="btn btn-secondary" href="<?= $escape($basePath) ?>/admin/platform/integrations"><i class="fa-solid fa-arrow-left"></i> Voltar</a>
</div>
<?php if(!empty($message)): ?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif; ?>
<?php if(!empty($error)): ?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif; ?>
<?php if(!$encryptionReady): ?><div class="alert alert-warning"><strong>Proteção pendente:</strong> configure a chave-mestra antes de salvar o token do fornecedor.</div><?php endif; ?>
<style>
.provider-layout{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:18px;align-items:start}.provider-card{background:#fff;border:1px solid #dbe3eb;border-radius:16px;padding:22px;box-shadow:0 8px 24px rgba(15,35,55,.05)}.provider-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding-bottom:18px;border-bottom:1px solid #e8edf2;margin-bottom:20px}.provider-title{display:flex;align-items:center;gap:14px}.provider-icon{display:grid;place-items:center;width:46px;height:46px;border-radius:13px;background:#fff0f1;color:#ed1c24;font-size:20px}.provider-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.provider-field-wide{grid-column:1/-1}.provider-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:20px}.provider-actions form{margin:0}.provider-note{display:flex;gap:12px;padding:15px;border-radius:12px;background:#fff7d7;border:1px solid #f2d36b;color:#654d00;margin-top:18px}.provider-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.provider-stat{padding:16px;border:1px solid #e1e7ed;border-radius:13px;background:#f8fafc}.provider-stat strong{display:block;font-size:24px;margin-top:4px}.provider-status-list{display:grid;gap:12px;margin-top:18px}.provider-status-row{display:flex;justify-content:space-between;gap:12px;padding-bottom:12px;border-bottom:1px solid #edf0f3}.provider-table-card{margin-top:18px}.provider-course{display:flex;align-items:center;gap:12px;min-width:280px}.provider-cover{width:54px;height:42px;object-fit:cover;border-radius:8px;background:#edf1f4}.provider-empty{padding:50px 20px;text-align:center;color:#64748b}.badge-soft{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:#eef2f6;font-size:12px;font-weight:700}.badge-soft.is-active{background:#e3f7ec;color:#08723d}.badge-soft.is-pending{background:#fff3cd;color:#765b00}@media(max-width:900px){.provider-layout{grid-template-columns:1fr}.provider-form-grid{grid-template-columns:1fr}.provider-field-wide{grid-column:auto}.provider-summary{grid-template-columns:1fr}}
</style>
<div class="provider-layout">
  <section class="provider-card">
    <div class="provider-head"><div class="provider-title"><span class="provider-icon"><i class="fa-solid fa-book-open-reader"></i></span><div><h2>Escola Avançada</h2><p>Fonte acadêmica do <strong><?= $escape($settings['catalog_name']) ?></strong></p></div></div><span class="badge-soft <?= $settings['configured']&&$settings['is_active']?'is-active':'is-pending' ?>"><?= $settings['configured']&&$settings['is_active']?'Conectada':'Configuração pendente' ?></span></div>
    <form method="post" action="<?= $escape($basePath) ?>/admin/platform/integrations/course-providers">
      <?= $csrfField ?>
      <div class="provider-form-grid">
        <label class="provider-field-wide"><span>URL especial da escola *</span><input type="url" name="base_url" value="<?= $escape($settings['base_url']) ?>" placeholder="https://suaescola.com" required><small>Use o endereço informado pelo suporte. O caminho /api/v2 é completado automaticamente.</small></label>
        <label><span>Token da API <?= !$settings['configured']?'*':'' ?></span><input type="password" name="token" autocomplete="new-password" placeholder="<?= $settings['token_last4']!==''?'Token salvo terminado em ····'.$escape($settings['token_last4']):'Cole o token fornecido' ?>"><small>Vazio preserva o token atual, que nunca é exibido.</small></label>
        <label><span>Nome do catálogo *</span><input type="text" name="catalog_name" value="<?= $escape($settings['catalog_name']?:'Catálogo PRO') ?>" required></label>
        <label><span>Entrega acadêmica</span><select name="delivery_mode"><?php foreach($modeLabels as$mode=>$label): ?><option value="<?= $escape($mode) ?>" <?= $settings['delivery_mode']===$mode?'selected':'' ?>><?= $escape($label) ?></option><?php endforeach; ?></select><small>Link externo é o modo seguro até o fornecedor homologar SSO ou LTI.</small></label>
        <label><span>Modelo de link de acesso</span><input type="text" name="launch_url_template" value="<?= $escape($settings['launch_url_template']) ?>" placeholder="https://escola.com/curso/{id}"><small>Opcional. Aceita {id} e {curso} quando documentados.</small></label>
        <label class="provider-field-wide"><span class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?= $settings['is_active']?'checked':'' ?>> Ativar integração</span></label>
      </div>
      <div class="provider-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar conexão</button></div>
    </form>
    <div class="provider-actions">
      <form method="post" action="<?= $escape($basePath) ?>/admin/platform/integrations/course-providers/test"><?= $csrfField ?><button class="btn btn-secondary" type="submit" <?= !$settings['configured']?'disabled':'' ?>><i class="fa-solid fa-plug-circle-check"></i> Testar conexão</button></form>
      <form method="post" action="<?= $escape($basePath) ?>/admin/platform/integrations/course-providers/sync"><?= $csrfField ?><button class="btn btn-secondary" type="submit" <?= !$settings['configured']||!$settings['is_active']?'disabled':'' ?>><i class="fa-solid fa-rotate"></i> Sincronizar Catálogo PRO</button></form>
    </div>
    <div class="provider-note"><i class="fa-solid fa-shield-halved"></i><div><strong>Financeiro independente.</strong><br>Preços recebidos são somente referência. Venda, campanha, cobrança e split continuam sob as regras do Mundo Inter e do Asaas escolhido para cada franquia.</div></div>
  </section>
  <aside class="provider-card">
    <h2>Resumo da integração</h2>
    <div class="provider-summary"><div class="provider-stat"><small>Importados</small><strong><?= (int)$summary['total'] ?></strong></div><div class="provider-stat"><small>Disponíveis</small><strong><?= (int)$summary['available'] ?></strong></div><div class="provider-stat"><small>Categorias</small><strong><?= (int)$summary['categories'] ?></strong></div></div>
    <div class="provider-status-list"><div class="provider-status-row"><span>Último teste</span><strong><?= $escape($formatDate($settings['last_tested_at'])) ?></strong></div><div class="provider-status-row"><span>Última sincronização</span><strong><?= $escape($formatDate($settings['last_synced_at'])) ?></strong></div><div class="provider-status-row"><span>Modo de entrega</span><strong><?= $escape($modeLabels[$settings['delivery_mode']]??'Não definido') ?></strong></div></div>
    <div class="alert alert-info" style="margin-top:18px"><strong>Próxima etapa:</strong> curadoria, liberação por franquia e publicação na loja com o selo <strong>Catálogo PRO</strong>.</div>
    <?php if($settings['delivery_mode']==='iframe'): ?><div class="alert alert-warning"><strong>Iframe experimental:</strong> pode ser bloqueado pelo AVA, cookies ou navegador. Prefira SSO/deep link quando disponível.</div><?php endif; ?>
  </aside>
</div>
<section class="provider-card provider-table-card" id="catalogo">
  <div class="provider-head"><div><h2>Catálogo importado</h2><p>Espelho técnico para revisão. Sincronizar nunca publica, vende ou matricula automaticamente.</p></div><span class="badge-soft"><?= count($courses) ?> item(ns)</span></div>
  <?php if($courses===[]): ?><div class="provider-empty"><i class="fa-solid fa-box-open fa-2x"></i><h3>Nenhum curso importado</h3><p>Salve a conexão, teste e sincronize o Catálogo PRO.</p></div><?php else: ?>
  <div class="table-responsive"><table><thead><tr><th>Curso</th><th>Categoria</th><th>Carga horária</th><th>Aulas</th><th>Referência remota</th><th>Situação</th></tr></thead><tbody><?php foreach($courses as$course): ?><tr>
    <td><div class="provider-course"><?php if((string)$course['cover_url']!==''): ?><img class="provider-cover" src="<?= $escape($course['cover_url']) ?>" alt="" loading="lazy"><?php else: ?><span class="provider-cover provider-icon"><i class="fa-solid fa-book"></i></span><?php endif; ?><div><strong><?= $escape($course['name']) ?></strong><small><?= $escape($course['catalog_name']) ?></small></div></div></td>
    <td><?= $escape($course['category']?:'Sem categoria') ?></td><td><?= $escape($course['workload']?:'—') ?></td><td><?= (int)$course['lesson_count'] ?></td><td><?= $course['remote_reference_price']!==null?'R$ '.number_format((float)$course['remote_reference_price'],2,',','.'):'—' ?></td><td><span class="badge-soft <?= (int)$course['is_available']===1?'is-active':'is-pending' ?>"><?= (int)$course['is_available']===1?'Disponível':'Retirado da origem' ?></span></td>
  </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
