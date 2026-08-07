<?php
$labels=['submitted'=>'Aguardando análise','reviewing'=>'Em análise','approved'=>'Aprovada','rejected'=>'Rejeitada','cancelled'=>'Cancelada'];
$ticketLabels=['open'=>'Aberto','in_progress'=>'Em andamento','waiting'=>'Aguardando','resolved'=>'Resolvido','closed'=>'Fechado'];
?>
<style>
.application-link-card{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:1.25rem;align-items:center;padding:1.4rem}.application-link-card h2{margin:0 0 .35rem}.application-link-card p{margin:0}.application-link-actions{display:flex;gap:.5rem;flex-wrap:wrap}.application-flow{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;margin-top:1rem}.application-flow div{display:flex;gap:.75rem;align-items:flex-start;padding:1rem;border:1px solid #e2e8ed;border-radius:.85rem;background:#fff}.application-flow i{color:var(--inter-accent);margin-top:.2rem}.application-flow strong,.application-flow small{display:block}.application-list-heading{display:flex;justify-content:space-between;align-items:end;gap:1rem;margin:2rem 0 .75rem}.application-list-heading h2,.application-list-heading p{margin:0}.application-ticket{display:inline-flex;align-items:center;gap:.4rem}.application-actions .button-icon{width:2.6rem;height:2.6rem;min-height:2.6rem;padding:0}.application-actions button:disabled{cursor:not-allowed;opacity:.38}@media(max-width:800px){.application-link-card{grid-template-columns:1fr}.application-flow{grid-template-columns:1fr}}
</style>

<div class="page-header">
  <div><p class="eyebrow">Franquias</p><h1>Solicitações</h1><p>Cadastros recebidos pelo formulário público e o andamento da implantação.</p></div>
</div>
<?php if(!empty($message)):?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif;?>
<?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>

<section class="card application-link-card">
  <div>
    <p class="eyebrow">Link permanente</p>
    <h2>Cadastro externo de franquia</h2>
    <p class="meta">Use sempre este mesmo endereço. Uma solicitação e um ticket serão criados somente depois que o formulário for enviado.</p>
    <div class="input-copy-row mt-3"><input id="franchise-public-url" readonly value="<?= $escape($publicUrl) ?>"><button class="button button-secondary" type="button" data-copy-target="franchise-public-url" title="Copiar link"><i class="fa-solid fa-copy"></i> Copiar</button></div>
  </div>
  <div class="application-link-actions"><a class="button button-primary" href="<?= $escape($publicUrl) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir formulário</a></div>
</section>

<div class="application-flow" aria-label="Fluxo das solicitações">
  <div><i class="fa-solid fa-file-pen"></i><span><strong>1. Cadastro recebido</strong><small class="meta">A futura franquia envia os dados mínimos.</small></span></div>
  <div><i class="fa-solid fa-ticket"></i><span><strong>2. Ticket automático</strong><small class="meta">A equipe central recebe a pendência para análise.</small></span></div>
  <div><i class="fa-solid fa-circle-check"></i><span><strong>3. Conclusão interna</strong><small class="meta">Modelo comercial, contrato e ativação são definidos pelo colaborador.</small></span></div>
</div>

<div class="application-list-heading"><div><h2>Cadastros recebidos</h2><p class="meta">Convites antigos ainda não preenchidos não aparecem nesta fila.</p></div><span class="badge"><?= count($applications) ?></span></div>
<div class="table-card"><div class="table-responsive"><table><thead><tr><th>Franquia</th><th>Gestor</th><th>Situação</th><th>Ticket</th><th>Contrato</th><th>Atualizado</th><th>Ação</th></tr></thead><tbody>
<?php foreach($applications as$item):?>
<?php $canDelete=empty($item['organization_id'])&&(int)($item['contract_count']??0)===0;?>
<tr>
  <td><span class="table-cell-stack"><strong><?= $escape($item['display_name']?:'Cadastro sem nome') ?></strong><small><?= $escape($item['cnpj']?:'—') ?></small></span></td>
  <td><span class="table-cell-stack"><strong><?= $escape($item['manager_name']?:'—') ?></strong><small><?= $escape($item['manager_email']?:'') ?></small></span></td>
  <td><span class="badge <?= $item['status']==='approved'?'badge-success':'badge-warning' ?>"><?= $escape($labels[$item['status']]??$item['status']) ?></span></td>
  <td><span class="table-cell-stack"><?php if(!empty($item['ticket_id'])):?><a class="application-ticket" href="<?= $escape($basePath) ?>/admin/tickets?q=F-<?= (int)$item['ticket_id'] ?>"><i class="fa-solid fa-ticket"></i> #F-<?= (int)$item['ticket_id'] ?></a><small><?= $escape($ticketLabels[$item['ticket_status']]??(string)$item['ticket_status']) ?></small><?php else:?><span class="meta">Não vinculado</span><?php endif;?></span></td>
  <td><?= $item['status']==='approved'?'Configuração em andamento':'Após aprovação' ?></td>
  <td><?= $escape(date('d/m/Y H:i',strtotime((string)$item['updated_at']))) ?></td>
  <td><div class="table-actions application-actions"><a class="button button-secondary button-icon" title="Analisar solicitação" aria-label="Analisar solicitação" href="<?= $escape($basePath) ?>/admin/franchise-applications/<?= (int)$item['id'] ?>"><i class="fa-solid fa-eye"></i></a><?php if($canDelete):?><form method="post" action="<?= $escape($basePath) ?>/admin/franchise-applications/<?= (int)$item['id'] ?>/delete" data-confirm-submit="Excluir esta solicitação e o ticket automático vinculado? Esta ação não poderá ser desfeita."><?= $csrfField ?><button class="button-danger button-icon" type="submit" title="Excluir solicitação" aria-label="Excluir solicitação"><i class="fa-solid fa-trash"></i></button></form><?php else:?><button class="button button-secondary button-icon" type="button" disabled title="O histórico de uma franquia ou contrato já criado não pode ser excluído" aria-label="Solicitação protegida contra exclusão"><i class="fa-solid fa-lock"></i></button><?php endif;?></div></td>
</tr>
<?php endforeach;?>
<?php if($applications===[]):?><tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-inbox"></i><strong>Nenhum cadastro recebido</strong><p>Compartilhe o link permanente acima para iniciar a primeira solicitação.</p></div></td></tr><?php endif;?>
</tbody></table></div></div>
