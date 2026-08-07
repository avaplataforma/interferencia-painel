<?php
$franchiseDocuments=$franchiseDocuments??[];$documentCategories=$documentCategories??[];
$documentPrefix=$basePath.'/admin/organizations/'.(int)$organization['id'].'/documents';
$latestByGroup=[];$delivered=[];
foreach($franchiseDocuments as$document){
    if(!isset($latestByGroup[$document['document_group']]))$latestByGroup[$document['document_group']]=$document;
    $delivered[(string)$document['category']]=true;
}
$essentialDocuments=[
    'contrato_social'=>['Contrato Social','fa-file-contract'],
    'cartao_cnpj'=>['Cartão CNPJ','fa-building-circle-check'],
    'cnh_gestor'=>['CNH do gestor','fa-id-card'],
    'comprovante_endereco'=>['Comprovante de endereço','fa-house-circle-check'],
];
?>
<style>
.franchise-documents{margin-top:1.25rem}.document-checklist{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem}.document-check{display:flex;align-items:center;gap:.75rem;padding:1rem;border:1px solid #dce3e8;border-radius:.85rem;background:#fff}.document-check>i{display:grid;place-items:center;width:2.35rem;height:2.35rem;border-radius:.65rem;background:#fff0f1;color:var(--inter-accent)}.document-check.is-delivered>i{background:#eaf8ef;color:#087a39}.document-check span{display:block}.document-check small{color:#617286}.franchise-document-grid{display:grid;grid-template-columns:minmax(18rem,.8fr) minmax(0,1.2fr);gap:1rem;padding:1.25rem}.franchise-document-list{border:1px solid #e0e6ea;border-radius:.85rem;overflow:hidden}.franchise-document-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:1rem;align-items:center;padding:.9rem 1rem;border-bottom:1px solid #e7ebee}.franchise-document-row:last-child{border-bottom:0}.franchise-document-row strong,.franchise-document-row small{display:block}.franchise-document-actions{display:flex;gap:.4rem;align-items:center}.franchise-document-actions form{margin:0}@media(max-width:900px){.document-checklist{grid-template-columns:repeat(2,minmax(0,1fr))}.franchise-document-grid{grid-template-columns:1fr}}@media(max-width:560px){.document-checklist{grid-template-columns:1fr}}
</style>
<section class="card franchise-documents" id="documentos">
 <div class="card-header"><div><p class="eyebrow">Cadastro da franquia</p><h2><i class="fa-solid fa-folder-open"></i> Documentos</h2><p class="meta">Arquivos vinculados exclusivamente a <?= $escape((string)$organization['display_name']) ?> e armazenados de forma privada no DigitalOcean Spaces.</p></div><a class="button button-secondary" href="<?= $escape($documentPrefix) ?>"><i class="fa-solid fa-clock-rotate-left"></i> Gestão completa</a></div>
 <div class="p-4 border-bottom"><div class="document-checklist"><?php foreach($essentialDocuments as$key=>$item):?><?php $ok=isset($delivered[$key]);?><article class="document-check <?= $ok?'is-delivered':'' ?>"><i class="fa-solid <?= $escape($item[1]) ?>"></i><div><strong><?= $escape($item[0]) ?></strong><small><?= $ok?'Documento entregue':'Ainda não anexado' ?></small></div></article><?php endforeach;?></div></div>
 <div class="franchise-document-grid">
  <form class="form-grid" method="post" action="<?= $escape($documentPrefix) ?>" enctype="multipart/form-data"><?= $csrfField ?><input type="hidden" name="return_to" value="edit">
   <label class="form-span-2">Tipo de documento <span class="required-mark">*</span><select required name="category"><option value="">Selecione</option><?php foreach($documentCategories as$value=>$label):?><option value="<?= $escape($value) ?>"><?= $escape($label) ?></option><?php endforeach;?></select></label>
   <label class="form-span-2">Título <span class="required-mark">*</span><input required maxlength="180" name="title" placeholder="Ex.: Contrato Social — alteração 2026"></label>
   <label class="form-span-2">Descrição<textarea maxlength="1000" rows="2" name="description" placeholder="Observações internas ou validade do documento"></textarea></label>
   <label class="form-span-2">Arquivo <span class="required-mark">*</span><input required type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt"><small>PDF, imagem, Word, Excel, CSV ou texto · máximo 25 MB.</small></label>
   <div class="form-actions form-span-2"><button class="button button-primary" type="submit"><i class="fa-solid fa-cloud-arrow-up"></i> Anexar à franquia</button></div>
  </form>
  <div><h3 class="mb-1">Arquivos vinculados</h3><p class="meta">Versões atuais dos documentos desta franquia.</p>
   <div class="franchise-document-list"><?php if($latestByGroup===[]):?><div class="empty-state"><i class="fa-regular fa-folder-open"></i><strong>Nenhum documento anexado</strong></div><?php else:?><?php foreach($latestByGroup as$document):?><article class="franchise-document-row"><div><strong><?= $escape((string)$document['title']) ?></strong><small class="meta"><?= $escape($documentCategories[$document['category']]??(string)$document['category']) ?> · v<?= (int)$document['version_number'] ?> · <?= $escape(date('d/m/Y H:i',strtotime((string)$document['created_at']))) ?></small></div><div class="franchise-document-actions"><a class="button button-secondary button-icon" title="Baixar" href="<?= $escape($documentPrefix) ?>/<?= (int)$document['id'] ?>/download"><i class="fa-solid fa-download"></i></a><?php if(str_starts_with((string)$document['mime_type'],'image/')||(string)$document['mime_type']==='application/pdf'):?><a class="button button-secondary button-icon" title="Visualizar" target="_blank" href="<?= $escape($documentPrefix) ?>/<?= (int)$document['id'] ?>/download?inline=1"><i class="fa-solid fa-eye"></i></a><?php endif;?><a class="button button-secondary button-icon" title="Nova versão" href="<?= $escape($documentPrefix) ?>?version_of=<?= (int)$document['id'] ?>"><i class="fa-solid fa-code-branch"></i></a><form method="post" action="<?= $escape($documentPrefix) ?>/<?= (int)$document['id'] ?>/delete" onsubmit="return confirm('Arquivar este documento? O arquivo será preservado para auditoria.')"><?= $csrfField ?><input type="hidden" name="return_to" value="edit"><button class="button button-danger button-icon" title="Arquivar" type="submit"><i class="fa-solid fa-box-archive"></i></button></form></div></article><?php endforeach;?><?php endif;?></div>
  </div>
 </div>
</section>
