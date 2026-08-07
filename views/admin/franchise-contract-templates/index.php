<div class="page-header"><div><p class="eyebrow">Franquias · Contratos</p><h1>Modelos</h1><p>Crie, organize e reutilize os textos-base dos contratos da rede.</p></div><a class="button button-primary" href="<?= $escape($basePath) ?>/admin/franchise-contract-templates/create"><i class="fa-solid fa-plus"></i> Novo modelo</a></div>
<?php if(!empty($message)):?><div class="alert alert-success"><?= $escape($message) ?></div><?php endif;?>
<?php if(!empty($error)):?><div class="alert alert-danger"><?= $escape($error) ?></div><?php endif;?>
<div class="table-card contract-template-list"><table><thead><tr><th>Modelo</th><th>Versão</th><th>Situação</th><th>Uso</th><th>Atualizado</th><th>Ações</th></tr></thead><tbody>
<?php foreach($templates as$item):$usage=(int)($item['usage_count']??0);$active=(int)$item['is_active']===1;?><tr>
<td><strong><?= $escape($item['title']) ?></strong><small><?= $active?'Disponível para novos contratos':'Mantido somente para histórico' ?></small></td>
<td><?= $escape($item['version']) ?></td>
<td><span class="status <?= $active?'status-active':'status-muted' ?>"><?= $active?'Ativo':'Arquivado' ?></span></td>
<td><span class="contract-usage"><i class="fa-solid fa-file-signature"></i> <?= $usage ?> contrato<?= $usage===1?'':'s' ?></span></td>
<td><?= $escape(date('d/m/Y H:i',strtotime((string)$item['updated_at']))) ?></td>
<td><div class="contract-template-actions"><a class="button button-secondary button-icon" title="Editar modelo" aria-label="Editar modelo" href="<?= $escape($basePath) ?>/admin/franchise-contract-templates/<?= (int)$item['id'] ?>/edit"><i class="fa-solid fa-pen"></i></a><form method="post" action="<?= $escape($basePath) ?>/admin/franchise-contract-templates/<?= (int)$item['id'] ?>/delete" data-confirm-submit="<?= $escape($usage>0?'Este modelo já foi utilizado. Ele será arquivado e o histórico dos contratos será preservado. Continuar?':'Excluir definitivamente este modelo?') ?>"><?= $csrfField ?><button class="button-danger button-icon" type="submit" title="<?= $usage>0?'Arquivar modelo':'Excluir modelo' ?>" aria-label="<?= $usage>0?'Arquivar modelo':'Excluir modelo' ?>"><i class="fa-solid <?= $usage>0?'fa-box-archive':'fa-trash' ?>"></i></button></form></div></td>
</tr><?php endforeach;?>
<?php if($templates===[]):?><tr><td colspan="6"><div class="empty-state"><i class="fa-regular fa-file-lines"></i><strong>Nenhum modelo cadastrado</strong><p>Crie o primeiro modelo para começar.</p></div></td></tr><?php endif;?>
</tbody></table></div>
